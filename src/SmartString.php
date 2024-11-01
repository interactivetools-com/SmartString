<?php

declare(strict_types=1);

namespace Itools\SmartString;

use InvalidArgumentException;
use Itools\SmartArray\SmartArray;
use JsonSerializable;

/**
 * SmartString class provides a fluent interface for various string and numeric manipulations.
 *
 * For inline help, call $smartStart->help() or print_r() on a SmartString object.
 */
class SmartString implements JsonSerializable
{
    private string|int|float|bool|null $data; // The stored value and type (not html-encoded)

    public static string $numberFormatDecimal   = '.';               // Default decimal separator
    public static string $numberFormatThousands = ',';               // Default thousands separator
    public static string $dateFormat            = 'Y-m-d';           // Default dateFormat() format
    public static string $dateTimeFormat        = 'Y-m-d H:i:s';     // Default dateTimeFormat() format
    public static array  $phoneFormat           = [                  // Default phoneFormat() formats
                                                                     ['digits' => 10, 'format' => '(###) ###-####'],
                                                                     ['digits' => 11, 'format' => '# (###) ###-####'],
    ];

    #region Basic Usage

    /**
     * Initializes a new Value object with a name and a value.
     *
     * @param string|int|float|bool|SmartString $value
     * @example $value = new SmartString('<b>Hello World!</b>');
     *
     */
    public function __construct(string|int|float|bool|null $value)
    {
        $this->data = $value;
    }

    /**
     * Returns a SmartString object for a value.
     *
     * @example $rows = SmartArray::new($resultSet);       // Nested SmartArray of SmartStrings
     *          $str  = SmartString::new("Hello, World!");  // Single value as SmartString
     *
     * @param mixed $value
     *
     * @return array|SmartString The newly created SmartString object.
     */
    public static function new(string|int|float|bool|null|array $value): SmartArray|SmartString
    {
        // Deprecated: Convert array to SmartArray object
        if (is_array($value)) {
            // Trigger a silent deprecation notice for logging purposes
            @user_error('SmartString::new($array) is deprecated, use "SmartArray::new($array)" instead.', E_USER_DEPRECATED);
            return new SmartArray($value);
        }

        // Return SmartString object for other types
        return new self($value);
    }

    /**
     * Returns original type and value.
     *
     * @return string|int|float|bool|null An indexed array containing row values.
     */
    public function value(): string|int|float|bool|null
    {
        return $this->data;
    }

    #endregion
    #region Type Conversion

    public function int(): int
    {
        return (int)$this->data;
    }

    public function float(): float
    {
        return (float)$this->data;
    }

    public function bool(): bool
    {
        return (bool)$this->data;
    }

