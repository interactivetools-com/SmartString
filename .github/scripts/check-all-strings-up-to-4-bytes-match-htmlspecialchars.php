<?php
declare(strict_types=1);

/**
 * Checks that SmartString encodes every possible string of 1 to 4 bytes
 * byte-identically to htmlspecialchars().
 *
 * Both htmlEncode() and __toString() on the real SmartString class must match
 * htmlspecialchars($s, HTML_ENCODE_FLAGS, 'UTF-8') for EVERY byte sequence of
 * the given length - 4,294,967,296 strings at length 4 plus 16,843,008 at
 * lengths 1-3. Four bytes is the longest UTF-8 sequence, so this covers the
 * full utf8mb4 space including every invalid byte combination. Not sampled,
 * not fuzzed: all of them.
 *
 * CI: the "Check all strings up to 4 bytes match htmlspecialchars" workflow
 * runs this on every supported PHP version (manual dispatch, ~20 min/version).
 *
 * Local run (from the repo root):
 *
 *     # lengths 1+2+3 in one pass (~17M strings, seconds):
 *     php -d opcache.enable_cli=1 .github/scripts/check-all-strings-up-to-4-bytes-match-htmlspecialchars.php --len=3
 *
 *     # length 4 (4.3 billion strings), sharded by first byte across all cores:
 *     seq 0 255 | xargs -P "$(nproc)" -I{} \
 *         php -d opcache.enable_cli=1 .github/scripts/check-all-strings-up-to-4-bytes-match-htmlspecialchars.php --len=4 --first={}
 *
 *     # then check for failures (no file = zero mismatches):
 *     cat __encode-mismatches.log 2>/dev/null || echo "ALL MATCH"
 *
 * Mismatches append to the log as hex triples (input / want / got); a summary
 * line per shard goes to stdout. Exit code 1 if this shard found any mismatch.
 *
 * The 256-byte strspn tier is out of this test's reach (max input here is 4
 * bytes); that path is covered by EncodingCorpusTest's boundary strings.
 */

require dirname(__DIR__, 2) . '/src/ErrorHelpersTrait.php';
require dirname(__DIR__, 2) . '/src/DeprecatedAliases.php';
require dirname(__DIR__, 2) . '/src/SmartString.php';

use Itools\SmartString\SmartString;

const CHECK_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5;

$opts    = getopt('', ['len:', 'first:', 'log:']);
$len     = (int)($opts['len'] ?? 3);
$logFile = (string)($opts['log'] ?? dirname(__DIR__, 2) . '/__encode-mismatches.log');

$fail    = 0;
$checked = 0;
$start   = hrtime(true);

$check = static function (string $s) use (&$fail, &$checked, $logFile): void {
    $checked++;
    $want = htmlspecialchars($s, CHECK_FLAGS, 'UTF-8');
    $obj  = new SmartString($s);
    $enc  = $obj->htmlEncode();
    $str  = (string)$obj;
    if ($enc !== $want || $str !== $want) {
        $fail++;
        $line = sprintf("PHP %s input=%s want=%s htmlEncode=%s toString=%s\n",
            PHP_VERSION, bin2hex($s), bin2hex($want), bin2hex($enc), bin2hex($str));
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
};

if ($len <= 3) {
    // Lengths 1, 2, and 3 in one pass (the empty string is covered by the corpus test)
    for ($a = 0; $a <= 0xFF; $a++) {
        $ca = chr($a);
        $check($ca);
        for ($b = 0; $b <= 0xFF; $b++) {
            $p = $ca . chr($b);
            $check($p);
            for ($c = 0; $c <= 0xFF; $c++) {
                $check($p . chr($c));
            }
        }
    }
    $label = 'len 1-3';
} else {
    // Length 4, one shard = one fixed first byte (run 256 shards in parallel)
    if (!isset($opts['first'])) {
        fwrite(STDERR, "--len=4 requires --first=0..255 (shard by first byte)\n");
        exit(2);
    }
    $ca = chr((int)$opts['first']);
    for ($b = 0; $b <= 0xFF; $b++) {
        $p2 = $ca . chr($b);
        for ($c = 0; $c <= 0xFF; $c++) {
            $p3 = $p2 . chr($c);
            for ($d = 0; $d <= 0xFF; $d++) {
                $check($p3 . chr($d));
            }
        }
    }
    $label = 'len 4 first=' . (int)$opts['first'];
}

printf("PHP %s | %s | checked=%d fail=%d in %.1fs\n",
    PHP_VERSION, $label, $checked, $fail, (hrtime(true) - $start) / 1e9);
exit($fail > 0 ? 1 : 0);
