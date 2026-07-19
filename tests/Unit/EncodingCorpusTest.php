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
}
