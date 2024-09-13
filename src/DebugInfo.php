<?php
declare(strict_types=1);

namespace Itools\SmartString;

class DebugInfo
{
    #region __debugInfo Method

    /**
     * Show useful developer info about object when print_r() is used to examine object.
     *
     * @return array An associative array containing debugging information.
     */
    public static function debugInfo($fieldObj): array
    {
        // get output
        $varValue = $fieldObj->value();
        $output = ['value' => self::getPrettyVarValue($varValue)];
        if ($varValue === null) {
            $output['notice'] = "This field has a NULL value, either from a NULL in the database or due to accessing a non-existent column.";
        }
        // On subsequent calls, show only the var name and value
        static $callCounter = 0;
        if (++$callCounter === 1) {
            $output['docs'] = "Developers, call \$obj->help() for more information and method examples.";
        }

        return $output;
    }

    #endregion
    #region Utility Methods

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
    public static function xmpWrap($output): string
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
}
