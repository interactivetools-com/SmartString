<?php
declare(strict_types=1);

namespace Itools\SmartString;

use Error;
use Iterator;
use IteratorAggregate;
use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayBase;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartArray\SmartNull;
use JsonSerializable;
use RuntimeException;

/**
 * SmartString class provides a fluent interface for various string and numeric manipulations.
 *
 * Missing values (null or "") follow one rule per method group:
 * - String Manipulation methods pass them through unchanged
 * - Formatting and Numeric Operations return null
 * - Conditional Logic methods skip the operation or apply the fallback
 * - Encoding methods return "" (except jsonEncode, which returns valid JSON literals)
 *
 * For inline help, call $smartString->help() or print_r() on a SmartString object.
 */
final class SmartString implements JsonSerializable, IteratorAggregate
{
    use DeprecatedAliases;
    use ErrorHelpersTrait;

    /**
     * Flags for HTML-encoding output. ENT_DISALLOWED substitutes code points HTML5 forbids
     * (C1 controls, noncharacters) with � so they can't hide in page source.
     */
    private const HTML_ENCODE_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5;

    /**
     * Fast path: skip htmlspecialchars() when it would return the input unchanged.
     *
     * - Matches every byte htmlspecialchars() with HTML_ENCODE_FLAGS can alter:
     *   `"&'<>`, HTML5-disallowed control chars, DEL, and all non-ASCII bytes.
     * - Tab, LF, FF, CR are legal in HTML5 and deliberately excluded.
     * - Non-ASCII always fails the gate, so multibyte and invalid UTF-8 take the
     *   full encoder; the gate only ever skips work, never changes output.
     * - Why: most CMS fields are short plain ASCII. Measured on PHP 8.1-8.5 across
     *   5 platforms (.github/workflows/speed-matrix.yml): 15-80% faster on short
     *   fields, 5-8x on 1KB clean text, 13-20x on native Windows builds.
     * - If HTML_ENCODE_FLAGS changes, this pattern must change with it;
     *   tests/Unit/EncodingCorpusTest.php enforces the match byte-for-byte.
     */
    private const ENCODE_SKIP_REGEX = '/[\x00-\x08\x0B\x0E-\x1F"&\'<>\x7F-\xFF]/';

    /**
     * The raw stored value, exactly as passed to the constructor.
     *
     * Speed: no property type or readonly, on purpose.
     * - The constructor parameter already type-checks every incoming value; a property
     *   type would repeat that check at assignment. Skipping the re-check makes
     *   construction 3-20% faster across PHP 8.1-8.5 on all 5 platforms; see
     *   .github/scripts/speed-results.md (test: prop-type).
     * - PHP requires readonly properties to be typed, so readonly goes too.
     *   ImmutabilityTest covers the same guarantee: no method changes the stored
     *   value after construction.
     *
     * @noinspection PhpMissingFieldTypeInspection
     * @var string|int|float|bool|null
     */
    private $rawData;

    //region Global Settings

    // default formats
    public static string $numberFormatDecimal   = '.';            // numberFormat() default decimal separator
    public static string $numberFormatThousands = ',';            // numberFormat() default thousands separator
    public static string $dateFormat            = 'Y-m-d';        // dateFormat() default format (for PHP date())

    //endregion
    //region Core

    /**
     * Initializes a new SmartString object with the given value.
     *
     *     $value = new SmartString('<b>Hello World!</b>');
     *
     * @param string|int|float|bool|null $value The value to store
     */
    public function __construct(string|int|float|bool|null $value)
    {
        $this->rawData = $value;
    }

    /**
     * Returns a SmartString object for a value.
     *
     *     $str  = SmartString::new("Hello, World!");   // single value as SmartString
     *     $user = SmartArrayHtml::new($record);        // whole array as SmartStrings (companion library)
     *
     * @param string|int|float|bool|array|null $value
     * @return SmartArrayHtml|SmartString The newly created SmartString object.
     */
    public static function new(string|int|float|bool|null|array $value): SmartArrayHtml|SmartString
    {
        if (is_array($value)) {
            self::logDeprecation("Replace SmartString::new(\$array) with SmartArrayHtml::new(\$array)");
            return new SmartArrayHtml($value);
        }
        return new self($value);
    }

    /**
     * Returns original type and value.
     *
     * Missing values (null or "") return null or "" unchanged.
     *
     * @return string|int|float|bool|null
     */
    public function value(): string|int|float|bool|null
    {
        return $this->rawData;
    }

