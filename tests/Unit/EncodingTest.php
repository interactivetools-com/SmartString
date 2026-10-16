<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Itools\SmartString\Tests\Support\Fixtures;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

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

    /**
     * When a fast-path check's preg_match() returns false (PCRE gave up), the value still gets
     * encoded. JIT off with pcre.backtrack_limit at 1 makes every check give up. Runs in its own
     * process because PHP keeps each compiled regex with the JIT setting it was compiled under.
     */
    public function testEncodesWhenPcreGivesUp(): void
    {
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $script   = <<<__PHP__
            require $autoload;
            \$out = [];
            foreach (['<b>x</b>', 'café <b>'] as \$input) {
                \$value = \Itools\SmartString\SmartString::new(\$input);
                \$out[] = [(string)\$value, \$value->htmlEncode(), \$value->appendHtml('<br>'), \$value->wrapHtml('<p>', '</p>')];
            }
            echo json_encode(['failed' => preg_match('/[<]/', '<') === false, 'out' => \$out]);
            __PHP__;

        [$stdout, $stderr, $exitCode] = $this->runCommand([PHP_BINARY, '-d', 'pcre.jit=0', '-d', 'pcre.backtrack_limit=1', '-r', $script]);
        $this->assertSame(0, $exitCode, $stderr);
        $result = json_decode($stdout, true);

        $this->assertTrue($result['failed'], 'preg_match() should return false in the child process');
        $this->assertSame([
            ['&lt;b&gt;x&lt;/b&gt;', '&lt;b&gt;x&lt;/b&gt;', '&lt;b&gt;x&lt;/b&gt;<br>', '<p>&lt;b&gt;x&lt;/b&gt;</p>'],
            ['café &lt;b&gt;', 'café &lt;b&gt;', 'café &lt;b&gt;<br>', '<p>café &lt;b&gt;</p>'],
        ], $result['out']);
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
                'expectedJson'     => '"Caf\u00e9 \u00f1 \u65e5\u672c\u8a9e"',
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

    public function testJsonEncodeInfAndNanEncodeAsNull(): void
    {
        // INF/NAN never reach the encoder: an overflowed result stores as null just
        // like a non-finite input does, so JSON output stays valid instead of
        // throwing "Inf and NaN cannot be JSON encoded"
        $this->assertSame('null', SmartString::new(-1.7976931348623157e+308)->subtract(1.7976931348623157e+308)->jsonEncode());
        $this->assertSame('null', json_encode(SmartString::new(NAN)));
    }

    /**
     * The jsonEncode hardening contract: malformed UTF-8 becomes � instead of
     * throwing, and every non-ASCII character comes out as \uXXXX, so the output
     * is plain ASCII and decodes back to the original.
     */
    public function testJsonEncodeHardening(): void
    {
        // malformed UTF-8 byte becomes U+FFFD instead of throwing
        $this->assertSame('"a\ufffd(b"', SmartString::new("a\xC3(b")->jsonEncode());

        // visible non-ASCII is escaped too: accented letters, CJK, and emoji as surrogate pairs
        $this->assertSame('"caf\u00e9 \u65e5"',         SmartString::new("café 日")->jsonEncode());
        $this->assertSame('"\ud83c\uddec\ud83c\udde7"', SmartString::new("\u{1F1EC}\u{1F1E7}")->jsonEncode());

        // invisible Unicode: zero-width space, RTL override, Unicode tag char, variation
        // selectors 16 and 17, then three that aren't format characters (a Mongolian
        // variation selector, the combining grapheme joiner, the Hangul filler)
        $this->assertSame('"a\u200bb"',       SmartString::new("a\u{200B}b")->jsonEncode());
        $this->assertSame('"a\u202eb"',       SmartString::new("a\u{202E}b")->jsonEncode());
        $this->assertSame('"a\udb40\udc41b"', SmartString::new("a\u{E0041}b")->jsonEncode());
        $this->assertSame('"a\ufe0fb"',       SmartString::new("a\u{FE0F}b")->jsonEncode());
        $this->assertSame('"a\udb40\udd00b"', SmartString::new("a\u{E0100}b")->jsonEncode());
        $this->assertSame('"a\u180bb"',       SmartString::new("a\u{180B}b")->jsonEncode());
        $this->assertSame('"a\u034fb"',       SmartString::new("a\u{034F}b")->jsonEncode());
        $this->assertSame('"a\u3164b"',       SmartString::new("a\u{3164}b")->jsonEncode());

        // U+2028/U+2029 line separators: invisible, legal in JSON, and syntax errors in
        // pre-ES2019 JS string literals
        $this->assertSame('"a\u2028b\u2029c"', SmartString::new("a\u{2028}b\u{2029}c")->jsonEncode());

        // the output is plain ASCII, and decoding returns the original characters
        $original = "x\u{200B}\u{202E}\u{E0041}\u{180B}\u{3164} café 日\u{1F1EC}\u{1F1E7}y";
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', SmartString::new($original)->jsonEncode());
        $this->assertSame($original, json_decode(SmartString::new($original)->jsonEncode()));
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
