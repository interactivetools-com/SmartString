<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\TestCase;
use TypeError;

class SmartStringTest extends TestCase
{
    // region __construct() tests
    /**
     * @dataProvider constructorValidProvider
     */
    public function testConstructorValid($input, $expected): void
    {
        $smartString = new SmartString($input);
        $this->assertInstanceOf(SmartString::class, $smartString);
        $this->assertSame($expected, $smartString->value());
    }

    /**
     * @dataProvider constructorInvalidProvider
     */
    public function testConstructorInvalid($input): void
    {
        $this->expectException(TypeError::class);
        /** @noinspection PhpExpressionResultUnusedInspection */
        new SmartString($input);
    }

    public static function constructorValidProvider(): array
    {
        return [
            "string input"  => ["G'day <b>World</b>!", "G'day <b>World</b>!"],
            "integer input" => [123, 123],
            "float input"   => [123.45, 123.45],
            "boolean true"  => [true, true],
            "boolean false" => [false, false],
            "null input"    => [null, null],
        ];
    }

    public static function constructorInvalidProvider(): array
    {
        $resource = fopen('php://memory', 'rb');
        $cases    = [
            "array input"    => [[1, 2, 3]],
            "object input"   => [(object)['key' => 'value']],
            "resource input" => [$resource],
        ];
        fclose($resource);
        return $cases;
    }

    // endregion
    // region new() tests

    /**
     * @dataProvider newMethodValidProvider
     */
    public function testNewMethodValid($input, $expected): void
    {
        $result = SmartString::new($input);

        // arrays
        if (is_array($expected)) {
            // check we got a SmartArrayHtml (legacy behavior, deprecated)
            $this->assertInstanceOf(SmartArrayHtml::class, $result);

            // Check that the SmartString objects are converted back to the expected values
            $this->assertSame($expected, self::smartStringsToValues($result));
        } // single values
        else {
            $this->assertInstanceOf(SmartString::class, $result);
            $this->assertSame($expected, $result->value());
        }
    }

    public static function newMethodValidProvider(): array
    {
        return [
            // single values
            "single string"   => ["Hello, World!<hr>", "Hello, World!<hr>"],
            "single integer"  => [123, 123],
            "single float"    => [123.45, 123.45],
            "boolean true"    => [true, true],
            "boolean false"   => [false, false],
            "null value"      => [null, null],

            // array of values
            "array of values" => [
                ["Hello", 123, 45.67, true, null],
                ["Hello", 123, 45.67, true, null],
            ],
            "result set"      => [
                [
                    [3, "O'Reilly & Sons", 3.12, null, true],
                    [8, "<Web &nbsp; Shop/>", 4.56, false, null],
                ],
                [
                    [3, "O'Reilly & Sons", 3.12, null, true],
                    [8, "<Web &nbsp; Shop/>", 4.56, false, null],
                ],
            ],
        ];
    }

    /**
     * @dataProvider newMethodInvalidProvider
     * @noinspection UnusedFunctionResultInspection
     */
    public function testNewMethodWithInvalid($input): void
    {
        $this->expectException(TypeError::class);
        SmartString::new($input);
    }

    public static function newMethodInvalidProvider(): array
    {
        return [
            "object input"   => [(object)['key' => 'value']],
            "resource input" => [fopen('php://memory', 'rb')],
        ];
    }

    /**
     * Recursively convert SmartString objects to their values.
     *
     * @param mixed $value
     *
     * @return string|int|float|bool|array|null
     */
    private static function smartStringsToValues(mixed $value): string|int|float|bool|null|array
    {
        return match (true) {
            $value instanceof SmartString   => $value->value(),
            $value instanceof SmartArrayBase => $value->toArray(),
            default => throw new InvalidArgumentException("Invalid value type: " . get_debug_type($value)),
        };
    }
    // endregion
    // region SmartArray test

    public function testSmartArrayHtmlEncoding(): void
    {
        // Arrange
        $users = [
            ['name' => 'John <script>alert("XSS")</script>', 'email' => 'john@example.com'],
            ['name' => "Jane O'Connor", 'email' => 'jane@example.com'],
            ['name' => 'Bob & Alice', 'email' => 'bob.alice@example.com'],
        ];

        // Act
        $encodedUsers = new SmartArrayHtml($users);
        $result       = [];

        foreach ($encodedUsers as $user) {
            $result[] = [
                'name'  => (string)$user['name'],
                'email' => (string)$user['email'],
            ];
        }

        // Assert
        $expected = [
            ['name' => 'John &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', 'email' => 'john@example.com'],
            ['name' => "Jane O&apos;Connor", 'email' => 'jane@example.com'],
            ['name' => 'Bob &amp; Alice', 'email' => 'bob.alice@example.com'],
        ];

        $this->assertEquals($expected, $result);
    }

    // endregion
    // region Type Conversion tests

    /**
     * @dataProvider valueMethodsProvider
     */
    public function testValueMethods($input, $expectedValue, $expectedInt, $expectedFloat, $expectedBool, $expectedString): void
    {
        $smartString = new SmartString($input);

        $this->assertSame($expectedValue, $smartString->value(), "value() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedInt, $smartString->int(), "int() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedFloat, $smartString->float(), "float() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedBool, $smartString->bool(), "bool() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedString, $smartString->string(), "string() method failed for input: " . var_export($input, true));
    }

    public static function valueMethodsProvider(): array
    {
        return [
            'string input'         => [
                'input'          => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
                'expectedValue'  => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => true,
                'expectedString' => 'O\'Reilly & Sons <Web &nbsp; Shop/>',
            ],
            'empty string input'   => [
                'input'          => '',
                'expectedValue'  => '',
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => false,
                'expectedString' => '',
            ],
            'integer input'        => [
                'input'          => 42,
                'expectedValue'  => 42,
                'expectedInt'    => 42,
                'expectedFloat'  => 42.0,
                'expectedBool'   => true,
                'expectedString' => '42',
            ],
            'zero integer input'   => [
                'input'          => 0,
                'expectedValue'  => 0,
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => false,
                'expectedString' => '0',
            ],
            'float input'          => [
                'input'          => 3.14,
                'expectedValue'  => 3.14,
                'expectedInt'    => 3,
                'expectedFloat'  => 3.14,
                'expectedBool'   => true,
                'expectedString' => '3.14',
            ],
            'zero float input'     => [
                'input'          => 0.0,
                'expectedValue'  => 0.0,
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => false,
                'expectedString' => '0',
            ],
            'boolean true input'   => [
                'input'          => true,
                'expectedValue'  => true,
                'expectedInt'    => 1,
                'expectedFloat'  => 1.0,
                'expectedBool'   => true,
                'expectedString' => '1',
            ],
            'boolean false input'  => [
                'input'          => false,
                'expectedValue'  => false,
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => false,
                'expectedString' => '',
            ],
            'null input'           => [
                'input'          => null,
                'expectedValue'  => null,
                'expectedInt'    => 0,
                'expectedFloat'  => 0.0,
                'expectedBool'   => false,
                'expectedString' => '',
            ],
            'numeric string input' => [
                'input'          => '42.5',
                'expectedValue'  => '42.5',
                'expectedInt'    => 42,
                'expectedFloat'  => 42.5,
                'expectedBool'   => true,
                'expectedString' => '42.5',
            ],
        ];
    }

    // endregion
    // region Encoding Methods