    /**
     * Converts Smart* objects to their original values, recursing into arrays; scalars and
     * null are returned as-is. Useful if you don't know the type but want the original
     * value. Any other type (objects, resources) throws CallerException.
     */
    public static function getRawValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self           => $value->value(),
            $value instanceof SmartArrayBase => $value->toArray(), // SmartArray and SmartArrayHtml
            $value instanceof SmartNull      => null,
            is_scalar($value)                => $value,
            is_null($value)                  => $value,
            is_array($value)                 => array_map([self::class, 'getRawValue'], $value), // for manually passed in arrays
            default                          => throw new CallerException("Unsupported value type: " . get_debug_type($value)),
        };
    }

    //endregion
    //region Type Conversion

    /**
     * Returns value as integer
     *
     * Missing values (null or "") return 0.
     */
    public function int(): int
    {
        return (int)$this->rawData;
    }

    /**
     * Returns value as float
     *
     * Missing values (null or "") return 0.0.
     */
    public function float(): float
    {
        return (float)$this->rawData;
    }

    /**
     * Returns value as boolean
     *
     * Missing values (null or "") return false.
     */
    public function bool(): bool
    {
        return (bool)$this->rawData;
    }

    /**
     * Returns value as string (doesn't HTML-encode, use ->htmlEncode() for HTML-encoded string)
     *
     * Missing values (null or "") return "".
     */
    public function string(): string
    {
        return (string)$this->rawData;
    }

    /**
     * Alias for value() - returns the unencoded, raw value.
     * This can be useful when you want to output trusted HTML content.
     *
     * Missing values (null or "") return null or "" unchanged - unlike the encoding methods, null does not become "".
     *
     * @return string|int|float|bool|null The raw, unencoded value
     */
    public function rawHtml(): string|int|float|bool|null
    {
        return $this->value();
    }

    //endregion
    //region Encoding

    /**
     * HTML-encodes the current value for safe output in an HTML context.
     *
     * Missing values (null or "") return "".
     *
     * @return string Html-encoded output
     */
    public function htmlEncode(): string
    {
        $text = (string)$this->rawData;
        if (!preg_match(self::ENCODE_SKIP_REGEX, $text)) {
            return $text; // speed: no byte that encoding would change, see ENCODE_SKIP_REGEX
        }
        return htmlspecialchars($text, self::HTML_ENCODE_FLAGS, 'UTF-8');
    }

    /**
     * HTML-encodes the value, then converts newlines to <br> tags. Returns a string.
     *
     * Unlike PHP's nl2br(), output is XSS-safe: the text is encoded first, so the
     * only tags in the result are the <br> tags this method adds.
     *
     *     echo "{$text->nl2br()}";    // "Bob & Sons\nSuite 5" → "Bob &amp; Sons<br>\nSuite 5"
     *
     * Missing values (null or "") return "".
     *
     * @return string HTML-safe output with newlines converted to <br> tags
     */
    public function nl2br(): string
    {
        $encoded = htmlspecialchars((string)$this->rawData, self::HTML_ENCODE_FLAGS, 'UTF-8');
        return nl2br($encoded, false);
    }

    /**
     * HTML-encodes the value and appends $html as-is, only when a value is present (not null
     * or ""), zero is considered present.
     *
     * Terminal: returns a plain string so nothing downstream can re-encode the markup.
     * The markup argument must be a literal you wrote - never pass user input.
     *
     *     echo $member->AddressLine1->appendHtml(",<br>\n");  // "12 High St,<br>\n", or "" when missing
     *
     * Missing values (null or "") return "" - nothing is appended.
     *
     * @param string $html Trusted markup, appended as-is (not encoded)
     * @return string HTML-safe output, or "" when the value is missing
     */
    public function appendHtml(string $html): string
    {
        if ($this->isMissing()) {
            return '';
        }
        return $this->htmlEncode() . $html;
    }

    /**
     * HTML-encodes the value and wraps it in $before/$after as-is, only when a value is
     * present (not null or ""), zero is considered present.
     *
     * Terminal: returns a plain string so nothing downstream can re-encode the markup.
     * Both sides are required: pass "" for a side you don't want. Best for single-tag
     * wrappers; keep the template if() for multi-element blocks. The markup arguments must
     * be literals you wrote - never pass user input.
     *
     *     echo $page->subheading->wrapHtml('<h2 class="lead">', '</h2>');  // whole <h2> vanishes when empty
     *
     * Missing values (null or "") return "" - the wrapper is not added.
     *
     * @param string $before Trusted markup placed before the encoded value (not encoded)
     * @param string $after  Trusted markup placed after the encoded value (not encoded)
     * @return string HTML-safe output, or "" when the value is missing
     */
    public function wrapHtml(string $before, string $after): string
    {
        if ($this->isMissing()) {
            return '';
        }
        return $before . $this->htmlEncode() . $after;
    }

    /**
     * URL encodes the current value for safe use within URLs.
     *
     *     echo "?offer={$offer->urlEncode()}";  // "Save 10%+ off" → "?offer=Save+10%25%2B+off"
     *
     * Invalid UTF-8 bytes are percent-encoded as-is (not substituted with � like
     * htmlEncode/jsonEncode) - urlencode() is byte-level by design.
     *
     * Missing values (null or "") return "".
     *
     * @return string The URL-encoded string.
     */
    public function urlEncode(): string
    {
        return urlencode((string)$this->rawData);
    }

    /**
     * Safely encodes the data as a JSON string with specific flags for secure and accurate JavaScript embedding.
     *
     * - Escapes " ' < > & as \uXXXX so values can't break out of a <script> block or JS string
     * - Substitutes malformed UTF-8 with � (U+FFFD) instead of throwing
     * - Re-escapes invisible Unicode (zero-width chars, bidi controls, variation selectors) as
     *   visible \uXXXX escapes so nothing can hide in page source
     * - INF, NAN, and recursion still throw JsonException - those are always code bugs
     *
     *     echo "<script>let title = {$title->jsonEncode()};</script>";
     *
     * Missing values (null or "") return `null` and `""` (quotes included) - valid JavaScript, never empty output.
     *
     * @return string The encoded JSON string, safely formatted for embedding in JavaScript.
     */
    public function jsonEncode(): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        $json  = json_encode($this->rawData, $flags);

        // Re-escape invisible Unicode (format chars, variation selectors) as visible \uXXXX escapes.
        // Lossless: each escape decodes back to the identical character, so the value JavaScript sees
        // never changes. json_encode of the single char (without JSON_UNESCAPED_UNICODE) produces the
        // correct escape, including surrogate pairs for chars above U+FFFF. The fast-path scan is
        // cheaper than preg_replace_callback's setup when there is nothing to replace (the common case).
        $invisibleRx = '/[\p{Cf}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]/u';
        if (preg_match($invisibleRx, $json)) {
            $json = preg_replace_callback($invisibleRx, fn($m) => substr(json_encode($m[0]), 1, -1), $json);
        }

        return $json;
    }

    //endregion
    //region String Manipulation

    /**
     * Decodes HTML entities, removes tags, and trims whitespace. Entities are decoded
     * first so entity-encoded tags (&lt;script&gt;) are removed too.
     *
     * Only "<" followed by a letter, "/", "!", or "?" counts as a tag (the same rule
     * browsers use), so prose like "Kids <12 eat free" or "I <3 PHP" keeps its "<".
     *
     * Missing values (null or "") pass through unchanged.
     */
    public function textOnly(): SmartString
    {
        if ($this->isMissing()) {
            return new self($this->rawData);
        }

        $prose = "\xEE\x80\x80"; // U+E000 (private use), restored to "<" after strip_tags
        $text  = html_entity_decode((string)$this->rawData, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $text  = preg_replace('/<(?![a-zA-Z\/!?])/', $prose, $text); // hide prose "<" so strip_tags doesn't delete from it to the next ">" or end of string (no /u: byte-level is UTF-8-safe here and never fails on bad bytes)
        $text  = str_replace($prose, '<', strip_tags($text));

        return new self(trim($text));
    }


    /**
     * Trim leading and trailing whitespace, supports same parameters as PHP trim()
     *
     * Missing values (null or "") pass through unchanged.
     */
    public function trim(...$args): SmartString
    {
        if ($this->isMissing()) {
            return new self($this->rawData);
        }

        $args = array_map(self::getRawValue(...), $args); // accept a SmartString char list, like every other value parameter
        return new self(trim((string)$this->rawData, ...$args));
    }

    /**
     * Limit words to $max, if truncated adds ... (override with second parameter).
     * Invalid UTF-8 bytes are substituted with � (like htmlEncode/jsonEncode).
     *
     * Missing values (null or "") pass through unchanged.
     */
    public function maxWords(int $max, string $ellipsis = "..."): SmartString
    {
        if ($this->isMissing()) {
            return new self($this->rawData);
        }

        $text     = trim(self::substituteInvalidUtf8((string)$this->rawData));
        $words    = preg_split('/\s+/u', $text);
        $newValue = implode(' ', array_slice($words, 0, $max));
        if (count($words) > $max) {
            $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Strip trailing Unicode punctuation before ellipsis
            $newValue .= $ellipsis;
        }

        return new self($newValue);
    }

    /**
     * Limit chars to $max breaking at the last whole word, if truncated adds ... (override
     * with second parameter). Whitespace runs collapse to single spaces before measuring.
     * Invalid UTF-8 bytes are substituted with � (like htmlEncode/jsonEncode).
     *
     * Missing values (null or "") pass through unchanged.
     */
    public function maxChars(int $max, string $ellipsis = "..."): SmartString
    {
        if ($this->isMissing()) {
            return new self($this->rawData);
        }

        $text = preg_replace('/\s+/u', ' ', trim(self::substituteInvalidUtf8((string)$this->rawData)));

        if (mb_strlen($text, 'UTF-8') <= $max) {
            $newValue = $text;
        } elseif ($max > 0 && preg_match("/^.{1,$max}(?=\s|$)/u", $text, $matches)) {
            $newValue = $matches[0];
            $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Strip trailing Unicode punctuation before ellipsis
            $newValue .= $ellipsis;
        } else {
            $newValue = mb_substr($text, 0, $max, 'UTF-8') . $ellipsis;
        }

        return new self($newValue);
    }

    //endregion
    //region Formatting Operations

    /**
     * Formats a date using default or specified format.
     *
     * Numeric values are treated as Unix timestamps (so '2024' formats as epoch + 2024
     * seconds, not as a year); everything else is parsed with strtotime().
     *
     * Missing values (null or "") and unparseable dates return null.
     *
     * @param string|null $format Date format (default: SmartString::$dateFormat)
     * @return SmartString Formatted date or null on failure
     */
    public function dateFormat(?string $format = null): SmartString
    {
        $format    ??= self::$dateFormat;
        $timestamp = match (true) {
            is_null($this->rawData)    => null,
            is_bool($this->rawData)    => null,
            is_numeric($this->rawData) => (int)$this->rawData,
            default                    => strtotime($this->rawData), // int|false; is_int() below keeps timestamp 0 (the epoch) formatting
        };

        $newValue = is_int($timestamp) ? date($format, $timestamp) : null;

        return new self($newValue);
    }

    /**
     * Formats a numeric value using number_format() function with configurable decimal places.
     * Uses the static properties $numberFormatDecimal and $numberFormatThousands for formatting.
     *
     *     $num = SmartString::new(1234.56);
     *     echo $num->numberFormat();     // "1,235" (rounded to 0 decimals)
     *     echo $num->numberFormat(2);    // "1,234.56" (2 decimal places)
     *
     * Missing values (null or "") and non-numeric values return null.
     *
     * @param int $decimals Number of decimal places to display (default: 0)
     * @return SmartString Formatted number or null if not numeric
     */
    public function numberFormat(int $decimals = 0): SmartString
    {
        $newValue = match (true) {
            !is_numeric($this->rawData) => null,
            default                     => number_format((float)$this->rawData, $decimals, self::$numberFormatDecimal, self::$numberFormatThousands),
        };
        return new self($newValue);
    }

    //endregion
    //region Numeric Operations

    /**
     * Converts a number to a percentage. Support optional decimal places and fallback value for zero
     * - If value is zero AND $ifZero is defined, $ifZero is returned
     * - Otherwise a percentage is returned. e.g., 0.1234 => 12.34%
     *
     * The zero rule is a parameter ($ifZero), not a chain link, because a chained
     * ->ifZero() can't detect zero after formatting (percent() has already made it "0.00%").
     *
     *     $val = SmartString::new(0.1234);
     *     echo $val->percent(2);                   // "12.34%"
     *
     *     $zero = SmartString::new(0);
     *     echo $zero->percent(2);                  // "0.00%"
     *     echo $zero->percent(2, ifZero: "None");  // "None"
     *
     * Missing values (null or "") and non-numeric values return null.
     *
     * @param int                                              $decimals Number of decimal places in formatted output
     * @param string|int|float|bool|null|SmartString|SmartNull $ifZero   Optional fallback returned when the value is zero
     * @return SmartString Formatted percentage, or null if not numeric
     */
    public function percent(int $decimals = 0, string|int|float|bool|null|SmartString|SmartNull $ifZero = null): SmartString
    {
        $value    = self::getFloatOrNull($this->rawData);
        $ifZero   = self::getRawValue($ifZero);
        $newValue = match (true) {
            is_null($value)                     => null,
            $value === 0.0 && !is_null($ifZero) => $ifZero,
            default                             => number_format($value * 100, $decimals, self::$numberFormatDecimal, self::$numberFormatThousands) . '%',
        };
        return new self($newValue);
    }

    /**
     * Returns the current value as a percentage of $total, e.g., 24 of 100 becomes 24%.
     * Both sides must be numeric; a zero $total returns null (no division by zero).
     *
     * Missing values (null or "") and non-numeric values return null.
     */
    public function percentOf(int|float|string|bool|null|SmartString|SmartNull $total, int $decimals = 0): SmartString
    {
        $left     = self::getFloatOrNull($this->rawData);
        $right    = self::getFloatOrNull($total);
        $newValue = (is_null($left) || is_null($right) || $right === 0.0)
            ? null
            : number_format($left / $right * 100, $decimals, self::$numberFormatDecimal, self::$numberFormatThousands) . '%';
        return new self($newValue);
    }

    /**
     * Adds $value to the current value. Both sides must be numeric. Result is a float.
     *
     * Missing values (null or "") and non-numeric values return null.
     */
    public function add(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $left     = self::getFloatOrNull($this->rawData);
        $right    = self::getFloatOrNull($value);
        $newValue = (is_null($left) || is_null($right)) ? null : $left + $right;
        return new self($newValue);
    }

    /**
     * Subtracts $value from the current value. Both sides must be numeric. Result is a float.
     *
     * Missing values (null or "") and non-numeric values return null.
     */
    public function subtract(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $left     = self::getFloatOrNull($this->rawData);
        $right    = self::getFloatOrNull($value);
        $newValue = (is_null($left) || is_null($right)) ? null : $left - $right;
        return new self($newValue);
    }

    /**
     * Multiplies the current value by $value. Both sides must be numeric. Result is a float.
     *
     * Missing values (null or "") and non-numeric values return null.
     */
    public function multiply(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $left     = self::getFloatOrNull($this->rawData);
        $right    = self::getFloatOrNull($value);
        $newValue = (is_null($left) || is_null($right)) ? null : $left * $right;
        return new self($newValue);
    }

    /**
     * Divides the current value by $value. Both sides must be numeric; dividing by zero returns null. Result is a float.
     *
     * Missing values (null or "") and non-numeric values return null.
     */
    public function divide(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $left     = self::getFloatOrNull($this->rawData);
        $right    = self::getFloatOrNull($value);
        $newValue = (is_null($left) || is_null($right) || $right === 0.0) ? null : $left / $right;
        return new self($newValue);
    }

    //endregion
    //region Conditional Logic

    /**
     * Replaces value if missing (null or ""), zero is not considered missing
     */
    public function or(int|float|string|bool|null|SmartString|SmartNull $fallback): SmartString
    {
        $newValue = $this->isMissing() ? self::getRawValue($fallback) : $this->rawData;
        return new self($newValue);
    }

    /**
     * Appends $value if present (not null or ""), zero is considered present
     *
     * A false value counts as present but converts to "", so only the appended value appears.
     *
     * Missing values (null or "") pass through unchanged - nothing is appended.
     */
    public function append(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $newValue = $this->rawData;
        if (!$this->isMissing()) {
            $newValue .= self::getRawValue($value);
        }
        return new self($newValue);
    }

    /**
     * Prepends $value if present (not null or ""), zero is considered present
     *
     * A false value counts as present but converts to "", so only the prepended value appears.
     *
     * Missing values (null or "") pass through unchanged - nothing is prepended.
     */
    public function prepend(int|float|string|bool|null|SmartString|SmartNull $value): SmartString
    {
        $newValue = $this->rawData;
        if (!$this->isMissing()) {
            $newValue = self::getRawValue($value) . $newValue;
        }
        return new self($newValue);
    }

    /**
     * Wraps the value in $before and $after if present (not null or ""), zero is considered present
     *
     * Both sides are required: pass "" for a side you don't want, or use
     * prepend()/append() for one-sided text.
     *
     *     $price->wrap('(', ')');            // "(19.99)"; missing stays missing
     *     $label->wrap('[', ']')->or('none'); // "[Sale]", or "none" when missing
     *
     * Missing values (null or "") pass through unchanged - the wrapper is not added.
     */
    public function wrap(int|float|string|bool|null|SmartString|SmartNull $before, int|float|string|bool|null|SmartString|SmartNull $after): SmartString
    {
        $newValue = $this->rawData;
        if (!$this->isMissing()) {
            $newValue = self::getRawValue($before) . $newValue . self::getRawValue($after);
        }
        return new self($newValue);
    }

    /**
     * Replaces value only if it's null or undefined
     *
     * Missing values (null or "") split here: null is replaced, "" passes through.  Use or() to replace both.
     */
    public function ifNull(int|float|string|bool|null|SmartString|SmartNull $fallback): SmartString
    {
        $newValue = $this->rawData ?? self::getRawValue($fallback);
        return new self($newValue);
    }

    /**
     * Replaces value only if it's numeric zero (0, 0.0, "0", "0.00", "-0" - any is_numeric zero);
     * non-numeric values never match
     *
     * Missing values (null or "") never match and pass through unchanged.
     */
    public function ifZero(int|float|string|bool|null|SmartString|SmartNull $fallback): SmartString
    {
        $isZero   = is_numeric($this->rawData) && (float)$this->rawData === 0.0;
        $newValue = $isZero ? self::getRawValue($fallback) : $this->rawData;
        return new self($newValue);
    }

    /**
     * Replaces the value with $newValue when $condition is truthy
     *
     * The condition is a plain value you computed, not a callback. This replaces the
     * VALUE only - it does not gate the rest of the chain.
     *
     *     $eggs->ifTrue($eggs->int() >= 12, 'Full Carton');
     */
    public function ifTrue(string|int|float|bool|null|SmartString|SmartNull $condition, string|int|float|bool|null|SmartString|SmartNull $newValue): SmartString
    {
        $newValue = self::getRawValue($condition) ? self::getRawValue($newValue) : $this->rawData;
        return new self($newValue);
    }

    /**
     * Replaces the value with $newValue when the value loosely equals $match (==)
     *
     * Loose comparison so "5" matches 5 - database numbers often arrive as strings.
     * For null use ifNull() instead: PHP's null == 0, null == "", and null == false
     * are all true, so ifEquals(null) would match far more than null.
     *
     *     $date->ifEquals('0000-00-00', null)->dateFormat('M j, Y')->or('Not set');
     *     $plan->max_users->ifEquals(-1, 'Unlimited');  // fires on -1 and "-1"
     */
    public function ifEquals(string|int|float|bool|null|SmartString|SmartNull $match, string|int|float|bool|null|SmartString|SmartNull $newValue): SmartString
    {
        $isMatch  = $this->rawData == self::getRawValue($match);
        $newValue = $isMatch ? self::getRawValue($newValue) : $this->rawData;
        return new self($newValue);
    }

    /**
     * Replaces the value with $newValue, useful for storing an expression result.
     *
     *     $label = $count->set(match($count->int()) { 0 => "No items", default => "$count items" });
     */
    public function set(string|int|float|bool|null|SmartString|SmartNull $newValue): SmartString
    {
        $newValue = self::getRawValue($newValue);
        return new self($newValue);
    }

    //endregion
    //region Validation

    /**
     * Returns true if the value is empty ("", null, false, 0, "0"), uses PHP empty()
     *
     * Zero IS empty here but is NOT missing to isMissing(), or(), append(),
     * prepend(), and wrap() - use isMissing() when a legitimate 0 must count as present.
     *
     * This is useful for conditionally showing blocks of HTML:
     *
     *     if ($value->isEmpty()) {
     *         echo "<p>No data available</p>";
     *     }
     */
    public function isEmpty(): bool
    {
        return empty($this->rawData);
    }

    /**
     * Returns true if the value has content - the exact opposite of isEmpty(), which
     * treats "", null, false, 0, and "0" as empty (PHP empty() semantics)
     *
     * This is useful for conditionally showing blocks of HTML:
     *
     *     if ($value->isNotEmpty()) {
     *         echo "<p>Value: $value</p>";
     *     }
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->rawData);
    }

    /**
     * Checks if the current value is missing (null or "")
     *
     * Zero is NOT missing here (it's a real value) but IS empty to isEmpty() -
     * or(), append(), prepend(), wrap(), and the or404/orDie/orThrow/orRedirect
     * guards all use this definition.
     *
     * @return bool True if the value is missing (null or "")
     */
    public function isMissing(): bool
    {
        return $this->rawData === null || $this->rawData === '';
    }

    /**
     * Returns true if the value is null
     *
     * This is useful for checking if a value is specifically null rather than just empty:
     *
     *     if ($value->isNull()) {
     *         echo "<p>Value is specifically NULL</p>";
     *     }
     *
     * @return bool True if the value is null
     */
    public function isNull(): bool
    {
        return $this->rawData === null;
    }

    //endregion
    //region Error Handling

    /**
     * Sends 404 header and exits if the current value is missing (null or ""), zero is not considered missing
     *
     * @param string|null $text Plain-text message; HTML-encoded automatically before output. Defaults to "The requested URL was not found on this server."
     */
    public function or404(?string $text = null): self
    {
        if (!$this->isMissing()) {
            return $this;
        }

        http_response_code(404);
        header("Content-Type: text/html; charset=utf-8");
        $text ??= "The requested URL was not found on this server.";
        $text = htmlspecialchars($text, self::HTML_ENCODE_FLAGS, 'UTF-8');

        echo <<<__HTML__
            <!DOCTYPE html>
            <html>
            <head>
                <title>Not Found</title>
            </head>
            <body>
                <h1>Not Found</h1>
                <p>$text</p>
            </body>
            </html>
            __HTML__;
        exit;
    }

    /**
     * Outputs message and exits if the current value is missing (null or ""), zero is not considered missing
     *
     * SECURITY: The message is intentionally HTML-encoded: die() sends it straight to the browser, and
     * messages often interpolate user input (e.g. ->orDie("Bad id: $id")). The only cost is encoded
     * entities in CLI output, which is cosmetic.
     *
     * Exits with code 1 so CLI and cron callers see a failure, not success.
     *
     * @param string $text Plain-text message; HTML-encoded automatically before output.
     */
    public function orDie(string $text): self
    {
        if ($this->isMissing()) {
            echo htmlspecialchars($text, self::HTML_ENCODE_FLAGS, 'UTF-8'); // SECURITY: intentional encode, do not remove (see docblock)
            exit(1);
        }
        return $this;
    }

    /**
     * Throws RuntimeException with message if the current value is missing (null or ""), zero is not considered missing
     *
     * SECURITY: The message is intentionally HTML-encoded: exception handlers often echo messages into
     * a page, and messages often interpolate user input (e.g. ->orThrow("Bad id: $id")). Encoding at
     * throw time keeps every handler safe. Handlers that want plain text (CLI, logs) can decode with:
     *
     *     htmlspecialchars_decode($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
     *
     * Pass those exact flags - the ENT_HTML401 default doesn't decode the &apos; this encoding produces.
     *
     * @param string $text Plain-text message; HTML-encoded automatically before being used as the exception message.
     * @throws RuntimeException If the value is missing (null or "")
     */
    public function orThrow(string $text): self
    {
        if ($this->isMissing()) {
            $text = htmlspecialchars($text, self::HTML_ENCODE_FLAGS, 'UTF-8'); // SECURITY: intentional encode, do not remove (see docblock)
            throw new RuntimeException($text);
        }
        return $this;
    }

    /**
     * Redirects to a URL if the current value is missing (null or ""), zero is not considered missing
     *
     * Uses a simple Location header redirect (HTTP 302, a temporary redirect).
     * If headers have already been sent, this method throws - even when the value is
     * present - so misuse fails on the first request instead of only on empty values.
     *
     * @param string $url The URL to redirect to if value is missing
     * @return self Returns $this for method chaining if not missing, redirects if missing
     * @throws RuntimeException If headers have already been sent
     */
    public function orRedirect(string $url): self
    {
        // Check early so developers find out immediately, not only when isMissing()
        if (headers_sent($file, $line)) {
            throw new RuntimeException("orRedirect(): headers already sent in $file on line $line");
        }

        if ($this->isMissing()) {
            http_response_code(302);
            header("Location: $url");
            exit;
        }
        return $this;
    }

    //endregion
    //region Utilities

    /**
     * Call a function on the value and rewrap the result, e.g. ->map('strtoupper')
     *
     * The callback always runs and receives the raw value - null included - like
     * array_map() and SmartArray::map(). Chain ->ifNull('') first when using
     * built-ins that require a string.
     *
     * @param callable|string $func    The function to call with the value
     * @param mixed           ...$args Additional arguments to pass to the function
     */
    public function map(callable|string $func, mixed ...$args): SmartString
    {
        if (!is_callable($func)) {
            throw new CallerException("Function '$func' is not callable");
        }

        $newValue = $func($this->rawData, ...$args);
        if (!is_null($newValue) && !is_scalar($newValue)) {
            throw new CallerException("map() callback must return a scalar value (string, int, float, bool, or null), got " . get_debug_type($newValue));
        }
        return new self($newValue);
    }

    /**
     * Apply preg_replace to the value, returning a new SmartString.
     *
     *     $digits = $phone->pregReplace('/\D/', '');           // "5551234567"
     *     $clean  = $text->pregReplace('/\s+/', ' ');          // normalize whitespace
     *     $wrap   = $slug->pregReplace('/(.+)/', 'pre-$1');    // add prefix via backreference
     *
     * Missing values (null or "") pass through unchanged.
     *
     * @param string $pattern     Regex pattern
     * @param string $replacement Replacement string (supports backreferences)
     * @return SmartString A new SmartString with the replaced value
     * @throws CallerException If the pattern is invalid or matching fails (an InvalidArgumentException that reports your file:line)
     */
    public function pregReplace(string $pattern, string $replacement): SmartString
    {
        if ($this->isMissing()) {
            return new self($this->rawData);
        }

        error_clear_last();
        $newValue = @preg_replace($pattern, $replacement, (string)$this->rawData); // @: PHP warning becomes the exception below
        if (is_null($newValue)) {
            $reason = error_get_last()['message'] ?? preg_last_error_msg();
            throw new CallerException("pregReplace(): $reason");
        }
        return new self($newValue);
    }

    //endregion
    //region Debugging and Help

    /**
     * Displays helpful documentation about SmartString methods and functionality.
     *
     * Static so both documented call forms work: SmartString::help() and $str->help().
     *
     * @param mixed $value Optional value to pass through
     * @return mixed $value, unchanged - so help() can be dropped into an expression without changing the result
     */
    public static function help(mixed $value = null): mixed
    {
        $helpPath = __DIR__ . '/help.txt';

        if (is_file($helpPath)) {
            $docs = file_get_contents($helpPath);
        } else {
            $docs = "SmartString help documentation not found.\nExpected location: $helpPath";
        }

        echo self::xmpWrap("\n$docs\n\n");
        return $value;
    }

    /**
     * Show useful developer info about object when print_r() is used to examine object
     *
     * @return array An associative array containing debugging information.
     */
    public function __debugInfo(): array
    {
        $output = [];

        // show help information for first instance
        static $callCounter = 0;
        if (++$callCounter === 1) {
            $output['README:private'] = "Call \$obj->help() for more information and method examples.";
        }

        $value                     = $this->rawData;
        $output['rawData:private'] = match (true) {
            is_string($value) => sprintf('"%s"', $value),
            is_bool($value)   => ($value ? "TRUE" : "FALSE"),
            is_null($value)   => "NULL, // Either value is NULL or field doesn't exist",
            default           => $value, // includes ints and floats
        };

        return $output;
    }

    //endregion
    //region Internal

    /**
     * Returns HTML-encoded string representation of the Value when object is accessed in string context.
     *
     * @return string The HTML-encoded representation of the value.
     */
    public function __toString(): string
    {
        $text = (string)$this->rawData;
        if (!preg_match(self::ENCODE_SKIP_REGEX, $text)) {
            return $text; // speed: no byte that encoding would change, see ENCODE_SKIP_REGEX
        }
        return htmlspecialchars($text, self::HTML_ENCODE_FLAGS, 'UTF-8');
    }

    /**
     * Magic getter that provides helpful error messages for common mistakes with dynamic properties/methods
     * Emits E_USER_WARNING when property access is invalid, providing detailed usage instructions.
     *
     * Handles two main error cases:
     * 1. Attempting to call methods without proper syntax:
     *    - Missing () brackets: $str->htmlEncode instead of $str->htmlEncode()
     *    - Missing {} in strings: "$str->htmlEncode()" instead of "{$str->htmlEncode()}"
     * 2. Accessing undefined properties
     *
     * @param string $property Name of the property/method being accessed
     * @return SmartString Always returns a new instance with null value to prevent fatal errors
     */
    /**
     * Returns a short quoted preview of the value for error messages (strings truncated to 20 chars).
     */
    private function valuePreview(): string
    {
        return match (true) {
            is_string($this->rawData) => mb_strlen($this->rawData, 'UTF-8') <= 20
                ? "\"$this->rawData\""
                : '"' . mb_substr($this->rawData, 0, 20, 'UTF-8') . '..."',
            is_bool($this->rawData)   => $this->rawData ? "TRUE" : "FALSE",
            is_null($this->rawData)   => "NULL",
            default                   => (string)$this->rawData,
        };
    }

    /**
     * SmartString is a single value, so foreach over one is always a mistake. PHP would
     * silently loop zero times (no accessible properties); this throws instead so the
     * mistake is visible. Also fires for iterator_to_array() and yield from.
     */
    public function getIterator(): Iterator
    {
        // SECURITY: encode < > & in the preview - exception handlers often echo messages into pages (see orThrow).
        // No ENT_QUOTES: the preview's own quotes stay readable in logs and CLI stack traces.
        $preview = htmlspecialchars($this->valuePreview(), ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
        throw new CallerException(
            "Can't foreach over SmartString $preview - it holds a single value, not a collection. " .
            "Did you mean to loop the SmartArray row or result set it came from?",
        );
    }

    public function __get(string $property): SmartString
    {
        // Format value for display (truncate strings to 20 chars)
        $formattedValue = $this->valuePreview();

        // throw unknown property warning
        // PHP Default Error: Warning: Undefined property: stdClass::$property in C:\dev\projects\SmartString\test.php on line 28
        if (method_exists($this, $property)) {
            $error = "$formattedValue->$property\n";
            $error .= "Method ->$property needs brackets() everywhere and {curly braces} in strings:\n";
            $error .= "    ✓ Outside strings:         \$str->$property()\n";
            $error .= "    ✗ Missing brackets:        \$str->$property\n";
            $error .= "    ✓ Inside strings:          \"Hello {\$str->$property()}\"\n";
            $error .= "    ✗ Missing { } in string:   \"Hello \$str->$property()\"\n";
        } else {
            $error = "Undefined property: $formattedValue->$property\n";
        }

        $error .= self::occurredInFile();
        trigger_error($error, E_USER_WARNING); // Emulate PHP warning

        return new self(null);
    }

    /**
     * Magic method handler for deprecated methods and error messages for unknown methods
     *
     * For deprecated methods, log a deprecation notice and return the new method.
     * For unknown methods, throw a PHP Error with suggestion if possible.
     *
     * @noinspection SpellCheckingInspection // ignore all lowercase function names
     */
    public function __call($method, $args): string|int|bool|null|float|SmartString
    { // NOSONAR - False-positive for unused $args parameter
        $methodLc = strtolower($method);

        // Deprecated Warnings: log warning and return proper value.  This will be removed in a future version
        [$return, $deprecationError] = match ($methodLc) {  // use lowercase names below for comparison
            'noencode'  => [$this->rawHtml(), "Replace ->$method() with ->rawHtml()"],
            'tostring'  => [$this->htmlEncode(), "Replace ->$method() with ->htmlEncode() or ->string()"], // htmlEncode() first: it matches this shim's output
            'jsencode'  => [addcslashes((string)$this->rawData, "\x00-\x1F'\"`\n\r\\<>"), "Replace ->$method() with ->jsonEncode() (not identical functionality, code refactoring required)"],
            'striptags' => [new self(is_null($this->rawData) ? null : strip_tags((string)$this->rawData, ...$args)), "Replace ->$method() with ->textOnly()"],
            default     => [null, null],
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // Common aliases: throw error with suggestion.  These are used by other libraries or common LLM suggestions
        $methodAliases = [
            // use lowercase for aliases
            'add'          => ['plus'],
            'append'       => ['concat', 'suffix'],
            'appendHtml'   => ['andhtml', 'addhtml', 'suffixhtml'],
            'bool'         => ['tobool', 'getbool', 'boolean'],
            'dateFormat'   => ['formatdate', 'todate', 'date_format', 'date', 'formatdatetime', 'todatetime', 'datetime'],
            'divide'       => ['div', 'divideby'],
            'float'        => ['tofloat', 'getfloat'],
            'htmlEncode'   => ['escapehtml', 'encodehtml', 'e', 'encode', 'escape', 'html_encode'],
            'int'          => ['toint', 'getint', 'integer'],
            'ifEquals'     => ['ifequal', 'ifmatch'],
            'ifTrue'       => ['when', 'setif'],
            'ifZero'       => ['iszero'], // pre-2.1.2 method name, promised a suggestion in UPGRADING.md
            'isEmpty'      => ['isblank', 'empty'],
            'isMissing'    => ['ismissingvalue'],
            'isNotEmpty'   => ['isnotblank', 'hasvalue', 'ispresent', 'notempty'],
            'jsonEncode'   => ['tojson', 'encodejson', 'json_encode', 'json'],
            'map'          => ['pipe', 'transform', 'callback'],
            'maxChars'     => ['truncate', 'limit', 'limitchars', 'excerpt', 'shorten'],
            'maxWords'     => ['truncatewords', 'limitwords'],
            'multiply'     => ['times', 'mul'],
            'nl2br'        => ['tohtml', 'text2html'],
            'numberFormat' => ['formatnumber', 'number_format', 'format'],
            'or'           => ['default', 'ifmissing', 'fallback', 'else'],
            'prepend'      => ['prefix'],
            'pregReplace'  => ['replace'], // replace(search, replacement) intent; set() would silently keep only the first argument
            'rawHtml'      => ['unsafe', 'unescaped', 'trusted', 'trustedhtml', 'unsafehtml', 'raw', 'html'],
            'set'          => ['setvalue'],
            'string'       => ['getstring', 'str'],
            'subtract'     => ['minus', 'sub'],
            'textOnly'     => ['plaintext', 'striphtml', 'strip', 'text'],
            'urlEncode'    => ['escapeurl', 'encodeurl', 'url_encode'],
            'value'        => ['noescape', 'getvalue', 'val'],
            'wrap'         => ['surround', 'enclose'],
            'wrapHtml'     => ['andwraphtml', 'surroundhtml', 'prependhtml', 'prefixhtml'], // no prepend-side method by design; wrapHtml($before, '') covers it
        ];

        // Check if the called method is an alias
        $suggestion = null;
        foreach ($methodAliases as $correctMethod => $aliases) {
            if (in_array($methodLc, $aliases, true)) {
                $suggestion = "did you mean ->$correctMethod()?";
                break;
            }
        }

        // throw unknown method Error
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method SmartString::method() in C:\dev\projects\SmartString\test.php:17
        $suggestion ??= "call ->help() for available methods.";
        $error      = sprintf("Call to undefined method %s->$method(), $suggestion\n", self::stripNamespace(self::class));
        $error      .= self::occurredInFile();
        throw new Error($error);
    }

    /**
     * Routes the deprecated static shims (fromArray, rawValue) to their replacements with an
     * E_USER_DEPRECATED notice, and throws a helpful Error for any other unknown static method.
     * @noinspection SpellCheckingInspection // ignore all lowercase method names
     */
    public static function __callStatic($method, $args): mixed
    { // NOSONAR - False-positive for unused $args parameter
        $methodLc = strtolower($method);

        // deprecated methods, log and return new method (these may be removed in the future)
        if ($methodLc === 'fromarray') {
            self::logDeprecation("Replace SmartString::$method() with SmartArrayHtml::new(\$array)");
            return SmartArray::new(...$args)->asHtml();
        }
        if ($methodLc === 'rawvalue') {
            self::logDeprecation("Replace SmartString::$method() with SmartString::getRawValue()");
            return self::getRawValue(...$args);
        }

        // throw unknown method Error
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method SmartString::method() in C:\dev\projects\SmartString\test.php:17
        $baseClass = self::stripNamespace(self::class);
        $error     = "Call to undefined method $baseClass::$method(), call ->help() for available methods.\n";
        $error     .= self::occurredInFile();
        throw new Error($error);
    }

    /**
     * Wrap output in <xmp> tag if text/html and not called from a function that already added <xmp>
     * @noinspection SpellCheckingInspection // ignore all lowercase strtolower function name
     */
    private static function xmpWrap(string $output): string
    {
        $output = trim($output, "\n");
        $plain  = "\n$output\n";

        // terminals show <xmp> literally; CGI builds misreport SAPI on some hosts, so check more than PHP_SAPI
        $inCli = PHP_SAPI === 'cli'
                 || ($_SERVER['SESSIONNAME'] ?? '') === 'Console' // Windows console
                 || empty($_SERVER['SCRIPT_NAME']);               // only web servers set SCRIPT_NAME
        if ($inCli) {
            return $plain;
        }

        // non-HTML responses (json, plain text, etc.) stay unwrapped
        $headersList    = implode("\n", headers_list());
        $hasContentType = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);                          // assume no content type will default to html
        $isTextHtml     = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        if (!$isTextHtml) {
            return $plain;
        }

        // showme() debug helper adds its own <xmp>
        $backtraceFunctions = array_map('strtolower', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        if (in_array('showme', $backtraceFunctions, true)) {
            return $plain;
        }

        return "\n<xmp>\n$output\n</xmp>\n";
    }

    /**
     * Internal method called by json_encode() to provide raw data for serialization (implements JsonSerializable).
     *
     * You never need to call this directly, as PHP will call it automatically when you pass a SmartString object to json_encode().
     *
     * Substitutes malformed UTF-8 with � (U+FFFD) so json_encode($smartString) returns valid JSON instead
     * of false, matching jsonEncode(). Escaping flags are still up to the caller.
     *
     * @see jsonEncode() Preferred for encoded JSON strings, escapes <, >, and & characters so they are safe for embedding in HTML.
     */
    public function jsonSerialize(): mixed
    {
        // json_encode's own U+FFFD substitution, so this path and jsonEncode() produce identical characters
        if (is_string($this->rawData) && preg_match('//u', $this->rawData) !== 1) { // isMalformed: ~5x faster than mb_check_encoding()
            return json_decode(json_encode($this->rawData, JSON_INVALID_UTF8_SUBSTITUTE));
        }

        return $this->rawData;
    }

    /**
     * Returns the string with invalid UTF-8 byte sequences substituted with � (U+FFFD),
     * so /u regexes and mb_* functions can process it. Valid strings pass through unchanged.
     */
    private static function substituteInvalidUtf8(string $text): string
    {
        if (preg_match('//u', $text) === 1) { // isValid: ~5x faster than mb_check_encoding()
            return $text;
        }

        // json_encode's own U+FFFD substitution, so bad bytes become the same � that htmlEncode() and jsonEncode() produce
        return json_decode(json_encode($text, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * Helper to convert $value to a float or null.
     */
    private static function getFloatOrNull(mixed $value): ?float
    {
        $value = self::getRawValue($value);  // unwrap SmartStrings
        return match (true) {
            is_float($value)   => $value,
            is_numeric($value) => (float)$value,
            default            => null,
        };
    }

    //endregion
}
