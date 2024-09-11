<?php
/** @noinspection UnknownInspectionInspection */
/** @noinspection PhpUnused */
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
     * @param int|float|string $fallback
     * @return int|float|string
     */
    public static function or(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string
    {
        $isZero        = is_numeric($value) && (float)$value === 0.0;
        $useFallback   = $isZero || empty($value);
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;

        return $useFallback ? $fallbackValue : $value;
    }

    public static function ifNull(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string
    {
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $value ?? $fallbackValue;
    }

    public static function ifBlank(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string|null
    {
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $value === "" ? $fallbackValue : $value;
    }
    public static function isZero(int|float|string|null $value, int|float|string|SmartString $fallback): int|float|string|null
    {
        $isZero        = is_numeric($value) && (float)$value === 0.0;
        $fallbackValue = $fallback instanceof SmartString ? $fallback->value() : $fallback;
        return $isZero ? $fallbackValue : $value;
    }
}
