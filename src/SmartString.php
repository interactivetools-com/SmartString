<?php
/** @noinspection PhpUnused */
/** @noinspection UnknownInspectionInspection */
/** @noinspection PhpDuplicateMatchArmBodyInspection */

declare(strict_types=1);

namespace Itools\SmartString;

use ArrayObject;
use InvalidArgumentException;

/**
 * For inline help call $smartStart->help() or see the DebugInfo class
 */
class SmartString
{
    private string|int|float|bool|null $data; // The value of the object

    #region Basic Usage

    /**
     * Returns a SmartString object from a given value, or an ArrayObject of SmartString objects from an array.
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
        return match (true) {
            is_array($value) => self::createArrayObject($value),
            default          => new self($value),
        };
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

    /**
     * Returns the value of the Field as a string, encoded with the specified method (html, js, url)
     *
     * @return int
     */

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
     * Distinct Features from vanilla htmlspecialchars($input):
     * - Single quotes: Single 'quotes' are also encoded in addition to double "quotes".
     * - HTML5 output: Generates HTML5-compliant output like <br> instead of <br /> and &apos; instead of &#039;.
     * - Invalid chars: Replaces invalid multibyte sequences with the Unicode replacement glyph (�) instead of returning empty string .
     * - UTF-8 Charset: Charset is hardcoded to UTF-8, disregarding php.ini 'default_charset'.
     *
     * Example Outputs:
     * 1. `$comment = htmlEncode('OMG that was so funny >_<');        // Outputs: OMG that was so funny &gt;_&lt;`
     * 2. `$company = htmlEncode("O'Reilly & Sons");                  // Outputs: O&apos;Reilly &amp; Sons`
     * 3. `$symbol  = htmlEncode('Why "flexible work hours" matter'); // Outputs: Why &quot;flexible work hours&quot; matter`
     *
     * Example Usage:
     * 1. `echo "<p>" . htmlEncode($comment) . "</p>";              // Safely render user comments`
     * 2. `<img ... title="<?php echo htmlEncode($company) ?>">     // Safely encode title attributes in HTML tags`
     *
     * @return string The HTML-encoded output.
     *
     */
    public function htmlEncode(): string
    {
        // Encode input
        return htmlspecialchars(
            string: (string)$this->data,  // input to encode, type cast input to string if it's not already
            flags: ENT_QUOTES|                // encode ' as &apos;
                   ENT_SUBSTITUTE|            // replace invalid code unit sequences with � instead of returning empty string
                   ENT_HTML5,                 // encode as HTML 5
            encoding: 'UTF-8',                // Character encoding
        /*  double_encode: true,              // Double encoding option (default value) */
        );
    }

    /**
     * URL encodes a string for safe use within URLs.
     *
     * Example Outputs:
     * 1. $question = urlEncode('Is this a test?');    // Outputs: Is+this+a+test%3F
     * 2. $company  = urlEncode('A&B Consulting');     // Outputs: A%26B+Consulting
     * 3. $offer    = urlEncode('Save 10%+ off');      // Outputs: Save+10%25%2B+off
     * 4. $postTags = urlEncode('Code=Life #DevLife'); // Outputs: Code%3DLife+%23DevLife
     *
     * Example Usage:
     * 1. `echo "https://example.com/add?company=$company&offer=$offer"; // insert vars`
     * 2. `echo "https://example.com/post?q=<?php echo $question; ?>&tags=<?php echo $postTags; ?>"; // php tags`
     *
     * We use PHP's urlencode() internally, which aligns with RFC 3986 but uses '+' for spaces:
     *  - Alphanumeric characters are not encoded.
     *  - Reserved characters are not encoded. e.g., !, *, ', (, )
     *  - Unsafe characters (e.g., :, /, ?, #, [, ], @, &) are percent-encoded.
     *  - Spaces are encoded as '+'
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
     * @throws JsonException If encoding fails.
     */
    public function jsonEncode(): string
    {
        $flags = JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
        return json_encode($this->data, $flags);
    }

    public function noEncode(): string|int|float|bool|null
    {
        return $this->value();
    }

    #endregion
    #region String Manipulation

    public function stripTags(...$args): SmartString
    {
        $newValue = strip_tags((string)$this->data, ...$args);
        return $this->cloneWithValue($newValue);
    }

    public function nl2br(): SmartString
    {
        $newValue = nl2br((string)$this->data, false);
        return $this->cloneWithValue($newValue);
    }

    public function trim(...$args): SmartString
    {
        $newValue = trim((string)$this->data, ...$args);
        return $this->cloneWithValue($newValue);
    }

    #endregion
    #region Formatting

    /**
     * Attempts to format the Field value as a date using the specified format.
     *
     * @param $format
     *
     * @return SmartString
     */
    public function dateFormat($format): SmartString
    {
        $timestamp = match (true) {
            is_bool($this->data)    => null,
            is_null($this->data)    => null,
            is_numeric($this->data) => (int)$this->data,
            default                 => strtotime($this->data) ?: null,
        };

        $newValue = $timestamp ? date($format, $timestamp) : null; // return null on null|0
        return $this->cloneWithValue($newValue);
    }

