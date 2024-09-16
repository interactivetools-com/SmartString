<?php /** @noinspection PhpUnused */

declare(strict_types=1);

namespace Itools\SmartString\Methods;

use Itools\SmartString\SmartString;

/**
 * Numeric methods for SmartString class.
 */
class Numeric
{
    /**
     * Converts a number to a percentage. e.g., 0.1234 => 12.34%
     * Returns the Field object for chaining and null if the value is null.
     *
     * @param mixed $value
     * @param int $decimals
     * @return string|null
     */
    public static function percent(mixed $value, int $decimals = 0): string|null
    {
        return match (true) {
            is_numeric($value) => number_format($value * 100, $decimals) . '%',
            default            => null,
        };
    }

    /**
     * @param mixed $value
     * @param int|float|SmartString $total
     * @param int|null $decimals
     * @return string|null
     */
    public static function percentOf(mixed $value, int|float|SmartString $total, ?int $decimals = 0): string|null
    {
        $totalValue = $total instanceof SmartString ? $total->value() : $total;

        return match (true) {
            !is_numeric($value)        => null,
            !is_numeric($totalValue)   => null,
            (float)$totalValue === 0.0 => null, // avoid division by zero error
            default                    => number_format($value / $totalValue * 100, $decimals) . '%',
        };
    }

    /**
     * Adds a value to the current field value.  Returns null if the value is not numeric.
     *
     * @param int|float|null $value
     * @param int|float|SmartString $addend
     * @return int|float|null
     */
    public static function add(mixed $value, int|float|SmartString $addend): int|float|null
    {
        $addValue = $addend instanceof SmartString ? $addend->value() : $addend;

        return match (true) {
            !is_numeric($value)    => null,
            !is_numeric($addValue) => null,
            default                => $value + $addValue,
        };
    }

    /**
     * Subtracts a value from the current field value.
     *
     * @param int|float|string|null $value The value to subtract
     * @param int|float|SmartString $subtrahend
     * @return int|float|null
     */
    public static function subtract(mixed $value, int|float|SmartString $subtrahend): int|float|null
    {
        $subtractValue = $subtrahend instanceof SmartString ? $subtrahend->value() : $subtrahend;

        return match (true) {
            !is_numeric($value)         => null,
            !is_numeric($subtractValue) => null,
            default                     => $value - $subtractValue,
        };
    }

    /**
     * Multiplies the current field value by the given value.
     *
     * @param int|float|null $value The value to multiply by
     * @param int|float|SmartString $multiplier
     * @return int|float|null
     */
    public static function multiply(mixed $value, int|float|SmartString $multiplier): int|float|null
    {
        $multiplyValue = $multiplier instanceof SmartString ? $multiplier->value() : $multiplier;

        return match (true) {
            !is_numeric($value)         => null,
            !is_numeric($multiplyValue) => null,
            default                     => $value * $multiplyValue,
        };
    }

    /**
     * Divides the current field value by the given value.
     *
     * @param int|float|null $value
     * @param int|float|SmartString $divisor
     *
     * @return int|float|null
     */
    public static function divide(mixed $value, int|float|SmartString $divisor): int|float|null
    {
        $divisorValue = $divisor instanceof SmartString ? $divisor->value() : $divisor;

        return match (true) {
            !is_numeric($value)          => null,
            !is_numeric($divisorValue)   => null,
            (float)$divisorValue === 0.0 => null, // avoid division by zero error
            default                      => $value / $divisorValue,
        };
    }
}
