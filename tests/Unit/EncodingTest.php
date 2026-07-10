<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\Support\SmartStringTestCase;

/**
 * htmlEncode(), urlEncode(), jsonEncode(), rawHtml(), __toString(),
 * jsonSerialize() - every output path against the hard inputs.
 *
 * n/a dimensions: global settings (encoders take none), argument matrix
 * (no value parameters).
 */
class EncodingTest extends SmartStringTestCase
{
    //region htmlEncode() and __toString()

    /**
     * The HTML_ENCODE_FLAGS contract: ENT_HTML5 quote entities, invalid
     * UTF-8 → �, C0/C1 controls → �, invisible Unicode passes through.
     */
    #[DataProvider('htmlPairsProvider')]
    public function testHtmlEncode(string $input, string $expected): void
    {
        $this->assertSame($expected, SmartString::new($input)->htmlEncode());
    }

    #[DataProvider('htmlPairsProvider')]
    public function testToStringMatchesHtmlEncode(string $input, string $expected): void
    {
        $this->assertSame($expected, (string)SmartString::new($input));
    }

    public static function htmlPairsProvider(): array
    {
        return Fixtures::htmlPairs();
    }

    public function testToStringEncodesInStringContexts(): void
    {
        $smartString = SmartString::new('<b>Bold</b> & <i>Italic</i>');
        $this->assertSame('Text: &lt;b&gt;Bold&lt;/b&gt; &amp; &lt;i&gt;Italic&lt;/i&gt;', 'Text: ' . $smartString);
        $this->assertSame("Text: &lt;b&gt;Bold&lt;/b&gt; &amp; &lt;i&gt;Italic&lt;/i&gt;", "Text: $smartString");
    }

    public function testHtmlEncodeEncodesBrTags(): void
    {
        $this->assertSame('Text with &lt;br&gt; and &lt;br/&gt; tags', SmartString::new('Text with <br> and <br/> tags')->htmlEncode());
    }

    //endregion
    //region Shared Encoder Menu

    /**
     * Ported from the old suite: four encoders against shared inputs.
     * Single-quoted inputs contain literal backslash-n on purpose.
     */
    #[DataProvider('encoderMenuProvider')]
    public function testEncoderMenu($input, string $expectedHtml, string $expectedUrl, string $expectedJson, $expectedRawHtml): void
    {
        $smartString = SmartString::new($input);
        $label       = "input: " . var_export($input, true);

        $this->assertSame($expectedHtml, $smartString->htmlEncode(), "htmlEncode() failed for $label");
        $this->assertSame($expectedUrl, $smartString->urlEncode(), "urlEncode() failed for $label");
        $this->assertSame($expectedJson, $smartString->jsonEncode(), "jsonEncode() failed for $label");
        $this->assertSame($expectedRawHtml, $smartString->rawHtml(), "rawHtml() failed for $label");
    }

    /** @noinspection SpellCheckingInspection */
    public static function encoderMenuProvider(): array
    {
        return [
            'plaintext with br'        => [
                'input'            => 'One\nTwo<br>Three<br>\nFour<br>\n',
                'expectedHtml'     => 'One\nTwo&lt;br&gt;Three&lt;br&gt;\nFour&lt;br&gt;\n',
                'expectedUrl'      => 'One%5CnTwo%3Cbr%3EThree%3Cbr%3E%5CnFour%3Cbr%3E%5Cn',
                'expectedJson'     => '"One\\\\nTwo\u003Cbr\u003EThree\u003Cbr\u003E\\\\nFour\u003Cbr\u003E\\\\n"',
                'expectedRawHtml'  => 'One\nTwo<br>Three<br>\nFour<br>\n',
            ],
            'already encoded'          => [
                'input'            => '&lt;hello&gt;&lt;br&gt;world!<br>\n',
                'expectedHtml'     => '&amp;lt;hello&amp;gt;&amp;lt;br&amp;gt;world!&lt;br&gt;\n',
                'expectedUrl'      => '%26lt%3Bhello%26gt%3B%26lt%3Bbr%26gt%3Bworld%21%3Cbr%3E%5Cn',
                'expectedJson'     => '"\u0026lt;hello\u0026gt;\u0026lt;br\u0026gt;world!\u003Cbr\u003E\\\\n"',
                'expectedRawHtml'  => '&lt;hello&gt;&lt;br&gt;world!<br>\n',
            ],
            'HTML special chars'       => [
                'input'            => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
                'expectedHtml'     => 'O&apos;Reilly &amp; Sons &lt;Web &amp;nbsp; Shop/&gt;',
                'expectedUrl'      => 'O%27Reilly+%26+Sons+%3CWeb+%26nbsp%3B+Shop%2F%3E',
                'expectedJson'     => '"O\u0027Reilly \u0026 Sons \u003CWeb \u0026nbsp; Shop/\u003E"',
                'expectedRawHtml'  => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
            ],
            'JavaScript special chars' => [
                'input'            => "Line1\nLine2\rLine3\tTab",
                'expectedHtml'     => "Line1\nLine2\rLine3\tTab",
                'expectedUrl'      => 'Line1%0ALine2%0DLine3%09Tab',
                'expectedJson'     => '"Line1\nLine2\rLine3\tTab"',
                'expectedRawHtml'  => "Line1\nLine2\rLine3\tTab",
            ],
            'URL special chars'        => [
                'input'            => 'https://example.com/path?param1=value1&param2=value2',
                'expectedHtml'     => 'https://example.com/path?param1=value1&amp;param2=value2',
                'expectedUrl'      => 'https%3A%2F%2Fexample.com%2Fpath%3Fparam1%3Dvalue1%26param2%3Dvalue2',
                'expectedJson'     => '"https://example.com/path?param1=value1\u0026param2=value2"',
                'expectedRawHtml'  => 'https://example.com/path?param1=value1&param2=value2',
            ],
            'Mixed special chars'      => [
                'input'            => '<script>alert("XSS & Injection!");</script>',
                'expectedHtml'     => '&lt;script&gt;alert(&quot;XSS &amp; Injection!&quot;);&lt;/script&gt;',
                'expectedUrl'      => '%3Cscript%3Ealert%28%22XSS+%26+Injection%21%22%29%3B%3C%2Fscript%3E',
                'expectedJson'     => '"\u003Cscript\u003Ealert(\u0022XSS \u0026 Injection!\u0022);\u003C/script\u003E"',
                'expectedRawHtml'  => '<script>alert("XSS & Injection!");</script>',
            ],
            'Unicode characters'       => [
                'input'            => 'Café ñ 日本語',
                'expectedHtml'     => 'Café ñ 日本語',
                'expectedUrl'      => 'Caf%C3%A9+%C3%B1+%E6%97%A5%E6%9C%AC%E8%AA%9E',
                'expectedJson'     => '"Café ñ 日本語"',
                'expectedRawHtml'  => 'Café ñ 日本語',
            ],
            'Empty string'             => [
                'input'            => '',
                'expectedHtml'     => '',
                'expectedUrl'      => '',
                'expectedJson'     => '""',
                'expectedRawHtml'  => '',
            ],
            'Null input'               => [
                'input'            => null,
                'expectedHtml'     => '',
                'expectedUrl'      => '',
                'expectedJson'     => 'null',
                'expectedRawHtml'  => null,
            ],
        ];
    }

