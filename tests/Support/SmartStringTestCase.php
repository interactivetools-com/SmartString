<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Support;

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
    use SharedTestHelpers;

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
     * Run $fn collecting deprecation messages, native and E_USER_DEPRECATED alike. The
     * library sends its own via @trigger_error, so only an error handler can observe them.
     * Returns [result, messages].
     *
     * @return array{0: mixed, 1: string[]}
     */
    protected function captureDeprecations(callable $fn): array
    {
        return $this->captureErrors($fn, E_DEPRECATED | E_USER_DEPRECATED);
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
     * Run $fn expecting exactly one warning, native E_WARNING or E_USER_WARNING, starting
     * with exactly $expected. The library appends an "Occurred in file:line ..." block
     * pointing at the calling test, so that block is asserted by format and stripped before
     * the exact comparison. $expected includes everything up to it, trailing newline
     * included. Returns $fn's result.
     */
    protected function expectUserWarning(callable $fn, string $expected): mixed
    {
        [$result, $messages] = $this->captureErrors($fn, E_WARNING | E_USER_WARNING);

        $this->assertCount(1, $messages, "Expected exactly one warning, got: " . var_export($messages, true));
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
        $diagnosticLevels         = E_WARNING | E_NOTICE | E_DEPRECATED | E_RECOVERABLE_ERROR
                                  | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED | E_USER_ERROR;
        [$captured, $diagnostics] = $this->captureErrors(fn() => $this->captureOutput($fn), $diagnosticLevels);
        [$result, $output]        = $captured;

        $this->assertSame('', $output, "Expected no echoed output");
        $this->assertSame([], $diagnostics, "Expected no warnings or deprecation notices");
        return $result;
    }

    //endregion
    //region Exit-Path Subprocess

    /**
     * Run one Support/bin script as a separate PHP process so exit paths can be
     * observed from outside. Returns [stdout, stderr, exitCode]. The default
     * script, empty-guard.php, reports "status=<int|false>" and a NOT-REACHED
     * sentinel on stderr (full protocol in the script header).
     *
     * @return array{0: string, 1: string, 2: int}
     */
    protected function runScript(string $method, ?string $arg = null, string $script = 'empty-guard.php'): array
    {
        $command = [PHP_BINARY, __DIR__ . "/bin/$script", $method];
        if ($arg !== null) {
            $command[] = $arg;
        }
        return $this->runCommand($command);
    }

    //endregion
}
