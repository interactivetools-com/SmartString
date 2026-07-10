<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * nl2br() and its silent alias textToHtml().
 *
 * Both are terminal (return native string), so immutability is n/a.
 * n/a dimensions: global settings, argument matrix (keepBr is a plain bool).
 */
class HtmlOutputTest extends SmartStringTestCase
{
    //region nl2br()

    /**
     * Encode first, then convert newlines - the only tags in the output are
     * the <br> tags the method adds.
     */
    #[DataProvider('nl2brProvider')]
    public function testNl2br($input, string $expected): void
    {
        $result = SmartString::new($input)->nl2br();
        $this->assertSame($expected, $result);
    }

    public static function nl2brProvider(): array
    {
        return [
            'newline to br'        => ["Hello\nWorld", "Hello<br>\nWorld"],
            'encode then br'       => ["It's <b>bold</b>\nLine 2", "It&apos;s &lt;b&gt;bold&lt;/b&gt;<br>\nLine 2"],
            'br in data encoded'   => ["Hello<br>World", 'Hello&lt;br&gt;World'],
            'docblock example'     => ["Bob & Sons\nSuite 5", "Bob &amp; Sons<br>\nSuite 5"],
            'crlf newline'         => ["a\r\nb", "a<br>\r\nb"],
            'null becomes empty'   => [null, ''],
            'empty string'         => ['', ''],
            'invalid utf8'         => ["caf\xE9\nbar", "caf�<br>\nbar"],
        ];
    }

    //endregion
    //region textToHtml()

    public function testTextToHtmlDefaultMatchesNl2br(): void
    {
        foreach (["It's <b>bold</b>\nLine 2", "Hello<br>World", null, '', "a\nb\nc"] as $input) {
            $this->assertSame(
                SmartString::new($input)->nl2br(),
                SmartString::new($input)->textToHtml(),
                'textToHtml() diverged from nl2br() for: ' . var_export($input, true)
            );
        }
    }

    /**
     * keepBr: existing <br> tags survive encoding and newlines are left
     * alone (for CMS text fields that store line breaks as <br> tags).
     */
    public function testTextToHtmlKeepBrPreservesBrTags(): void
    {
        $this->assertSame('Hello<br>World', SmartString::new('Hello<br>World')->textToHtml(keepBr: true));
        $this->assertSame('Hello<BR>World<br/>End', SmartString::new('Hello<BR>World<br/>End')->textToHtml(keepBr: true));
        $this->assertSame('a<br />b', SmartString::new('a<br />b')->textToHtml(keepBr: true));
    }

    public function testTextToHtmlKeepBrLeavesNewlinesAlone(): void
    {
        $this->assertSame("line\ntwo", SmartString::new("line\ntwo")->textToHtml(keepBr: true));
    }

    public function testTextToHtmlKeepBrDoesNotPreserveBrWithAttributes(): void
    {
        // only bare <br>, <br/>, <br /> forms are restored; anything else stays encoded
        $this->assertSame('&lt;br class=&quot;x&quot;&gt;line', SmartString::new('<br class="x">line')->textToHtml(keepBr: true));
    }

    public function testTextToHtmlKeepBrStillEncodesEverythingElse(): void
    {
        $this->assertSame('&lt;b&gt;bold&lt;/b&gt;<br>ok', SmartString::new('<b>bold</b><br>ok')->textToHtml(keepBr: true));
    }

    //endregion
}
