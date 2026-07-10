<?php
declare(strict_types=1);

namespace Tests\Support;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the Unit and Integration suites.
 *
 * Conventions:
 * - assertSame with literal expected values; assertSmartString() for chainable returns
 * - Warnings and deprecations are asserted by message or asserted absent, never suppressed
 * - The five global settings and the process timezone are snapshotted in setUp() and
 *   restored in tearDown(), so tests set what they need and never clean up
 * - Exit paths (or404/orDie/orRedirect) run out of process via runScript()
 */
abstract class SmartStringTestCase extends TestCase
{
    //region Global Settings Isolation

    private array  $savedSettings;
    private string $savedTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedSettings = [
            'numberFormatDecimal'   => SmartString::$numberFormatDecimal,
            'numberFormatThousands' => SmartString::$numberFormatThousands,
            'dateFormat'            => SmartString::$dateFormat,
            'dateTimeFormat'        => SmartString::$dateTimeFormat,
            'phoneFormat'           => SmartString::$phoneFormat,
        ];
        $this->savedTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        SmartString::$numberFormatDecimal   = $this->savedSettings['numberFormatDecimal'];
        SmartString::$numberFormatThousands = $this->savedSettings['numberFormatThousands'];
        SmartString::$dateFormat            = $this->savedSettings['dateFormat'];
        SmartString::$dateTimeFormat        = $this->savedSettings['dateTimeFormat'];
        SmartString::$phoneFormat           = $this->savedSettings['phoneFormat'];
        date_default_timezone_set($this->savedTimezone);
        parent::tearDown();
    }

    //endregion
    //region Assertions

    /**
     * Assert a chainable method returned a SmartString wrapping exactly $expectedRaw
     * (assertSame on ->value(), so type and value both match).
     */
    protected function assertSmartString(string|int|float|bool|null $expectedRaw, mixed $actual, string $message = ''): void
    {
        $this->assertInstanceOf(SmartString::class, $actual, $message);
        $this->assertSame($expectedRaw, $actual->value(), $message);
    }

    //endregion
    //region Output and Error Capture

    /**
     * Run $fn capturing echoed output. Returns [result, output].
     *
     * @return array{0: mixed, 1: string}
     */
    protected function captureOutput(callable $fn): array
    {
        ob_start();
        try {
            $result = $fn();
        } finally {
            $output = ob_get_clean();
        }
        return [$result, $output];
    }

    /**
     * Run $fn collecting E_USER_DEPRECATED messages. The library sends these via
     * @trigger_error, so only an error handler can observe them. Returns [result, messages].
     *
     * @return array{0: mixed, 1: string[]}
     */
    protected function captureDeprecations(callable $fn): array
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$messages): bool {
            $messages[] = $errstr;
            return $errno === E_USER_DEPRECATED; // always true given the mask; anything else falls through to PHP
        }, E_USER_DEPRECATED);
        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }
        return [$result, $messages];
    }

    /**
     * Run $fn expecting exactly one deprecation notice with exactly $expected as its text.
     * logDeprecation() appends " in file:line." pointing at the calling test, so that
     * suffix is asserted by format and stripped before the exact comparison (the line
     * number shifts every time a test file is edited). Returns $fn's result.
     */
    protected function expectDeprecationMessage(callable $fn, string $expected): mixed
    {
        [$result, $messages] = $this->captureDeprecations($fn);
        $this->assertCount(1, $messages, "Expected exactly one deprecation notice, got: " . var_export($messages, true));
        $this->assertMatchesRegularExpression('/ in \S+:\d+\.$/', $messages[0], 'Deprecation should end with the " in file:line." location suffix');
        $this->assertSame($expected, preg_replace('/ in \S+:\d+\.$/', '', $messages[0]));
        return $result;
    }

    /**
     * Run $fn expecting exactly one E_USER_WARNING starting with exactly $expected.
     * The library appends an "Occurred in file:line ..." block pointing at the calling
     * test, so that block is asserted by format and stripped before the exact comparison.
     * $expected includes everything up to it, trailing newline included. Returns $fn's result.
     */
    protected function expectUserWarning(callable $fn, string $expected): mixed
    {
        $messages = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$messages): bool {
            $messages[] = $errstr;
            return $errno === E_USER_WARNING; // always true given the mask; anything else falls through to PHP
        }, E_USER_WARNING);
        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $messages, "Expected exactly one E_USER_WARNING, got: " . var_export($messages, true));
        $this->assertMatchesRegularExpression('/Occurred in .+:\d+.*\nReported$/s', $messages[0], 'Warning should end with the "Occurred in file:line" location block');
        $this->assertSame($expected, preg_replace('/Occurred in .*$/s', '', $messages[0]));
        return $result;
    }

    /**
     * Run $fn asserting the happy path stays silent: no echoed output, no warnings,
     * no deprecation notices. Returns $fn's result.
     */
    protected function assertNoOutput(callable $fn): mixed
    {
        $userErrors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$userErrors): bool {
            $userErrors[] = $errstr;
            return ($errno & (E_USER_WARNING | E_USER_DEPRECATED | E_USER_NOTICE)) !== 0; // always true given the mask
        }, E_USER_WARNING | E_USER_DEPRECATED | E_USER_NOTICE);
        try {
            [$result, $output] = $this->captureOutput($fn);
        } finally {
            restore_error_handler();
        }

        $this->assertSame('', $output, "Expected no echoed output");
        $this->assertSame([], $userErrors, "Expected no warnings or deprecation notices");
        return $result;
    }

    //endregion
    //region Exit-Path Subprocess

    /**
     * Run one guard in Support/bin/empty-guard.php as a separate PHP process so exit
     * paths can be observed from outside. Returns [stdout, stderr, exitCode]; the
     * script reports "status=<int|false>" and a NOT-REACHED sentinel on stderr (full
     * protocol in the script header).
     *
     * @return array{0: string, 1: string, 2: int}
     */
    protected function runScript(string $method, ?string $arg = null): array
    {
        $command = [PHP_BINARY, __DIR__ . '/bin/empty-guard.php', $method];
        if ($arg !== null) {
            $command[] = $arg;
        }

        $pipes   = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process, "Failed to start PHP subprocess");

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$stdout, $stderr, $exitCode];
    }

    //endregion
}
