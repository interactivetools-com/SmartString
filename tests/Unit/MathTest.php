<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * add(), subtract(), multiply(), divide(), percent(), percentOf().
 *
 * The contract: results are float (or null), asserted with assertSame
 * float literals. The one sanctioned exception is assertEqualsWithDelta for
 * genuine float-precision cases (stated epsilon 1e-9).
 *
 * n/a dimensions: encoding (math transforms the raw value).
 */
class MathTest extends SmartStringTestCase
{
    //region Float Contract

    public function testMathAlwaysReturnsFloat(): void
    {
        $this->assertSame(8.0, SmartString::new(5)->add(3)->value());
        $this->assertSame(5.0, SmartString::new(8)->subtract(3)->value());
        $this->assertSame(15.0, SmartString::new(5)->multiply(3)->value());
        $this->assertSame(5.0, SmartString::new(10)->divide(2)->value());
        $this->assertSame(50.0, SmartString::new('100')->percentOf(200)->float()); // numeric strings too
    }

    //endregion
    //region add()

    #[DataProvider('addProvider')]
    public function testAdd($input, $addend, ?float $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->add($addend));
    }

    public static function addProvider(): array
    {
        return [
            'positive integers'   => [5, 3, 8.0],
            'negative integers'   => [-5, -3, -8.0],
            'mixed signs'         => [5, -3, 2.0],
            'adding zero'         => [10, 0, 10.0],
            'adding to zero'      => [0, 10, 10.0],
            'int max overflows'   => [PHP_INT_MAX, 1, (float)PHP_INT_MAX + 1],
            'SmartString addend'  => [5, new SmartString(3), 8.0],
            'numeric with spaces' => [' 5 ', 1, 6.0],
            'exponent string'     => ['1e3', 1, 1001.0],
        ];
    }

    public function testAddFloatPrecision(): void
    {
        $this->assertEqualsWithDelta(8.8, SmartString::new(5.5)->add(3.3)->value(), 1e-9);
    }

    //endregion
    //region subtract()

    #[DataProvider('subtractProvider')]
    public function testSubtract($input, $subtrahend, ?float $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->subtract($subtrahend));
    }

    public static function subtractProvider(): array
    {
        return [
            'positive integers'      => [8, 3, 5.0],
            'negative integers'      => [-8, -3, -5.0],
            'mixed signs'            => [5, -3, 8.0],
            'subtracting zero'       => [10, 0, 10.0],
            'subtracting from zero'  => [0, 10, -10.0],
            'int max overflows'      => [PHP_INT_MAX, -1, (float)PHP_INT_MAX + 1],
            'SmartString subtrahend' => [8, new SmartString(3), 5.0],
        ];
    }

    public function testSubtractFloatPrecision(): void
    {
        $this->assertEqualsWithDelta(5.5, SmartString::new(8.8)->subtract(3.3)->value(), 1e-9);
    }

    //endregion
    //region multiply()

    #[DataProvider('multiplyProvider')]
    public function testMultiply($input, $multiplier, ?float $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->multiply($multiplier));
    }

    public static function multiplyProvider(): array
    {
        return [
            'positive integers'      => [5, 3, 15.0],
            'negative integers'      => [-5, -3, 15.0],
            'mixed signs'            => [5, -3, -15.0],
            'float by int'           => [5.5, 2, 11.0],
            'multiply by zero'       => [10, 0, 0.0],
            'multiply by one'        => [10, 1, 10.0],
            'int max doubles'        => [PHP_INT_MAX, 2, (float)PHP_INT_MAX * 2],
            'SmartString multiplier' => [5, new SmartString(3), 15.0],
        ];
    }

    public function testMultiplyOverflowsToInf(): void
    {
        $this->assertInfinite(SmartString::new(1.7976931348623157e+308)->multiply(2)->value());
        $negative = SmartString::new(-1.7976931348623157e+308)->multiply(2)->value();
        $this->assertInfinite($negative);
        $this->assertLessThan(0, $negative);
    }

    //endregion
    //region divide()

    #[DataProvider('divideProvider')]
    public function testDivide($input, $divisor, ?float $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->divide($divisor));
    }

    public static function divideProvider(): array
    {
        return [
            'positive integers'      => [10, 2, 5.0],
            'negative integers'      => [-10, -2, 5.0],
            'mixed signs'            => [10, -2, -5.0],
            'repeating decimal'      => [10, 3, 3.3333333333333335],
            'divide by one'          => [10, 1, 10.0],
            'divide by negative one' => [10, -1, -10.0],
            'zero numerator'         => [0, 5, 0.0],
            'divide by zero'         => [10, 0, null],
            'divide by float zero'   => [10, 0.0, null],
            'divide by string zero'  => [10, '0', null],
            'SmartString divisor'    => [800, new SmartString(25), 32.0],
        ];
    }

    //endregion
    //region Null and Non-Numeric Propagation

    public function testNullPropagatesThroughMath(): void
    {
        // null as left operand
        $this->assertNull(SmartString::new(null)->add(5)->value());
        $this->assertNull(SmartString::new(null)->subtract(5)->value());
        $this->assertNull(SmartString::new(null)->multiply(5)->value());
        $this->assertNull(SmartString::new(null)->divide(5)->value());
        $this->assertNull(SmartString::new(null)->percent()->value());
        $this->assertNull(SmartString::new(null)->percentOf(100)->value());

        // null as right operand
        $this->assertNull(SmartString::new(10)->add(SmartString::new(null))->value());
        $this->assertNull(SmartString::new(10)->subtract(SmartString::new(null))->value());
        $this->assertNull(SmartString::new(10)->multiply(SmartString::new(null))->value());
        $this->assertNull(SmartString::new(10)->divide(SmartString::new(null))->value());
        $this->assertNull(SmartString::new(50)->percentOf(SmartString::new(null))->value());
    }

    public function testNonNumericValuesBecomeNull(): void
    {
        $this->assertNull(SmartString::new('abc')->add(5)->value());
        $this->assertNull(SmartString::new(10)->subtract(SmartString::new('abc'))->value());
        $this->assertNull(SmartString::new('abc')->multiply(5)->value());
        $this->assertNull(SmartString::new('abc')->divide(5)->value());
        $this->assertNull(SmartString::new('abc')->percent()->value());
        $this->assertNull(SmartString::new('abc')->percentOf(100)->value());

        // formatted numbers with commas are non-numeric
        $this->assertNull(SmartString::new(10)->add(SmartString::new('1,234'))->value());
        $this->assertNull(SmartString::new(7)->add(SmartString::new('3,000'))->add(1)->value());
    }

    /**
     * The shared argument matrix through add(): every shape is accepted (no
     * TypeError); bools and non-numeric strings are non-numeric, so the
     * result is null.
     */
    #[DataProvider('mathArgumentProvider')]
    public function testAddAcceptsEveryArgumentShape($argument, ?float $expected): void
    {
        $this->assertSmartString($expected, SmartString::new(10)->add($argument));
    }

    public static function mathArgumentProvider(): array
    {
        return [
            'null'             => [null, null],
            'false'            => [false, null],
            'true'             => [true, null],
            'int'              => [42, 52.0],
            'float'            => [3.5, 13.5],
            'numeric string'   => ['42', 52.0],
            'text string'      => ['text', null],
            'empty string'     => ['', null],
            'SmartString int'  => [new SmartString(5), 15.0],
            'SmartString null' => [new SmartString(null), null],
            'SmartNull'        => [new SmartNull(), null],
        ];
    }

    /**
     * The rescue contract: a failed operation returns null and or()/set()/
     * ifNull() rescue the chain - math works again afterward (no hidden
     * error flag survives the rescue).
     */
    public function testRescuedChainsCanDoMathAgain(): void
    {
        $this->assertNull(SmartString::new('abc')->add(5)->value());
        $this->assertSame(15.0, SmartString::new('abc')->add(5)->or(10)->add(5)->value());
        $this->assertSame(15.0, SmartString::new('abc')->add(5)->set(10)->add(5)->value());
        $this->assertSame(15.0, SmartString::new(null)->add(5)->ifNull(10)->add(5)->value());
    }

    //endregion
    //region percent()

    #[DataProvider('percentProvider')]
    public function testPercent($input, int $decimals, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->percent($decimals));
    }

    public static function percentProvider(): array
    {
        return [
            'half'               => [0.5, 0, '50%'],
            'quarter'            => [0.25, 0, '25%'],
            'whole'              => [1.0, 1, '100.0%'],
            'with decimals'      => [0.4567, 2, '45.67%'],
            'zero'               => [0, 0, '0%'],
            'greater than one'   => [1.5, 0, '150%'],
            'negative'           => [-0.5, 0, '-50%'],
            'small number'       => [0.00000001, 8, '0.00000100%'],
            'many decimals'      => [0.333333333333, 10, '33.3333333333%'],
            'large number'       => [1000000, 2, '100,000,000.00%'],
            'null'               => [null, 0, null],
            'non-numeric string' => ['abc', 0, null],
        ];
    }

    /**
     * percent()/percentOf() honor the global separator
     * settings like numberFormat() does.
     */
    public function testPercentHonorsGlobalSeparators(): void
    {
        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = '.';

        $this->assertSame('1.234.567,80%', SmartString::new(12345.678)->percent(2)->value());
        $this->assertSame('1.234.567,00%', SmartString::new(1234567)->percentOf(100, 2)->value());
    }

    /**
     * The zero rule is a parameter ($ifZero), not a chain link, because a
     * chained ->ifZero() can't detect zero after formatting (percent() has
     * already made it "0.00%").
     */
    public function testPercentIfZeroParameter(): void
    {
        $result = $this->assertNoOutput(fn() => SmartString::new(0)->percent(2, ifZero: 'N/A'));
        $this->assertSmartString('N/A', $result);
    }

    /**
     * A SmartString $ifZero unwraps to its raw value like every other value
     * parameter - the fallback must not arrive HTML-encoded (double-encodes on
     * output) or throw TypeError under strict_types.
     */
    public function testPercentIfZeroAcceptsSmartString(): void
    {
        $this->assertSame('Tom & Co', SmartString::new(0)->percent(2, ifZero: SmartString::new('Tom & Co'))->value());
    }

    public function testPercentIfZeroOnlyAppliesToZero(): void
    {
        $this->assertSmartString(0, SmartString::new(0)->percent(2, 0));              // numeric fallback keeps its type
        $this->assertSmartString('50.00%', SmartString::new(0.5)->percent(2, 'N/A')); // non-zero ignores fallback
        $this->assertSmartString(null, SmartString::new(null)->percent(2, 'N/A'));    // null stays null, fallback is for zero only
    }

    //endregion
    //region percentOf()

    #[DataProvider('percentOfProvider')]
    public function testPercentOf($input, $total, int $decimals, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->percentOf($total, $decimals));
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
            'SmartString total' => [75, new SmartString(150.555555), 2, '49.82%'],
        ];
    }

    //endregion
    //region Immutability

    public function testMathIsImmutable(): void
    {
        $original = SmartString::new(10);
        $original->add(5)->multiply(2);
        $this->assertSame(10, $original->value());
    }

    //endregion
}
