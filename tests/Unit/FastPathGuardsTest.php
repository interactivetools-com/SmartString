<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Itools\SmartString\Tests\Support\SmartStringTestCase;
use ReflectionMethod;

/**
 * Call-site-form enforcement: the per-row hot methods guard every
 * self::getRawValue() call with an inline is_scalar()/is_string() fast path,
 * so plain values (the common case) skip the call entirely. This test reads
 * the source of each hot method and fails any getRawValue() call that lost
 * its guard, so the pattern can't drift out in later edits.
 *
 * Cold methods (dieHttp texts, __callStatic, etc.) are intentionally not
 * listed - the guard is only justified where templates call per row.
 */
class FastPathGuardsTest extends SmartStringTestCase
{
    /** @return array<string, array{string}> */
    public static function hotMethodProvider(): array
    {
        $methods = [
            'appendHtml', 'wrapHtml', 'percent', 'or', 'append', 'prepend', 'wrap',
            'ifNull', 'ifZero', 'ifTrue', 'ifEquals', 'set', 'pregReplace',
        ];
        $cases = [];
        foreach ($methods as $method) {
            $cases[$method] = [$method];
        }
        return $cases;
    }

    #[DataProvider('hotMethodProvider')]
    public function testHotMethodGuardsItsGetRawValueCalls(string $method): void
    {
        $reflection = new ReflectionMethod(SmartString::class, $method);
        $lines      = file($reflection->getFileName());
        $body       = array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1);

        $callCount = 0;
        foreach ($body as $offset => $line) {
            if (!str_contains($line, 'self::getRawValue(')) {
                continue;
            }
            $callCount++;
            $hasGuard = str_contains($line, 'is_scalar(') || str_contains($line, 'is_string(');
            $lineNo   = $reflection->getStartLine() + $offset;
            $this->assertTrue($hasGuard, "$method() line $lineNo calls self::getRawValue() without an inline is_scalar()/is_string() fast-path guard:\n" . trim($line));
        }
        $this->assertGreaterThan(0, $callCount, "$method() no longer calls self::getRawValue() - remove it from this test's hot-method list");
    }
}
