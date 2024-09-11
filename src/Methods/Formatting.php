<?php
/** @noinspection UnknownInspectionInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Itools\SmartString\Methods;

use Itools\SmartString\SmartString;

/**
 * Formatting methods for SmartString class.
 */
class Formatting
{

    /**
     * Formats a date using default or specified format.  Returns null on failure.
     *
     * @param int|float|string|null $value Timestamp or date string
     * @param string|null $format Date format (default: SmartString::$dateFormat)
     * @return string|null Formatted date or null on failure
     */
    public static function dateFormat(int|float|string|null $value, ?string $format = null): string|null
    {
        $format ??= SmartString::$dateFormat;

        $timestamp = match (true) {
            is_null($value)    => null,
            is_numeric($value) => (int)$value,
            default            => strtotime($value) ?: null,
        };

        return $timestamp ? date($format, $timestamp) : null; // return null on null or 0
    }

    /**
     * Format date by $dateTimeFormat or specified format.  Returns null on failure.
     *
     * @param int|float|string|null $value Timestamp or date string
     * @param string|null $format Date format (default: SmartString::$dateFormat)
     * @return string|null Formatted date or null on failure
     */
    public static function dateTimeFormat(int|float|string|null $value, ?string $format = null): string|null
    {
        $format ??= SmartString::$dateTimeFormat;
        return self::dateFormat($value, $format);
    }

    /**
     * Calls the number_format() function on the current value.
     *
     * @param int|float|string|null $value
     * @param mixed ...$args
     *
     * @return string|null
     */
    public static function numberFormat(int|float|string|null $value, ?int $decimals = 0): string|null
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format(
            num                : (float)$value,
            decimals           : $decimals,
            decimal_separator  : SmartString::$numberFormatDecimal,
            thousands_separator: SmartString::$numberFormatThousands,
        );
    }

    /**
     * Formats a phone number according to the specified format or returns null.
     *
     * @param string|int|null $value The input phone number
     * @return string|null The formatted phone number or null if input is invalid
     */
    public static function phoneFormat(string|int|null $value): ?string
    {
        // get array of digits only
        $digits = str_split(preg_replace('/\D/', '', (string)$value));

        // get phone format by number of digits, e.g., 10 => '(###) ###-####'
        $phoneFormatByDigits = array_column(SmartString::$phoneFormat, 'format', 'digits');
        $phoneFormat         = $phoneFormatByDigits[count($digits)] ?? null;

        // Replace # with digits
        if ($phoneFormat) {
            $format = str_replace('#', '%s', $phoneFormat);
            return sprintf($format, ...$digits);
        }

        return null;
    }
}
