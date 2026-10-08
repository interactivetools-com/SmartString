<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Closure;
use Itools\SmartString\SmartString;
use Itools\SmartString\Tests\Support\ExitCalled;
use Itools\SmartString\Tests\Support\SmartStringTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * or404(), orDie(), orThrow(), orRedirect(), and set404Handler().
 *
 * Present values pass through in-process (each guard returns $this).
 * The guards end in self::exit(), which throws ExitCalled under PHPUnit
 * (tests/bootstrap.php loads the class), so orDie() and orRedirect() are
 * asserted in-process: output, exit status, and http_response_code().
 * or404() still runs out of process via runScript() / Support/bin/empty-guard.php:
 * it discards every open output buffer, PHPUnit's capture buffer included, and
 * PHPUnit fails a test that closes buffers it didn't open. The headers-sent
 * variants need a process where output really was sent.
 * The headers the guards send are asserted by serving the same script through
 * PHP's built-in server, where responses carry real headers.
 *
 * A set404Handler() handler gets or404()'s message as plain text and encodes
 * it itself; every other path here HTML-encodes the message.
 *
 * n/a dimensions: the formatting settings, immutability (guards return $this,
 * pinned here as instance identity).
 */
class EmptyGuardsTest extends SmartStringTestCase
{
    /** The exact page or404() writes, with %s for the HTML-encoded message. */
    private const NOT_FOUND_PAGE = <<<'__HTML__'
        <!DOCTYPE html>
        <html>
        <head>
            <title>Not Found</title>
        </head>
        <body>
            <h1>Not Found</h1>
            <p>%s</p>
        </body>
        </html>
        __HTML__;

    /** The message or404() shows when called without one. */
    private const DEFAULT_TEXT = 'The requested URL was not found on this server.';

    protected function setUp(): void
    {
        parent::setUp();
        http_response_code_clear();   // the status survives between tests in one process; start each from false
    }

    protected function tearDown(): void
    {
        SmartString::set404Handler(null);   // the handler is static, so a test that sets one would leak it into the next
        parent::tearDown();
    }

    /**
     * Run $fn expecting it to end in self::exit(). Returns [the ExitCalled it threw, what it printed first].
     *
     * @return array{0: ExitCalled, 1: string}
     */
    private function expectExit(callable $fn): array
    {
        ob_start();
        try {
            $fn();
        } catch (ExitCalled $e) {
            return [$e, ob_get_clean()];
        } finally {
            if (!isset($e)) {   // returned or threw something else: close the buffer so PHPUnit does not flag the test as risky
                ob_end_clean();
            }
        }
        $this->fail('expected self::exit() to be called');
    }

    //region Present Values Pass Through

    #[DataProvider('presentValuesProvider')]
    public function testGuardsReturnSameInstanceForPresentValues($value): void
    {
        $smartString = SmartString::new($value);
        $this->assertSame($smartString, $smartString->or404());
        $this->assertSame($smartString, $smartString->orDie('unused'));
        $this->assertSame($smartString, $smartString->orThrow('unused'));
    }

    public static function presentValuesProvider(): array
    {
        return [
            'string'      => ['Hello'],
            'int'         => [42],
            'zero int'    => [0],   // zero is present, not missing
            'zero string' => ['0'],
            'false'       => [false],
        ];
    }

    public function testOrRedirectReturnsSameInstanceForPresentValue(): void
    {
        // PHPUnit's console output goes to a stream, so headers_sent() is
        // still false here and the present-value path is reachable in-process
        $smartString = SmartString::new('Hello');
        $this->assertSame($smartString, $smartString->orRedirect('https://example.com/'));
    }

    //endregion
    //region orThrow()