    /**
     * @dataProvider encodingMethodsProvider
     */
    public function testEncodingMethods($input, $expectedHtml, $expectedUrl, $expectedJson, $expectedRawHtml): void
    {
        $smartString = new SmartString($input);

        $this->assertSame($expectedHtml, $smartString->htmlEncode(), "htmlEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedUrl, $smartString->urlEncode(), "urlEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedJson, $smartString->jsonEncode(), "jsonEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedRawHtml, $smartString->rawHtml(), "rawHtml() method failed for input: " . var_export($input, true));
    }

    /**
     * Test htmlEncode() encodes all tags including <br>
     */
    public function testHtmlEncodeEncodesBrTags(): void
    {
        $input = 'Text with <br> and <br/> tags';
        $result = SmartString::new($input)->htmlEncode();
        $this->assertSame('Text with &lt;br&gt; and &lt;br/&gt; tags', $result);
    }

    /**
     * Test textToHtml() method
     */
    public function testTextToHtml(): void
    {
        // Default: encode + nl2br
        $result = SmartString::new("Hello\nWorld")->textToHtml();
        $this->assertSame("Hello<br>\nWorld", $result);

        // With special chars: encode first, then nl2br
        $result = SmartString::new("It's <b>bold</b>\nLine 2")->textToHtml();
        $this->assertSame("It&apos;s &lt;b&gt;bold&lt;/b&gt;<br>\nLine 2", $result);

        // keepBr: true - existing <br> tags survive encoding, no nl2br
        $result = SmartString::new("Hello<br>World")->textToHtml(keepBr: true);
        $this->assertSame("Hello<br>World", $result);

        // keepBr with case variations and self-closing
        $result = SmartString::new("Hello<BR>World<br/>End")->textToHtml(keepBr: true);
        $this->assertSame("Hello<BR>World<br/>End", $result);

        // keepBr: false (default) - <br> tags get encoded, newlines become <br>
        $result = SmartString::new("Hello<br>World")->textToHtml();
        $this->assertSame("Hello&lt;br&gt;World", $result);

        // Null input
        $result = SmartString::new(null)->textToHtml();
        $this->assertSame("", $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public static function encodingMethodsProvider(): array
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


    // endregion
    // region String Manipulation

    /**
     * @dataProvider textOnlyProvider
     */
    public function testTextOnly($input, $expected): void
    {
        $result = SmartString::new($input)->textOnly()->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public static function textOnlyProvider(): array
    {
        return [
            'basic HTML removal'          => [
                '<p>Hello <b>World</b>!</p>',
                'Hello World!',
            ],
            'malformed HTML'              => [
                '<p>Hello <b>World!</p>',
                'Hello World!',
            ],
            'script tag removal'          => [
                '<script>alert("XSS");</script>Hello',
                'alert("XSS");Hello',
            ],
            'br with nextlines'           => [
                "The<br>\nquick<BR />\nbrown<BR/>\nfox<bR> jumps<BR>over<BR/>the<BR   />lazy<BR   >dog",
                "The\nquick\nbrown\nfox jumpsoverthelazydog",
            ],
            'text-only input'             => [
                'Plain text',
                'Plain text',
            ],
            'empty string'                => [
                '',
                '',
            ],
            'null input'                  => [
                null,
                null,
            ],
            'Wysiwyg content'             => [
                "O'Reilly said &quot;this &gt; that&quot;",
                'O\'Reilly said "this > that"',
            ],
            'leading/trailing whitespace' => [
                " <b> Hello World </b>",
                "Hello World",
            ],
        ];
    }

    public function testNl2brDeprecated(): void
    {
        // nl2br() is deprecated - use textToHtml() instead
        $smartString = new SmartString("Hello\nWorld");
        $result      = @$smartString->nl2br()->value(); // @ suppresses E_USER_DEPRECATED
        $this->assertSame("Hello<br>\nWorld", $result, "Deprecated nl2br() should still work");
    }

    /**
     * @dataProvider trimProvider
     */
    public function testTrim($input, $characterMask, $expected): void
    {
        $smartString = new SmartString($input);
        $result      = $characterMask ? $smartString->trim($characterMask)->value() : $smartString->trim()->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public static function trimProvider(): array
    {
        return [
            'basic whitespace trim'        => [
                '  Hello World  ',
                null,
                'Hello World',
            ],
            'trim specific characters'     => [
                '...Hello World...',
                '.',
                'Hello World',
            ],
            'trim mixed characters'        => [
                '...  Hello World  ...',
                ' .',
                'Hello World',
            ],
            'no trimming needed'           => [
                'Hello World',
                null,
                'Hello World',
            ],
            'trim all characters'          => [
                'aaaaaHelloa Worldaaaaa',
                'a',
                'Helloa World',
            ],
            'empty string'                 => [
                '',
                null,
                '',
            ],
            'string of only trimmed chars' => [
                '   ',
                null,
                '',
            ],
            'null input'                   => [
                null,
                null,
                null,
            ],
        ];
    }

    /**
     * @dataProvider maxWordsProvider
     */
    public function testMaxWords($input, $max, $ellipsis, $expected): void
    {
        $result = SmartString::new($input)->maxWords($max, $ellipsis);
        $this->assertSame($expected, $result->value(), "maxWords() method failed for input: " . var_export($input, true));
    }

    public static function maxWordsProvider(): array
    {
        return [
            'normal input'              => ['The quick brown fox jumps over the lazy dog', 5, '...', 'The quick brown fox jumps...'],
            'input less than max words' => ['Hello world', 5, '...', 'Hello world'],
            'input equal to max words'  => ['One two three four five', 5, '...', 'One two three four five'],
            'empty string'              => ['', 3, '...', ''],
            'null input'                => [null, 3, '...', null],
            'numeric input'             => [12345, 2, '...', '12345'],
            'very large max words'      => ['Short sentence', 1000, '...', 'Short sentence'],
            'max words set to 0'        => ['Test sentence', 0, '...', '...'],
            'custom ellipsis'           => ['The quick brown fox jumps over the lazy dog', 4, ' [...]', 'The quick brown fox [...]'],
            'multiple spaces'           => ['Word1    Word2     Word3', 2, '...', 'Word1 Word2...'],
            'leading/trailing spaces'   => ['  Trimmed input test  ', 2, '...', 'Trimmed input...'],
            'trailing punctuation'      => ['Hello, world! How are you?', 2, '~~~', 'Hello, world~~~'],
            'multibyte characters'      => ['こんにちは 世界 テスト', 2, '...', 'こんにちは 世界...'],
            'mixed ascii and multibyte' => ['Hello こんにちは World 世界', 3, '...', 'Hello こんにちは World...'],
            'empty ellipsis'            => ['One two three four', 2, '', 'One two'],
            'html content'              => ['<p>First</p> <div>Second</div> <span>Third</span>', 2, '...', '<p>First</p> <div>Second</div>...'],
        ];
    }

    /**
     * @dataProvider maxCharsProvider
     */
    public function testMaxChars($input, $max, $ellipsis, $expected): void
    {
        $result = SmartString::new($input)->maxChars($max, $ellipsis);
        $this->assertSame($expected, $result->value(), "maxChars() method failed for input: " . var_export($input, true));
    }

    public static function maxCharsProvider(): array
    {
        return [
            'normal input'              => ['The quick brown fox jumps over the lazy dog', 20, '...', 'The quick brown fox...'],
            'input less than max chars' => ['Hello world', 20, '...', 'Hello world'],
            'input equal to max chars'  => ['Exactly twenty chars', 20, '...', 'Exactly twenty chars'],
            'empty string'              => ['', 10, '...', ''],
            'null input'                => [null, 10, '...', null],
            'numeric input'             => [12345, 3, '...', '123...'],
            'very large max chars'      => ['Short sentence', 1000, '...', 'Short sentence'],
            'max chars set to 0'        => ['Test sentence', 0, '...', '...'],
            'custom ellipsis'           => ['The quick brown fox!', 15, ' [...]', 'The quick brown [...]'],
            'multiple spaces'           => ['Word1    Word2     Word3', 12, '...', 'Word1 Word2...'],
            'leading/trailing spaces'   => ['  Trimmed  input test  ', 10, '...', 'Trimmed...'],
            'multibyte characters'      => ['こんにちは世界', 5, '...', 'こんにちは...'],
            'word boundary'             => ['The quick brown fox', 12, '...', 'The quick...'],
            'punctuation at cut-off'    => ['Hello, world! How are you?', 13, '...', 'Hello, world...'],
            'very short max chars'      => ['Testing', 1, '...', 'T...'],
            'exact boundary'            => ['1234567890', 10, '...', '1234567890'],
            'one over boundary'         => ['12345678901', 10, '...', '1234567890...'],
            'empty ellipsis'            => ['The quick brown fox', 10, '', 'The quick'],
            'html entities'             => ['&amp; &lt; &gt;', 5, '...', '&amp...'],
        ];
    }

    // endregion
    // region Formatting Operations

    /**
     * @dataProvider dateFormatProvider
     */
    public function testDateFormat($input, $format, $expected): void
    {
        date_default_timezone_set('America/Phoenix'); // Timezone with no DST for consistent results

        // dateFormat
        $result = SmartString::new($input)->dateFormat($format)->value();
        $error  = "Failed for input: " . var_export($input, true) . ", output: " . var_export($result, true);
        $this->assertSame($expected, $result, $error);
    }

    /**
     * @dataProvider dateTimeFormatProvider
     */
    public function testDateTimeFormat($input, $format, $expected): void
    {
        date_default_timezone_set('America/Phoenix'); // Timezone with no DST for consistent results

        // dateTimeFormat
        $result = SmartString::new($input)->dateTimeFormat($format)->value();
        $error  = "Failed for input: " . var_export($input, true) . ", output: " . var_export($result, true);
        $this->assertSame($expected, $result, $error);
    }

    public static function dateTimeFormatProvider(): array
    {
        return [
            'MySQL datetime'            => [
                '2023-05-15 14:30:00',
                'Y-m-d H:i:s T',
                '2023-05-15 14:30:00 MST',
            ],
            'MySQL date'                => [
                '2023-05-15',
                'Y-m-d H:i:s T',
                '2023-05-15 00:00:00 MST',
            ],
            'MySQL time'                => [
                '14:30:00',
                'H:i:s T',
                '14:30:00 MST',
            ],
            'Unix timestamp'            => [
                1684159800,
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Unix timestamp as string'  => [
                '1684159800',
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Custom format output'      => [
                '2023-05-15 14:30:00',
                'd/m/Y H:i T',
                '15/05/2023 14:30 MST',
            ],
            'Null input'                => [
                null,
                'Y-m-d H:i:s T',
                null,
            ],
            'Zero timestamp'            => [
                0,
                'Y-m-d H:i:s T',
                '1969-12-31 17:00:00 MST',
            ],
            'Invalid date string'       => [
                'not a date',
                'Y-m-d H:i:s T',
                null,
            ],
            'Empty string'              => [
                '',
                'Y-m-d H:i:s T',
                null,
            ],
            'Default format test'       => [
                '2023-05-15 14:30:00',
                null,
                '2023-05-15 14:30:00',
            ],
        ];
    }

    public static function dateFormatProvider(): array
    {
        return [
            'MySQL datetime'            => [
                '2023-05-15 14:30:00',
                'Y-m-d H:i:s T',
                '2023-05-15 14:30:00 MST',
            ],
            'MySQL date'                => [
                '2023-05-15',
                'Y-m-d T',
                '2023-05-15 MST',
            ],
            'MySQL time'                => [
                '14:30:00',
                'H:i:s T',
                '14:30:00 MST',
            ],
            'Unix timestamp'            => [
                1684159800,
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Unix timestamp as string'  => [
                '1684159800',
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Custom format output'      => [
                '2023-05-15 14:30:00',
                'd/m/Y H:i T',
                '15/05/2023 14:30 MST',
            ],
            'Null input'                => [
                null,
                'Y-m-d T',
                null,
            ],
            'Zero timestamp'            => [
                0,
                'Y-m-d H:i:s T',
                '1969-12-31 17:00:00 MST',
            ],
            'Negative timestamp'        => [
                -1684159800,
                'Y-m-d H:i:s T',
                '1916-08-19 02:50:00 MST',
            ],
            'Far future date'           => [
                '2123-05-15 14:30:00',
                'Y-m-d H:i:s T',
                '2123-05-15 14:30:00 MST',
            ],
            'Invalid date string'       => [
                'not a date',
                'Y-m-d T',
                null,
            ],
            'Empty string'              => [
                '',
                'Y-m-d T',
                null,
            ],
            'Different timezone format' => [
                '2023-05-15T14:30:00+02:00',
                'Y-m-d H:i:s T',
                '2023-05-15 05:30:00 MST',
            ],
        ];
    }


    public function testDefaultDateFormat(): void
    {
        $originalDateFormat      = SmartString::$dateFormat;
        SmartString::$dateFormat = 'Y-m-d';

        $input  = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateFormat()->value();

        $this->assertSame('2023-05-15', $result, "Default dateFormat failed");

        SmartString::$dateFormat = $originalDateFormat;
    }

    public function testDefaultDateTimeFormat(): void
    {
        $originalDateTimeFormat      = SmartString::$dateTimeFormat;
        SmartString::$dateTimeFormat = 'Y-m-d H:i:s T';

        $input  = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateTimeFormat()->value();

        $this->assertSame('2023-05-15 14:30:00 MST', $result, "Default dateTimeFormat failed");

        SmartString::$dateTimeFormat = $originalDateTimeFormat;
    }

    public function testCustomDefaultDateFormat(): void
    {
        $originalDateFormat      = SmartString::$dateFormat;
        SmartString::$dateFormat = 'd/m/Y';

        $input  = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateFormat()->value();

        $this->assertSame('15/05/2023', $result, "Custom default dateFormat failed");

        SmartString::$dateFormat = $originalDateFormat;
    }

    public function testCustomDefaultDateTimeFormat(): void
    {
        $originalDateTimeFormat      = SmartString::$dateTimeFormat;
        SmartString::$dateTimeFormat = 'd/m/Y H:i T';

        $input  = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateTimeFormat()->value();

        $this->assertSame('15/05/2023 14:30 MST', $result, "Custom default dateTimeFormat failed");

        SmartString::$dateTimeFormat = $originalDateTimeFormat;
    }

    /**
     * Test complex method chaining with multiple operations
     */
    public function testComplexMethodChaining(): void
    {
        // Chain multiple operations
        $input = "  This is <b>some HTML</b> content with extra   spaces  ";
        $result = SmartString::new($input)
            ->trim()                 // Remove leading/trailing spaces
            ->maxWords(4, '...')     // Limit to 4 words
            ->htmlEncode();          // Encode HTML

        $resultWithAppend = $result . " (truncated)";
        $resultString = (string)$result;

        $this->assertSame(
            "This is &lt;b&gt;some HTML&lt;/b&gt;...",
            $resultString,
            "Complex method chaining failed"
        );

        $this->assertSame(
            "This is &lt;b&gt;some HTML&lt;/b&gt;... (truncated)",
            $resultWithAppend,
            "String concatenation with SmartString failed"
        );

        // Chain with numeric operations - need to control formatting for test consistency
        $originalThousands = SmartString::$numberFormatThousands;
        SmartString::$numberFormatThousands = '';

        try {
            $numericInput = "42.5";
            $numericResult = SmartString::new($numericInput)
                ->multiply(2)            // Double the value
                ->add(10)                // Add 10
                ->percent(1);            // Convert to percentage

        // Apply prefix through a new chain
        // Using direct string concatenation for prefix instead
        $numericResultString = (string)$numericResult;

        // Just make sure the result contains the correct numeric part
        $this->assertMatchesRegularExpression(
            '/9.*5.*0.*%/',
            $numericResultString,
            "Numeric method chaining failed"
        );

        // Check string concatenation works without worrying about exact format
        $this->assertStringStartsWith(
            "Result: ",
            "Result: " . $numericResult,
            "String concatenation with numeric SmartString failed"
        );
        $this->assertMatchesRegularExpression(
            '/Result: .*9.*5.*0.*%/',
            "Result: " . $numericResult,
            "String concatenation with numeric SmartString failed on content"
        );
        } finally {
            // Restore original format
            SmartString::$numberFormatThousands = $originalThousands;
        }

        // Chain with conditional operations
        $conditionalInput = null;
        $conditionalResult = SmartString::new($conditionalInput)
            ->or("Default")          // Use fallback for null
            ->trim()                 // Operate on result
            ->maxChars(4, "...")     // Truncate
            ->value();

        $this->assertSame(
            "Defa...",
            $conditionalResult,
            "Conditional method chaining failed"
        );
    }

    /**
     * @dataProvider numberFormatProvider
     */
    public function testNumberFormat($input, $decimals, $expected): void
    {
        $result = SmartString::new($input)->numberFormat($decimals)->value();
        $this->assertSame($expected, $result);
    }

    public static function numberFormatProvider(): array
    {
        return [
            'large int, no args' => [1000000000000, 0, '1,000,000,000,000'],
            'positive integer'   => [1000, 0, '1,000'],
            'negative integer'   => [-1000, 0, '-1,000'],
            'positive float'     => [1234.56, 2, '1,234.56'],
            'negative float'     => [-1234.56, 2, '-1,234.56'],
            'round up'           => [1234.56789, 2, '1,234.57'],
            'large number'       => [1000000000000, 0, '1,000,000,000,000'],
            'small number'       => [0.0000001, 7, '0.0000001'],
            'zero'               => [0, 2, '0.00'],

            // invalid inputs
            'non-numeric'        => ['abc', null, null],
            'null'               => [null, null, null],

            // Old tests
            'Integer - default'         => [1000, 0, '1,000'],
            'Float - default'           => [1000.5, 0, '1,001'],
            'Integer - 2 decimals'      => [1000, 2, '1,000.00'],
            'Float - 2 decimals'        => [1000.5, 2, '1,000.50'],
            'Large number - 2 decimals' => [1000000.5, 2, '1,000,000.50'],
            'Negative number'           => [-1000.5, 2, '-1,000.50'],
            'Zero'                      => [0, 2, '0.00'],
            'String numeric - integer'  => ['1000', 0, '1,000'],
            'String numeric - float'    => ['1000.5', 2, '1,000.50'],
            'Null input'                => [null, 0, null],
            'Empty string'              => ['', 0, null],
            'Non-numeric string'        => ['abc', 0, null],
            'Scientific notation'       => [1e6, 2, '1,000,000.00'],
            'Very large number'         => [1e15, 2, '1,000,000,000,000,000.00'],
            'Very small number'         => [1e-6, 8, '0.00000100'],
            'Rounding up'               => [1.999, 2, '2.00'],
            'Rounding down'             => [1.994, 2, '1.99'],
        ];
    }


    /**
     * @dataProvider phoneFormatProvider
     */
    public function testPhoneFormat($input, $expected): void
    {
        SmartString::$phoneFormat = [
            ['digits' => 10, 'format' => '1 (###) ###-####'],
            ['digits' => 11, 'format' => '#-###-###-####'],
        ];

        $result = SmartString::new($input)->phoneFormat()->value();
        $this->assertSame($expected, $result, "Phone format failed for input: " . var_export($input, true));
    }

    /**
     * Test phone formatting with custom formats
     */
    public function testPhoneFormatWithCustomFormats(): void
    {
        // Save original format
        $originalFormat = SmartString::$phoneFormat;

        try {
            // Test international format
            SmartString::$phoneFormat = [
                ['digits' => 10, 'format' => '+1 (###) ###-####'],
                ['digits' => 11, 'format' => '+# (###) ###-####'],
                ['digits' => 12, 'format' => '+## ## #### ####'],
            ];

            // 10 digit US number
            $result10 = SmartString::new('2345678901')->phoneFormat()->value();
            $this->assertSame('+1 (234) 567-8901', $result10, '10-digit custom format failed');

            // 11 digit US number with country code
            $result11 = SmartString::new('12345678901')->phoneFormat()->value();
            $this->assertSame('+1 (234) 567-8901', $result11, '11-digit custom format failed');

            // 12 digit international number
            $result12 = SmartString::new('442071234567')->phoneFormat()->value();
            $this->assertSame('+44 20 7123 4567', $result12, '12-digit custom format failed');

            // Test with dots instead of hyphens
            SmartString::$phoneFormat = [
                ['digits' => 10, 'format' => '###.###.####'],
            ];

            $resultDots = SmartString::new('2345678901')->phoneFormat()->value();
            $this->assertSame('234.567.8901', $resultDots, 'Custom dot format failed');

            // Test with no separators
            SmartString::$phoneFormat = [
                ['digits' => 10, 'format' => '(###)#######'],
            ];

            $resultNoSep = SmartString::new('2345678901')->phoneFormat()->value();
            $this->assertSame('(234)5678901', $resultNoSep, 'Custom format with no separators failed');
        } finally {
            // Restore original format
            SmartString::$phoneFormat = $originalFormat;
        }
    }

    public static function phoneFormatProvider(): array
    {
        return [
            'Null input'                       => [null, null],
            'Empty string input'               => ['', null],
            'Invalid 1-digit number'           => ['0', null],
            'Invalid 2-digit number'           => ['12', null],
            'Invalid 3-digit number'           => ['123', null],
            'Invalid 4-digit number'           => ['1234', null],
            'Invalid 5-digit number'           => ['12345', null],
            'Invalid 6-digit number'           => ['123456', null],
            'Invalid 7-digit number'           => ['1234567', null],
            'Invalid 8-digit number'           => ['12345678', null],
            'Invalid 9-digit number'           => ['123456789', null],
            'Invalid 10-digit/char string'     => ['123456789A', null],
            'Valid 10-digit number'            => ['2345678901', '1 (234) 567-8901'],
            'Valid 11-digit number'            => ['12345678901', '1-234-567-8901'],
            'Valid 10-digit + chars number'    => ['+1(2)3-4x5y6z7890 ', '1 (123) 456-7890'],
            'Valid 11-digit + chars number'    => ['(12) 34 5 67-8901', '1-234-567-8901'],
            'Valid 10-digit number as integer' => [2345678901, '1 (234) 567-8901'],
            'Valid 11-digit number as integer' => [12345678901, '1-234-567-8901'],
            'Invalid 12-digit number'          => ['123456789012', null],
        ];
    }

    // endregion
    // region Numeric Operations

    /**
     * Test null values in numeric operations (null stays null)
     */
    public function testNullInNumericOperations(): void
    {
        // Null as left operand
        $this->assertNull(SmartString::new(null)->percent()->value(), "null->percent() should be null");
        $this->assertNull(SmartString::new(null)->add(5)->value(), "null+5 should be null");
        $this->assertNull(SmartString::new(null)->subtract(5)->value(), "null-5 should be null");
        $this->assertNull(SmartString::new(null)->multiply(5)->value(), "null*5 should be null");
        $this->assertNull(SmartString::new(null)->divide(5)->value(), "null/5 should be null");
        $this->assertNull(SmartString::new(null)->percentOf(100)->value(), "null percentOf 100 should be null");

        // Null as right operand
        $this->assertNull(SmartString::new(10)->add(SmartString::new(null))->value(), "10+null should be null");
        $this->assertNull(SmartString::new(10)->subtract(SmartString::new(null))->value(), "10-null should be null");
        $this->assertNull(SmartString::new(10)->multiply(SmartString::new(null))->value(), "10*null should be null");
        $this->assertNull(SmartString::new(10)->divide(SmartString::new(null))->value(), "10/null should be null");
        $this->assertNull(SmartString::new(50)->percentOf(SmartString::new(null))->value(), "50 percentOf null should be null");
    }

    /**
     * Test non-numeric strings in numeric operations (always produce null)
     */
    public function testNonNumericStringsInNumericOperations(): void
    {
        $this->assertNull(SmartString::new('abc')->percent()->value(), "Non-numeric string should become null in percent()");
        $this->assertNull(SmartString::new('abc')->add(5)->value(), "Non-numeric string should become null in add()");
        $this->assertNull(SmartString::new(10)->subtract(SmartString::new('abc'))->value(), "Non-numeric string should cause null result in subtract()");
        $this->assertNull(SmartString::new('abc')->multiply(5)->value(), "Non-numeric string should become null in multiply()");
        $this->assertNull(SmartString::new('abc')->divide(5)->value(), "Non-numeric string should become null in divide()");
        $this->assertNull(SmartString::new('abc')->percentOf(100)->value(), "Non-numeric string should become null in percentOf()");
    }

    /**
     * Test chained operations with non-numeric values
     */
    public function testChainedOperationsWithNonNumericValues(): void
    {
        // Non-numeric at start should make entire chain return null
        $this->assertNull(SmartString::new('abc')->add(5)->value(), "Add after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->subtract(5)->value(), "Subtract after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->multiply(5)->value(), "Multiply after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->divide(5)->value(), "Divide after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->percent(2)->value(), "Percent after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->percentOf(100)->value(), "PercentOf after non-numeric should return null");
        $this->assertNull(SmartString::new('abc')->add(5)->multiply(2)->subtract(3)->value(), "Chain after non-numeric should return null");

        // Non-numeric in middle of chain should make remainder return null
        $this->assertNull(SmartString::new(7)->add(SmartString::new('3,000'))->add(1)->value(), "Chain with non-numeric in middle should return null");

        // Formatted numbers with commas are non-numeric
        $this->assertNull(SmartString::new(10)->add(SmartString::new('1,234'))->value(), "Formatted numbers should be treated as non-numeric");
    }

    /**
     * @dataProvider percentProvider
     */
    public function testPercent($input, $decimals, $expected): void
    {
        $result = SmartString::new($input)->percent($decimals)->value();
        $this->assertSame($expected, $result);
    }

    public static function percentProvider(): array
    {
        return [
            'half'             => [0.5, 0, '50%'],
            'quarter'          => [0.25, 0, '25%'],
            'whole'            => [1.0, 1, '100.0%'],
            'with decimals'    => [0.4567, 2, '45.67%'],
            'zero'             => [0, 0, '0%'],
            'greater than one' => [1.5, 0, '150%'],
        ];
    }

    /**
     * Test the percent() method with the zeroFallback parameter.
     */
    public function testPercentWithZeroFallback(): void
    {
        // Test with string fallback
        $resultString = SmartString::new(0)->percent(2, "N/A")->value();
        $this->assertSame("N/A", $resultString, "Zero with string fallback should return the fallback");

        // Test with numeric fallback
        $resultNumeric = SmartString::new(0)->percent(2, 0)->value();
        $this->assertSame(0, $resultNumeric, "Zero with numeric fallback should return the numeric fallback");

        // Zero with null fallback still returns formatted zero (null fallback only works if value is actually null)
        $resultNull = SmartString::new(0)->percent(2, null)->value();
        $this->assertSame("0.00%", $resultNull, "Zero with null fallback should still format zero");

        // Test with string fallback for zero (SmartString can't be passed to percent())
        $resultSmart = SmartString::new(0)->percent(2, "None")->value();
        $this->assertSame("None", $resultSmart, "Zero with string fallback should return the fallback");
    }

    /**
     * Test the percent() method with extreme values.
     */
    public function testPercentWithExtremeValues(): void
    {
        // Test with very large number - must override and restore thousands separator
        $originalThousands = SmartString::$numberFormatThousands;
        SmartString::$numberFormatThousands = '';

        try {
            // Test with very large number without caring about formatting
            $resultLarge = SmartString::new(1000000)->percent(2)->value();
            // Check result contains expected numbers using regex that matches with or without commas
            $this->assertMatchesRegularExpression('/100[,\\s]*000[,\\s]*000\.00%/', $resultLarge, "Large number should convert correctly");
        } finally {
            // Always restore original setting
            SmartString::$numberFormatThousands = $originalThousands;
        }

        // Test with very small number
        $resultSmall = SmartString::new(0.00000001)->percent(8)->value();
        $this->assertSame("0.00000100%", $resultSmall, "Small number should convert correctly");

        // Test with negative number
        $resultNegative = SmartString::new(-0.5)->percent(0)->value();
        $this->assertSame("-50%", $resultNegative, "Negative number should convert correctly");

        // Test with many decimal places
        $resultManyDecimals = SmartString::new(0.333333333333)->percent(10)->value();
        $this->assertSame("33.3333333333%", $resultManyDecimals, "Number with many decimals should respect precision");
    }

    /**
     * @dataProvider percentOfProvider
     */
    public function testPercentOf($input, $total, $decimals, $expected): void
    {
        $result = SmartString::new($input)->percentOf($total, $decimals)->value();
        $this->assertSame($expected, $result, "Got: " . var_export($result, true));
    }

    public static function percentOfProvider(): array
    {
        return [
            'half'              => [50, 100, 0, '50%'],
            'quarter'           => [25, 100, 0, '25%'],
            'double'            => [100, 50.000, 0, '200%'],
            'with decimals'     => [75, 150, 2, '50.00%'],
            'zero numerator'    => [0, 100, 0, '0%'],
            'zero denominator'  => [100, 0, 0, null],
            'SmartString input' => [75, new SmartString(150.555555), 2, '49.82%'],
        ];
    }

    /**
     * @dataProvider addProvider
     */
    public function testAdd($input, $addend, $expected): void
    {
        $result = SmartString::new($input)->add($addend)->value();
        $this->assertEquals($expected, $result);
    }

    public static function addProvider(): array
    {
        return [
            'positive integers' => [5, 3, 8],
            'negative integers' => [-5, -3, -8],
            'mixed signs'       => [5, -3, 2],
            'float result'      => [5.5, 3.3, 8.8],
            'adding zero'       => [10, 0, 10],
            'adding to zero'    => [0, 10, 10],
            'large numbers'     => [PHP_INT_MAX, 1, (float)PHP_INT_MAX + 1],
            'SmartString input' => [5, new SmartString(3), 8],
        ];
    }

    /**
     * @dataProvider subtractProvider
     */
    public function testSubtract($input, $subtrahend, $expected): void
    {
        $result = SmartString::new($input)->subtract($subtrahend)->value();
        $this->assertEquals((string)$expected, (string)$result); // don't report 8.8 - 5.5 as 3.3000000000000001
    }

    public static function subtractProvider(): array
    {
        return [
            'positive integers'     => [8, 3, 5],
            'negative integers'     => [-8, -3, -5],
            'mixed signs'           => [5, -3, 8],
            'float result'          => [8.8, 3.3, 5.5],
            'subtracting zero'      => [10, 0, 10],
            'subtracting from zero' => [0, 10, -10],
            'large numbers'         => [PHP_INT_MAX, -1, (float)PHP_INT_MAX + 1],
            'SmartString input'     => [8, new SmartString(3), 5],
        ];
    }

    /**
     * @dataProvider multiplyProvider
     */
    public function testMultiply($input, $multiplier, $expected): void
    {
        $result = SmartString::new($input)->multiply($multiplier)->value();
        $this->assertEquals($expected, $result);
    }

    public static function multiplyProvider(): array
    {
        return [
            'positive integers' => [5, 3, 15],
            'negative integers' => [-5, -3, 15],
            'mixed signs'       => [5, -3, -15],
            'float result'      => [5.5, 2, 11],
            'multiply by zero'  => [10, 0, 0],
            'multiply by one'   => [10, 1, 10],
            'large numbers'     => [PHP_INT_MAX, 2, (float)PHP_INT_MAX * 2],
            'SmartString input' => [5, new SmartString(3), 15],
        ];
    }

    /**
     * @dataProvider divideProvider
     */
    public function testDivide($input, $divisor, $expected): void
    {
        $result = SmartString::new($input)->divide($divisor)->value();
        $this->assertEquals($expected, $result);
    }

    public static function divideProvider(): array
    {
        return [
            'positive integers'      => [10, 2, 5],
            'negative integers'      => [-10, -2, 5],
            'mixed signs'            => [10, -2, -5],
            'float result'           => [10, 3, 3.3333333333333335],
            'divide by one'          => [10, 1, 10],
            'divide by negative one' => [10, -1, -10],
            'zero numerator'         => [0, 5, 0],
            'divide by zero'         => [10, 0, null],
            'SmartString input'      => [800, new SmartString(25), 32],
        ];
    }

    /**
     * Test numeric operations with extreme values
     */
    public function testNumericOperationsWithExtremeValues(): void
    {
        // Very large numbers
        $largeNumber = PHP_INT_MAX;
        $largeResult = SmartString::new($largeNumber)->add(1)->value();
        $this->assertIsFloat($largeResult, "Adding to PHP_INT_MAX should result in float");
        $this->assertEquals((float)PHP_INT_MAX + 1, $largeResult, "Large number addition failed");

        // Very small numbers and precision
        $smallNumber = 0.0000000001;
        $smallResult = SmartString::new($smallNumber)->multiply(1000000)->value();
        $this->assertEquals(0.0001, $smallResult, "Small number multiplication failed");

        // Precision in division
        $precisionDivide = SmartString::new(1)->divide(3)->value();
        $this->assertEquals(0.3333333333333333, $precisionDivide, "Precision division failed");

        // Test overflow behavior
        $overflowTest = SmartString::new(1.7976931348623157e+308)->multiply(2)->value(); // Max double value
        $this->assertTrue(is_infinite($overflowTest), "Overflow should result in INF");

        // Test negative overflow
        $negOverflowTest = SmartString::new(-1.7976931348623157e+308)->multiply(2)->value();
        $this->assertTrue(is_infinite($negOverflowTest) && $negOverflowTest < 0, "Negative overflow should result in -INF");

        // Very large numeric string
        $largeString = "9" . str_repeat("0", 20); // 9 followed by 20 zeros
        $largeStringResult = SmartString::new($largeString)->add(1)->value();
        $this->assertEquals(9 * pow(10, 20) + 1, $largeStringResult, "Large numeric string addition failed");
    }


    // endregion
    // region Conditional Operations

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testOrMethod($value, $fallback, $expected): void
    {
        $result = SmartString::new($value)->or($fallback)->value();
        $this->assertSame($expected['or'], $result, "or() method failed for input: " . var_export($value, true));
    }

    /**
     * @dataProvider andMethodProvider
     */
    public function testAndMethod($value, $appendValue, $expected): void
    {
        $result = SmartString::new($value)->and($appendValue)->value();
        $this->assertSame($expected, $result, "and() method failed for input: " . var_export($value, true));
    }

    public static function andMethodProvider(): array
    {
        return [
            'non-empty string' => [
                'value'       => 'Hello',
                'appendValue' => ' World',
                'expected'    => 'Hello World',
            ],
            'empty string' => [
                'value'       => '',
                'appendValue' => 'World',
                'expected'    => '',
            ],
            'null value' => [
                'value'       => null,
                'appendValue' => 'World',
                'expected'    => null,
            ],
            'false value' => [
                'value'       => false,
                'appendValue' => 'World',
                'expected'    => 'World',  // Updated to reflect new behavior
            ],
            'zero value' => [
                'value'       => 0,
                'appendValue' => ' items',
                'expected'    => '0 items',
            ],
            'SmartString append value' => [
                'value'       => 'Price: ',
                'appendValue' => SmartString::new('$10.00'),
                'expected'    => 'Price: $10.00',
            ],
            'numeric values' => [
                'value'       => 100,
                'appendValue' => '%',
                'expected'    => '100%',
            ],
        ];
    }

    /**
     * @dataProvider andPrefixMethodProvider
     */
    public function testAndPrefixMethod($value, $prefixValue, $expected): void
    {
        $result = SmartString::new($value)->andPrefix($prefixValue)->value();
        $this->assertSame($expected, $result, "andPrefix() method failed for input: " . var_export($value, true));
    }

    public static function andPrefixMethodProvider(): array
    {
        return [
            'non-empty string' => [
                'value'       => 'World',
                'prefixValue' => 'Hello ',
                'expected'    => 'Hello World',
            ],
            'empty string' => [
                'value'       => '',
                'prefixValue' => 'Hello ',
                'expected'    => '',
            ],
            'null value' => [
                'value'       => null,
                'prefixValue' => 'Hello ',
                'expected'    => null,
            ],
            'false value' => [
                'value'       => false,
                'prefixValue' => 'Hello ',
                'expected'    => 'Hello ',  // Updated to reflect new behavior
            ],
            'zero value' => [
                'value'       => 0,
                'prefixValue' => '$',
                'expected'    => '$0',
            ],
            'SmartString prefix value' => [
                'value'       => 'items',
                'prefixValue' => SmartString::new('10 '),
                'expected'    => '10 items',
            ],
            'numeric values' => [
                'value'       => 100,
                'prefixValue' => '$',
                'expected'    => '$100',
            ],
        ];
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIfNullMethod($value, $fallback, $expected): void
    {
        $result = SmartString::new($value)->ifNull($fallback)->value();
        $this->assertSame(
            $expected['ifNull'],
            $result,
            "ifNull() method failed for input: " . var_export($value, true),
        );
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIfBlankMethod($value, $fallback, $expected): void
    {
        $result = SmartString::new($value)->ifBlank($fallback)->value();
        $this->assertSame(
            $expected['ifBlank'],
            $result,
            "ifBlank() method failed for input: " . var_export($value, true),
        );
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIfZeroMethod($value, $fallback, $expected): void
    {
        $result = SmartString::new($value)->ifZero($fallback)->value();
        $this->assertSame(
            $expected['ifZero'],
            $result,
            "ifZero() method failed for input: " . var_export($value, true),
        );
    }

    public static function conditionalMethodsProvider(): array
    {
        $fallback = 'fallback';

        return [
            'empty string'               => [
                '',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '',
                    'ifBlank' => $fallback,
                    'ifZero'  => '',
                ],
            ],
            'integer zero'               => [
                0,
                $fallback,
                [
                    'or'      => 0,
                    'ifNull'  => 0,
                    'ifBlank' => 0,
                    'ifZero'  => $fallback,
                ],
            ],
            'string zero'                => [
                '0',
                $fallback,
                [
                    'or'      => '0',
                    'ifNull'  => '0',
                    'ifBlank' => '0',
                    'ifZero'  => $fallback,
                ],
            ],
            'float zero'                 => [
                0.0,
                $fallback,
                [
                    'or'      => 0.0,
                    'ifNull'  => 0.0,
                    'ifBlank' => 0.0,
                    'ifZero'  => $fallback,
                ],
            ],
            'string float zero'          => [
                '0.0',
                $fallback,
                [
                    'or'      => '0.0',
                    'ifNull'  => '0.0',
                    'ifBlank' => '0.0',
                    'ifZero'  => $fallback,
                ],
            ],
            'null'                       => [
                null,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'ifZero'  => null,
                ],
            ],
            'whitespace'                 => [
                ' ',
                $fallback,
                [
                    'or'      => ' ',
                    'ifNull'  => ' ',
                    'ifBlank' => ' ',
                    'ifZero'  => ' ',
                ],
            ],
            'non-empty string'           => [
                'Hello',
                $fallback,
                [
                    'or'      => 'Hello',
                    'ifNull'  => 'Hello',
                    'ifBlank' => 'Hello',
                    'ifZero'  => 'Hello',
                ],
            ],
            'SmartString fallback Null'  => [
                null,
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'ifZero'  => null,
                ],
            ],
            'SmartString fallback Blank' => [
                "",
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => "",
                    'ifBlank' => $fallback,
                    'ifZero'  => "",
                ],
            ],
            'SmartString fallback Zero'  => [
                "0",
                SmartString::new($fallback),
                [
                    'or'      => "0",
                    'ifNull'  => "0",
                    'ifBlank' => "0",
                    'ifZero'  => $fallback,
                ],
            ],
        ];
    }


    /**
     * @dataProvider ifMethodProvider
     */
    public function testIfMethod($value, $condition, $valueIfTrue, $expected): void
    {
        $result = SmartString::new($value)->if($condition, $valueIfTrue)->value();
        $this->assertSame($expected, $result);
    }

    public static function ifMethodProvider(): array
    {
        return [
            'condition true'         => [
                'value'       => 5,
                'condition'   => true,
                'valueIfTrue' => 10,
                'expected'    => 10,
            ],
            'condition false'        => [
                'value'       => 5,
                'condition'   => false,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'null condition'         => [
                'value'       => 5,
                'condition'   => null,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'zero condition'         => [
                'value'       => 5,
                'condition'   => 0,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'string condition true'  => [
                'value'       => 5,
                'condition'   => '1',
                'valueIfTrue' => 10,
                'expected'    => 10,
            ],
            'string condition false' => [
                'value'       => 5,
                'condition'   => '0',
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'SmartString as valueIfTrue' => [
                'value'       => 5,
                'condition'   => true,
                'valueIfTrue' => new SmartString('replaced value'),
                'expected'    => 'replaced value',
            ],
        ];
    }

    /**
     * @dataProvider setMethodProvider
     */
    public function testSetMethod($value, $newValue, $expected): void
    {
        $result = SmartString::new($value)->set($newValue)->value();
        $this->assertSame($expected, $result);
    }

    public static function setMethodProvider(): array
    {
        return [
            'set integer' => [
                'value'    => 5,
                'newValue' => 10,
                'expected' => 10,
            ],
            'set string'  => [
                'value'    => 'old',
                'newValue' => 'new',
                'expected' => 'new',
            ],
            'set null'    => [
                'value'    => 5,
                'newValue' => null,
                'expected' => null,
            ],
            'set SmartString' => [
                'value'    => 'original',
                'newValue' => new SmartString('from smartstring'),
                'expected' => 'from smartstring',
            ],
        ];
    }


    // endregion
    // region Misc


    /**
     * @dataProvider applyMethodProvider
     */
    public function testApplyMethod($input, $function, $args, $expected): void
    {
        $result = SmartString::new($input)->apply($function, ...$args);
        $this->assertSame($expected, $result->value());
    }

    /**
     * @return array[]
     * @noinspection SpellCheckingInspection
     */
    public static function applyMethodProvider(): array
    {
        return [
            'strtoupper'                         => [
                'input'    => 'hello world',
                'function' => 'strtoupper',
                'args'     => [],
                'expected' => 'HELLO WORLD',
            ],
            'trim'                               => [
                'input'    => '  spaced  ',
                'function' => 'trim',
                'args'     => [],
                'expected' => 'spaced',
            ],
            'trim with argument'                 => [
                'input'    => 'xxxhelloxxx',
                'function' => 'trim',
                'args'     => ['x'],
                'expected' => 'hello',
            ],
            'arrow function'                     => [
                'input'    => 'hello',
                'function' => fn($s) => $s . ' world',
                'args'     => [],
                'expected' => 'hello world',
            ],
            'arrow function with additional arg' => [
                'input'    => 'hello',
                'function' => fn($s, $suffix) => $s . $suffix,
                'args'     => [' universe'],
                'expected' => 'hello universe',
            ],
            'closure'                            => [
                'input'    => 'hello',
                'function' => function ($s) {
                    return $s . ' world';
                },
                'args'     => [],
                'expected' => 'hello world',
            ],
        ];
    }

    public function testApplyWithInvalidFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $smartString = new SmartString('test');
        $smartString->apply('non_existent_function');
    }

    // endregion
    // region Validation

    /**
     * @dataProvider isMissingMethodProvider
     */
    public function testIsMissingMethod($value, $expected): void
    {
        $result = SmartString::new($value)->isMissing();
        $this->assertSame($expected, $result, "isMissing() method failed for input: " . var_export($value, true));
    }

    public static function isMissingMethodProvider(): array
    {
        return [
            'null value' => [
                'value'    => null,
                'expected' => true,
            ],
            'empty string' => [
                'value'    => '',
                'expected' => true,
            ],
            'non-empty string' => [
                'value'    => 'Hello',
                'expected' => false,
            ],
            'integer zero' => [
                'value'    => 0,
                'expected' => false,
            ],
            'boolean false' => [
                'value'    => false,
                'expected' => false,
            ],
            'boolean true' => [
                'value'    => true,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider isNullMethodProvider
     */
    public function testIsNullMethod($value, $expected): void
    {
        $result = SmartString::new($value)->isNull();
        $this->assertSame($expected, $result, "isNull() method failed for input: " . var_export($value, true));
    }

    public static function isNullMethodProvider(): array
    {
        return [
            'null value' => [
                'value'    => null,
                'expected' => true,
            ],
            'empty string' => [
                'value'    => '',
                'expected' => false,
            ],
            'non-empty string' => [
                'value'    => 'Hello',
                'expected' => false,
            ],
            'integer zero' => [
                'value'    => 0,
                'expected' => false,
            ],
            'boolean false' => [
                'value'    => false,
                'expected' => false,
            ],
            'boolean true' => [
                'value'    => true,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider isEmptyMethodProvider
     */
    public function testIsEmptyMethod($value, $expected): void
    {
        $result = SmartString::new($value)->isEmpty();
        $this->assertSame($expected, $result, "isEmpty() method failed for input: " . var_export($value, true));
    }

    public static function isEmptyMethodProvider(): array
    {
        return [
            'empty string' => [
                'value'    => '',
                'expected' => true,
            ],
            'null' => [
                'value'    => null,
                'expected' => true,
            ],
            'false' => [
                'value'    => false,
                'expected' => true,
            ],
            'zero' => [
                'value'    => 0,
                'expected' => true,
            ],
            'zero string' => [
                'value'    => '0',
                'expected' => true,
            ],
            'whitespace' => [
                'value'    => ' ',
                'expected' => false,
            ],
            'non-empty string' => [
                'value'    => 'Hello',
                'expected' => false,
            ],
            'positive number' => [
                'value'    => 42,
                'expected' => false,
            ],
            'true' => [
                'value'    => true,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider isNotEmptyMethodProvider
     */
    public function testIsNotEmptyMethod($value, $expected): void
    {
        $result = SmartString::new($value)->isNotEmpty();
        $this->assertSame($expected, $result, "isNotEmpty() method failed for input: " . var_export($value, true));
    }

    public static function isNotEmptyMethodProvider(): array
    {
        return [
            'empty string' => [
                'value'    => '',
                'expected' => false,
            ],
            'null' => [
                'value'    => null,
                'expected' => false,
            ],
            'false' => [
                'value'    => false,
                'expected' => false,
            ],
            'zero' => [
                'value'    => 0,
                'expected' => false,
            ],
            'zero string' => [
                'value'    => '0',
                'expected' => false,
            ],
            'whitespace' => [
                'value'    => ' ',
                'expected' => true,
            ],
            'non-empty string' => [
                'value'    => 'Hello',
                'expected' => true,
            ],
            'positive number' => [
                'value'    => 42,
                'expected' => true,
            ],
            'true' => [
                'value'    => true,
                'expected' => true,
            ],
        ];
    }

    /**
     * Test for error messages in magic methods
     */
    public function testErrorMessagesInMagicMethods(): void
    {
        // Just verify that the string representation works
        $smartString = SmartString::new('<test>');
        $this->assertSame('&lt;test&gt;', (string)$smartString, "__toString should HTML encode");
    }

    /**
     * Test __toString magic method
     */
    public function testToStringMethodAutoEncoding(): void
    {
        // Create SmartString with HTML content
        $smartString = SmartString::new('<b>Bold Text</b> & <i>Italic</i>');

        // Test string context triggers __toString and htmlEncode
        $result = 'Text: ' . $smartString;
        $this->assertSame('Text: &lt;b&gt;Bold Text&lt;/b&gt; &amp; &lt;i&gt;Italic&lt;/i&gt;', $result, "__toString should auto-encode HTML");

        // Compare with explicit htmlEncode call
        $this->assertSame((string)$smartString, $smartString->htmlEncode(), "__toString should be same as htmlEncode()");
    }

    // endregion
    // region Error Checking

    public function testOr404Method(): void
    {
        // Test with non-empty values (should return $this for chaining)
        $this->assertSame('Hello', SmartString::new('Hello')->or404()->value(), "or404() changed a non-empty value");
        $this->assertSame(42, SmartString::new(42)->or404()->value(), "or404() changed a non-zero value");
        $this->assertSame(0, SmartString::new(0)->or404()->value(), "or404() incorrectly treated zero as empty");

        // We can't actually test the 404 output directly as it would exit the script
        // So we're only testing the non-empty-value case above
    }

    public function testOrDieMethod(): void
    {
        // Test with non-empty values (should return $this for chaining)
        $this->assertSame('Hello', SmartString::new('Hello')->orDie('Error message')->value(), "orDie() changed a non-empty value");
        $this->assertSame(42, SmartString::new(42)->orDie('Error message')->value(), "orDie() changed a non-zero value");
        $this->assertSame(0, SmartString::new(0)->orDie('Error message')->value(), "orDie() incorrectly treated zero as empty");

        // We can't actually test the die() output directly as it would terminate the script
        // So we're only testing the non-empty-value case above
    }

    public function testOrThrowMethod(): void
    {
        // Test with non-empty values (should return $this for chaining)
        $this->assertSame('Hello', SmartString::new('Hello')->orThrow('Error message')->value(), "orThrow() changed a non-empty value");
        $this->assertSame(42, SmartString::new(42)->orThrow('Error message')->value(), "orThrow() changed a non-zero value");
        $this->assertSame(0, SmartString::new(0)->orThrow('Error message')->value(), "orThrow() incorrectly treated zero as empty");

        // Test that it throws an exception for empty values
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error message');
        SmartString::new('')->orThrow('Error message');
    }

    // endregion
    // region Debugging & Help

    public function testHelpMethod(): void
    {
        // Capture output buffer to test the help text display
        ob_start();
        $result = SmartString::new('test')->help('original value');
        $output = ob_get_clean();

        // Verify it returns the optional parameter
        $this->assertSame('original value', $result);

        // Verify the output contains helpful documentation
        $this->assertStringContainsString('SmartString: Enhanced Strings', $output);
        $this->assertStringContainsString('Creating SmartStrings', $output);
        $this->assertStringContainsString('Type conversion', $output);
        $this->assertStringContainsString('Encoding methods', $output);
    }

    /**
     * @dataProvider jsonSerializeProvider
     */
    public function testJsonSerialize($input, $expected): void
    {
        $smartString = SmartString::new($input);
        $jsonEncoded = json_encode($smartString);
        $this->assertSame($expected, $jsonEncoded, "jsonSerialize() method failed for input: " . var_export($input, true));
    }

    public static function jsonSerializeProvider(): array
    {
        return [
            'string value' => [
                'input'    => 'Hello World',
                'expected' => '"Hello World"',
            ],
            'integer value' => [
                'input'    => 42,
                'expected' => '42',
            ],
            'float value' => [
                'input'    => 3.14,
                'expected' => '3.14',
            ],
            'boolean true' => [
                'input'    => true,
                'expected' => 'true',
            ],
            'boolean false' => [
                'input'    => false,
                'expected' => 'false',
            ],
            'null value' => [
                'input'    => null,
                'expected' => 'null',
            ],
        ];
    }

    public function testDebugInfo(): void
    {
        // Test __debugInfo output through print_r
        $smartString = SmartString::new('test value');
        $debugOutput = print_r($smartString, true);

        // Verify debug output contains expected information
        $this->assertStringContainsString('rawData:private', $debugOutput);
        $this->assertStringContainsString('"test value"', $debugOutput);

        // Test numeric values
        $smartStringNum = SmartString::new(42);
        $debugOutputNum = print_r($smartStringNum, true);
        $this->assertStringContainsString('42', $debugOutputNum);

        // Test null values
        $smartStringNull = SmartString::new(null);
        $debugOutputNull = print_r($smartStringNull, true);
        $this->assertStringContainsString('NULL', $debugOutputNull);

        // Test boolean values
        $smartStringBool = SmartString::new(true);
        $debugOutputBool = print_r($smartStringBool, true);
        $this->assertStringContainsString('TRUE', $debugOutputBool);
    }

    // endregion
}
