<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * help() is deprecated: it prints links to the online docs, plain (no <xmp>)
 * on CLI. The <xmp> wrap only happens for text/html web responses, which
 * can't be simulated in-process (xmpWrap reads PHP_SAPI); one test reaches
 * it through PHP's built-in server.
 *
 * n/a dimensions: encoding, global settings, immutability, argument matrix
 * ($value passes through untouched by design).
 */
class HelpTest extends SmartStringTestCase
{
    public function testHelpPrintsDocLinksPlainOnCli(): void
    {
        [$result, $output] = $this->captureOutput(fn() => SmartString::help());

        $this->assertNull($result);
        $this->assertStringNotContainsString('<xmp>', $output);
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartString#readme', $output);
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartString/blob/main/docs/method-reference.md', $output);
    }

    /**
     * help() is static so both documented call forms work; $value passes through
     * so help() can be dropped into an expression without changing the result.
     */
    public function testHelpReturnsValuePassthroughOnBothCallForms(): void
    {
        [$staticResult, $staticOutput]     = $this->captureOutput(fn() => SmartString::help('passthrough'));
        [$instanceResult, $instanceOutput] = $this->captureOutput(fn() => SmartString::new('x')->help('original value'));

        $this->assertSame('passthrough', $staticResult);
        $this->assertSame('original value', $instanceResult);
        $this->assertSame($staticOutput, $instanceOutput);
    }

    /**
     * The <xmp> web branch is reachable under PHP's built-in server (SAPI
     * cli-server), so this is the one test that asserts the wrapped path: a
     * literal </xmp> can't end the block early - it displays as <\/xmp>, the
     * same escaping as CMSB's xmp_safe().
     */
    public function testXmpWrapEscapesXmpClosingTagOnWebResponses(): void
    {
        $body = $this->requestViaBuiltInServer('xmp-breakout.php');

        $this->assertStringContainsString('<xmp>', $body);
        $this->assertStringContainsString('<\/xmp><script>alert(1)</script>', $body, 'payload displays escaped');
        $this->assertSame(1, substr_count($body, '</xmp>'), 'only the wrapper itself closes the block');
    }

    /**
     * Serve one Support/bin script through php -S and return the response
     * body. The built-in server is the one place tests can reach xmpWrap()'s
     * web branch (PHP_SAPI is 'cli' everywhere else in the suite).
     */
    private function requestViaBuiltInServer(string $script): string
    {
        $docRoot = dirname(__DIR__) . '/Support/bin';

        // find a free port, then hand it to php -S (it can't pick its own)
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket, 'could not find a free port');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $pipes  = [];
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $docRoot], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($server, 'could not start php -S');

        try {
            $context = stream_context_create(['http' => ['timeout' => 1]]);
            $body    = false;
            for ($attempt = 0; $attempt < 50 && $body === false; $attempt++) {
                usleep(100_000);
                $body = @file_get_contents("http://127.0.0.1:$port/$script", false, $context);
            }
            $this->assertIsString($body, 'no response from php -S after 5 seconds');
            return $body;
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }
}