    /**
     * The SECURITY contract: the message is HTML-encoded at throw time so
     * exception handlers that echo into a page stay safe.
     */
    public function testOrThrowThrowsEncodedMessage(): void
    {
        // the fail() sentinel stays outside the try: PHPUnit's AssertionFailedError
        // is itself a RuntimeException, so a fail() inside would land in the catch
        $threw = false;
        try {
            SmartString::new(null)->orThrow("Bad <id> & 'quote'");
        } catch (RuntimeException $e) {
            $threw = true;
            $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $e->getMessage());
        }
        $this->assertTrue($threw, 'Expected RuntimeException was not thrown');
    }

    public function testOrThrowEncodesSmartStringMessageOnce(): void
    {
        // a SmartString message unwraps to its raw value first, so it encodes
        // once at throw time instead of double-encoding the __toString output
        $threw = false;
        try {
            SmartString::new(null)->orThrow(SmartString::new("Bad <id> & 'quote'"));
        } catch (RuntimeException $e) {
            $threw = true;
            $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $e->getMessage());
        }
        $this->assertTrue($threw, 'Expected RuntimeException was not thrown');
    }

    public function testOrThrowTreatsEmptyStringAsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        SmartString::new('')->orThrow('missing');
    }

    public function testOrThrowMessageDecodesBackToPlainText(): void
    {
        // the docblock's recovery recipe for CLI/log handlers
        $threw = false;
        try {
            SmartString::new(null)->orThrow("Bad <id> & 'quote'");
        } catch (RuntimeException $e) {
            $threw = true;
            $this->assertSame("Bad <id> & 'quote'", htmlspecialchars_decode($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
        }
        $this->assertTrue($threw, 'Expected RuntimeException was not thrown');
    }

    //endregion
    //region or404() Exit Path (subprocess)

    public function testOr404OutputsDefault404Page(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-default');

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, self::DEFAULT_TEXT), $stdout);
        $this->assertStringContainsString('status=404', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode, 'or404() exits with status 1 like orDie(), so shells and cron see the failure');
    }

    public function testOr404EncodesCustomMessage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404', "Bad <id> & 'quote'");

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, 'Bad &lt;id&gt; &amp; &apos;quote&apos;'), $stdout);
        $this->assertStringContainsString('status=404', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode, 'or404() exits with status 1 like orDie(), so shells and cron see the failure');
    }

    public function testOr404AfterOutputSentStillRendersThePage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-headers-sent');

        $this->assertStringContainsString('<h1>Not Found</h1>', $stdout);
        $this->assertStringNotContainsString('Cannot modify header information', $stdout . $stderr);
        $this->assertStringContainsString('status=false', $stderr, 'headers already sent: the status cannot change, and no attempt is made');
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testOr404DiscardsOpenOutputBuffers(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-ob-discard');

        $this->assertStringNotContainsString('partial page content', $stdout, 'the partial page is discarded, not shown above the 404');
        $this->assertStringContainsString('<h1>Not Found</h1>', $stdout);
        $this->assertStringContainsString('status=404', $stderr, 'buffered output was never sent, so the status still gets set');
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode);
    }

    /**
     * A buffer started without PHP_OUTPUT_HANDLER_REMOVABLE can't be discarded:
     * ob_end_clean() returns false and leaves it open. or404() must stop and
     * render the 404 inside that buffer, which exit then flushes. Spinning on
     * the undeletable buffer would hang the request until max_execution_time.
     */
    public function testOr404StopsDiscardingWhenABufferCannotBeRemoved(): void
    {
        $started = microtime(true);
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-locked-buffer');

        $this->assertLessThan(2.0, microtime(true) - $started, 'or404() must give up on the locked buffer, not spin');
        $this->assertStringContainsString('partial page content', $stdout, 'the locked buffer cannot be discarded, so its content still shows');
        $this->assertStringContainsString('<h1>Not Found</h1>', $stdout, 'the 404 renders inside the locked buffer and exit flushes it');
        $this->assertStringContainsString('status=404', $stderr, 'nothing reached the client, so the status still gets set');
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testOr404EncodesSmartStringMessageOnce(): void
    {
        // a SmartString message unwraps to its raw value first, so the page
        // shows it encoded once instead of double-encoding the __toString output
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-smart-text', "Bad <id> & 'quote'");

        $this->assertStringContainsString("<p>Bad &lt;id&gt; &amp; &apos;quote&apos;</p>", $stdout);
        $this->assertStringContainsString('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    /**
     * The Content-Type is only observable in a real web response: header() is
     * a no-op under CLI. PHP's own default for a response that sets nothing
     * ("Content-type: text/html; charset=UTF-8") differs from the library's
     * only in case, so the exact match is what tells the two apart.
     */
    public function testOr404SendsHtmlContentTypeHeader(): void
    {
        [$headers, $body] = $this->requestGuard('or404-default');

        $this->assertContains('Content-Type: text/html; charset=utf-8', $headers, "Response headers: " . var_export($headers, true));
        $this->assertStringContainsString('404 Not Found', $headers[0]);
        $this->assertStringContainsString('<h1>Not Found</h1>', $body);
    }

    //endregion
    //region set404Handler()

    public function testSet404HandlerReturnsThePreviousHandler(): void
    {
        $handler = fn(?string $text) => null;

        $this->assertNull(SmartString::set404Handler($handler), 'no handler was set before');
        $this->assertSame($handler, SmartString::set404Handler(null), 'a Closure comes back as the same object');
        $this->assertNull(SmartString::set404Handler(null), 'null went back to the built-in page');
    }

    #[DataProvider('presentValuesProvider')]
    public function testHandlerIsNotCalledForPresentValues($value): void
    {
        $called = false;
        SmartString::set404Handler(function (?string $text) use (&$called): void {
            $called = true;
        });
        $smartString = SmartString::new($value);

        $this->assertSame($smartString, $smartString->or404('Not found'));
        $this->assertFalse($called);
    }

    /**
     * @return array<string, array{array<int, string>, string|null}>
     */
    public static function handlerMessageProvider(): array
    {
        return [
            'plain text, not encoded' => [['or404-handler', 'No <b>value</b>'], 'No <b>value</b>'],
            'no message'              => [['or404-handler-default'], null],
            'empty message'           => [['or404-handler', ''], ''],
            'empty-string value'      => [['or404-handler-empty-value', 'Blank'], 'Blank'],
            'SmartString message'     => [['or404-handler-smart-text', "O'Brien & co"], "O'Brien & co"],   // the raw value, not the encoded __toString()
            'SmartString number'      => [['or404-handler-smart-int'], '404'],
            'SmartNull message'       => [['or404-handler-smartnull'], null],
        ];
    }

    /**
     * The handler runs after the 404 status is set and the buffers are gone, and
     * or404() exits with status 1 once it returns.
     */
    #[DataProvider('handlerMessageProvider')]
    public function testHandlerGetsMessageAsPlainText(array $scriptArgs, ?string $expectedText): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript(...$scriptArgs);

        $this->assertSame(self::handlerReport($expectedText), $stdout);
        $this->assertSame('status=404', $stderr, 'no NOT-REACHED: or404() exited after the handler returned');
        $this->assertSame(1, $exitCode);
    }

    public function testHandlerRunsAfterBufferedOutputIsDiscarded(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-handler-ob-discard');

        $this->assertSame(self::handlerReport(null), $stdout, 'the partial page is discarded before the handler runs');
        $this->assertSame('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testHandlerStillRunsAfterOutputSent(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-handler-headers-sent');

        $this->assertSame("already-flushed\n" . self::handlerReport(null, 'false'), $stdout, 'headers already sent: no status, but the handler still prints the page');
        $this->assertSame('status=false', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testSettingNullBringsBackTheBuiltInPage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-handler-reset');

        $this->assertSame(sprintf(self::NOT_FOUND_PAGE, self::DEFAULT_TEXT), $stdout);
        $this->assertSame('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    /**
     * An exception from the handler isn't caught: it reaches the app's own error
     * handling with the 404 status already sent. The fatal error text isn't
     * matched, since display_errors decides whether it goes to stdout or stderr.
     */
    public function testHandlerExceptionPassesThroughWith404Status(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-handler-throws');

        $this->assertStringEndsWith('status=404', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stdout . $stderr);
        $this->assertSame(255, $exitCode, 'PHP exits with 255 on an uncaught exception');
    }

    public function testOr404InsideHandlerPrintsTheBuiltInPage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404-handler-nested');

        $this->assertSame("handler-page\n" . sprintf(self::NOT_FOUND_PAGE, 'nested call'), $stdout, 'the handler runs once, then the nested or404() prints the built-in page');
        $this->assertSame('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    /**
     * first() on an empty SmartArrayHtml result returns a SmartNull, and its
     * or404() is SmartString's, so this handler runs (not SmartArray's).
     */
    public function testFirstOnEmptyHtmlResultUsesThisHandler(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('smartnull-or404-handler', 'No entry');

        $this->assertSame(self::handlerReport('No entry'), $stdout);
        $this->assertSame('status=404', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testHandlerResponseKeeps404StatusAndContentType(): void
    {
        [$headers, $body] = $this->requestGuard('or404-handler', 'No entry');

        $this->assertStringContainsString('404 Not Found', $headers[0]);
        $this->assertContains('Content-Type: text/html; charset=utf-8', $headers, "Response headers: " . var_export($headers, true));
        $this->assertSame(self::handlerReport('No entry'), $body);
    }

    /**
     * What the handler in empty-guard.php prints: the message it got, the status
     * when it ran, and the output buffer level when it ran.
     */
    private static function handlerReport(?string $text, string $status = '404'): string
    {
        return "handler-text=" . var_export($text, true) . "\n"
             . "handler-status=$status\n"
             . "handler-ob-level=0\n";
    }

    //endregion
    //region orDie() Exit Path

    /**
     * orDie exits 1 so CLI and cron callers see a failure, not success.
     * The message is HTML-encoded (same SECURITY contract as orThrow).
     */
    public function testOrDieOutputsEncodedMessageAndExits1(): void
    {
        [$exit, $output] = $this->expectExit(fn() => SmartString::new(null)->orDie("Bad <id> & 'quote'"));

        $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $output);
        $this->assertFalse(http_response_code(), 'orDie() sets no HTTP status');
        $this->assertSame(1, $exit->status);
    }

    public function testOrDieEncodesSmartStringMessageOnce(): void
    {
        // a SmartString message unwraps to its raw value first, so the output
        // is encoded once instead of double-encoding the __toString output
        [$exit, $output] = $this->expectExit(fn() => SmartString::new(null)->orDie(SmartString::new("Bad <id> & 'quote'")));

        $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $output);
        $this->assertSame(1, $exit->status);
    }

    //endregion
    //region orRedirect() Exit Path

    public function testOrRedirectSends302AndExits(): void
    {
        [$exit, $output] = $this->expectExit(fn() => SmartString::new(null)->orRedirect('https://example.com/login'));

        $this->assertSame('', $output, 'a redirect writes no body');
        $this->assertSame(302, http_response_code());
        $this->assertSame(0, $exit->status, 'orRedirect() exits with the default status 0');
    }

    /**
     * The headers-sent check runs before the isMissing() check, so
     * misuse fails on the first request even when the value is present.
     */
    public function testOrRedirectThrowsWhenHeadersSentEvenIfValuePresent(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('orRedirect-headers-sent', 'https://example.com/login');

        // basename only: this message can reach page output, so no full server paths
        $this->assertMatchesRegularExpression('|orRedirect\(\): headers already sent in [^/\\\\]+ on line \d+|', $stderr);
        $this->assertStringContainsString('output-sent', $stdout); // the output that sent the headers
        $this->assertSame(255, $exitCode); // uncaught RuntimeException
    }

    /**
     * The redirect destination is only observable in a real web response:
     * header() is a no-op under CLI, so the in-process test above sees the 302
     * status but not where it points. The query string is the part a login
     * guard builds per request, e.g. ->orRedirect('/login.php?return=' . $path).
     */
    public function testOrRedirectSendsLocationHeaderForTheGivenUrl(): void
    {
        [$headers, $body] = $this->requestGuard('orRedirect', 'https://example.com/login?return=/admin');

        $this->assertContains('Location: https://example.com/login?return=/admin', $headers, "Response headers: " . var_export($headers, true));
        $this->assertStringContainsString('302 Found', $headers[0]);
        $this->assertSame('', $body);
    }

    public function testOrRedirectSendsRawLocationForSmartStringUrl(): void
    {
        // a SmartString URL unwraps to its raw value: the & must reach the
        // Location header as &, not as the &amp; __toString coercion produces
        [$headers, $body] = $this->requestGuard('orRedirect-smart-url', 'https://example.com/go?a=1&b=2');

        $this->assertContains('Location: https://example.com/go?a=1&b=2', $headers, "Response headers: " . var_export($headers, true));
        $this->assertStringContainsString('302 Found', $headers[0]);
        $this->assertSame('', $body);
    }

    public function testOrRedirectThrowsOnBlankUrl(): void
    {
        // checked before isMissing() like the headers-sent check, so the bug
        // shows on the first request, not only once a value comes up missing
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('orRedirect(): redirect URL is blank');
        SmartString::new('Hello')->orRedirect('');
    }

    public function testOrRedirectThrowsOnMissingSmartStringUrl(): void
    {
        // a SmartString holding null unwraps to a blank URL - same loud failure
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('orRedirect(): redirect URL is blank');
        SmartString::new('Hello')->orRedirect(SmartString::new(null));
    }

    //endregion
    //region self::exit()

    /**
     * @return array<string, array{string|int, string, int}>
     */
    public static function exitCases(): array
    {
        return [
            'string prints and exits 0'       => ['Not found', 'Not found', 0],
            'int sets status, prints nothing' => [3, '', 3],
            'no argument exits 0'             => [0, '', 0],
        ];
    }

    /**
     * The seam every guard ends in. While tests/bootstrap.php has ExitCalled loaded, self::exit()
     * throws it, carrying the output and status with the same string-or-int rules as PHP's exit.
     */
    #[DataProvider('exitCases')]
    public function testExitThrowsExitCalledUnderPhpunit(string|int $arg, string $expectedOutput, int $expectedStatus): void
    {
        $exit = Closure::bind(static fn() => SmartString::exit($arg), null, SmartString::class);   // protected: call from inside the class

        try {
            $exit();
        } catch (ExitCalled $e) {
            $this->assertSame($expectedOutput, $e->output);
            $this->assertSame($expectedStatus, $e->status);
            $this->assertNotInstanceOf(RuntimeException::class, $e, 'a catch (RuntimeException) in the code under test must not swallow it');
            return;
        }
        $this->fail('self::exit() should have thrown ExitCalled');
    }

    /**
     * Outside PHPUnit nothing loads ExitCalled, so self::exit() is a real exit: the message reaches
     * stdout and the process ends with the status. One fresh php with the script on stdin: no shell
     * (Windows escapeshellarg() drops "!" and cmd.exe reads 2>/dev/null as a file path) and no
     * stderr pipe to deadlock on.
     */
    public function testExitPrintsAndSetsStatusOutsidePhpunit(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script   = '<?php require ' . var_export($autoload, true) . '; \Itools\SmartString\SmartString::new(null)->orDie("Gone!");';
        $process  = proc_open([PHP_BINARY], [['pipe', 'r'], ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $script);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        $this->assertSame('Gone!', $stdout);
        $this->assertSame(1, $status, 'orDie() exits 1 so shell scripts and cron jobs see the failure');
    }

    //endregion
    //region Web Requests (php -S)

    /**
     * Run one guard in Support/bin/empty-guard.php as a web request and return
     * [responseHeaders, body]. follow_location off so the 302 is read here instead of
     * sending a real request to the redirect target; ignore_errors keeps the 404 body
     * and headers instead of returning false.
     *
     * @return array{0: string[], 1: string}
     */
    private function requestGuard(string $method, string $arg = ''): array
    {
        $query = sprintf('empty-guard.php?method=%s&arg=%s', urlencode($method), urlencode($arg));
        return $this->requestViaBuiltInServer(dirname(__DIR__) . '/Support/bin', $query, ['follow_location' => 0, 'ignore_errors' => true]);
    }

    //endregion
}
