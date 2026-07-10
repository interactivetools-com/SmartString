<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\SmartStringTestCase;

/**
 * or404(), orDie(), orThrow(), orRedirect().
 *
 * Present values pass through in-process (each guard returns $this).
 * Exit paths run out of process via runScript() / Support/bin/empty-guard.php.
 * orRedirect's happy path also runs out of process: PHPUnit's own console
 * output does not mark headers as sent, but only a fresh process guarantees
 * headers_sent() is false at call time.
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
        try {
            SmartString::new(null)->orThrow("Bad <id> & 'quote'");
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Bad &lt;id&gt; &amp; &apos;quote&apos;', $e->getMessage());
        }
    }

    public function testOrThrowTreatsEmptyStringAsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        SmartString::new('')->orThrow('missing');
    }

    public function testOrThrowMessageDecodesBackToPlainText(): void
    {
        // the docblock's recovery recipe for CLI/log handlers
        try {
            SmartString::new(null)->orThrow("Bad <id> & 'quote'");
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame("Bad <id> & 'quote'", htmlspecialchars_decode($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
        }
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
        $this->assertSame(0, $exitCode);
    }

    public function testOr404EncodesCustomMessage(): void
    {
        [$stdout, $stderr, $exitCode] = $this->runScript('or404', "Bad <id> & 'quote'");

        $this->assertStringContainsString("<p>Bad &lt;id&gt; &amp; &apos;quote&apos;</p>", $stdout);
        $this->assertStringContainsString('status=404', $stderr);
        $this->assertStringNotContainsString('NOT-REACHED', $stderr);
        $this->assertSame(0, $exitCode);
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

        $this->assertStringContainsString('orRedirect(): headers already sent in', $stderr);
        $this->assertStringContainsString('output-sent', $stdout); // the output that sent the headers
        $this->assertSame(255, $exitCode); // uncaught RuntimeException
    }

    //endregion
}
