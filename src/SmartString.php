<?php
declare(strict_types=1);

namespace Itools\SmartString;

use ArrayObject;
use BadMethodCallException;

/**
 * SmartString class provides a fluent interface for various string and numeric manipulations.
 *
 * For inline help, call $smartStart->help() or print_r() on a SmartString object.
 *
 * String methods:
 * @method SmartString textOnly() Convert HTML entities to text and strip tags from a string
 * @method SmartString nl2br() Convert newlines to <br> HTML tags in a string
 * @method SmartString trim(mixed ...$args) Strip whitespace ( or other characters) from the beginning and end of a string
 * @method SmartString maxWords(int $max, string $ellipsis = '...') Limit the string to a certain number of words, appending an ellipsis if needed
 * @method SmartString maxChars(int $max, string $ellipsis = '...') Limit the string to a certain number of characters, appending an ellipsis if needed
 *
 * Numeric
 * @method SmartString percent(int $decimals = 0) Convert the number to a percentage with optional decimal precision
 * @method SmartString percentOf(float $total, int $decimals = 0) Calculate the percentage of the current value compared to a total
 * @method SmartString add(float|int|SmartString $addend) Add a value to the current number
 * @method SmartString subtract(float|int|SmartString $subtrahend) Subtract a value from the current number
 * @method SmartString multiply(float|int|SmartString $multiplier) Multiply the current number by a multiplier
 * @method SmartString divide(float|int|SmartString $divisor) Divide the current number by a divisor
 *
 * Formatting
 * @method SmartString dateFormat(?string $format = null) Format date with class default $dateFormat or specified format
 * @method SmartString dateTimeFormat(?string $format = null) Format date with class default $dateTimeFormat or specified format
 * @method SmartString numberFormat(mixed ...$args) Format the current numeric value using number_format() function
 * @method SmartString phoneFormat() Format a phone number with default $phoneFormat rules or return null
 *
 * Conditional
 * @method SmartString or(int|float|string|SmartString $fallback) Return fallback value if the current value is falsy
 * @method SmartString ifNull(int|float|string $fallback) Return fallback value if the current value is null
 * @method SmartString ifBlank(int|float|string $fallback) Return fallback value if the current value is an empty string
 * @method SmartString isZero(int|float|string $fallback) Return fallback value if the current value is zero
 *
 * Misc
 * @method SmartString apply(callable|string $func, mixed ...$args) Apply a function to the current value
 * @method SmartString help() Display a list of available methods and properties for the current object
 */
class SmartString
{
    public static string $numberFormatDecimal   = '.';               // Default decimal separator
    public static string $numberFormatThousands = ',';               // Default thousands separator
    public static string $dateFormat            = 'Y-m-d';           // Default dateFormat() format
    public static string $dateTimeFormat        = 'Y-m-d H:i:s';     // Default dateTimeFormat() format
    public static array  $phoneFormat           = [                  // Default phoneFormat() formats
        ['digits' => 10, 'format' => '(###) ###-####'],
        ['digits' => 11, 'format' => '# (###) ###-####'],
    ];

    private string|int|float|bool|null $data; // The stored value

    #region Basic Usage

    /**
     * Returns original type and value.
     *
     * @return string|int|float|bool|null An indexed array containing row values.
     */
    public function value(): string|int|float|bool|null
    {
        return $this->data;
    }

    /**
     * Returns a SmartString object for a value, or an ArrayObject of SmartStrings from an array.
     *
     * @example $rows = SmartString::new($resultSet);       // Nested ArrayObject of SmartStrings
     *          $str  = SmartString::new("Hello, World!");  // Single value as SmartString
     *          $req  = SmartString::new($_REQUEST);        // ArrayObject of SmartStrings
     *
     * @param mixed $value
     *
     * @return array|SmartString The newly created SmartString object.
     */
    public static function new(string|int|float|bool|null|array $value): ArrayObject|SmartString
    {
        return is_array($value) ? self::fromArray($value) : new self($value);
    }

    /**
     * Converts arrays to ArrayObjects of SmartString objects, supporting nested arrays.
     *
     * @param array $array
     *
     * @return ArrayObject
     */
    public static function fromArray(array $array): ArrayObject
    {
        $arrayObject = new ArrayObject($array, ArrayObject::ARRAY_AS_PROPS);
        foreach ($arrayObject as $key => $value) {
            $arrayObject[$key] = match (true) {
                is_array($value) => self::fromArray($value),
                default          => new self($value),
            };
        }
        return $arrayObject;
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
        $encoded = htmlspecialchars((string)$this->data, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8');
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
    #region Internals

    public static function help(): void {
        Methods\Misc::help();
    }

    /**
     * Initializes a new Value object with a name and a value.
     *
     * @param string|int|float|bool|SmartString|null $value
     */
    public function __construct(string|int|float|bool|null $value)
    {
        $this->data = $value;
    }

    /**
     * @param string $method
     * @param mixed ...$args
     * @return SmartString The value of the property, possibly encoded.
     */
    public function __call(string $method, array $args): SmartString
    {
        // Find and call method if it exists
        foreach (['Numeric', 'Strings', 'Formatting', 'Conditional', 'Misc'] as $class) {
            $fullClassName = __NAMESPACE__ . "\\Methods\\$class";
            if (method_exists($fullClassName, $method)) {
                $clonedObject       = clone $this;
                $clonedObject->data = $fullClassName::$method($this->data, ...$args);
                return $clonedObject;
            }
        }

        throw new BadMethodCallException("Method $method does not exist");
    }

    /**
     * Dynamically load methods as needed to save on memory and processing time.
     *
     * @param string $property
     *
     * @return SmartString The value of the property, possibly encoded.
     */
    public function __get(string $property): SmartString
    {
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
     * Show useful developer info about object when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public function __debugInfo(): array
    {
        return DebugInfo::debugInfo($this);
    }

    #endregion
}
