<?php

/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

namespace Tests\Methods;

use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;

class EncodingTest extends TestCase
{
    // region Encoding tests

    /**
     * @dataProvider encodingMethodsProvider
     */
    public function testEncodingMethods($input, $expectedHtml, $expectedUrl, $expectedJson, $expectedNoEncode): void
    {
        $smartString = new SmartString($input);

        $this->assertSame($expectedHtml, $smartString->htmlEncode(), "htmlEncode() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedUrl, $smartString->urlEncode(), "urlEncode() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedJson, $smartString->jsonEncode(), "jsonEncode() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedNoEncode, $smartString->noEncode(), "noEncode() method failed for input: ".var_export($input, true));
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function encodingMethodsProvider(): array
    {
        return [
            'plaintext with br'    => [
                'input'            => 'One\nTwo<br>Three<br>\nFour<br>\n',
                'expectedHtml'     => 'One\nTwo<br>Three<br>\nFour<br>\n',
                'expectedUrl'      => 'One%5CnTwo%3Cbr%3EThree%3Cbr%3E%5CnFour%3Cbr%3E%5Cn',
                'expectedJson'     => '"One\\\\nTwo\u003Cbr\u003EThree\u003Cbr\u003E\\\\nFour\u003Cbr\u003E\\\\n"',
                'expectedNoEncode' => 'One\nTwo<br>Three<br>\nFour<br>\n',
            ],
            'already encoded'    => [
                'input'            => '&lt;hello&gt;&lt;br&gt;world!<br>\n',
                'expectedHtml'     => '&amp;lt;hello&amp;gt;&amp;lt;br&amp;gt;world!<br>\n',
                'expectedUrl'      => '%26lt%3Bhello%26gt%3B%26lt%3Bbr%26gt%3Bworld%21%3Cbr%3E%5Cn',
                'expectedJson'     => '"\u0026lt;hello\u0026gt;\u0026lt;br\u0026gt;world!\u003Cbr\u003E\\\\n"',
                'expectedNoEncode' => '&lt;hello&gt;&lt;br&gt;world!<br>\n',
            ],
            'HTML special chars'       => [
                'input'            => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
                'expectedHtml'     => 'O&apos;Reilly &amp; Sons &lt;Web &amp;nbsp; Shop/&gt;',
                'expectedUrl'      => 'O%27Reilly+%26+Sons+%3CWeb+%26nbsp%3B+Shop%2F%3E',
                'expectedJson'     => '"O\u0027Reilly \u0026 Sons \u003CWeb \u0026nbsp; Shop/\u003E"',
                'expectedNoEncode' => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
            ],
            'JavaScript special chars' => [
                'input'            => "Line1\nLine2\rLine3\tTab",
                'expectedHtml'     => "Line1\nLine2\rLine3\tTab",
                'expectedUrl'      => 'Line1%0ALine2%0DLine3%09Tab',
                'expectedJson'     => '"Line1\nLine2\rLine3\tTab"',
                'expectedNoEncode' => "Line1\nLine2\rLine3\tTab",
            ],
            'URL special chars'        => [
                'input'            => 'https://example.com/path?param1=value1&param2=value2',
                'expectedHtml'     => 'https://example.com/path?param1=value1&amp;param2=value2',
                'expectedUrl'      => 'https%3A%2F%2Fexample.com%2Fpath%3Fparam1%3Dvalue1%26param2%3Dvalue2',
                'expectedJson'     => '"https://example.com/path?param1=value1\u0026param2=value2"',
                'expectedNoEncode' => 'https://example.com/path?param1=value1&param2=value2',
            ],
            'Mixed special chars'      => [
                'input'            => '<script>alert("XSS & Injection!");</script>',
                'expectedHtml'     => '&lt;script&gt;alert(&quot;XSS &amp; Injection!&quot;);&lt;/script&gt;',
                'expectedUrl'      => '%3Cscript%3Ealert%28%22XSS+%26+Injection%21%22%29%3B%3C%2Fscript%3E',
                'expectedJson'     => '"\u003Cscript\u003Ealert(\u0022XSS \u0026 Injection!\u0022);\u003C/script\u003E"',
                'expectedNoEncode' => '<script>alert("XSS & Injection!");</script>',
            ],
            'Unicode characters'       => [
                'input'            => 'Café ñ 日本語',
                'expectedHtml'     => 'Café ñ 日本語',
                'expectedUrl'      => 'Caf%C3%A9+%C3%B1+%E6%97%A5%E6%9C%AC%E8%AA%9E',
                'expectedJson'     => '"Café ñ 日本語"',
                'expectedNoEncode' => 'Café ñ 日本語',
            ],
            'Empty string'             => [
                'input'            => '',
                'expectedHtml'     => '',
                'expectedUrl'      => '',
                'expectedJson'     => '""',
                'expectedNoEncode' => '',
            ],
            'Null input'               => [
                'input'            => null,
                'expectedHtml'     => '',
                'expectedUrl'      => '',
                'expectedJson'     => 'null',
                'expectedNoEncode' => null,
            ],
        ];
    }

    // endregion
}
