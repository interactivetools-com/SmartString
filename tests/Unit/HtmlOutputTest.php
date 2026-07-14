<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * The terminal HTML exits: nl2br(), appendHtml(), wrapHtml(), and the
 * silent alias textToHtml().
 *
 * All are terminal (return native string), so immutability is n/a.
 * n/a dimensions: global settings, argument matrix (markup arguments are
 * plain strings by contract - trusted literals, never user values).
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
    //region appendHtml()

    /**
     * The value is encoded, the markup argument is not, and missing values
     * return "" so the markup never appears alone.
     */
    #[DataProvider('appendHtmlProvider')]
    public function testAppendHtml($input, string $html, string $expected): void
    {
        $this->assertSame($expected, SmartString::new($input)->appendHtml($html));
    }

    public static function appendHtmlProvider(): array
    {
        return [
            'address line'       => ['12 High St', ",<br>\n", "12 High St,<br>\n"],
            'value is encoded'   => ['Bob & Sons <Ltd>', '<br>', 'Bob &amp; Sons &lt;Ltd&gt;<br>'],
            'null returns empty' => [null, '<br>', ''],
            'blank returns empty' => ['', '<br>', ''],
            'zero is present'    => [0, '<br>', '0<br>'],
            'false is present but blank' => [false, '<br>', '<br>'], // matches append(): false attaches alone
        ];
    }

    //endregion
    //region wrapHtml()

    /**
     * The value is encoded, the markup arguments are not, and missing values
     * return "" - the whole wrapper vanishes (replaces the isNotEmpty-guard
     * template idiom).
     */
    #[DataProvider('wrapHtmlProvider')]
    public function testWrapHtml($input, string $before, string $after, string $expected): void
    {
        $this->assertSame($expected, SmartString::new($input)->wrapHtml($before, $after));
    }

    public static function wrapHtmlProvider(): array
    {
        return [
            'heading wrapper'     => ['Our Story', '<h2 class="lead">', '</h2>', '<h2 class="lead">Our Story</h2>'],
            'value is encoded'    => ['<script>alert(1)</script>', '<p>', '</p>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
            'null vanishes'       => [null, '<h2>', '</h2>', ''],
            'blank vanishes'      => ['', '<h2>', '</h2>', ''],
            'zero is present'     => [0, '<td>', '</td>', '<td>0</td>'],
            'prefix-only markup'  => ['555-1234', '<i class="icon-phone"></i> ', '', '<i class="icon-phone"></i> 555-1234'],
            'value into attribute' => ['photo "1".jpg', '<img src="/uploads/', '" alt="">', '<img src="/uploads/photo &quot;1&quot;.jpg" alt="">'],
        ];
    }

    public function testHtmlExitsAreTerminalStrings(): void
    {
        $this->assertIsString(SmartString::new('x')->appendHtml('<br>'));
        $this->assertIsString(SmartString::new('x')->wrapHtml('<b>', '</b>'));

        // formatting and fallbacks chain BEFORE the terminal exit
        $price = SmartString::new(null);
        $this->assertSame('0.00<br>', $price->numberFormat(2)->or('0.00')->appendHtml('<br>'));
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
