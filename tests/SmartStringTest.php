<?php

/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;

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
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function testConstructorInvalid($input): void
    {
        $this->expectException(TypeError::class);
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
            // check we got an ArrayObject
            $this->assertInstanceOf(ArrayObject::class, $result);

            // Ensure all elements are either ArrayObject or SmartString objects
            array_walk_recursive($result, function ($value) {
                $this->assertTrue(
                    $value instanceof ArrayObject || $value instanceof SmartString,
                    "Invalid value type: ".get_debug_type($value),
                );
            });

            // Check that the SmartString objects are converted back to the expected values
            $this->assertSame($expected, self::smartStringsToValues($result));
        } // single values
        else {
            $this->assertInstanceOf(SmartString::class, $result);
            $this->assertSame($expected, $result->value());
        }
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

    public function newMethodValidProvider(): array
    {
        return [
            // single values
            "single string"   => ["Hello, World!", "Hello, World!"],
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
            'ArrayObject' => array_map(static fn($item) => self::smartStringsToValues($item), $value->getArrayCopy()),
            'SmartString' => $value->value(),
            default       => throw new InvalidArgumentException("Invalid value type: $debugType"),
        };
    }
    // endregion
    // region Type Conversion tests

    /**
     * @dataProvider valueMethodsProvider
     */
    public function testValueMethods($input, $expectedValue, $expectedInt, $expectedFloat, $expectedBool, $expectedString): void
    {
        $smartString = new SmartString($input);

        $this->assertSame($expectedValue, $smartString->value(), "value() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedInt, $smartString->int(), "int() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedFloat, $smartString->float(), "float() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedBool, $smartString->bool(), "bool() method failed for input: ".var_export($input, true));
        $this->assertSame($expectedString, $smartString->string(), "string() method failed for input: ".var_export($input, true));
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
    // region String Operation tests

    /**
     * @dataProvider stripTagsProvider
     */
    public function testStripTags($input, $allowedTags, $expected): void
    {
        $result = SmartString::new($input)->stripTags($allowedTags)->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function stripTagsProvider(): array
    {
        return [
            'basic HTML removal'  => [
                '<p>Hello <b>World</b>!</p>',
                null,
                'Hello World!',
            ],
            'allow specific tags' => [
                '<p>Hello <b>World</b>!</p>',
                '<p>',
                '<p>Hello World!</p>',
            ],
            'nested tags'         => [
                '<div><p>Hello <b>World</b>!</p></div>',
                '<p>',
                '<p>Hello World!</p>',
            ],
            'malformed HTML'      => [
                '<p>Hello <b>World!</p>',
                null,
                'Hello World!',
            ],
            'script tag removal'  => [
                '<script>alert("XSS");</script>Hello',
                null,
                'alert("XSS");Hello',
            ],
            'br with nextlines'   => [
                "The<br>\nquick<BR />\nbrown<BR/>\nfox<bR> jumps<BR>over<BR/>the<BR   />lazy<BR   >dog",
                null,
                "The\nquick\nbrown\nfox jumpsoverthelazydog",
            ],
            'non-HTML input'      => [
                'Plain text',
                null,
                'Plain text',
            ],
            'empty string'        => [
                '',
                null,
                '',
            ],
            'null input'          => [
                null,
                null,
                '',
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
                '',
            ],
            'newlines at start and end'   => [
                "\nHello World\n",
                "<br>\nHello World<br>\n",
            ],
            'consecutive newlines'        => [
                "Hello\n\n\nWorld",
                "Hello<br>\n<br>\n<br>\nWorld",
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
                '',
            ],
        ];
    }

    // endregion
    // region Date Operation tests

    /**
     * @dataProvider dateFormatProvider
     */
    public function testDateFormat($input, $format, $expected): void
    {
        // Temporarily change the timezone to ensure consistent results
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Phoenix'); // Timezone with no DST for consistent results

        // Perform the test
        try {
            $result = SmartString::new($input)->dateFormat($format)->value();
            $this->assertSame($expected, $result, "Failed for input: ".var_export($input, true).", output: ".var_export($result, true));
        } finally {
            // Restore the original timezone
            date_default_timezone_set($originalTimezone);
        }
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
            'Boolean true'              => [
                true,
                'Y-m-d H:i:s T',
                null,
            ],
            'Boolean false'             => [
                false,
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

    // endregion
    // region Numeric Operation tests

    /**
     * @dataProvider numberFormatProvider
     */
    public function testNumberFormat($input, $decimals, $decPoint, $thousandsSep, $expected): void
    {
        $result = SmartString::new($input)->numberFormat($decimals, $decPoint, $thousandsSep)->value();
        $this->assertSame($expected, $result);
    }

    public function numberFormatProvider(): array
    {
        return [
            'large int, no args' => [1000000000000, 0, null, null, '1,000,000,000,000'],
            'positive integer'   => [1000, 0, '.', ',', '1,000'],
            'negative integer'   => [-1000, 0, '.', ',', '-1,000'],
            'positive float'     => [1234.56, 2, '.', ',', '1,234.56'],
            'negative float'     => [-1234.56, 2, '.', ',', '-1,234.56'],
            'custom separator'   => [1000000, 0, '.', ' ', '1 000 000'],
            'round up'           => [1234.56789, 2, ',', '.', '1.234,57'],
            'large number'       => [1000000000000, 0, '.', ',', '1,000,000,000,000'],
            'small number'       => [0.0000001, 7, '.', ',', '0.0000001'],
            'zero'               => [0, 2, '.', ',', '0.00'],
        ];
    }

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
            'whole'            => [1.0, 0, '100%'],
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
        $this->assertSame($expected, $result, "Got: ".var_export($result, true));
    }

    public function percentOfProvider(): array
    {
        return [
            'half'              => [50, 100, 0, '50%'],
            'quarter'           => [25, 100, 0, '25%'],
            'double'            => [100, 50, 0, '200%'],
            'with decimals'     => [75, 150, 2, '50.00%'],
            'zero numerator'    => [0, 100, 0, '0%'],
            'zero denominator'  => [100, 0, 0, null],
            'SmartString input' => [75, new SmartString(150.555555), 2, '49.82%'],
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

    /**
     * @dataProvider invalidNumericInputProvider
     */
    public function testInvalidNumericInputs($method, $input, ...$args): void
    {
        $result = SmartString::new($input)->$method(...$args)->value();
        $this->assertNull($result);
    }

    public function invalidNumericInputProvider(): array
    {
        return [
            'numberFormat non-numeric' => ['numberFormat', 'abc', 2],
            'numberFormat null'        => ['numberFormat', null, 2],
            'numberFormat boolean'     => ['numberFormat', true, 2],
            'percent non-numeric'      => ['percent', 'abc', 2],
            'percent null'             => ['percent', null, 2],
            'percent boolean'          => ['percent', true, 2],
            'percentOf non-numeric'    => ['percentOf', 'abc', 100, 2],
            'percentOf null'           => ['percentOf', null, 100, 2],
            'percentOf boolean'        => ['percentOf', true, 100, 2],
            'divide non-numeric'       => ['divide', 'abc', 2],
            'divide null'              => ['divide', null, 2],
            'divide boolean'           => ['divide', true, 2],
        ];
    }




    // endregion
    // region Conditional Operation tests

    /**
     * @dataProvider conditionalOperationsProvider
     */
    public function testConditionalOperations($input, $alternative, $orExpected, $ifNullExpected, $ifBlankExpected): void
    {
        $smartString = new SmartString($input);

        $orResult = $smartString->or($alternative);
        $this->assertSame($orExpected, $orResult->value(), "or() method failed for input: ".var_export($input, true));

        $ifNullResult = $smartString->ifNull($alternative);
        $this->assertSame($ifNullExpected, $ifNullResult->value(), "ifNull() method failed for input: ".var_export($input, true));

        $ifBlankResult = $smartString->ifBlank($alternative);
        $this->assertSame($ifBlankExpected, $ifBlankResult->value(), "ifBlank() method failed for input: ".var_export($input, true));
    }

    public function conditionalOperationsProvider(): array
    {
        return [
            'null value'        => [
                'input'           => null,
                'alternate value' => 'alternate value',
                'orExpected'      => 'alternate value',
                'ifNullExpected'  => 'alternate value',
                'ifBlankExpected' => null,
            ],
            'empty string'      => [
                'input'           => '',
                'alternate value' => 'alternate value',
                'orExpected'      => 'alternate value',
                'ifNullExpected'  => '',
                'ifBlankExpected' => '',
            ],
            'whitespace string' => [
                'input'           => '   ',
                'alternate value' => 'alternate value',
                'orExpected'      => '   ',
                'ifNullExpected'  => '   ',
                'ifBlankExpected' => '   ',
            ],
            'zero'              => [
                'input'           => 0,
                'alternate value' => 'alternate value',
                'orExpected'      => 'alternate value',
                'ifNullExpected'  => 0,
                'ifBlankExpected' => 0,
            ],
            'false'             => [
                'input'           => false,
                'alternate value' => 'alternate value',
                'orExpected'      => 'alternate value',
                'ifNullExpected'  => false,
                'ifBlankExpected' => false,
            ],
            'true'              => [
                'input'           => true,
                'alternate value' => 'alternate value',
                'orExpected'      => true,
                'ifNullExpected'  => true,
                'ifBlankExpected' => true,
            ],
            'non-empty string'  => [
                'input'           => 'Hello',
                'alternate value' => 'alternate value',
                'orExpected'      => 'Hello',
                'ifNullExpected'  => 'Hello',
                'ifBlankExpected' => 'Hello',
            ],
            'positive number'   => [
                'input'           => 42,
                'alternate value' => 'alternate value',
                'orExpected'      => 42,
                'ifNullExpected'  => 42,
                'ifBlankExpected' => 42,
            ],
            'negative number'   => [
                'input'           => -10,
                'alternate value' => 'alternate value',
                'orExpected'      => -10,
                'ifNullExpected'  => -10,
                'ifBlankExpected' => -10,
            ],
            'float number'      => [
                'input'           => 3.14,
                'alternate value' => 'alternate value',
                'orExpected'      => 3.14,
                'ifNullExpected'  => 3.14,
                'ifBlankExpected' => 3.14,
            ],
        ];
    }

    // endregion
    // region Apply Method tests

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
                'function' => fn($s) => $s.' world',
                'args'     => [],
                'expected' => 'hello world',
            ],
            'arrow function with additional arg' => [
                'input'    => 'hello',
                'function' => fn($s, $suffix) => $s.$suffix,
                'args'     => [' universe'],
                'expected' => 'hello universe',
            ],
            'closure'                            => [
                'input'    => 'hello',
                'function' => function ($s) {
                    return $s.' world';
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
    // region Help tests


    // endregion
}
