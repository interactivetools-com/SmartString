<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;
use Itools\SmartArray\SmartArray;
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

    public function constructorValidProvider(): array
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

    public function constructorInvalidProvider(): array
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
            // check we got an SmartArray
            $this->assertInstanceOf(SmartArray::class, $result);

            // Check that the SmartString objects are converted back to the expected values
            $this->assertSame($expected, self::smartStringsToValues($result));
        } // single values
        else {
            $this->assertInstanceOf(SmartString::class, $result);
            $this->assertSame($expected, $result->value());
        }
    }

    public function newMethodValidProvider(): array
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

    public function newMethodInvalidProvider(): array
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
        $debugType = basename(get_debug_type($value)); // Itools\SmartString\SmartString => SmartString
        return match ($debugType) {
            'SmartArray'  => $value->toArray(),
            'SmartString' => $value->value(),
            default       => throw new InvalidArgumentException("Invalid value type: $debugType"),
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
        $encodedUsers = new SmartArray($users);
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

    public function valueMethodsProvider(): array
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
    public function testEncodingMethods($input, $expectedHtml, $expectedUrl, $expectedJson, $expectedNoEncode): void
    {
        $smartString = new SmartString($input);

        $this->assertSame($expectedHtml, $smartString->htmlEncode(), "htmlEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedUrl, $smartString->urlEncode(), "urlEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedJson, $smartString->jsonEncode(), "jsonEncode() method failed for input: " . var_export($input, true));
        $this->assertSame($expectedNoEncode, $smartString->noEncode(), "noEncode() method failed for input: " . var_export($input, true));
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function encodingMethodsProvider(): array
    {
        return [
            'plaintext with br'        => [
                'input'            => 'One\nTwo<br>Three<br>\nFour<br>\n',
                'expectedHtml'     => 'One\nTwo<br>Three<br>\nFour<br>\n',
                'expectedUrl'      => 'One%5CnTwo%3Cbr%3EThree%3Cbr%3E%5CnFour%3Cbr%3E%5Cn',
                'expectedJson'     => '"One\\\\nTwo\u003Cbr\u003EThree\u003Cbr\u003E\\\\nFour\u003Cbr\u003E\\\\n"',
                'expectedNoEncode' => 'One\nTwo<br>Three<br>\nFour<br>\n',
            ],
            'already encoded'          => [
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
    public function textOnlyProvider(): array
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

    /**
     * @dataProvider nl2brProvider
     * @noinspection OneTimeUseVariablesInspection
     */
    public function testNl2br($input, $expected): void
    {
        $smartString = new SmartString($input);
        $result      = $smartString->nl2br()->value();
        $this->assertSame($expected, $result);
    }

    public function nl2brProvider(): array
    {
        return [
            'basic newline conversion'    => [
                "Hello\nWorld",
                "Hello<br>\nWorld",
            ],
            'multiple newlines'           => [
                "Hello\nWorld\nAgain",
                "Hello<br>\nWorld<br>\nAgain",
            ],
            'carriage return and newline' => [
                "Hello\r\nWorld",
                "Hello<br>\r\nWorld",
            ],
            'mixed newlines'              => [
                "Hello\nWorld\r\nAgain\rAnd\n\rAgain",
                "Hello<br>\nWorld<br>\r\nAgain<br>\rAnd<br>\n\rAgain",
            ],
            'no newlines'                 => [
                'Hello World',
                'Hello World',
            ],
            'empty string'                => [
                '',
                '',
            ],
            'null input'                  => [
                null,
                null,
            ],
            'newlines at start and end'   => [
                "\nHello World\n",
                "<br>\nHello World<br>\n",
            ],
            'consecutive newlines'        => [
                "Hello\n\nWorld",
                "Hello<br>\n<br>\nWorld",
            ],
        ];
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
    public function trimProvider(): array
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

    public function maxWordsProvider(): array
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

    public function maxCharsProvider(): array
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
     * @dataProvider dateFormatProvider
     */
    public function testDateTimeFormat($input, $format, $expected): void
    {
        date_default_timezone_set('America/Phoenix'); // Timezone with no DST for consistent results

        // dateFormat
        $result = SmartString::new($input)->dateFormat($format)->value();
        $error  = "Failed for input: " . var_export($input, true) . ", output: " . var_export($result, true);
        $this->assertSame($expected, $result, $error);
    }

    public function dateFormatProvider(): array
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
                null,
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
     * @dataProvider numberFormatProvider
     */
    public function testNumberFormat($input, $decimals, $expected): void
    {
        $result = SmartString::new($input)->numberFormat($decimals)->value();
        $this->assertSame($expected, $result);
    }

    public function numberFormatProvider(): array
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

    public function phoneFormatProvider(): array
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
     * @dataProvider percentProvider
     */
    public function testPercent($input, $decimals, $expected): void
    {
        $result = SmartString::new($input)->percent($decimals)->value();
        $this->assertSame($expected, $result);
    }

    public function percentProvider(): array
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
     * @dataProvider percentOfProvider
     */
    public function testPercentOf($input, $total, $decimals, $expected): void
    {
        $result = SmartString::new($input)->percentOf($total, $decimals)->value();
        $this->assertSame($expected, $result, "Got: " . var_export($result, true));
    }

    public function percentOfProvider(): array
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

    public function addProvider(): array
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

    public function subtractProvider(): array
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

    public function multiplyProvider(): array
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

    public function divideProvider(): array
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
    public function testIsZeroMethod($value, $fallback, $expected): void
    {
        $result = SmartString::new($value)->isZero($fallback)->value();
        $this->assertSame(
            $expected['isZero'],
            $result,
            "isZero() method failed for input: " . var_export($value, true),
        );
    }

    public function conditionalMethodsProvider(): array
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
                    'isZero'  => '',
                ],
            ],
            'integer zero'               => [
                0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0,
                    'ifBlank' => 0,
                    'isZero'  => $fallback,
                ],
            ],
            'string zero'                => [
                '0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0',
                    'ifBlank' => '0',
                    'isZero'  => $fallback,
                ],
            ],
            'float zero'                 => [
                0.0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0.0,
                    'ifBlank' => 0.0,
                    'isZero'  => $fallback,
                ],
            ],
            'string float zero'          => [
                '0.0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0.0',
                    'ifBlank' => '0.0',
                    'isZero'  => $fallback,
                ],
            ],
            'null'                       => [
                null,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'isZero'  => null,
                ],
            ],
            'whitespace'                 => [
                ' ',
                $fallback,
                [
                    'or'      => ' ',
                    'ifNull'  => ' ',
                    'ifBlank' => ' ',
                    'isZero'  => ' ',
                ],
            ],
            'non-empty string'           => [
                'Hello',
                $fallback,
                [
                    'or'      => 'Hello',
                    'ifNull'  => 'Hello',
                    'ifBlank' => 'Hello',
                    'isZero'  => 'Hello',
                ],
            ],
            'SmartString fallback Null'  => [
                null,
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'isZero'  => null,
                ],
            ],
            'SmartString fallback Blank' => [
                "",
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => "",
                    'ifBlank' => $fallback,
                    'isZero'  => "",
                ],
            ],
            'SmartString fallback Zero'  => [
                "0",
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => "0",
                    'ifBlank' => "0",
                    'isZero'  => $fallback,
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

    public function ifMethodProvider(): array
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
            'object valueIfTrue'     => [
                'value'       => 5,
                'condition'   => true,
                'valueIfTrue' => new class {
                    public function value(): int
                    {
                        return 15;
                    }
                },
                'expected'    => 15,
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

    public function setMethodProvider(): array
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
            'set object'  => [
                'value'    => 5,
                'newValue' => new class {
                    public function value(): int
                    {
                        return 15;
                    }
                },
                'expected' => 15,
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
    public function applyMethodProvider(): array
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
    // region Debugging & Help

    // endregion
}
