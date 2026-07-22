<?php
declare(strict_types=1);
/**
 * Generates the docs/performance.md category table: helper-wrapped htmlspecialchars()
 * versus echoing a real SmartString, per content category and size.
 *
 *     php .github/scripts/speed-page-table.php [--scale=1.0]
 *
 * Loads the real SmartString class from src/ (no composer install needed). Each
 * SmartString timing creates the object and outputs it, so the numbers carry the
 * full construction and method-call overhead. Run via the Speed Page Table
 * workflow for citable numbers: CI enables opcache like production. The script
 * checks its own environment - opcache off costs only a few percent here but
 * is not the production configuration, and a loaded xdebug taxes every PHP
 * call several-fold, so numbers measured under it are flagged invalid.
 *
 * To refresh docs/performance.md: dispatch the workflow
 * (`gh workflow run speed-page-table.yml`), paste the linux-x64 table into the
 * page verbatim, update the run link below the table, and refresh the
 * worked-example breakdown and platform bullets from the same table (the
 * News-article page row is the whole-page multiplier the bullets cite).
 *
 * Harness rules (same as speed-probe.php): runtime-built pools of 64 distinct
 * strings, interleaved A/B in one process, best-of-7, results consumed. Before any
 * timing, every pool entry is checked against its category definition and
 * SmartString output is verified byte-identical to full-flag htmlspecialchars().
 */

require __DIR__ . '/../../src/DeprecatedAliases.php';
require __DIR__ . '/../../src/ErrorHelpersTrait.php';
require __DIR__ . '/../../src/SmartString.php';

use Itools\SmartString\SmartString;

const FULL_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5;

// The baseline: the standard safe call wrapped once per project (Laravel's e(),
// Twig's escaper, your own helper) - the page's comparison target. Full flags
// (ENT_DISALLOWED | ENT_HTML5) would slow this baseline ~50% on long strings
// (ENT_DISALLOWED checks every character); we race the faster call.
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

#region Pool builders

/** Cut to at most $bytes without splitting a UTF-8 sequence */
function cutValid(string $text, int $bytes): string
{
    $cut = substr($text, 0, $bytes);
    while ($cut !== '' && preg_match('//u', $cut) !== 1) {
        $cut = substr($cut, 0, -1);
    }
    return $cut;
}

/**
 * 64 distinct strings of ~$bytes built from $unit, rotated per entry by whole
 * characters so multibyte units never produce invalid UTF-8.
 */
function pool(string $unit, int $bytes): array
{
    $chars = preg_split('//u', $unit, -1, PREG_SPLIT_NO_EMPTY);
    $out   = [];
    for ($i = 0; $i < 64; $i++) {
        $shift   = $i % count($chars);
        $rotated = implode('', array_merge(array_slice($chars, $shift), array_slice($chars, 0, $shift)));
        $out[]   = cutValid(str_repeat($rotated, intdiv($bytes, strlen($rotated)) + 1), $bytes);
    }
    return $out;
}

