<?php
declare(strict_types=1);

namespace Itools\SmartString;

use InvalidArgumentException;

/**
 * Same as InvalidArgumentException, but it reports the caller's file and line
 * instead of ours. Throw it when the caller caused the error (bad regex, bad
 * callback, wrong type) so the error names the line they need to fix:
 *
 *     Uncaught CallerException: pregReplace(): No ending delimiter '/' found
 *     in /var/www/templates/race.php:345    // their code, not SmartString.php
 *
 * "Caller" means the first file outside this class's directory, so internal
 * calls are skipped: or() calling getRawValue() still reports the template
 * line that called or(). If the whole backtrace is internal, it falls back
 * to reporting the throw line as usual.
 *
 * Catch it as InvalidArgumentException - only the reported location changes.
 * The real throw site is kept in $thrownInFile/$thrownInLine.
 */
final class CallerException extends InvalidArgumentException
{
    public readonly string $thrownInFile;  // library file that threw, e.g. .../src/SmartString.php
    public readonly int    $thrownInLine;

    public function __construct(string $message)
    {
        parent::__construct($message);

        // PHP set $file/$line to the throw site when the object was created; save them before overriding
        $this->thrownInFile = $this->file;
        $this->thrownInLine = $this->line;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!empty($frame['file']) && dirname($frame['file']) !== __DIR__) {
                $this->file = $frame['file'];
                $this->line = $frame['line'] ?? 0;
                break;
            }
        }
    }
}