    //endregion
    //region urlEncode()

    /**
     * Pinned: urlencode() is byte-level, so invalid UTF-8 passes through
     * percent-encoded raw instead of being substituted with � like the
     * other encoders.
     */
    public function testUrlEncodePassesInvalidUtf8ThroughRaw(): void
    {
        $this->assertSame('caf%E9', SmartString::new("caf\xE9")->urlEncode());
    }

    public function testUrlEncodeDocblockExample(): void
    {
        $this->assertSame('Save+10%25%2B+off', SmartString::new('Save 10%+ off')->urlEncode());
    }

    //endregion
    //region jsonEncode()

    /**
     * The 2.7.0 hardening contract, ported as-is: malformed UTF-8 becomes �
     * instead of throwing, and invisible Unicode is re-escaped as visible
     * \uXXXX (losslessly).
     */
    public function testJsonEncodeHardening(): void
    {
        // malformed UTF-8 byte becomes U+FFFD instead of throwing
        $this->assertSame('"a�(b"', SmartString::new("a\xC3(b")->jsonEncode());

        // invisible Unicode re-escaped as visible \uXXXX:
        // zero-width space, RTL override, Unicode tag char, variation selectors 16 and 17
        $this->assertSame('"a\u200bb"',       SmartString::new("a\u{200B}b")->jsonEncode());
        $this->assertSame('"a\u202eb"',       SmartString::new("a\u{202E}b")->jsonEncode());
        $this->assertSame('"a\udb40\udc41b"', SmartString::new("a\u{E0041}b")->jsonEncode());
        $this->assertSame('"a\ufe0fb"',       SmartString::new("a\u{FE0F}b")->jsonEncode());
        $this->assertSame('"a\udb40\udd00b"', SmartString::new("a\u{E0100}b")->jsonEncode());

        // escaping is lossless: decoding returns the original characters
        $original = "x\u{200B}\u{202E}\u{E0041}y";
        $this->assertSame($original, json_decode(SmartString::new($original)->jsonEncode()));

        // visible non-ASCII text stays raw
        $this->assertSame('"café 日"', SmartString::new("café 日")->jsonEncode());
    }

    public function testJsonEncodePreservesScalarTypes(): void
    {
        $this->assertSame('42', SmartString::new(42)->jsonEncode());
        $this->assertSame('3.14', SmartString::new(3.14)->jsonEncode());
        $this->assertSame('true', SmartString::new(true)->jsonEncode());
        $this->assertSame('false', SmartString::new(false)->jsonEncode());
        $this->assertSame('null', SmartString::new(null)->jsonEncode());
    }

    //endregion
    //region jsonSerialize() / json_encode()

    public function testJsonSerializeSubstitutesMalformedUtf8(): void
    {
        // corrupt byte becomes U+FFFD instead of json_encode() returning false (default flags escape non-ASCII)
        $this->assertSame('"a\ufffd(b"',          json_encode(SmartString::new("a\xC3(b")));
        $this->assertSame('{"name":"a\ufffd(b"}', json_encode(['name' => SmartString::new("a\xC3(b")]));

        // valid strings pass through untouched
        $this->assertSame('"caf\u00e9"', json_encode(SmartString::new("café")));
        $this->assertSame('"café"',      json_encode(SmartString::new("café"), JSON_UNESCAPED_UNICODE));
    }

    #[DataProvider('jsonSerializeProvider')]
    public function testJsonSerializePreservesScalarTypes($input, string $expected): void
    {
        $this->assertSame($expected, json_encode(SmartString::new($input)));
    }

    public static function jsonSerializeProvider(): array
    {
        return [
            'string' => ['Hello World', '"Hello World"'],
            'int'    => [42, '42'],
            'float'  => [3.14, '3.14'],
            'true'   => [true, 'true'],
            'false'  => [false, 'false'],
            'null'   => [null, 'null'],
        ];
    }

    //endregion
}
