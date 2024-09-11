<?php

/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

namespace Tests\Methods;

use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;

class NumericTest extends TestCase
{
    //region numberFormat

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
        ];
    }

    //endregion
    //region percent

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

    //endregion
    //region percentOf

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
            'double'            => [100, 50.000, 0, '200%'],
            'with decimals'     => [75, 150, 2, '50.00%'],
            'zero numerator'    => [0, 100, 0, '0%'],
            'zero denominator'  => [100, 0, 0, null],
            'SmartString input' => [75, new SmartString(150.555555), 2, '49.82%'],
        ];
    }

    //endregion
    //region add

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
            'positive integers'       => [5, 3, 8],
            'negative integers'       => [-5, -3, -8],
            'mixed signs'             => [5, -3, 2],
            'float result'            => [5.5, 3.3, 8.8],
            'adding zero'             => [10, 0, 10],
            'adding to zero'          => [0, 10, 10],
            'large numbers'           => [PHP_INT_MAX, 1, (float)PHP_INT_MAX + 1],
            'SmartString input'       => [5, new SmartString(3), 8],
        ];
    }

    //endregion
    //region subtract

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
            'positive integers'       => [8, 3, 5],
            'negative integers'       => [-8, -3, -5],
            'mixed signs'             => [5, -3, 8],
            'float result'            => [8.8, 3.3, 5.5],
            'subtracting zero'        => [10, 0, 10],
            'subtracting from zero'   => [0, 10, -10],
            'large numbers'           => [PHP_INT_MAX, -1, (float)PHP_INT_MAX + 1],
            'SmartString input'       => [8, new SmartString(3), 5],
        ];
    }

    //endregion
    //region multiply

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
            'positive integers'       => [5, 3, 15],
            'negative integers'       => [-5, -3, 15],
            'mixed signs'             => [5, -3, -15],
            'float result'            => [5.5, 2, 11],
            'multiply by zero'        => [10, 0, 0],
            'multiply by one'         => [10, 1, 10],
            'large numbers'           => [PHP_INT_MAX, 2, (float)PHP_INT_MAX * 2],
            'SmartString input'       => [5, new SmartString(3), 15],
        ];
    }


    //endregion
    //region divide

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

    //endregion
}
