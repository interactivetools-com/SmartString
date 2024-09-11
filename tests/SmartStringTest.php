<?php
/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

namespace Tests;

use ArrayObject;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;
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
        $this->expectException(Error::class);
        $smartString = new SmartString('test');
        $smartString->apply('non_existent_function');
    }

    // endregion
    // region ArrayObject test

    public function testArrayObjectHtmlEncoding()
    {
        // Arrange
        $users = [
            ['name' => 'John <script>alert("XSS")</script>', 'email' => 'john@example.com'],
            ['name' => "Jane O'Connor", 'email' => 'jane@example.com'],
            ['name' => 'Bob & Alice', 'email' => 'bob.alice@example.com'],
        ];

        // Act
        $encodedUsers = SmartString::fromArray($users);
        $result = [];

        foreach ($encodedUsers as $user) {
            $result[] = [
                'name' => (string)$user['name'],
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
}
