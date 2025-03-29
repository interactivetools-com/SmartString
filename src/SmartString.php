<?php

declare(strict_types=1);

namespace Itools\SmartString;

use Error, RuntimeException, InvalidArgumentException;
use Itools\SmartArray\SmartArray, Itools\SmartArray\SmartNull;
use JsonSerializable;

/**
 * SmartString class provides a fluent interface for various string and numeric manipulations.
 *
 * For inline help, call $smartString->help() or print_r() on a SmartString object.
 */
class SmartString implements JsonSerializable
{
    /**
     * The raw stored value (type as passed: string|int|float|bool|null).
     */
    private string|int|float|bool|null $rawData;

    /**
     * Controls how null values are handled in numeric operations.
     * When true: null values are treated as 0 in operations like add(), subtract(), multiply(), etc.
     * When false: operations with null values return null results.
     * Note: Non-numeric strings (e.g. "cat", "1,234") always become null regardless of this setting.
     */
    public static bool $treatNullAsZero = true;

    /**
     * Flag to indicate if a numeric operation resulted in an error (e.g., non-numeric value, division by zero, or null if above flag is true).
     * This flag is used to prevent further operations on the result.  e.g., one error in a chain of operations will propagate to the end and return null.
     */
    private bool $hasNumericError;

    // default formats
    public static string $numberFormatDecimal   = '.';                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        // numberFormat() default decimal separator
    public static string $numberFormatThousands = ',';                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       // numberFormat() default thousands separator
    public static string $dateFormat            = 'Y-m-d';                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   // dateFormat() default format (for PHP date())
    public static string $dateTimeFormat        = 'Y-m-d H:i:s';                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             // dateTimeFormat() default format (for PHP date())
    public static array  $phoneFormat           = [
        ['digits' => 10, 'format' => '(###) ###-####'],
        ['digits' => 11, 'format' => '# (###) ###-####'],
    ];

    #region Core

    /**
     * Initializes a new Value object with a name and a value.
     *
     * @param string|int|float|bool|null $value The value to store
     * @param array $properties Optional properties to initialize the object with
     *
     * @example $value = new SmartString('<b>Hello World!</b>');
     */
    public function __construct(string|int|float|bool|null $value, array $properties = [])
    {
        // set properties
        $this->rawData         = $value;
        $this->hasNumericError = $properties['hasNumericError'] ?? false;
    }

    /**
     * Returns a SmartString object for a value.
     *
     * @example $str  = SmartString::new("Hello, World!");                  // Single value as SmartString
     *          $rows = SmartArray::new($resultSet)->withSmartStrings();    // SmartArray of SmartStrings (verbose method)
     *          $rows = SmartArray::new($resultSet, true);                  // SmartArray of SmartStrings (shortcut method)
     *
     * @return SmartArray|SmartString The newly created SmartString object.
     */
    public static function new(string|int|float|bool|null|array $value, array $properties = []): SmartArray|SmartString
    {
        return match (is_array($value)) {
            true  => new SmartArray($value, true),
            false => new self($value, $properties),
        };
    }

    /**
     * Returns original type and value.
     *
     * @return string|int|float|bool|null
     */
    public function value(): string|int|float|bool|null
    {
        return $this->rawData;
    }

