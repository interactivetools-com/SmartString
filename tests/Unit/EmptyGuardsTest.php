<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * or404(), orDie(), orThrow(), orRedirect().
 *
 * Present values pass through in-process (each guard returns $this).
 * Exit paths run out of process via runScript() / Support/bin/empty-guard.php.
 * orRedirect's happy path also runs out of process: PHPUnit's own console
 * output does not mark headers as sent, but only a fresh process guarantees
 * headers_sent() is false at call time.
 * The headers the guards send are asserted by serving the same script through
 * PHP's built-in server, where responses carry real headers.
 *
 * n/a dimensions: global settings, immutability (guards return $this, pinned
 * here as instance identity).
 */
class EmptyGuardsTest extends SmartStringTestCase
{
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

        $this->assertStringContainsString('<h1>Not Found</h1>', $stdout);
        $this->assertStringContainsString('<p>The requested URL was not found on this server.</p>', $stdout);
        $this->assertStringContainsString('status=404', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode, 'or404() exits with status 1 like orDie(), so shells and cron see the failure');
    }

    public function testOr404EncodesCustomMessage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404', "Bad <id> & 'quote'");

        $this->assertStringContainsString("<p>Bad &lt;id&gt; &amp; &apos;quote&apos;</p>", $stdout);
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
    //region orDie() Exit Path (subprocess)

    /**
     * orDie exits 1 so CLI and cron callers see a failure, not success.
     * The message is HTML-encoded (same SECURITY contract as orThrow).
     */
    public function testOrDieOutputsEncodedMessageAndExits1(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('orDie', "Bad <id> & 'quote'");

        $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $stdout);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode);
    }

    public function testOrDieEncodesSmartStringMessageOnce(): void
    {
        // a SmartString message unwraps to its raw value first, so the output
        // is encoded once instead of double-encoding the __toString output
        [$stdout, $stderr, $exitCode] = $this->runScript('orDie-smart-text', "Bad <id> & 'quote'");

        $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $stdout);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(1, $exitCode);
    }

    //endregion
    //region orRedirect() Exit Path (subprocess)

    public function testOrRedirectSends302AndExits(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('orRedirect', 'https://example.com/login');

        $this->assertSame('', $stdout);
        $this->assertStringContainsString('status=302', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(0, $exitCode);
    }

    public function testOrRedirectPassesPresentValueThrough(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('orRedirect-present', 'https://example.com/login');

        $this->assertSame('ok', $stdout);
        $this->assertStringContainsString('NOT-REACHED', $stderr); // reaching the end IS the pass-through
        $this->assertSame(0, $exitCode);
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
     * header() is a no-op under CLI, so the subprocess tests above see the 302
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
