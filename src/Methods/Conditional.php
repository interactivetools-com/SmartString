<?php
declare(strict_types=1);

namespace Itools\SmartString\Methods;

use Itools\SmartString\SmartString;

/**
 * Conditional methods for SmartString class.
 */
class Conditional
{
    /**
     * Returns new value if the current value is falsy, e.g., null, false, empty string, or zero (or "0.0")
     *
     * @param int|float|string|null $value
     * @param int|float|string|SmartString $fallback
     * @return int|float|string
     */
    public static function or(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string
    {
        $isZero        = is_numeric($value) && (float)$value === 0.0;
        $useFallback   = $isZero || empty($value);
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;

        return $useFallback ? $fallbackValue : $value;
    }

    /**
     * @param int|float|string|null $value
     * @param int|float|string|SmartString $fallback
     * @return int|float|string
     */
    public static function ifNull(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string
    {
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $value ?? $fallbackValue;
    }

    /**
     * @param int|float|string|null $value
     * @param int|float|string|SmartString $fallback
     * @return int|float|string|null
     */
    public static function ifBlank(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string|null
    {
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $value === "" ? $fallbackValue : $value;
    }

    /**
     * @param int|float|string|null $value
     * @param int|float|string|SmartString $fallback
     * @return int|float|string|null
     */
    public static function isZero(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string|null
    {
        $isZero        = is_numeric($value) && (float)$value === 0.0;
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $isZero ? $fallbackValue : $value;
    }

    /**
     * @param int|float|string|null $value
     * @param string|int|float|bool|null $condition
     * @param string|int|float|bool|object|null $valueIfTrue
     * @return string|int|float|bool|null
     */
    public static function if(int|float|string|null $value, string|int|float|bool|null $condition, string|int|float|bool|null|object $valueIfTrue): string|int|float|bool|null
    {
        if ($condition) {
            return is_callable([$valueIfTrue, 'value']) ? $valueIfTrue->value() : $valueIfTrue;
        }

        // otherwise, return the original unchanged value
        return $value;
    }

    /**
     * @param int|float|string|null $value
     * @param string|int|float|bool|object|null $newValue
     * @return string|int|float|bool|null
     * @noinspection PhpUnusedParameterInspection
     */
    public static function set(int|float|string|null $value, string|int|float|bool|null|object $newValue): string|int|float|bool|null // NOSONAR: Unused parameter $value
    {
        return is_callable([$newValue, 'value']) ? $newValue->value() : $newValue;
    }

}
