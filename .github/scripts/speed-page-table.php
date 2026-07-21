<?php
declare(strict_types=1);
/**
 * Generates the docs/performance.md category table: helper-wrapped htmlspecialchars()
 * versus echoing a real SmartString, per content category and size.
 *
 *     php .github/scripts/speed-page-table.php [--scale=1.0]
 *
 * Loads the real SmartString class from src/ (no composer install needed) so the
 * numbers include true object and method-call overhead. Run via the Speed Page Table
 * workflow for citable numbers: CI enables opcache like production; this script
 * warns when opcache is off because short-string rows are then dominated by
 * unoptimized call overhead.
 *
 * To refresh docs/performance.md: dispatch the workflow
 * (`gh workflow run speed-page-table.yml`), paste the linux-x64 table into the
 * page verbatim, and update the run link below the table.
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
const UNIT_CLEAN    = 'Annual Report 2026 Sales Data ';
const UNIT_SPECIALS = "O'Brien & Co Ltd";                                  // exactly 16 bytes: every 16B rotation keeps both specials
const UNIT_ACCENTED = "Caf\xC3\xA9 Montr\xC3\xA9al QC";                    // "Café Montréal QC", 16 bytes, no specials
const UNIT_PROSE    = "The company's Q3 report, prepared by O'Brien & Co, shows steady growth this year. "; // realistic apostrophe density for paragraph/article rows

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
 * $objects are prebuilt (construction isn't what the table measures).
 */
function bench(array $strings, array $objects, int $iterations): array
{
    foreach ($strings as $k => $s) {  // byte-identity gate before any timing
        if ((string)$objects[$k] !== htmlspecialchars($s, FULL_FLAGS, 'UTF-8')) {
            fwrite(STDERR, "MISMATCH: SmartString output differs from htmlspecialchars: " . var_export($s, true) . "\n");
            exit(1);
        }
    }
    $count = count($strings);
    $bestA = $bestB = PHP_FLOAT_MAX;
    $sink  = 0;
    for ($rep = 0; $rep < 7; $rep++) {
        $t0 = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $sink += strlen(e($strings[$i % $count]));
        }
        $t1 = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $sink += strlen((string)$objects[$i % $count]);
        }
        $t2    = hrtime(true);
        $bestA = min($bestA, ($t1 - $t0) / $iterations);
        $bestB = min($bestB, ($t2 - $t1) / $iterations);
    }
    if ($sink === -1) {
        echo '';  // consume so the loops can't be optimized away
    }
    return [$bestA, $bestB];
}

function ratioLabel(float $ratio): string
{
    return $ratio >= 9.5 ? sprintf('%.0fx', $ratio) : sprintf('%.1fx', $ratio);
}

#endregion
#region Rows

$opts  = getopt('', ['scale::']);
$scale = max(0.01, (float)($opts['scale'] ?? 1.0));

// [category, bytes label, example, pool, iterations]
$mixLabel = '70% 16B clean, 15% 200B, 10% 1KB, 5% 1KB with quotes';
$rows     = [];

// Numbers first: ints and floats skip the scan entirely
$numbers = [];
for ($i = 0; $i < 32; $i++) {
    $numbers[] = 1000000 + $i * 12345;
    $numbers[] = round(9.99 + $i * 1.37, 2);
}
// Measured for the record but kept out of the docs table: both sides are a handful
// of nanoseconds, so the ratio reads as alarming while the absolute cost (~60ns of
// object overhead per field) is noise at page scale
$rows[] = ['Numbers - int, float', 'any', '`1499`, `24.99`', $numbers, 300000, false];

// Empty fields: null skips everything via the non-string cast, "" runs the scan
// and finds nothing - both cost pure call overhead
$empties = [];
for ($i = 0; $i < 32; $i++) {
    $empties[] = null;
    $empties[] = '';
}
$rows[] = ['Empty - null or ""', 'any', 'a blank optional field', $empties, 300000, false];

