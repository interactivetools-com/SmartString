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
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartString#readme', $staticOutput);
        $this->assertStringContainsString('https://github.com/interactivetools-com/SmartString/blob/main/docs/method-reference.md', $staticOutput);
        $this->assertSame($staticOutput, $instanceOutput);
    }

    /**
     * The <xmp> web branch is reachable under PHP's built-in server (SAPI
     * cli-server), so this is the one test that asserts the wrapped path: a
     * literal </xmp> can't end the block early - it displays as <\/xmp>, the
     * same escaping as CMSB's xmp_safe(). HTML tag names are case-insensitive, so
     * the fixture sends lower, upper and mixed case: matching only the two extremes
     * would let an escape that lists spellings literally through.
     *
     * The escaped forms are compared case-insensitively because the safe output is
     * the backslash, not the casing an implementation happens to emit.
     */
    public function testXmpWrapEscapesXmpClosingTagOnWebResponses(): void
    {
        [, $body] = $this->requestViaBuiltInServer('xmp-breakout.php');

        $this->assertStringContainsString('<xmp>', $body);
        $this->assertStringContainsStringIgnoringCase('<\/xmp><script>alert(1)</script>', $body, 'lowercase payload displays escaped');
        $this->assertStringContainsStringIgnoringCase('<\/xmp><script>alert(2)</script>', $body, 'uppercase payload displays escaped');
        $this->assertStringContainsStringIgnoringCase('<\/xmp><script>alert(3)</script>', $body, 'mixed case payload displays escaped');
        $this->assertSame(1, substr_count(strtolower($body), '</xmp>'), 'only the wrapper itself closes the block');
    }
}
