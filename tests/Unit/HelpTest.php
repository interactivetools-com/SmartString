<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * help() is deprecated: it prints links to the online docs, plain (no <xmp>)
 * on CLI. The <xmp> wrap only happens for text/html web responses, which
 * can't be simulated in-process (xmpWrap reads PHP_SAPI).
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
}