// Category units. Rotation keeps every character in every entry, so each specials
// entry keeps its & and ' and each accented entry keeps its multibyte characters.
// Densities are corpus-measured (Gutenberg EN/FR + published letter-frequency
// tables; modern Wikipedia articles in both languages measure the same rates):
// the apostrophe is the dominant special in real text (~0.1-0.5% of
// typed-English characters, ~1% of typed French); & < > are essentially absent
// from prose (they live in names and titles); French accented characters are
// ~2.5% of all characters. Short units are denser than prose because a short
// field with a special or accent is dense by definition.
const UNIT_CLEAN    = 'Annual Report 2026 Sales Data ';
const UNIT_SPECIALS = "O'Brien & Co Ltd";                                  // exactly 16 bytes: every 16B rotation keeps both specials
const UNIT_ACCENTED = "Caf\xC3\xA9 Montr\xC3\xA9al QC";                    // "Café Montréal QC", 16 bytes, no specials
// One quoted phrase and an apostrophe per ~220 chars (~1.3% specials - prose
// that HAS specials, at typed-content density)
const UNIT_PROSE    = "The company's third-quarter report shows steady growth in every region, and the board called the results \"very encouraging\" in its letter to shareholders. Management expects the same pace next year as new locations open. ";
// Sentence-length variants (< 100 bytes) for the 100 B rows: every 100 B window
// of a rotation must still contain the category's characters
const UNIT_PROSE_SHORT    = "The company's report shows steady growth and the board called it \"very encouraging\" this year. ";
// "La société précise que l'équipe prévoit des résultats stables ces trois prochaines années."
const UNIT_ACCENTED_SHORT = "La soci\xC3\xA9t\xC3\xA9 pr\xC3\xA9cise que l\xE2\x80\x99\xC3\xA9quipe pr\xC3\xA9voit des r\xC3\xA9sultats stables ces trois prochaines ann\xC3\xA9es. ";
// French prose at measured density (~3% accented characters, elision uses the
// typographic apostrophe U+2019, which needs no encoding): "La société précise
// que l'équipe prévoit des résultats stables pour les trois prochaines années.
// Le conseil salue ce travail et confirme les grandes lignes du plan, avec un
// point complet sur les ventes au printemps prochain."
const UNIT_ACCENTED_PROSE = "La soci\xC3\xA9t\xC3\xA9 pr\xC3\xA9cise que l\xE2\x80\x99\xC3\xA9quipe pr\xC3\xA9voit des r\xC3\xA9sultats stables pour les trois prochaines ann\xC3\xA9es. Le conseil salue ce travail et confirme les grandes lignes du plan, avec un point complet sur les ventes au printemps prochain. ";

/** Die unless every entry matches the row's category definition */
function checkCategory(array $strings, string $category): void
{
    foreach ($strings as $s) {
        $hasSpecial   = preg_match('/[&<>"\']/', $s) === 1;
        $hasMultibyte = preg_match('/[\x80-\xFF]/', $s) === 1;
        $ok           = match ($category) {
            'clean'    => !$hasSpecial && !$hasMultibyte,
            'specials' => $hasSpecial && !$hasMultibyte,
            'accented' => !$hasSpecial && $hasMultibyte && preg_match('//u', $s) === 1,
            'mix'      => true,
        };
        if (!$ok) {
            fwrite(STDERR, "Category check failed ($category): " . var_export($s, true) . "\n");
            exit(1);
        }
    }
}

#endregion
#region Timing

/**
 * Best-of-7 interleaved A/B over a pool; returns [a_ns, b_ns] per call.
 * Both sides receive original-typed values and pay their own to-string
 * conversion in the loop: the helper casts then encodes (a template's
 * `e($row['price'])`), SmartString is constructed then output - the (string)
 * cast invokes __toString exactly like echo does. The multiplier is the full
 * cost of each approach per value.
 */
function bench(array $values, int $iterations, string $ssPath = 'echo'): array
{
    foreach ($values as $v) {  // byte-identity gate before any timing
        if ((string)(new SmartString($v)) !== htmlspecialchars((string)$v, FULL_FLAGS, 'UTF-8')) {
            fwrite(STDERR, "MISMATCH: SmartString output differs from htmlspecialchars: " . var_export($v, true) . "\n");
            exit(1);
        }
    }
    $count = count($values);
    $bestA = $bestB = PHP_FLOAT_MAX;
    $sink  = 0;
    for ($rep = 0; $rep < 7; $rep++) {
        $t0 = hrtime(true);
        if ($ssPath !== 'new') {  // construction-only rows have no helper side
            for ($i = 0; $i < $iterations; $i++) {
                $sink += strlen(e((string)$values[$i % $count]));
            }
        }
        $t1 = hrtime(true);
        // Each path is its own literal loop so no closure-dispatch cost pollutes the timing
        switch ($ssPath) {
            case 'new':
                for ($i = 0; $i < $iterations; $i++) {
                    new SmartString($values[$i % $count]);
                }
                break;
            case 'int':
                for ($i = 0; $i < $iterations; $i++) {
                    $sink += strlen((string)(new SmartString($values[$i % $count]))->int());
                }
                break;
            case 'float':
                for ($i = 0; $i < $iterations; $i++) {
                    $sink += strlen((string)(new SmartString($values[$i % $count]))->float());
                }
                break;
            default:
                for ($i = 0; $i < $iterations; $i++) {
                    $sink += strlen((string)(new SmartString($values[$i % $count])));
                }
        }
        $t2    = hrtime(true);
        $bestA = min($bestA, ($t1 - $t0) / $iterations);
        $bestB = min($bestB, ($t2 - $t1) / $iterations);
    }
    if ($sink === -1) {
        echo '';  // consume so the loops can't be optimized away
    }
    return [$ssPath === 'new' ? null : $bestA, $bestB];
}

