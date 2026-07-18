<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * help(): output content, $value passthrough, both call forms, and plain
 * (no <xmp>) output on CLI. The <xmp> wrap only happens for text/html web
 * responses, which can't be simulated in-process (xmpWrap reads PHP_SAPI).
 *
 * n/a dimensions: encoding, global settings, immutability, argument matrix
 * ($value passes through untouched by design).
 */
class HelpTest extends SmartStringTestCase
{
    public function testHelpOutputsDocumentationPlainOnCli(): void
    {
        [$result, $output] = $this->captureOutput(fn() => SmartString::new('test')->help());

        $this->assertNull($result);
        $this->assertStringNotContainsString('<xmp>', $output);
        $this->assertStringContainsString('SmartString: XSS-Safe Strings', $output);
        $this->assertStringContainsString('Basics', $output);
        $this->assertStringContainsString('Type Conversion', $output);
        $this->assertStringContainsString('Dates & Numbers', $output);
    }

    public function testHelpReturnsValuePassthrough(): void
    {
        [$result, ] = $this->captureOutput(fn() => SmartString::new('test')->help('original value'));
        $this->assertSame('original value', $result);
    }

    /**
     * help() is static so both documented call forms work.
     */
    public function testHelpWorksAsStaticCall(): void
    {
        [$result, $output] = $this->captureOutput(fn() => SmartString::help('passthrough'));

        $this->assertSame('passthrough', $result);
        $this->assertStringContainsString('SmartString: XSS-Safe Strings', $output);
    }

    public function testHelpInstanceAndStaticOutputMatch(): void
    {
        [, $staticOutput]   = $this->captureOutput(fn() => SmartString::help());
        [, $instanceOutput] = $this->captureOutput(fn() => SmartString::new('x')->help());

        $this->assertSame($staticOutput, $instanceOutput);
    }
}
