<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Integration;

use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * itools/smartarray is a suggested dependency, not a required one, so
 * SmartString must work on its own. Each test runs
 * Support/bin/standalone-install.php in a subprocess that loads src/ without
 * the Composer autoloader: scalar values work normally, and the deprecated
 * array paths (the only code that instantiates SmartArray classes) throw a
 * RuntimeException naming the package, the composer command, and the caller's
 * file instead of PHP's "Class not found" fatal pointing at library internals.
 */
class StandaloneInstallTest extends SmartStringTestCase
{
    public function testScalarsWorkWithoutSmartArray(): void
    {
        [$stdout, , $exitCode] = $this->runScript('scalar', null, 'standalone-install.php');
        $this->assertSame(0, $exitCode);
        $this->assertSame('Bob &amp; Sons', $stdout);
    }

    public function testNewArrayNamesTheMissingPackage(): void
    {
        [$stdout, , ] = $this->runScript('new-array', null, 'standalone-install.php');
        $this->assertStringContainsString('RuntimeException: SmartString::new($array) needs the itools/smartarray package: run "composer require itools/smartarray", then replace the call with SmartArrayHtml::new($array).', $stdout);
        $this->assertStringContainsString('standalone-install.php', $stdout, 'the error must point at the caller, not library internals');
    }

    public function testFromArrayNamesTheMissingPackage(): void
    {
        [$stdout, , ] = $this->runScript('from-array', null, 'standalone-install.php');
        $this->assertStringContainsString('RuntimeException: SmartString::fromArray() needs the itools/smartarray package: run "composer require itools/smartarray", then replace the call with SmartArrayHtml::new($array).', $stdout);
        $this->assertStringContainsString('standalone-install.php', $stdout, 'the error must point at the caller, not library internals');
    }
}