    /**
     * Calls the number_format() function on the current value.
     *
     * @param mixed ...$args
     *
     * @return \Itools\SmartString\SmartString
     */
    public function numberFormat(...$args): SmartString
    {
        $newValue = match (true) {
            is_numeric($this->data) => number_format((float)$this->data, ...$args),
            default                 => null,
        };
        return $this->cloneWithValue($newValue);
    }

    #endregion
    #region Numeric Operations


    /*
     * Converts a number to a percentage. e.g., 0.1234 => 12.34%
     * Returns the Field object for chaining and null if the value is null.
     */
    public function percent($decimals = 0): SmartString
    {
        $newValue = match (true) {
            is_numeric($this->data) => number_format((float)$this->data * 100, $decimals).'%',
            default                 => null,
        };
        return $this->cloneWithValue($newValue);
    }

    public function percentOf(int|float|SmartString $total, ?int $decimals = 0): ?SmartString
    {
        $totalValue = $total instanceof self ? $total->value() : $total;

        $newValue = match (true) {
            !is_numeric($this->data)   => null,
            !is_numeric($totalValue)   => null,
            (float)$totalValue === 0.0 => null, // avoid division by zero error
            default                    => number_format((float)$this->data / (float)$totalValue * 100, $decimals).'%',
        };

        return $this->cloneWithValue($newValue);
    }

    /**
     * Subtracts a value from the current field value.
     *
     * @param int|float|SmartString $value The value to subtract
     *
     * @return SmartString
     */
    public function subtract(int|float|SmartString $value): SmartString
    {
        $subtractValue = $value instanceof self ? $value->value() : $value;

        $newValue = match (true) {
            !is_numeric($this->data)    => null,
            !is_numeric($subtractValue) => null,
            default                     => (float)$this->data - (float)$subtractValue,
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
     * Returns new value if the current value is falsey, e.g., null, false, empty string, or zero
     *
     * @param $value
     *
     * @return SmartString
     */
    public function or($value): SmartString
    {
        return $this->cloneWithValue($this->data ?: $value);
    }

    public function ifNull($value): SmartString
    {
        return $this->cloneWithValue($this->data ?? $value);
    }

    public function ifBlank($value): SmartString
    {
        $newValue = $value === "" ? $value : $this->data;
        return $this->cloneWithValue($newValue);
    }

    #endregion
    #region Misc

    /**
     * Applies a function to the value.
     * The function can be specified as a callable, a string (function name), or null.
     *
     * @param callable|string $func    The function to apply
     * @param mixed           ...$args Additional arguments to pass to the function
     *
     * @return SmartString
     */
    public function apply(callable|string $func, mixed ...$args): SmartString
    {
        if (is_string($func) && !function_exists($func)) {
            throw new InvalidArgumentException("Function '$func' does not exist");
        }

        $newValue = match (true) {
            is_callable($func) => $func($this->value(), ...$args),
            is_string($func)   => $func($this->value(), ...$args),
            default            => throw new InvalidArgumentException("Invalid function type"),
        };

        return $this->cloneWithValue($newValue);
    }

    public function help(): void
    {
        print_r($this);
    }

    #endregion

    #region Internals

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
        if (method_exists($this, $property)) {
            $warning .= ", ensure you use brackets and curly braces when in strings.\n";
            $warning .= sprintf('Examples: $var->%s() or "{$var->%s()}"', $property, $property);
        } // Otherwise suggest help() method
        else {
            $warning .= ", use the ->help() method to see available methods and properties";
        }

        // Return null and show warning message
        trigger_error($warning, E_USER_WARNING);
        return new self(null);
    }

    /**
     * Magic setter which prevents modification of read-only properties.
     *
     * @throws InvalidArgumentException Always, since properties are read-only.
     * @noinspection MagicMethodsValidityInspection // EA: __set should have pair method __isset (not applicable in this case)
     * @SuppressWarnings("php:S1172") // SonarLint: Remove the unused function parameter ... (false positive, required for magic methods)
     */
    public function __set($name, $value): void // NoSonar
    {
        $error = sprintf("Setting %s properties is not supported", basename(__CLASS__));
        throw new InvalidArgumentException($error);
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

    /**
     * Converts arrays to ArrayObjects of SmartString objects, supporting nested arrays.
     *
     * @param array $array
     *
     * @return \ArrayObject
     */
    public static function createArrayObject(array $array): ArrayObject
    {
        // convert arrays to ArrayObjects
        $arrayObject = new ArrayObject($array, ArrayObject::ARRAY_AS_PROPS);
        foreach ($arrayObject as $key => $value) {
            $arrayObject[$key] = match (true) {
                is_array($value) => self::createArrayObject($value),
                default          => new self($value),
            };
        }
        return $arrayObject;
    }

    private function cloneWithValue($newValue): self
    {
        $clonedObject       = clone $this;
        $clonedObject->data = $newValue;
        return $clonedObject;
    }

    #endregion
}
