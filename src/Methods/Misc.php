<?php
/** @noinspection UnknownInspectionInspection */

/** @noinspection PhpUnused */
declare(strict_types=1);

namespace Itools\SmartString\Methods;

use Itools\SmartString\DebugInfo;
use Itools\SmartString\SmartString;

/**
 * Misc methods for SmartString class.
 */
class Misc
{

    /**
     * Applies a function to the value.
     * The function can be specified as a callable, a string (function name), or null.
     *
     * @param callable|string $func The function to apply
     * @param mixed ...$args Additional arguments to pass to the function
     *
     * @return SmartString
     */
    public static function apply(int|float|string|null $value, callable|string $func, mixed ...$args): mixed
    {
        if (is_string($func) && !function_exists($func)) {
            throw new InvalidArgumentException("Function '$func' does not exist");
        }

        return match (true) {
            is_callable($func) => $func($value, ...$args),
            is_string($func)   => $func($value, ...$args),
            default            => throw new InvalidArgumentException("Invalid function type"),
        };
    }

    public static function help(mixed $value = null): mixed
    {
        $docs = <<<__TEXT__
            This 'SmartString' object automatically HTML-encodes output in string contexts for XSS protection.
            It also provides access to the original value, alternative encoding methods, and various utility methods.
            
            Creating SmartStrings
            \$str = SmartString::new("It's easy!<hr>"); 
            \$req = SmartString::fromArray(\$_REQUEST);  // ArrayObject of SmartStrings

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
            \$u    = SmartString::fromArray(\$user);               // ArrayObject of SmartStrings
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
                ['digits' => 11, 'format' => '1-###-###-####'],
            ];

        __TEXT__;

        // output docs
        echo DebugInfo::xmpWrap("\n$docs\n\n");

        // return original value
        return $value;
    }

}