function ratioLabel(float $ratio): string
{
    return $ratio >= 9.5 ? sprintf('%.0fx', $ratio) : sprintf('%.1fx', $ratio);
}

/** Character count without mbstring: bytes minus UTF-8 continuation bytes */
function charWidth(string $s): int
{
    return strlen($s) - preg_match_all('/[\x80-\xBF]/', $s);
}

/** Markdown table with every column padded so the pipes line up (multibyte-safe) */
function alignedTable(array $rows): string
{
    $widths = [];
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $widths[$i] = max($widths[$i] ?? 0, charWidth($cell));
        }
    }
    $out = '';
    foreach ($rows as $n => $row) {
        $cells = [];
        foreach ($row as $i => $cell) {
            $cells[] = $cell . str_repeat(' ', $widths[$i] - charWidth($cell));
        }
        $out .= '| ' . implode(' | ', $cells) . " |\n";
        if ($n === 0) {
            $out .= '|' . implode('|', array_map(static fn(int $w): string => str_repeat('-', $w + 2), $widths)) . "|\n";
        }
    }
    return $out;
}

#endregion
#region Rows

$opts  = getopt('', ['scale::']);
$scale = max(0.01, (float)($opts['scale'] ?? 1.0));

// [category, bytes label, example, pool, iterations]
$rows = [];

// Construction alone, no output - the object-overhead floor every SmartString
// number below includes; there is no helper side to compare against
$rows[] = ['Create a SmartString - no output', 'any', '`new SmartString($value)`', pool(UNIT_CLEAN, 16), 300000, 'new'];

// Empty fields: null skips everything via the non-string cast, "" runs the scan
// and finds nothing - both cost pure call overhead
$empties = [];
for ($i = 0; $i < 32; $i++) {
    $empties[] = null;
    $empties[] = '';
}
$rows[] = ['Empty - null or ""', 'any', 'a blank optional field', $empties, 300000];

// Numbers: ints and floats skip the scan entirely; each side pays its own
// to-string conversion inside the timed loop
$ints = $floats = [];
for ($i = 0; $i < 64; $i++) {
    $ints[]   = 1000000 + $i * 12345;
    $floats[] = round(9.99 + $i * 1.37, 2);
}
$rows[] = ['Numbers - int', 'any', '`1499`', $ints, 300000];
$rows[] = ['Numbers - float', 'any', '`24.99`', $floats, 300000];
// Same int/float pools output through the typed accessors instead of __toString:
// echo $row->qty->int() returns the raw number, no encoding path at all
$rows[] = ['Numbers - via `->int()`', 'any', '`1499`', $ints, 300000, 'int'];
$rows[] = ['Numbers - via `->float()`', 'any', '`24.99`', $floats, 300000, 'float'];

$examples = [
    'clean'    => [16 => '`Annual Report 2026`', 100 => 'a short sentence', 200 => 'a sentence or two', 1024 => 'a plain-text paragraph', 10240 => 'a long field, nothing to encode'],
    'specials' => [16 => "`O'Brien & Co Ltd`", 100 => 'a sentence with quotes', 200 => 'a sentence or two with quotes', 1024 => 'a paragraph with quotes', 10240 => 'a 1,500-word article'],
    'accented' => [16 => "`Caf\xC3\xA9 Montr\xC3\xA9al QC`", 100 => 'a short French sentence', 200 => 'a French sentence or two', 1024 => 'a French paragraph', 10240 => 'a French article'],
];
$sizes = [16 => 200000, 100 => 180000, 200 => 150000, 1024 => 30000, 10240 => 4000];
foreach ([['clean', UNIT_CLEAN], ['specials', UNIT_SPECIALS], ['accented', UNIT_ACCENTED]] as [$category, $unit]) {
    $label = match ($category) {
        'clean'    => 'Clean text - no `& < > " \'`',
        'specials' => 'Has `& < > " \'`',
        'accented' => 'Accented text - no `& < > " \'`',
    };
    foreach ($sizes as $bytes => $iterations) {
        // Sentence/paragraph rows use prose units at corpus density; the 16B rows
        // keep the dense units - a short field with a special or accent is dense
        // by definition
        $poolUnit = match (true) {
            $category === 'specials' && $bytes === 100 => UNIT_PROSE_SHORT,
            $category === 'specials' && $bytes > 100   => UNIT_PROSE,
            $category === 'accented' && $bytes === 100 => UNIT_ACCENTED_SHORT,
            $category === 'accented' && $bytes > 100   => UNIT_ACCENTED_PROSE,
            default                                    => $unit,
        };
        $strings  = pool($poolUnit, $bytes);
        checkCategory($strings, $category);
        $sizeLabel = $bytes >= 1024 ? sprintf('%d KB', intdiv($bytes, 1024)) : sprintf('%d B', $bytes);
        $rows[]    = [$label, $sizeLabel, $examples[$category][$bytes], $strings, $iterations];
    }
}

