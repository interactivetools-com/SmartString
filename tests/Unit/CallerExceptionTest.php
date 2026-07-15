<?php
declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Itools\SmartString\CallerException;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Support\SmartStringTestCase;

/**
 * CallerException reports the caller's file:line instead of the library's
 * throw site. These tests pin the contract: getFile()/getLine() point at the
 * code that called the library (this file), the real throw site is preserved
 * in $thrownInFile/$thrownInLine, and catch blocks for InvalidArgumentException
 * still work.
 *
 * n/a dimensions: encoding, global settings, immutability (not a value method).
 */
class CallerExceptionTest extends SmartStringTestCase
{
    /**
     * Every developer-mistake throw site in the library.
     */
    public static function throwSitesProvider(): array
    {
        return [
            'pregReplace() bad pattern' => [
                fn() => SmartString::new('555-1234')->pregReplace('/[unclosed', ''),
                "pregReplace(): preg_replace(): No ending delimiter '/' found",
            ],
            'map() uncallable function' => [
                fn() => SmartString::new('hello')->map('noSuchFunction12345'),
                "Function 'noSuchFunction12345' is not callable",
            ],
            'map() non-scalar return' => [
                fn() => SmartString::new('hello')->map(fn() => new stdClass()),
                "map() callback must return a scalar value (string, int, float, bool, or null), got stdClass",
            ],
            'getRawValue() unsupported type' => [
                fn() => SmartString::getRawValue(new stdClass()),
                "Unsupported value type: stdClass",
            ],
        ];
    }

    #[DataProvider('throwSitesProvider')]
    public function testReportsThisFileAsTheCaller(callable $trigger, string $expectedMessage): void
    {
        try {
            $trigger();
            $this->fail('Expected CallerException was not thrown');
        } catch (CallerException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());
            $this->assertSame(__FILE__, $e->getFile(), 'getFile() should report the calling code, not the library');
            $this->assertGreaterThan(0, $e->getLine());
        }
    }

    public function testReportsTheExactCallingLine(): void
    {
        $line = 0;
        try {
            $line = __LINE__ + 1;
            SmartString::new('555-1234')->pregReplace('/[unclosed', '');
            $this->fail('Expected CallerException was not thrown');
        } catch (CallerException $e) {
            $this->assertSame(__FILE__, $e->getFile());
            $this->assertSame($line, $e->getLine());
        }
    }

    public function testKeepsTheRealThrowSiteInThrownInProperties(): void
    {
        try {
            SmartString::getRawValue(new stdClass());
            $this->fail('Expected CallerException was not thrown');
        } catch (CallerException $e) {
            $this->assertStringEndsWith('SmartString.php', $e->thrownInFile);
            $this->assertGreaterThan(0, $e->thrownInLine);
            $this->assertNotSame($e->getFile(), $e->thrownInFile);
        }
    }

    public function testSkipsInternalLibraryFrames(): void
    {
        // arrays recurse through getRawValue() via array_map(), adding internal frames
        // that the backtrace walk must skip to still reach this file
        $line = 0;
        try {
            $line = __LINE__ + 1;
            SmartString::getRawValue(['key' => new stdClass()]);
            $this->fail('Expected CallerException was not thrown');
        } catch (CallerException $e) {
            $this->assertSame(__FILE__, $e->getFile());
            $this->assertSame($line, $e->getLine());
        }
    }

    public function testCatchableAsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type: stdClass');
        SmartString::getRawValue(new stdClass());
    }
}
