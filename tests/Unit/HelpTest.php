<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * help(): output content, $value passthrough, both call forms, and
 * <xmp> wrapping (CLI has no Content-Type header, which counts as text/html).
 *
 * n/a dimensions: encoding, global settings, immutability, argument matrix
 * ($value passes through untouched by design).
 */
class HelpTest extends SmartStringTestCase
{
    public function testHelpOutputsDocumentationWrappedInXmp(): void
    {
        [$result, $output] = $this->captureOutput(fn() => SmartString::new('test')->help());

        $this->assertNull($result);
        $this->assertStringStartsWith("\n<xmp>\n", $output);
        $this->assertStringEndsWith("\n</xmp>\n", $output);
        $this->assertStringContainsString('SmartString: Enhanced Strings', $output);
        $this->assertStringContainsString('Creating SmartStrings', $output);
        $this->assertStringContainsString('Type conversion', $output);
        $this->assertStringContainsString('Encoding methods', $output);
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
        $this->assertStringContainsString('SmartString: Enhanced Strings', $output);
    }

    public function testHelpInstanceAndStaticOutputMatch(): void
    {
        [, $staticOutput]   = $this->captureOutput(fn() => SmartString::help());
        [, $instanceOutput] = $this->captureOutput(fn() => SmartString::new('x')->help());

        $this->assertSame($staticOutput, $instanceOutput);
    }
}