// News-article page: the page the performance page prices field by field - a
// quoted headline, three clean shorts, a 200 B caption, and a 10 KB body per
// six-field cycle. The measured ratio IS the whole-page multiplier the docs
// bullets cite; the per-field rows above are its components.
$sp16  = pool(UNIT_SPECIALS, 16);
$cl16  = pool(UNIT_CLEAN, 16);
$cl200 = pool(UNIT_CLEAN, 200);
$body  = pool(UNIT_PROSE, 10240);
$page  = [];
for ($i = 0; $i < 12; $i++) {
    $page[] = $sp16[$i];
    $page[] = $cl16[$i];
    $page[] = $cl16[$i + 12];
    $page[] = $cl16[$i + 24];
    $page[] = $cl200[$i];
    $page[] = $body[$i];
}
$rows[] = ['News-article page', 'mixed', '*', $page, 12000];

#endregion
#region Run and report

$opcache = function_exists('opcache_get_status') && opcache_get_status() !== false;
// xdebug.mode=off removes xdebug's per-call tax (measured identical to not
// loading it); any active mode invalidates every short-row timing.
// xdebug_info('mode') lists the active modes - empty means off, regardless of
// whether that came from -d, the ini file, or the XDEBUG_MODE env var.
/** @disregard P1010 xdebug_info() is guarded by function_exists(); xdebug stubs may be absent */
$xdebugModes = extension_loaded('xdebug') && function_exists('xdebug_info') ? xdebug_info('mode') : null;
$xdebugLabel = match (true) {
    !extension_loaded('xdebug') => ' | xdebug off',
    $xdebugModes === []         => ' | xdebug loaded, mode off',
    default                     => ' | xdebug ACTIVE - NOT CITABLE, xdebug taxes every PHP call (retry with -d xdebug.mode=off added to the command)',
};
printf(
    "PHP %s | %s %s | opcache %s%s%s\n\n",
    PHP_VERSION,
    PHP_OS_FAMILY,
    php_uname('m'),
    $opcache ? 'on' : 'OFF',
    $opcache ? '' : ' - not the production configuration; retry with -d opcache.enable_cli=1',
    $xdebugLabel
);

$tableRows = [['Content', 'Size', 'Example', '`htmlspecialchars()`', 'SmartString', 'Speed vs `htmlspecialchars()`']];
foreach ($rows as $row) {
    [$label, $sizeLabel, $example, $values, $iterations] = $row;
    [$a, $b] = bench($values, max(1, (int)($iterations * $scale)), $row[5] ?? 'echo');
    $tableRows[] = [
        $label,
        $sizeLabel,
        $example,
        $a === null ? '-' : number_format($a) . ' ns',
        number_format($b) . ' ns',
        $a === null ? '-' : ratioLabel($a / $b),
    ];
}

echo alignedTable($tableRows);
echo "\n\\* News-article page: a 16 B quoted headline; author, category, and date (16 B plain); a 200 B caption; and a 10 KB body with quotes.\n";
echo "\nPer call, best of 7, measured on " . PHP_OS_FAMILY . ' ' . php_uname('m') . ", PHP " . PHP_VERSION . ".\n";

#endregion
