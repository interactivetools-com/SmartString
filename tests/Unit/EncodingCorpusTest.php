<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * Guards the encoding fast path: __toString() and htmlEncode() skip
 * htmlspecialchars() via ENCODE_SKIP_REGEX when no byte would change, so their
 * output must stay byte-identical to plain htmlspecialchars() with
 * HTML_ENCODE_FLAGS on every input. This test proves that over the ~106k-string
 * corpus in .github/scripts/speed-corpus.php (every 1- and 2-byte string, byte
 * position sweeps, valid and invalid UTF-8, noncharacters, seeded fuzz).
 *
 * If HTML_ENCODE_FLAGS or ENCODE_SKIP_REGEX changes without the other, this
 * test fails with hex samples of the mismatching inputs.
 */
class EncodingCorpusTest extends SmartStringTestCase
{
    public function testOutputMatchesHtmlspecialcharsOnFullCorpus(): void
    {
        require_once dirname(__DIR__, 2) . '/.github/scripts/speed-corpus.php';

        $corpus = speed_corpus();
        $this->assertGreaterThan(90000, count($corpus), 'corpus builder returned too few strings');

        $fail    = 0;
        $samples = [];
        foreach ($corpus as $s) {
            $want = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
            $str  = new SmartString($s);
            if ((string)$str !== $want || $str->htmlEncode() !== $want) {
                $fail++;
                if (count($samples) < 5) {
                    $samples[] = sprintf('input=%s want=%s toString=%s htmlEncode=%s',
                        bin2hex($s), bin2hex($want), bin2hex((string)$str), bin2hex($str->htmlEncode()));
                }
            }
        }

        $this->assertSame(0, $fail, "encoding mismatches on corpus:\n" . implode("\n", $samples));
    }

    /**
     * Tier 1 tests the same byte set two ways: ENCODE_SKIP_REGEX (preg, "any byte
     * needing encoding?") and ENCODE_CLEAN_CHARS (strspn on PHP 8.4+, "every byte
     * clean?"). This proves they are exact complements for all 256 bytes, on every
     * PHP version - the corpus test alone only exercises the strspn path on 8.4+.
     */
    public function testCleanCharsIsExactComplementOfSkipRegex(): void
    {
        $class     = new \ReflectionClass(SmartString::class);
        $skipRegex = $class->getConstant('ENCODE_SKIP_REGEX');
        $cleanSet  = $class->getConstant('ENCODE_CLEAN_CHARS');

        for ($b = 0; $b <= 0xFF; $b++) {
            $char        = chr($b);
            $regexSkips  = preg_match($skipRegex, $char) === 1;
            $strspnClean = strpos($cleanSet, $char) !== false;
            $this->assertSame($regexSkips, !$strspnClean,
                sprintf('byte 0x%02X: ENCODE_SKIP_REGEX says %s but ENCODE_CLEAN_CHARS says %s',
                    $b, $regexSkips ? 'encode' : 'clean', $strspnClean ? 'clean' : 'encode'));
        }
        $this->assertSame(strlen(count_chars($cleanSet, 3)), strlen($cleanSet), 'ENCODE_CLEAN_CHARS has duplicate bytes');
    }

    /**
     * The type fast path returns (string)$value for non-strings with no scan. That
     * is only safe if every int/float/bool/null cast produces characters the encoder
     * would leave untouched - digits, sign, '.', scientific 'E+', 'INF'/'NAN', '1',
     * ''. This sweep enforces it against htmlspecialchars directly and against both
     * output methods.
     */
    public function testNonStringValuesNeedNoEncoding(): void
    {
        $values = [
            null, true, false,
            0, 1, -1, 42, -9876543210, PHP_INT_MAX, PHP_INT_MIN,
            0.0, -0.0, 0.5, -123.456, 1 / 3, 0.1 + 0.2,
            1.0E+25, -1.0E-25, 9.9999999999999E+308, PHP_FLOAT_EPSILON, PHP_FLOAT_MAX, -PHP_FLOAT_MAX,
            INF, -INF, NAN,
        ];
        for ($i = 0; $i < 500; $i++) { // seeded numeric fuzz
            mt_srand($i);
            $values[] = mt_rand(PHP_INT_MIN, PHP_INT_MAX);
            $values[] = mt_rand() / mt_getrandmax() * (10 ** mt_rand(-300, 300)) * (mt_rand(0, 1) ? 1 : -1);
        }

        foreach ($values as $value) {
            // PHP 8.5 warns when NAN/INF coerce to string; the outputs ('NAN', 'INF',
            // '-INF') are still what we assert against, so suppress just those casts
            $cast = @(string)$value;
            $this->assertSame(
                htmlspecialchars($cast, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8'),
                $cast,
                "cast of " . var_export($value, true) . " is not encoding-neutral"
            );
            $str = new SmartString($value);
            $this->assertSame($cast, @(string)$str);
            $this->assertSame($cast, @$str->htmlEncode());
        }
    }
}