    public function string(): string
    {
        return (string)$this->data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    /**
     * Returns HTML-encoded string representation of the Value when object is accessed in string context.
     *
     * @return string The HTML-encoded representation of the value.
     */
    public function __toString(): string
    {
        return $this->htmlEncode();
    }

    #endregion
    #region Encoding Methods

    /**
     * HTML encodes a given input for safe output in an HTML context.
     *
     * @param bool $encodeBr HTML encode <br> tags in content, enabled to prevent "{$str->nl2br()}" from being double-encoded.
     *
     * @return string Html-encoded output
     */
    public function htmlEncode(bool $encodeBr = false): string
    {
        $encoded = htmlspecialchars((string)$this->data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return $encodeBr ? $encoded : preg_replace("|&lt;(br\s*/?)&gt;|i", "<$1>", $encoded);
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
        return urlencode((string)$this->data);
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
        return json_encode($this->data, $flags);
    }

    public function noEncode(): string|int|float|bool|null
    {
        return $this->value();
    }

    #endregion
#region String Manipulation

    /**
     * @return SmartString
     */
    public function textOnly(): SmartString
    {
        $newValue = match (true) {
            is_null($this->data) => null,
            default              => trim(strip_tags(html_entity_decode((string)$this->data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'))),
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * @return SmartString
     */
    public function nl2br(): SmartString
    {
        $newValue = match (true) {
            is_null($this->data) => null,
            default              => nl2br((string)$this->data, false),
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param ...$args
     * @return SmartString
     */
    public function trim(...$args): SmartString
    {
        $newValue = match (true) {
            is_null($this->data) => null,
            default              => trim((string)$this->data, ...$args),
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int $max
     * @param string $ellipsis
     * @return SmartString
     */
    public function maxWords(int $max, string $ellipsis = "..."): SmartString
    {
        $newValue = null;
        if (!is_null($this->data)) {
            $text     = trim((string)$this->data);
            $words    = preg_split('/\s+/u', $text);
            $newValue = implode(' ', array_slice($words, 0, $max));
            $newValue = preg_replace('/\p{P}+$/u', '', $newValue); // Remove trailing punctuation
            if (count($words) > $max) {
                $newValue .= $ellipsis;
            }
        }

        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int $max
     * @param string $ellipsis
     * @return SmartString
     */
    public function maxChars(int $max, string $ellipsis = "..."): SmartString
    {
        $newValue = null;
        if (!is_null($this->data)) {
            $text = preg_replace('/\s+/u', ' ', trim((string)$this->data));

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

        return $this->cloneWithValue($newValue);
    }

    /**
     * @param ...$args
     * @return SmartString
     * @deprecated Use textOnly() instead, this method will be removed in the future.
     */
    public function stripTags(...$args): SmartString
    {
        $newValue = match (true) {
            is_null($this->data) => null,
            default              => strip_tags((string)$this->data, ...$args),
        };
        return $this->cloneWithValue($newValue);
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
            is_null($this->data)    => null,
            is_numeric($this->data) => (int)$this->data,
            default                 => strtotime($this->data) ?: null,
        };

        $newValue = $timestamp ? date($format, $timestamp) : null; // return null on null or 0

        return $this->cloneWithValue($newValue);
    }

    /**
     * Format date by $dateTimeFormat or specified format.  Returns null on failure.
     *
     * @param string|null $format Date format (default: SmartString::$dateFormat)
     * @return SmartString Formatted date or null on failure
     */
    public function dateTimeFormat(?string $format = null): SmartString
    {
        $format   ??= self::$dateTimeFormat;
        $newValue = $this->dateFormat($format);
        return $this->cloneWithValue($newValue);
    }

    /**
     * Calls the number_format() function on the current value.
     *
     * @param int|null $decimals
     * @return SmartString
     */
    public function numberFormat(?int $decimals = 0): SmartString
    {
        $newValue = match (true) {
            !is_numeric($this->data) => null,
            default                  => number_format((float)$this->data, $decimals, self::$numberFormatDecimal, self::$numberFormatThousands),
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * Formats a phone number according to the specified format or returns null.
     *
     * @return SmartString The formatted phone number or null if input is invalid
     */
    public function phoneFormat(): SmartString
    {
        $newValue = null;

        // get array of digits only
        $digits = str_split(preg_replace('/\D/', '', (string)$this->data));

        // get phone format by number of digits, e.g., 10 => '(###) ###-####'
        $phoneFormatByDigits = array_column(self::$phoneFormat, 'format', 'digits');
        $phoneFormat         = $phoneFormatByDigits[count($digits)] ?? null;

        // Replace # with digits
        if ($phoneFormat) {
            $format   = str_replace('#', '%s', $phoneFormat);
            $newValue = sprintf($format, ...$digits);
        }

        return $this->cloneWithValue($newValue);
    }

#endregion
#region Numeric Operations

    /**
     * Converts a number to a percentage. e.g., 0.1234 => 12.34%
     * Returns the Field object for chaining and null if the value is null.
     *
     * @param int $decimals
     * @return SmartString
     */
    public function percent(int $decimals = 0): SmartString
    {
        $newValue = match (true) {
            is_numeric($this->data) => number_format($this->data * 100, $decimals) . '%',
            default                 => null,
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int|float|SmartString $total
     * @param int|null $decimals
     * @return SmartString
     */
    public function percentOf(int|float|SmartString $total, ?int $decimals = 0): SmartString
    {
        $totalValue = $total instanceof self ? $total->value() : $total;

        $newValue = match (true) {
            !is_numeric($this->data)   => null,
            !is_numeric($totalValue)   => null,
            (float)$totalValue === 0.0 => null, // avoid division by zero error
            default                    => number_format($this->data / $totalValue * 100, $decimals) . '%',
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * Adds a value to the current field value.  Returns null if the value is not numeric.
     *
     * @param int|float|SmartString $addend
     * @return SmartString
     */
    public function add(int|float|SmartString $addend): SmartString
    {
        $addValue = $addend instanceof self ? $addend->value() : $addend;

        $newValue = match (true) {
            !is_numeric($this->data) => null,
            !is_numeric($addValue)   => null,
            default                  => $this->data + $addValue,
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * Subtracts a value from the current field value.
     *
     * @param int|float|SmartString $subtrahend
     * @return SmartString
     */
    public function subtract(int|float|SmartString $subtrahend): SmartString
    {
        $subtractValue = $subtrahend instanceof self ? $subtrahend->value() : $subtrahend;

        $newValue = match (true) {
            !is_numeric($this->data)    => null,
            !is_numeric($subtractValue) => null,
            default                     => $this->data - $subtractValue,
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * Multiplies the current field value by the given value.
     *
     * @param int|float|SmartString $multiplier
     * @return SmartString
     */
    public function multiply(int|float|SmartString $multiplier): SmartString
    {
        $multiplyValue = $multiplier instanceof self ? $multiplier->value() : $multiplier;

        $newValue = match (true) {
            !is_numeric($this->data)    => null,
            !is_numeric($multiplyValue) => null,
            default                     => $this->data * $multiplyValue,
        };
        return $this->cloneWithValue($newValue);
    }

    /**
     * Divides the current field value by the given value.
     *
     * @param int|float|SmartString $divisor
     *
     * @return SmartString
     */
    public function divide(int|float|SmartString $divisor): SmartString
    {
        $divisorValue = $divisor instanceof self ? $divisor->value() : $divisor;

        $newValue = match (true) {
            !is_numeric($this->data)     => null,
            !is_numeric($divisorValue)   => null,
            (float)$divisorValue === 0.0 => null, // avoid division by zero error
            default                      => $this->data / $divisorValue,
        };
        return $this->cloneWithValue($newValue);
    }

#endregion
#region Conditional Operations

    /**
     * Returns new value if the current value is falsy, e.g., null, false, empty string, or zero (or "0.0")
     *
     * @param int|float|string|SmartString $fallback
     * @return SmartString
     */
    public function or(int|float|string|SmartString $fallback): SmartString
    {
        $isZero        = is_numeric($this->data) && (float)$this->data === 0.0;
        $useFallback   = $isZero || empty($this->data);
        $fallbackValue = $fallback instanceof self ? $fallback->value() : $fallback;

        $newValue = $useFallback ? $fallbackValue : $this->data;
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int|float|string|SmartString $fallback
     * @return SmartString
     */
    public function ifNull(int|float|string|SmartString $fallback): SmartString
    {
        $fallbackValue = $fallback instanceof self ? $fallback->value() : $fallback;
        $newValue      = $this->data ?? $fallbackValue;
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int|float|string|SmartString $fallback
     * @return SmartString
     */
    public function ifBlank(int|float|string|SmartString $fallback): SmartString
    {
        $fallbackValue = $fallback instanceof self ? $fallback->value() : $fallback;
        $newValue      = $this->data === "" ? $fallbackValue : $this->data;
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param int|float|string|SmartString $fallback
     * @return SmartString
     */
    public function isZero(int|float|string|SmartString $fallback): SmartString
    {
        $isZero        = is_numeric($this->data) && (float)$this->data === 0.0;
        $fallbackValue = $fallback instanceof self ? $fallback->value() : $fallback;
        $newValue      = $isZero ? $fallbackValue : $this->data;
        return $this->cloneWithValue($newValue);
    }

    /**
     * @param string|int|float|bool|null $condition
     * @param string|int|float|bool|object|null $valueIfTrue
     * @return SmartString
     */
    public function if(string|int|float|bool|null $condition, string|int|float|bool|null|object $valueIfTrue): SmartString
    {
        $newValue = $this->data;
        if ($condition) {
            $newValue = is_callable([$valueIfTrue, 'value']) ? $valueIfTrue->value() : $valueIfTrue;
        }

        return $this->cloneWithValue($newValue);
    }

    /**
     * @param string|int|float|bool|object|null $newValue
     * @return SmartString
     */
    public function set(string|int|float|bool|null|object $newValue): SmartString // NOSONAR: Unused parameter $value
    {
        $newValue = is_callable([$newValue, 'value']) ? $newValue->value() : $newValue;
        return $this->cloneWithValue($newValue);
    }
#endregion
#region Misc

    /**
     * Applies a function to the value.
     * The function can be specified as a callable, a string (function name), or null.
     *
     * @param callable|string $func The function to apply
     * @param mixed ...$args Additional arguments to pass to the function
     *
     * @return SmartString
     */
    public function apply(callable|string $func, mixed ...$args): SmartString
    {
        if (!is_callable($func)) {
            throw new InvalidArgumentException("Function '$func' is not callable");
        }

        $newValue = $func($this->data, ...$args);
        return $this->cloneWithValue($newValue);
    }

#endregion
    #region Debugging and Help


    /**
     * @param mixed|null $value
     * @return mixed
     * @noinspection GrazieInspection
     */
    public function help(mixed $value = null): mixed
    {
        $docs = <<<__TEXT__
            This 'SmartString' object automatically HTML-encodes output in string contexts for XSS protection.
            It also provides access to the original value, alternative encoding methods, and various utility methods.
            
            Creating SmartStrings
            \$str = SmartString::new("It's easy!<hr>");
            \$req = SmartArray::new(\$_REQUEST);  // SmartArray of SmartStrings
            
            Automatic HTML-encoding in string contexts:
            echo \$str;             // "It&apos;s easy!&lt;hr&gt;"
            print \$str;            // "It&apos;s easy!&lt;hr&gt;"
            (string) \$str;         // "It&apos;s easy!&lt;hr&gt;"
            \$new = \$str."\\n";      // "It&apos;s easy!&lt;hr&gt;\\n"
            echo \$str->value();    // "It's easy!<hr>" (original value)
            
            Value access:
            \$str->value()          // Access original value
            \$str->noEncode()       // Alias for ->value() for readability
            "{\$str->value()}"      // Output original value in string context
            "{\$str->noEncode()}"   // Output original value in string context
            print_r(\$str)          // show object value in a readable debug format (for developers)
            
            Working with arrays
            \$user = ['id' => 42, 'name' => "John O'Reilly", "lastLogin" => "2024-09-10 14:30:00"];
            \$u    = SmartArray::new(\$user);                      // SmartArray of SmartStrings
            "Hello, \$u->name"                                    // "Hello, John O&apos;Reilly"
            "Hello, {\$u->name->noEncode()}"                      // Returns "Hello, John O'Reilly"
            "Last login: {\$u->lastLogin->dateFormat('F j, Y')}"  // "Last login: Sep 10, 2024"
            
            Type conversion (returns value):
            ->string()                Returns value as string (returns original value, use ->htmlEncode() for HTML-encoded string)
            ->int()                   Returns value as integer
            ->bool()                  Returns value as boolean
            ->float()                 Returns value as float
            
            Encoding methods (returns value):
            ->urlEncode()             Returns URL-encoded string, example: "?user={\$user->name->urlEncode()}"
            ->jsonEncode()            Returns JSON-encoded value, example: "let user='{\$user->name->jsonEncode()}'"
            ->htmlEncode()            Returns HTML-encoded string (for readability and non-string contexts)
            ->noEncode()              Alias for ->value() for readability, example: "{\$record->wysiwyg->noEncode()}"
            
            String Manipulation (returns object, chainable):
            ->textOnly(...)           Remove HTML tags, decode HTML entities, and trims whitespace
            ->nl2br()                 Convert newlines to br tags
            ->trim(...)               Trim leading and trailing whitespace, supports same parameters as trim()
            ->maxWords(\$max)          Limit words to \$max, if truncated adds ... (override with second parameter)
            ->maxChars(\$max)          Limit chars to \$max, if truncated adds ... (override with second parameter)
            
            Formatting (returns object, chainable):
            ->numberFormat(...)       Format number, args: \$decimals = 0
            ->dateFormat(...)         Format date in default format or date() format (e.g., "Y-m-d")
            ->dateTimeFormat(...)     Format date/time in default format or date() format (e.g., "Y-m-d H:i:s")
            ->phoneFormat()           Format phone number in your default format
            
            Numeric Operations (returns object, chainable):
            ->percent()               Returns value as a percentage, e.g. 0.5 becomes 50%
            ->percentOf(\$total)       Returns value as a percentage of \$total, e.g., 24 of 100 becomes 24%
            ->add(\$value)             Returns value plus \$value
            ->subtract(\$value)        Returns value minus \$value
            ->divide(\$value)          Returns value divided by \$value
            ->multiply(\$value)        Returns value multiplied by \$value
            
            Conditional Operations (returns object, chainable):
            ->or('replacement')       Changes value if the Field is falsy (false, null, zero, or "")
            ->ifBlank('replacement')  Changes value if the Field is blank (empty string)
            ->ifNull('replacement')   Changes value if the Field is null or undefined (chainable)
            ->ifZero('replacement')   Changes value if the Field is zero (0, 0.0, "0", or "0.0")
            
            Miscellaneous:
            ->apply()                 Apply a callback or function to the value, e.g. ->apply('strtoupper')
            
            Setting defaults (at the top of your script or in an init file):
            
            SmartString::\$numberFormatDecimal   = '.';             // Default decimal separator
            SmartString::\$numberFormatThousands = ',';             // Default thousands separator
            SmartString::\$dateFormat            = 'Y-m-d';         // Default dateFormat() format
            SmartString::\$dateTimeFormat        = 'Y-m-d H:i:s';   // Default dateTimeFormat() format
            SmartString::\$phoneFormat           = [                // Default phone number formats
                ['digits' => 10, 'format' => '(###) ###-####'],
                ['digits' => 11, 'format' => '# (###) ###-####'],
            ];
            __TEXT__;

        // output docs
        echo DebugInfo::xmpWrap("\n$docs\n\n");

        // return original value
        return $value;
    }

    /**
     * Show useful developer info about object when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public function __debugInfo(): array
    {
        return DebugInfo::debugInfo($this);
    }

    /**
     * @param string $property
     *
     * @return SmartString The value of the property, possibly encoded.
     */
    public function __get(string $property): SmartString
    {
        // This is typically called when a user tries to access a method without brackets or curly braces
        // e.g., echo "Hello, $name->htmlEncode()" instead of "Hello, {$name->htmlEncode()}"
        // In this case, we show a warning message and suggest the user use brackets and curly braces

        // Define warning message
        // Default Warning: Undefined property: Itools\SmartString\SmartString::$property in C:\path\file.php on line 9
        $warning = sprintf("%s property \$var->$property is undefined", basename(__CLASS__));

        // Show helpful message if user trying to access method without brackets or curly braces
        // e.g., echo "Hello, $name->htmlEncode()" instead of "Hello, {$name->htmlEncode()}"
        $warning .= ", ensure you use brackets and curly braces when in strings.\n";
        $warning .= sprintf('Examples: $var->%s() or "{$var->%s()}"', $property, $property);

        // Otherwise suggest help() method
        $warning .= ", use the ->help() method to see available methods and properties\n";

        // Return null and show warning message
        trigger_error($warning, E_USER_WARNING);
        return new self(null);
    }

    #endregion
    #region Deprecated

    /**
     * Converts an array to a SmartArray object, supporting nested arrays.
     *
     * @param array $array The array to convert.
     * @return SmartArray The resulting SmartArray object.
     * @deprecated Use the SmartArray constructor instead: $var = SmartArray::new($array)
     *
     */
    public static function fromArray(array $array): SmartArray
    {
        // Trigger a silent deprecation notice for logging purposes
        @user_error('SmartString::fromArray() is deprecated, use "SmartArray::new($array)" instead.', E_USER_DEPRECATED);
        return new SmartArray($array);
    }

    #endregion
    #region Internal

    private function cloneWithValue($newValue): self
    {
        $clonedObject       = clone $this;
        $clonedObject->data = $newValue;
        return $clonedObject;
    }

    #endregion
}