    /**
     * Converts Smart* objects to their original values while leaving other types unchanged,
     * useful if you don't know the type but want the original value.
     */
    public static function getRawValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self       => $value->value(),
            $value instanceof SmartArray => $value->toArray(),
            $value instanceof SmartNull  => null,
            is_scalar($value),
            is_null($value)              => $value,
            is_array($value)             => array_map([self::class, 'getRawValue'], $value), // for manually passed in arrays
            default                      => throw new InvalidArgumentException("Unsupported value type: " . get_debug_type($value)),
        };
    }

    #endregion
    #region Type Conversion

    /**
     * Returns value as integer
     */
    public function int(): int
    {
        return (int)$this->rawData;
    }

    /**
     * Returns value as float
     */
    public function float(): float
    {
        return (float)$this->rawData;
    }

    /**
     * Returns value as boolean
     */
    public function bool(): bool
    {
        return (bool)$this->rawData;
    }

    /**
     * Returns value as string (doesn't HTML-encode, use ->htmlEncode() for HTML-encoded string)
     */
    public function string(): string
    {
        return (string)$this->rawData;
    }

    /**
     * Alias for value() - returns the unencoded, raw value.
     * This can be useful when you want to output trusted HTML content.
     *
     * @return string|int|float|bool|null The raw, unencoded value
     */
    public function rawHtml(): string|int|float|bool|null
    {
        return $this->value();
    }

    #endregion
    #region Encoding

    /**
     * HTML encodes a given input for safe output in an HTML context.
     *
     * @param bool $encodeBrTags HTML encode <br> tags in content, enabled to prevent "{$str->nl2br()}" from being double-encoded.
     *
     * @return string Html-encoded output
     */
    public function htmlEncode(bool $encodeBrTags = false): string
    {
        $encoded = htmlspecialchars((string)$this->rawData, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return $encodeBrTags ? $encoded : preg_replace("|&lt;(br\s*/?)&gt;|i", "<$1>", $encoded);
    }

    /**
     * URL encodes a string for safe use within URLs.
     *
     * Example Output: "Save 10%+ off" becomes "Save+10%25%2B+off"
     * Example Usage: echo "?company=$company&offer=$offer"; // encode url parameter values only
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
     * Example Usage:
     * 1. `<script>var jsonString = <?php echo $var->jsonEncode() ?>;</script>` // Embedded in single quotes
     * 3. `echo "<script>var data = {$this->jsonEncode()};</script>";`          // Direct output in script
     *
     * @return string The encoded JSON string, safely formatted for embedding in JavaScript.
     */
    public function jsonEncode(): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        return json_encode($this->rawData, $flags);
    }

    #endregion
    #region String Manipulation

    /**
     * Remove HTML tags, decode HTML entities, and trims whitespace
     */
    public function textOnly(): SmartString
    {
        $newValue = match (true) {
            is_null($this->rawData) => null,
            default                 => trim(strip_tags(html_entity_decode((string)$this->rawData, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'))),
        };
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Convert newlines ("\n") to <br> tags
     */
    public function nl2br(): SmartString
    {
        $newValue = match (true) {
            is_null($this->rawData) => null,
            default                 => nl2br((string)$this->rawData, false),
        };
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Trim leading and trailing whitespace, supports same parameters as PHP trim()
     */
    public function trim(...$args): SmartString
    {
        $newValue = match (true) {
            is_null($this->rawData) => null,
            default                 => trim((string)$this->rawData, ...$args),
        };
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Limit words to $max, if truncated adds ... (override with second parameter)
     */
    public function maxWords(int $max, string $ellipsis = "..."): SmartString
    {
        $newValue = null;
        if (!is_null($this->rawData)) {
            $text     = trim((string)$this->rawData);
            $words    = preg_split('/\s+/u', $text);
            $newValue = implode(' ', array_slice($words, 0, $max));
            $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Remove trailing punctuation
            if (count($words) > $max) {
                $newValue .= $ellipsis;
            }
        }

        return new self($newValue, get_object_vars($this));
    }

    /**
     * Limit chars to $max, if truncated adds ... (override with second parameter)
     */
    public function maxChars(int $max, string $ellipsis = "..."): SmartString
    {
        $newValue = null;
        if (!is_null($this->rawData)) {
            $text = preg_replace('/\s+/u', ' ', trim((string)$this->rawData));

            if (mb_strlen($text) <= $max) {
                $newValue = $text;
            } elseif ($max > 0 && preg_match("/^.{1,$max}(?=\s|$)/u", $text, $matches)) {
                $newValue = $matches[0];
                $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Remove trailing punctuation
                $newValue .= $ellipsis;
            } else {
                $newValue = mb_substr($text, 0, $max) . $ellipsis;
            }
        }

        return new self($newValue, get_object_vars($this));
    }

    #endregion
    #region Formatting Operations

    /**
     * Formats a date using default or specified format.  Returns null on failure.
     *
     * @param string|null $format Date format (default: SmartString::$dateFormat)
     * @return SmartString Formatted date or null on failure
     */
    public function dateFormat(?string $format = null): SmartString
    {
        $format    ??= self::$dateFormat;
        $timestamp = match (true) {
            is_null($this->rawData)    => null,
            is_numeric($this->rawData) => (int)$this->rawData,
            default                    => strtotime($this->rawData) ?: null,
        };

        $newValue = $timestamp ? date($format, $timestamp) : null; // return null on null or 0

        return new self($newValue, get_object_vars($this));
    }

    /**
     * Format date by $dateTimeFormat or specified format. Returns null on failure.
     *
     * @param string|null $format Date format (default: SmartString::$dateTimeFormat)
     * @return SmartString Formatted date or null on failure
     *
     * @example $date = SmartString::new('2023-05-15');
     *          echo $date->dateTimeFormat();                  // "2023-05-15 00:00:00" (using default format)
     *          echo $date->dateTimeFormat('Y-m-d H:i:s T');   // "2023-05-15 00:00:00 MST"
     */
    public function dateTimeFormat(?string $format = null): SmartString
    {
        $format   ??= self::$dateTimeFormat;
        $newValue = $this->dateFormat($format)->value();
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Formats a numeric value using number_format() function with configurable decimal places.
     * Uses the static properties $numberFormatDecimal and $numberFormatThousands for formatting.
     * Returns null if the value is not numeric.
     *
     * @param int|null $decimals Number of decimal places to display (default: 0)
     * @return SmartString Formatted number or null if not numeric
     *
     * @example $num = SmartString::new(1234.56);
     *          echo $num->numberFormat();     // "1,235" (rounded to 0 decimals)
     *          echo $num->numberFormat(2);    // "1,234.56" (2 decimal places)
     */
    public function numberFormat(?int $decimals = 0): SmartString
    {
        $newValue = match (true) {
            !is_numeric($this->rawData) => null,
            default                     => number_format((float)$this->rawData, $decimals, self::$numberFormatDecimal, self::$numberFormatThousands),
        };
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Formats a phone number according to the configuration in SmartString::$phoneFormat.
     * The format is chosen based on the number of digits in the input.
     * Non-digit characters in the input are stripped before formatting.
     * Returns null if the input contains an unsupported number of digits.
     *
     * @return SmartString The formatted phone number or null if input is invalid
     *
     * @example $phone = SmartString::new('2345678901');
     *          echo $phone->phoneFormat();                // "(234) 567-8901" (using default format for 10 digits)
     *
     *          // With custom format (after setting SmartString::$phoneFormat)
     *          // SmartString::$phoneFormat = [['digits' => 10, 'format' => '###.###.####']];
     *          echo $phone->phoneFormat();                // "234.567.8901"
     */
    public function phoneFormat(): SmartString
    {
        $newValue = null;

        // get array of digits only
        $digits = str_split(preg_replace('/\D/', '', (string)$this->rawData));

        // get phone format by number of digits, e.g., 10 => '(###) ###-####'
        $phoneFormatByDigits = array_column(self::$phoneFormat, 'format', 'digits');
        $phoneFormat         = $phoneFormatByDigits[count($digits)] ?? null;

        // Replace # with digits
        if ($phoneFormat) {
            $format   = str_replace('#', '%s', $phoneFormat);
            $newValue = sprintf($format, ...$digits);
        }

        return new self($newValue, get_object_vars($this));
    }

    #endregion
    #region Numeric Operations

    /**
     * Converts a number to a percentage. Support optional decimal places and fallback value for zero
     * - If value is null, null is returned
     * - If value is zero AND $zeroFallback is defined, $zeroFallback is returned
     * - Otherwise a percentage is returned. e.g., 0.1234 => 12.34%
     *
     * @param int|null $decimals Number of decimal places in formatted output
     * @param string|int|float|null $zeroFallback Alternative value to return when input is zero
     * @return SmartString Formatted percentage, zeroFallback value if zero, or null if not numeric
     *
     * @example Converting numbers to percentages:
     *   $val = SmartString::new(0.1234);
     *   echo $val->percent(2);           // "12.34%"
     *
     * @example Handling zero values:
     *   $zero = SmartString::new(0);
     *   echo $zero->percent(2);          // "0.00%"
     *   echo $zero->percent(2, "None");  // "None"
     */
    public function percent(?int $decimals = 0, string|int|float|null $zeroFallback = null): SmartString
    {
        $value                 = self::getFloatOrNull($this->rawData);
        $this->hasNumericError = $this->hasNumericError || is_null($value);
        $newValue              = match (true) {
            $this->hasNumericError                    => null,
            $value === 0.0 && !is_null($zeroFallback) => $zeroFallback,
            default                                   => number_format($value * 100, $decimals) . '%',
        };

        return new self($newValue, get_object_vars($this));
    }

    /**
     * Returns the current value as a percentage of $total, e.g., 24 of 100 becomes 24%. Returns null if either value is non-numeric.
     *
     * Note: If SmartString::$treatNullAsZero is true, null values are treated as 0 (but non-numeric strings like "abc" still produce null).
     */
    public function percentOf(int|float|SmartString $total, ?int $decimals = 0): SmartString
    {
        $left                  = self::getFloatOrNull($this->rawData);
        $right                 = self::getFloatOrNull($total);
        $this->hasNumericError = $this->hasNumericError || is_null($left) || is_null($right) || $right === 0.0;
        $newValue              = $this->hasNumericError ? null : number_format($left / $right * 100, $decimals) . '%';
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Adds $addend to the current value. Returns null if either value is non-numeric.
     *
     * Note: If SmartString::$treatNullAsZero is true, null values are treated as 0 (but non-numeric strings like "abc" still produce null).
     */
    public function add(int|float|SmartString $addend): SmartString
    {
        $left                  = self::getFloatOrNull($this->rawData);
        $right                 = self::getFloatOrNull($addend);
        $this->hasNumericError = $this->hasNumericError || is_null($left) || is_null($right);
        $newValue              = $this->hasNumericError ? null : $left + $right;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Subtracts $subtrahend from the current value. Returns null if either value is non-numeric.
     *
     * Note: If SmartString::$treatNullAsZero is true, null values are treated as 0 (but non-numeric strings like "abc" still produce null).
     */
    public function subtract(int|float|SmartString $subtrahend): SmartString
    {
        $left                  = self::getFloatOrNull($this->rawData);
        $right                 = self::getFloatOrNull($subtrahend);
        $this->hasNumericError = $this->hasNumericError || is_null($left) || is_null($right);
        $newValue              = $this->hasNumericError ? null : $left - $right;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Multiplies the current value by $multiplier. Returns null if either value is non-numeric.
     *
     * Note: If SmartString::$treatNullAsZero is true, null values are treated as 0 (but non-numeric strings like "abc" still produce null).
     */
    public function multiply(int|float|SmartString $multiplier): SmartString
    {
        $left                  = self::getFloatOrNull($this->rawData);
        $right                 = self::getFloatOrNull($multiplier);
        $this->hasNumericError = $this->hasNumericError || is_null($left) || is_null($right);
        $newValue              = $this->hasNumericError ? null : $left * $right;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Divides the current value by $divisor. Returns null if either value is non-numeric or the divisor is zero.
     *
     * Note: If SmartString::$treatNullAsZero is true, null values are treated as 0 (but non-numeric strings like "abc" still produce null).
     */
    public function divide(int|float|SmartString $divisor): SmartString
    {
        $left                  = self::getFloatOrNull($this->rawData);
        $right                 = self::getFloatOrNull($divisor);
        $this->hasNumericError = $this->hasNumericError || is_null($left) || is_null($right) || $right === 0.0;
        $newValue              = $this->hasNumericError ? null : $left / $right;
        return new self($newValue, get_object_vars($this));
    }

    #endregion
    #region Conditional Logic

    /**
     * Replaces value if missing (null or ""), zero is not considered missing
     */
    public function or(int|float|string|SmartString $fallback): SmartString
    {
        $newValue = $this->isMissing() ? self::getRawValue($fallback) : $this->rawData;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Appends value if present (not null or ""), zero is considered present
     */
    public function and(int|float|string|SmartString $value): SmartString
    {
        $newValue = $this->rawData;
        if (!$this->isMissing()) {
            $newValue .= self::getRawValue($value);
        }
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Prepends value if present (not null or ""), zero is considered present
     */
    public function andPrefix(int|float|string|SmartString $prefix): SmartString
    {
        $newValue = $this->rawData;
        if (!$this->isMissing()) {
            $newValue = self::getRawValue($prefix) . $newValue;
        }
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Replaces value only if it's an empty string ("")
     */
    public function ifBlank(int|float|string|SmartString $fallback): SmartString
    {
        $newValue = $this->rawData === "" ? self::getRawValue($fallback) : $this->rawData;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Replaces value only if it's null or undefined
     */
    public function ifNull(int|float|string|SmartString $fallback): SmartString
    {
        $newValue = $this->rawData ?? self::getRawValue($fallback);
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Replaces value only if it's zero (0, 0.0, "0", or "0.0")
     */
    public function ifZero(int|float|string|SmartString $fallback): SmartString
    {
        $isZero   = is_numeric($this->rawData) && (float)$this->rawData === 0.0;
        $newValue = $isZero ? self::getRawValue($fallback) : $this->rawData;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Sets to $valueIfTrue only if $condition is true ($value can be a SmartString)
     */
    public function if(string|int|float|bool|null|SmartString $condition, string|int|float|bool|null|object $valueIfTrue): SmartString
    {
        $newValue = self::getRawValue($condition) ? self::getRawValue($valueIfTrue) : $this->rawData;
        return new self($newValue, get_object_vars($this));
    }

    /**
     * Sets to $value (accepts expression, e.g., match($itemCount->int()) { 0 => "No items", ... })
     */
    public function set(string|int|float|bool|null|object $newValue): SmartString
    {
        $newValue = self::getRawValue($newValue);
        return new self($newValue, get_object_vars($this));
    }

    #endregion
    #region Validation

    /**
     * Returns true if the value is empty ("", null, false, 0, "0"), uses PHP empty()
     *
     * This is useful for conditionally showing blocks of HTML:
     * if ($value->isEmpty()) { echo "<p>No data available</p>"; }
     */
    public function isEmpty(): bool
    {
        return empty($this->rawData);
    }

    /**
     * Returns true if the value is not empty ("", null, false, 0, "0"), uses PHP empty()
     *
     * This is useful for conditionally showing blocks of HTML:
     * if ($value->isNotEmpty()) { echo "<p>Value: $value</p>"; }
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->rawData);
    }

    /**
     * Checks if the current value is missing (null or "")
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
     * if ($value->isNull()) { echo "<p>Value is specifically NULL</p>"; }
     *
     * @return bool True if the value is null
     */
    public function isNull(): bool
    {
        return $this->rawData === null;
    }

    #endregion
    #region Error Handling

    /**
     * Sends 404 header and exits if the current value is missing (null or ""), zero is not considered missing
     */
    public function or404(?string $message = null): self
    {
        if (!$this->isMissing()) {
            return $this;
        }

        http_response_code(404);
        header("Content-Type: text/html; charset=utf-8");
        $message ??= "The requested URL was not found on this server.";
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        echo <<<__HTML__
            <!DOCTYPE html>
            <html>
            <head>
                <title>Not Found</title>
            </head>
            <body>
                <h1>Not Found</h1>
                <p>$message</p>
            </body>
            </html>
            __HTML__;
        exit;
    }

    /**
     * Outputs message and exits if the current value is missing (null or ""), zero is not considered missing
     */
    public function orDie(string $message): self
    {
        if ($this->isMissing()) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            die($message);
        }
        return $this;
    }

    /**
     * Throws Exception with message if the current value is missing (null or ""), zero is not considered missing
     */
    public function orThrow(string $message): self
    {
        if ($this->isMissing()) {
            $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            throw new RuntimeException($message);
        }
        return $this;
    }

    #endregion
    #region Utilities

    /**
     * Apply a callback or function to the value, e.g. ->apply('strtoupper')
     *
     * @param callable|string $func The function to apply
     * @param mixed ...$args Additional arguments to pass to the function
     */
    public function apply(callable|string $func, mixed ...$args): SmartString
    {
        if (!is_callable($func)) {
            throw new InvalidArgumentException("Function '$func' is not callable");
        }

        $newValue = $func($this->rawData, ...$args);
        return new self($newValue, get_object_vars($this));
    }

    #endregion
    #region Debugging and Help

    /**
     * Displays helpful documentation about SmartString methods and functionality
     */
    public function help(mixed $value = null): mixed
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

    #endregion
    #region Internal

    /**
     * Returns HTML-encoded string representation of the Value when object is accessed in string context.
     *
     * @return string The HTML-encoded representation of the value.
     */
    public function __toString(): string
    {
        return $this->htmlEncode();
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
    public function __get(string $property): SmartString
    {
        $baseClass = basename(self::class);

        // throw unknown property warning
        // PHP Default Error: Warning: Undefined property: stdClass::$property in C:\dev\projects\SmartString\test.php on line 28
        if (method_exists($this, $property)) {
            $error = "Method ->$property needs brackets() everywhere and {curly braces} in strings:\n";
            $error .= "    ✓ Outside strings:         \$str->$property()\n";
            $error .= "    ✗ Missing brackets:        \$str->$property\n";
            $error .= "    ✓ Inside strings:          \"Hello {\$str->$property()}\"\n";
            $error .= "    ✗ Missing { } in string:   \"Hello \$str->$property()\"\n";
        } else {
            $error = "Undefined property $baseClass->$property, call ->help() for available methods.\n";
        }

        // Add caller info
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
        $error  .= "Occurred in {$caller['file']}:{$caller['line']}\n";
        $error  .= "Reported";                 // PHP will append " in file:line" to the error
        trigger_error($error, E_USER_WARNING); // Emulate PHP warning

        return new self(null, []);
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
            'tostring'  => [$this->htmlEncode(), "Replace ->$method() with ->string() or ->htmlEncode()"],
            'jsencode'  => [addcslashes((string)$this->rawData, "\x00-\x1F'\"`\n\r\\<>"), "Replace ->$method() with ->jsonEncode() (not identical functionality, code refactoring required)"],
            'striptags' => [new self(is_null($this->rawData) ? null : strip_tags((string)$this->rawData, ...$args), get_object_vars($this)), "Replace ->$method() with ->textOnly()"],
            default     => [null, null],
        };
        if ($deprecationError) {
            self::logDeprecation($deprecationError);
            return $return;
        }

        // Common aliases: throw error with suggestion.  These are used by other libraries or common LLM suggestions
        $methodAliases = [
            // use lowercase for aliases
            'and'            => ['append', 'concat'],
            'andPrefix'      => ['prepend', 'prefix'],
            'bool'           => ['tobool', 'getbool', 'boolean'],
            'dateFormat'     => ['formatdate', 'todate', 'date_format', 'date'],
            'dateTimeFormat' => ['formatdatetime', 'todatetime', 'datetime'],
            'float'          => ['tofloat', 'getfloat'],
            'htmlEncode'     => ['escapehtml', 'encodehtml', 'e', 'html_encode'],
            'int'            => ['toint', 'getint', 'integer'],
            'isEmpty'        => ['isblank', 'empty'],
            'isNotEmpty'     => ['isnotblank', 'hasvalue', 'ispresent', 'notempty'],
            'jsonEncode'     => ['tojson', 'encodejson', 'jsencode', 'json_encode'],
            'numberFormat'   => ['formatnumber', 'number_format'],
            'or'             => ['default', 'ifmissing'],
            'phoneFormat'    => ['formatphone', 'phone', 'phone_format'],
            'set'            => ['setvalue'],
            'string'         => ['tostring', 'getstring'],
            'urlEncode'      => ['escapeurl', 'encodeurl', 'url_encode', 'urlencode'],
            'value'          => ['raw', 'noescape'],
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
        $error      = sprintf("Call to undefined method %s->$method(), $suggestion\n", basename(self::class));
        $caller     = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
        $error      .= "Occurred in {$caller['file']}:{$caller['line']}\n";
        $error      .= "Reported"; // PHP will append " in file:line" to the error
        throw new Error($error);
    }

    /**
     * Show a helpful error message when an unknown method is called.
     */
    public static function __callStatic($method, $args): mixed
    { // NOSONAR - False-positive for unused $args parameter
        $methodLc = strtolower($method);

        // deprecated methods, log and return new method (these may be removed in the future)
        if ($methodLc === 'fromarray') {
            self::logDeprecation("Replace SmartString::$method() with SmartArray::new(\$array)->withSmartStrings()");
            return new SmartArray(...$args);
        }
        if ($methodLc === 'rawvalue') {
            return self::getRawValue(...$args);
        }

        // throw unknown method Error
        // PHP Default Error: Fatal error: Uncaught Error: Call to undefined method SmartString::method() in C:\dev\projects\SmartString\test.php:17
        $baseClass = basename(self::class);
        $caller    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0];
        $error     = "Call to undefined method $baseClass::$method(), call ->help() for available methods.\n";
        $error     .= "Occurred in {$caller['file']}:{$caller['line']}\n";
        $error     .= "Reported"; // PHP will append " in file:line" to the error
        throw new Error($error);
    }

    /**
     * Wrap output in <xmp> tag if text/html and not called from a function that already added <xmp>
     * @noinspection SpellCheckingInspection // ignore all lowercase strtolower function name
     */
    private static function xmpWrap($output): string
    {
        $output             = trim($output, "\n");
        $headersList        = implode("\n", headers_list());
        $hasContentType     = (bool)preg_match('|^\s*Content-Type:\s*|im', $headersList);                          // assume no content type will default to html
        $isTextHtml         = !$hasContentType || preg_match('|^\s*Content-Type:\s*text/html\b|im', $headersList); // match: text/html or ...;charset=utf-8
        $backtraceFunctions = array_map('strtolower', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'));
        $wrapInXmp          = $isTextHtml && !in_array('showme', $backtraceFunctions, true);

        return $wrapInXmp ? "\n<xmp>\n$output\n</xmp>\n" : "\n$output\n";
    }

    /**
     * Logs a deprecation notice
     */
    public static function logDeprecation($error): void
    {
        @trigger_error($error, E_USER_DEPRECATED);  // Trigger a silent deprecation notice for logging purposes
    }

    /**
     * Internal method called by json_encode() to provide raw data for serialization (implements JsonSerializable).
     *
     * You never need to call this directly, as PHP will call it automatically when you pass a SmartString object to json_encode().
     *
     * @see jsonEncode() Preferred for encoded JSON strings, escapes <, >, and & characters so they are safe for embedding in HTML.
 */
    public function jsonSerialize(): mixed
    {
        return $this->rawData;
    }

    /**
     * Helper to convert $value to a float or null, respecting self::$treatNullAsZero.
     */
    private static function getFloatOrNull(mixed $value): ?float
    {
        $value = self::getRawValue($value);  // unwrap SmartStrings
        return match (true) {
            is_float($value)                          => $value,
            is_numeric($value)                        => (float)$value,
            is_null($value) && self::$treatNullAsZero => 0.0,
            default                                   => null,
        };
    }

    #endregion
}
