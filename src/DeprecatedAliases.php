<?php
declare(strict_types=1);

namespace Itools\SmartString;

use Itools\SmartArray\SmartNull;
use JetBrains\PhpStorm\Deprecated;

/**
 * Old method names, kept working forever as silent one-line stubs.
 *
 * No runtime notices: the #[Deprecated] attribute gives PHPStorm a strikethrough
 * and a one-click rewrite to the new name, and static analyzers report usages via
 * each method's deprecated docblock tag. PHP ignores attributes whose class isn't
 * loaded, so there is no runtime dependency.
 */
trait DeprecatedAliases
{

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
