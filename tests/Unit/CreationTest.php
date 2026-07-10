<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\Support\SmartStringTestCase;
use TypeError;

/**
 * __construct(), new(), and value().
 *
 * n/a dimensions: encoding (no output path), global settings, immutability
 * (nothing to mutate yet).
 */
class CreationTest extends SmartStringTestCase
{
    //region __construct()

    #[DataProvider('edgeScalarsProvider')]
    public function testConstructorStoresScalarUnchanged(string|int|float|bool|null $input): void
    {
        $this->assertSmartString($input, new SmartString($input));
    }

    public static function edgeScalarsProvider(): array
    {
        return array_map(static fn($value) => [$value], Fixtures::edgeScalars());
    }

    #[DataProvider('invalidConstructorInputProvider')]
    public function testConstructorRejectsNonScalars(mixed $input): void
    {
        $this->expectException(TypeError::class);
        new SmartString($input);
    }

    public static function invalidConstructorInputProvider(): array
    {
        return [
            'array'  => [[1, 2, 3]],
            'object' => [(object)['key' => 'value']],
        ];
    }

    //endregion
    //region new()

    public function testNewReturnsSmartStringForScalars(): void
    {
        foreach (Fixtures::edgeScalars() as $label => $value) {
            $this->assertSmartString($value, SmartString::new($value), "new() failed for: $label");
        }
    }

    public function testNewArrayReturnsSmartArrayHtmlWithDeprecation(): void
    {
        $rows = [
            [3, "O'Reilly & Sons", 3.12, null, true],
            [8, "<Web &nbsp; Shop/>", 4.56, false, null],
        ];

        $result = $this->expectDeprecationMessage(
            fn() => SmartString::new($rows),
            'Replace SmartString::new($array) with SmartArray::new($array)->asHtml()'
        );

        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertSame($rows, $result->toArray());
    }

    public function testNewRejectsObjects(): void
    {
        $this->expectException(TypeError::class);
        /** @var mixed $object phpdoc widens the type: the wrong-type call is the test */
        $object = (object)['key' => 'value'];
        SmartString::new($object);
    }

    //endregion
    //region value()

    public function testValueReturnsOriginalTypeAndValue(): void
    {
        $this->assertSame("G'day <b>World</b>!", SmartString::new("G'day <b>World</b>!")->value());
        $this->assertSame(0, SmartString::new(0)->value());
        $this->assertSame(false, SmartString::new(false)->value());
        $this->assertNull(SmartString::new(null)->value());
    }

    //endregion
}
