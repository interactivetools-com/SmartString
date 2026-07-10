<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * int()/float()/bool()/string() terminal conversions and the getRawValue()
 * static unwrapper.
 *
 * n/a dimensions: encoding (string() is documented as NOT encoding - pinned
 * here), global settings, immutability (terminals don't mutate).
 */
class TypeConversionTest extends SmartStringTestCase
{
    //region Type Quartet

    /**
     * Every input through value/int/float/bool/string in one pass - the
     * library's core type contract.
     */
    #[DataProvider('typeQuartetProvider')]
    public function testTypeConversions($input, $expectedValue, int $expectedInt, float $expectedFloat, bool $expectedBool, string $expectedString): void
    {
        $smartString = new SmartString($input);
        $label       = "input: " . var_export($input, true);

        $this->assertSame($expectedValue, $smartString->value(), "value() failed for $label");
        $this->assertSame($expectedInt, $smartString->int(), "int() failed for $label");
        $this->assertSame($expectedFloat, $smartString->float(), "float() failed for $label");
        $this->assertSame($expectedBool, $smartString->bool(), "bool() failed for $label");
        $this->assertSame($expectedString, $smartString->string(), "string() failed for $label");
    }

    public static function typeQuartetProvider(): array
    {
        // input, value(), int(), float(), bool(), string()
        return [
            'string'         => ["O'Reilly & Sons <Web &nbsp; Shop/>", "O'Reilly & Sons <Web &nbsp; Shop/>", 0, 0.0, true, "O'Reilly & Sons <Web &nbsp; Shop/>"],
            'empty string'   => ['', '', 0, 0.0, false, ''],
            'integer'        => [42, 42, 42, 42.0, true, '42'],
            'zero int'       => [0, 0, 0, 0.0, false, '0'],
            'float'          => [3.14, 3.14, 3, 3.14, true, '3.14'],
            'zero float'     => [0.0, 0.0, 0, 0.0, false, '0'],
            'true'           => [true, true, 1, 1.0, true, '1'],
            'false'          => [false, false, 0, 0.0, false, ''],
            'null'           => [null, null, 0, 0.0, false, ''],
            'numeric string' => ['42.5', '42.5', 42, 42.5, true, '42.5'],
        ];
    }

    public function testStringDoesNotHtmlEncode(): void
    {
        $this->assertSame('<b>&amp;</b>', SmartString::new('<b>&amp;</b>')->string());
    }

    //endregion
    //region getRawValue()

    public function testGetRawValueUnwrapsEverySmartType(): void
    {
        $this->assertSame('x', SmartString::getRawValue(SmartString::new('x')));
        $this->assertSame(['a' => 1], SmartString::getRawValue(SmartArray::new(['a' => 1])));
        $this->assertSame(['a' => 1], SmartString::getRawValue(SmartArrayHtml::new(['a' => 1]))); // html-mode arrays unwrap too (SmartArrayBase check)
        $this->assertNull(SmartString::getRawValue(SmartArray::new([])->first())); // SmartNull
    }

    public function testGetRawValuePassesScalarsAndNullThrough(): void
    {
        $this->assertSame(5, SmartString::getRawValue(5));
        $this->assertSame(3.14, SmartString::getRawValue(3.14));
        $this->assertSame('text', SmartString::getRawValue('text'));
        $this->assertFalse(SmartString::getRawValue(false));
        $this->assertNull(SmartString::getRawValue(null));
    }

    public function testGetRawValueRecursesIntoArrays(): void
    {
        $input = [
            'plain' => 5,
            'smart' => SmartString::new('x'),
            'html'  => SmartArrayHtml::new(['a' => 1]),
            'deep'  => ['inner' => SmartString::new(null)],
        ];
        $expected = [
            'plain' => 5,
            'smart' => 'x',
            'html'  => ['a' => 1],
            'deep'  => ['inner' => null],
        ];
        $this->assertSame($expected, SmartString::getRawValue($input));
    }

    public function testGetRawValueRejectsUnknownObjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: stdClass');
        SmartString::getRawValue((object)['a' => 1]);
    }

    //endregion
    //region rawHtml()

    public function testRawHtmlReturnsUnencodedValue(): void
    {
        $this->assertSame('<b>Hi</b> & bye', SmartString::new('<b>Hi</b> & bye')->rawHtml());
        $this->assertSame(42, SmartString::new(42)->rawHtml());
        $this->assertNull(SmartString::new(null)->rawHtml());
    }

    //endregion
}
