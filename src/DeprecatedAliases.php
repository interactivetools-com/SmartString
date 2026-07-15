<?php
declare(strict_types=1);

namespace Itools\SmartString;

use Itools\SmartArray\SmartNull;
use JetBrains\PhpStorm\Deprecated;

/**
 * Old method names and retired methods, kept working forever.
 *
 * No runtime notices: the #[Deprecated] attribute gives PHPStorm a strikethrough
 * and a one-click rewrite to the new name, and static analyzers report usages via
 * each method's deprecated docblock tag. PHP ignores attributes whose class isn't
 * loaded, so there is no runtime dependency.
 */
trait DeprecatedAliases
{
    /**
     * Default format for dateTimeFormat() (for PHP date()).
     */
    public static string $dateTimeFormat = 'Y-m-d H:i:s';

    /**
     * Format rules for phoneFormat(), keyed by digit count.
     */
    public static array $phoneFormat = [
        ['digits' => 10, 'format' => '(###) ###-####'],
        ['digits' => 11, 'format' => '# (###) ###-####'],
    ];

    /**
     * @deprecated Use append() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to append() in v3.0', replacement: '%class%->append(%parametersList%)')]
    public function and(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        return $this->append($value);
    }

    /**
     * @deprecated Use prepend() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to prepend() in v3.0', replacement: '%class%->prepend(%parametersList%)')]
    public function andPrefix(int|float|string|bool|null|SmartString|SmartNull $prefix): SmartString
    {
        return $this->prepend($prefix);
    }

    /**
     * @deprecated Use map() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to map() in v3.0', replacement: '%class%->map(%parametersList%)')]
    public function apply(callable|string $func, mixed ...$args): SmartString
    {
        return $this->map($func, ...$args);
    }

    /**
     * Same as dateFormat() except the default format comes from
     * SmartString::$dateTimeFormat instead of SmartString::$dateFormat.
     *
     * Retired: still supported, no longer documented. Pass the format to
     * dateFormat() instead - inline or as an app-wide constant, e.g.
     * ->dateFormat('Y-m-d H:i:s') or ->dateFormat(DATETIME_FORMAT).
     *
     * @deprecated Retired in v3.0 - use dateFormat() and pass the format
     */
    #[Deprecated(reason: 'retired in v3.0 - use dateFormat() and pass the format')]
    public function dateTimeFormat(?string $format = null): SmartString
    {
        return $this->dateFormat($format ?? self::$dateTimeFormat);
    }

    /**
     * @deprecated Use ifTrue() - same behavior, new name
     */
    #[Deprecated(reason: 'renamed to ifTrue() in v3.0', replacement: '%class%->ifTrue(%parametersList%)')]
    public function if(string|int|float|bool|null|SmartString|SmartNull $condition, string|int|float|bool|null|SmartString|SmartNull $valueIfTrue): SmartString
    {
        return $this->ifTrue($condition, $valueIfTrue);
    }

    /**
     * Replaces value only if it's an empty string ("") - strictly "", not null.
     *
     * Retired: use or() when null and "" should both be replaced (the usual intent),
     * or ifEquals('', ...) when blank specifically should be (note ifEquals is loose,
     * so it also matches null and false).
     *
     * @deprecated Use or() for missing values, or ifEquals('', ...) for blank
     */
    #[Deprecated(reason: 'retired in v3.0 - use or() for missing values, or ifEquals("", ...) for blank')]
    public function ifBlank(int|float|string|bool|null|SmartString|SmartNull $fallback): SmartString
    {
        $newValue = $this->rawData === "" ? SmartString::getRawValue($fallback) : $this->rawData;
        return new SmartString($newValue);
    }

    /**
     * Formats phone numbers using the SmartString::$phoneFormat rules, chosen by
     * digit count (defaults cover North-America 10 and 11 digit numbers). Non-digits
     * are stripped first; unsupported digit counts return null.
     *
     * Retired: still supported, no longer documented. Digit count is the only rule
     * selector, so formats that vary within one digit count (UK groupings differ by
     * area code) can only be approximated; pregReplace() covers custom needs, e.g.
     * ->pregReplace('/\D/', '') for tel: links.
     *
     * @deprecated Retired in v3.0 - still works; see pregReplace() for custom formatting
     */
    #[Deprecated(reason: 'retired in v3.0 - still supported, no longer documented')]
    public function phoneFormat(): SmartString
    {
        $newValue = null;

        // get array of digits only ('' check: str_split('') returns [''] on PHP 8.1 but [] on 8.2+)
        $digitsOnly = preg_replace('/\D/', '', (string)$this->rawData);
        $digits     = $digitsOnly === '' ? [] : str_split($digitsOnly);

        // get phone format by number of digits, e.g., 10 => '(###) ###-####'
        $phoneFormatByDigits = array_column(self::$phoneFormat, 'format', 'digits');
        $phoneFormat         = $phoneFormatByDigits[count($digits)] ?? null;

        // Replace # with digits
        if ($phoneFormat) {
            $format   = str_replace('#', '%s', $phoneFormat);
            $newValue = sprintf($format, ...$digits);
        }

        return new SmartString($newValue);
    }

    /**
     * Same as nl2br() when $keepBr is false (the default); kept so code written for
     * v2.6-v2.7 keeps working.
     *
     * keepBr: true preserves existing <br> tags instead of converting newlines (for
     * CMS text fields that already store line breaks as <br> tags). It stays available
     * only here and has no nl2br() equivalent.
     *
     * @deprecated Use nl2br() - same string output; keepBr: true stays available only here
     */
    #[Deprecated(reason: 'renamed to nl2br() in v3.0; keepBr: true has no replacement and keeps working here')]
    public function textToHtml(bool $keepBr = false): string
    {
        return $keepBr
            ? preg_replace('|&lt;(br\s*/?)&gt;|i', "<$1>", htmlspecialchars((string)$this->rawData, self::HTML_ENCODE_FLAGS, 'UTF-8'))
            : $this->nl2br();
    }

}
