<?php
/** @noinspection UnknownInspectionInspection */

declare(strict_types=1);

namespace Itools\SmartString;

use InvalidArgumentException;
use ReflectionClass, ReflectionMethod;
use SplFileObject;

/**
 * DebugInfo
 */
class DebugInfo
{
    #region Errors

    /**
     * Get a list of public methods for a class.
     *
     * @param string $class
     * @param array|null $excludeMethods
     *
     * @return array
     * @throws \ReflectionException
     */
    private static function getPublicMethods(string $class, ?array $excludeMethods = []): array
    {
        // get a list of available public methods for this class
        $reflectionMethods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);
        $publicMethodNames = array_map(static fn($method) => $method->name, $reflectionMethods);

        // remove magic methods
        $publicMethodNames = array_filter($publicMethodNames, static fn($method) => !str_starts_with($method, '__'));

        // remove excluded methods
        $publicMethodNames = array_diff($publicMethodNames, $excludeMethods);

        //
        sort($publicMethodNames);

        //
        return $publicMethodNames;
    }

    #endregion
    #region __debugInfo Method

    /**
     * Show useful developer info about object when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public static function debugInfo($fieldObj): array
    {
        static $callCounter = 0; // track how many times we've been called

        // get var name and value
        $varName = '$'.(self::getCallerVarName() ?? 'var');
        $varValue = $fieldObj->value();

        // Get Basic Usage Info
        $isSimpleVar     = preg_match("/^\\$\w+$/", $varName) || preg_match("/^\\$\w+->\w+$/", $varName); // $var or $record->name
        $quotedVarName   = $isSimpleVar ? '"'.$varName.'"' : '"{'.$varName.'}"';
        $quotedValueCall = '"{'.$varName.'->noEncode()}"';
        $basicUsageArray = [
            [$varName, "Itools\SmartString\SmartString Object", '// Field object itself'],
            [$varName."->value()", $fieldObj->value(), '// Access original value'],
            [$quotedValueCall, $fieldObj->value(), '// Output original value in string context'],
            [$quotedVarName, $fieldObj->__toString(), '// HTML-encoded in string contexts: "$f", $f."", echo $f, print $f, (string)$f'],
        ];

        // Format Basic Usage Info
        $maxLengthByCol = array_map(static fn($col) => max(array_map('strlen', $col)), array_map(null, ...$basicUsageArray));
        $basicUsageText = implode("\n", array_map(static function ($values) use ($maxLengthByCol) {
            return sprintf("%-$maxLengthByCol[0]s = %-$maxLengthByCol[1]s %s", ...$values);
        }, $basicUsageArray));

        // Check for NULL values
        $nullWarning = "";
        if ($varValue === null) {
            $nullWarning = "\nThis field has a NULL value, either from a NULL in the database or due to accessing a non-existent column.\n";
        }

        // create output
        $output     = <<<__TEXT__
            This 'SmartString' object automatically HTML-encodes output in string contexts for XSS protection.
            It also provides access to the original value, alternative encoding methods, and various utility methods.
            $nullWarning
            Basic Usage:
            $basicUsageText
            
            Value retrieval and encoding (returns value):
            ->value()               Original unencoded value
            ->noEncode()            Alias for ->value() for readability, example: "{\$record->wysiwyg->noEncode()}"
            ->htmlEncode()          HTML-encoded string (for readability and non-string contexts)
            ->urlEncode()           URL-encoded string, example: "?user={\$user->name->urlEncode()}"
            ->jsEncode()            JavaScript-encoded, example: "let user='{\$user->name->jsEncode()}'"

            Type conversion (returns value):
            ->bool()                Value as boolean
            ->int()                 Value as integer
            ->float()               Value as float
            ->string()              Value as string (returns original value, use ->htmlEncode() for HTML-encoded string)
            
            String Manipulation (returns object, chainable):
            ->stripTags()           Remove HTML tags
            ->nl2br()               Convert newlines to br tags
            ->trim(...)             Trim whitespace (default \$characters = " \\n\\r\\t\\v\\0")
            
            Date Formatting (returns object, chainable):
            ->dateFormat(\$format)   Format date using PHP date() function syntax (e.g., "Y-m-d H:i:s")
            
            Date Formatting (returns object, chainable):
            ->numberFormat(...)     Format number (\$number, \$decimals)
            ->percent()             Returns value as a percentage, e.g. 0.5 becomes 50%
            ->percentOf(\$total)     Returns value as a percentage of \$total, e.g., 24 of 100 becomes 24%
            ->subtract(\$value)      Returns value minus \$value
            ->divide(\$value)        Returns value divided by \$value
                                    
            Miscellaneous:
            ->or('new value')       Changes value if the Field is falsey (false, null, 0, or "")
            ->ifBlank('new value')   Changes value if the Field is blank (empty string)
            ->ifNull('new value')   Changes value if the Field is null or undefined (chainable)
            ->apply()               Apply a callback or function to the value, e.g. ->apply('strtoupper')
            ->help()                Output this help text
            
            Field Value:
            __TEXT__;

        // Just show value on subsequent calls
        $prettyVarDump = self::getPrettyVarDump($varValue);
        if (++$callCounter > 1) {
            return ["__DEBUG_INFO__" => "$varName = $prettyVarDump"];
        }

        return self::getOutputAsDebugInfoArray("$output\n$prettyVarDump");
    }

    #endregion
    #region Utility Methods

    private static function getOutputAsDebugInfoArray(string $output): array
    {
        $output = str_replace("\n", "\n        ", "\n$output"); // left pad with spaces

        // var_dump wraps output in "" so final line looks like : SmartString  Value: "string"", so we add a \n for clarity
        if (self::inCallStack('var_dump')) {
            $output .= "\n";
        }

        return ["__DEVELOPERS__" => self::xmpWrap($output)];
    }

    /**
     * Return human-readable var info like print_r, but more nicely formatted for quick debugging.
     *
     * @param $var
     *
     * @return string|int|float
     * @noinspection OneTimeUseVariablesInspection
     * @SuppressWarnings("php:S1142") // SonarLint: This method has 4 returns which is more than 3 allowed (we'll refactor in future)
     */
    private static function getPrettyVarDump($var): string|int|float
    {
        // single value
        if (!is_array($var)) {
            return self::getPrettyVarValue($var);
        }

        // empty array
        if (empty($var)) {
            return "None";
        }

        // array of values (which aren't arrays)
        $elements      = $var; // at this point, $var is an array since we checked above
        $output        = "";
        $isNestedArray = in_array(true, array_map('is_array', $elements), true);
        if (!$isNestedArray) {
            $maxKeyLength = strlen("->") + max(array_map('strlen', array_keys($elements)));
            foreach ($elements as $key => $value) {
                $output .= sprintf("%-{$maxKeyLength}s = %s\n", "->$key", self::getPrettyVarValue($value));
            }
            return trim($output);
        }

        // nested arrays - at this point we know $var is an array of arrays
        foreach ($elements as $index => $row) {
            $output       .= "[$index] => [\n";
            $maxKeyLength = max(array_map('strlen', array_keys($row)));
            foreach ($row as $key => $value) {
                if (is_array($value)) { // workaround to show nested-nested arrays as returned by groupBy()
                    $data = self::getPrettyVarDump($row);
                    $output .= preg_replace("/^/m", "    ", "$data\n");
                    break;
                }
                $output .= sprintf("    %-{$maxKeyLength}s => %s\n", $key, self::getPrettyVarValue($value));
            }
            $output .= "]\n";
        }
        return trim($output);
    }

    /**
     * Return a human-readable version of a variable. E.g., "string", 123, 1.23, TRUE, FALSE, NULL
     *
     * @param $value
     *
     * @return string|int|float
     */
    private static function getPrettyVarValue($value): string|int|float
    {
        return match (true) {
            is_string($value) => sprintf('"%s"', $value),
            is_bool($value)   => ($value ? "TRUE" : "FALSE"), // not returned by MySQL but let's use this in general Collections
            is_null($value)   => "NULL",
            default           => $value, // includes ints and floats
        };
    }

    /**
     * Check if a function is in the call stack.
     *
     * @param $function
     *
     * @return bool
     * @noinspection OneTimeUseVariablesInspection
     */
    private static function inCallStack($function): bool
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $trace) {
            if ($trace['function'] === $function) {
                return true;
            }
        }
        return false;
    }

    /**
     * Wrap output in <xmp> tag if not text/plain or called from a showme() function.
     * @param $output
     *
     * @return string
     */
    private static function xmpWrap($output): string
    {
        // is text/plain header set?
        $headerLines = implode("\n", headers_list());
        $textPlainRx = '|^\s*Content-Type:\s*text/plain\b|im'; // Content-Type: text/plain or text/plain;charset=utf-8
        $isTextPlain = (bool)preg_match($textPlainRx, $headerLines);

        // wrap output in <xmp> tag if not text/plain or called from showme()
        if (!$isTextPlain && !self::inCallStack('showme')) {
            $output = "\n<xmp>".trim($output, "\n")."</xmp>\n";
        }

        return $output;
    }

    #endregion
    #region Parse caller variable name

    /**
     * We try to get the actual variable name the developer is using to make the output more useful and relevant.
     * E.g., if $user->name->help() is called, we want to show $user->name in the help text.
     * E.g., if print_r($user->name) is called, we want to show $user->name in the output.
     *
     * @return string|null
     */
    private static function getCallerVarName(): string|null
    {
        // get caller index that triggered __debugInfo()
        $callerIndex     = null;
        $possibleCallers = ['help', 'showme', 'print_r', 'var_dump', 'var_export'];
        $backtrace       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $functionToIndex = array_flip(array_column($backtrace, 'function'));
        foreach ($possibleCallers as $function) {
            if (isset($functionToIndex[$function])) {
                $callerIndex = $functionToIndex[$function];
                break;
            }
        }

        // get caller
        $caller = isset($callerIndex) ? $backtrace[$callerIndex] : null;
        if (empty($caller) || empty($caller['file']) || empty($caller['line']) || empty($caller['function'])) {
            return null;
        }

        // get line of code
        $fileObj = new SplFileObject($caller['file']);
        $fileObj->seek($caller['line'] - 1);
        $lineText = $fileObj->current();

        //
        $arguments = match (strtolower($caller['function'])) {
            'help'  => self::getHelpMethodChainPrefix($lineText),
            default => self::getFunctionArgsFromLine($lineText, $caller['function']),
        };

        // returns vars only but without the leading $, eg: 'user->name' instead of '$user->name'
        return match (true) {
            str_starts_with((string)$arguments, '$') => ltrim($arguments, '$'), // remove leading $
            default                                  => null,                   // ignore functions, eg: DB::select(...)->first()->name
        };
    }

    /**
     * Try to extract the arguments from a function call in a line of PHP code. E.g., print_r($user->name) returns $user->name.
     *
     * @param $phpCodeLine
     * @param $functionName
     *
     * @return string
     * @SuppressWarnings("php:S3776") // SonarLint: Refactor this function to reduce its Cognitive Complexity (we'll refactor in future)
     */
    private static function getFunctionArgsFromLine($phpCodeLine, $functionName): string
    {
        $arguments  = '';
        $tokens     = token_get_all("<?php $phpCodeLine"); // tokenize PHP code
        $capturing  = false;
        $parenCount = 0;
        foreach ($tokens as $token) {
            $tokenValue = is_array($token) ? $token[1] : $token;

            if ($capturing) {
                if ($tokenValue === '(') {
                    if (++$parenCount === 1) {  // Start capturing after the first opening parenthesis
                        continue;
                    }
                } elseif ($tokenValue === ')') {
                    if (--$parenCount === 0) { // Stop capturing before the last closing parenthesis
                        break;
                    }
                }

                $arguments .= $tokenValue;
            }

            if (!$capturing) {
                $isCallerToken = is_array($token) && $token[0] === T_STRING && $token[1] === $functionName;
                $capturing     = $isCallerToken;
            }
        }

        return $arguments;
    }

    /**
     * When $records->first()->username->help() is called, return everything before ->help()
     * to show the method chain leading to the help() method in the help text.
     *
     * @param $phpCodeLine
     *
     * @return string|null
     * @SuppressWarnings("php:S3776") // SonarLint: Refactor this function to reduce its Cognitive Complexity (we'll refactor in future)
     */
    private static function getHelpMethodChainPrefix($phpCodeLine): string|null
    {
        $tokens       = token_get_all("<?php $phpCodeLine");
        $capturedCode = '';
        $parenCount   = 0;
        $capturing    = false;

        // Reverse the tokens array to start from the end
        $tokens = array_reverse($tokens);

        foreach ($tokens as $token) {
            $tokenValue = is_array($token) ? $token[1] : $token;

            if (!$capturing && $tokenValue === 'help') {
                $capturing = true;
                continue;
            }

            if ($capturing) {
                if ($tokenValue === ')') {
                    $parenCount++;
                } elseif ($tokenValue === '(') {
                    if ($parenCount === 0) {
                        break; // Stop capturing when an unmatched "(" is found
                    }
                    $parenCount--;
                } elseif ($tokenValue === ';') {
                    break; // Stop capturing when a ";" is found
                }

                // Prepend the token value to build the chain in reverse order
                $capturedCode = $tokenValue.$capturedCode;
            }
        }

        return $capturedCode ? rtrim($capturedCode, '->') : null;
    }

    #endregion
}
