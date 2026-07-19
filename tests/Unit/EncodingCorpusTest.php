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
}