$examples = [
    'clean'    => [16 => '`Annual Report 2026`', 1024 => 'a plain-text paragraph', 10240 => 'a long field, nothing to encode'],
    'specials' => [16 => "`O'Brien & Co Ltd`", 1024 => 'a paragraph with quotes', 10240 => 'a 1,500-word article'],
    'accented' => [16 => "`Caf\xC3\xA9 Montr\xC3\xA9al QC`", 1024 => 'a French paragraph', 10240 => 'a French article'],
];
foreach ([['clean', UNIT_CLEAN], ['specials', UNIT_SPECIALS], ['accented', UNIT_ACCENTED]] as [$category, $unit]) {
    $label = match ($category) {
        'clean'    => 'Clean text - no `& < > " \'`',
        'specials' => 'Has `& < > " \'`',
        'accented' => 'Accented text - no specials',
    };
    foreach ([16 => 200000, 1024 => 30000, 10240 => 4000] as $bytes => $iterations) {
        // Paragraph/article rows use realistic prose density (an apostrophe or two
        // per sentence); the 16B row keeps the dense unit - a short field with a
        // special is dense by definition
        $poolUnit = ($category === 'specials' && $bytes > 16) ? UNIT_PROSE : $unit;
        $strings  = pool($poolUnit, $bytes);
        checkCategory($strings, $category);
        $sizeLabel = $bytes >= 1024 ? sprintf('%d KB', intdiv($bytes, 1024)) : sprintf('%d B', $bytes);
        $rows[]    = [$label, $sizeLabel, $examples[$category][$bytes], $strings, $iterations];
    }
}

// Page mixes: proportions of a 64-entry pool; every iteration cycles the pool so
// the measured cost is the weighted average of one field output
$standardMix = array_merge(
    array_slice(pool(UNIT_CLEAN, 16), 0, 45),
    array_slice(pool(UNIT_CLEAN, 200), 0, 10),
    array_slice(pool(UNIT_CLEAN, 1024), 0, 6),
    array_slice(pool(UNIT_PROSE, 1024), 0, 3),
);
$rows[] = ['Realistic page mix', 'mixed', $mixLabel, $standardMix, 60000];

$articleMix = array_merge(array_slice($standardMix, 0, 63), array_slice(pool(UNIT_PROSE, 10240), 0, 1));
$rows[] = ['Page mix + one 10 KB article', 'mixed', 'the mix above plus an article with quotes', $articleMix, 60000];

#endregion
#region Run and report

$opcache = function_exists('opcache_get_status') && opcache_get_status() !== false;
printf(
    "PHP %s | %s %s | opcache %s%s\n\n",
    PHP_VERSION,
    PHP_OS_FAMILY,
    php_uname('m'),
    $opcache ? 'on' : 'OFF',
    $opcache ? '' : ' - NOT CITABLE, short-string rows dominated by unoptimized call overhead'
);

$table   = "| Content | Size | Example | SmartString speed |\n|---|---|---|---|\n";
$rawLines = [];
foreach ($rows as $row) {
    [$label, $sizeLabel, $example, $values, $iterations] = $row;
    $inTable = $row[5] ?? true;
    // Helper side gets strings (a template's implicit cast); SmartString stores the
    // original type, so the Numbers row exercises the non-string fast path
    $strings = array_map(static fn($v): string => (string)$v, $values);
    $objects = array_map(static fn($v): SmartString => new SmartString($v), $values);
    [$a, $b] = bench($strings, $objects, max(1, (int)($iterations * $scale)));
    if ($inTable) {
        $table .= sprintf("| %s | %s | %s | %s |\n", $label, $sizeLabel, $example, ratioLabel($a / $b));
    }
    $rawLines[] = sprintf('%-30s %8s  helper %8.0f ns  SmartString %8.0f ns', $label, $sizeLabel, $a, $b);
}

echo $table;
echo "\n2x = SmartString outputs the value in half the time `htmlspecialchars()` takes; 1.0x = same speed; below 1x = slower.\n";
echo "Measured on " . PHP_OS_FAMILY . ' ' . php_uname('m') . ", PHP " . PHP_VERSION . ".\n";
echo "\nRaw timings (per call, best of 7):\n" . implode("\n", $rawLines) . "\n";

#endregion
