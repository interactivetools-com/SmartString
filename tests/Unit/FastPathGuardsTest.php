<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Itools\SmartString\Tests\Support\SmartStringTestCase;
use ReflectionMethod;

/**
 * Call-site-form enforcement: source-inspection tests that pin two fast-path
 * patterns in SmartString so later edits can't silently drop them.
 *
 * 1. Every method that calls self::getRawValue() guards each call with an inline
 *    is_scalar()/is_string() check on the same line, so plain values (the common
 *    case) skip the call entirely. The only unlisted caller is the deprecated
 *    rawValue() shim in __callStatic, which just forwards its arguments.
 *
 * 2. The methods that check for a missing value inline isMissing()'s two
 *    comparisons instead of calling it - a method call costs more than the
 *    comparisons themselves. Each guard line is pinned character-for-character
 *    to isMissing()'s body (line comments stripped first), so a copy can't
 *    drift - not by isMissing() changing, not by an extra condition spliced
 *    into a guard, and not by the expression surviving only in a comment.
 */
class FastPathGuardsTest extends SmartStringTestCase
{
    /** isMissing()'s body, and its negation, exactly as the inlined call sites spell them */
    private const IS_MISSING_EXPR     = "\$this->rawData === null || \$this->rawData === ''";
    private const IS_NOT_MISSING_EXPR = "\$this->rawData !== null && \$this->rawData !== ''";

    /** @return array{0: list<string>, 1: int} Method source lines and the first line's number */
    private static function methodSource(string $method): array
    {
        $reflection = new ReflectionMethod(SmartString::class, $method);
        $lines      = file($reflection->getFileName());
        $body       = array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1);
        return [$body, $reflection->getStartLine()];
    }

    /**
     * Every line of a method that compares $this->rawData, with line comments
     * stripped and whitespace trimmed. Comment stripping means a guard that only
     * survives in a comment doesn't count, and returning whole lines means an
     * extra comparison spliced into a guard changes the line and fails assertSame.
     *
     * @return list<string>
     */
    private static function rawDataComparisonLines(string $method): array
    {
        [$body, ] = self::methodSource($method);
        $comparisonLines = [];
        foreach ($body as $line) {
            $code = trim(preg_replace('~//.*~', '', $line));
            if (preg_match('~\$this->rawData\s*[=!]=~', $code)) {
                $comparisonLines[] = $code;
            }
        }
        return $comparisonLines;
    }

    /** @return array<string, array{string}> */
    public static function hotMethodProvider(): array
    {
        $methods = [
            'appendHtml', 'wrapHtml', 'percent', 'or', 'append', 'prepend', 'wrap',
            'ifNull', 'ifZero', 'ifTrue', 'ifEquals', 'set', 'pregReplace',
            'trim', 'or404', 'orDie', 'orThrow', 'orRedirect', 'getFloatOrNull',
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
        [$body, $startLine] = self::methodSource($method);

        $callCount = 0;
        foreach ($body as $offset => $line) {
            if (!str_contains($line, 'self::getRawValue(')) {
                continue;
            }
            $callCount++;
            $hasGuard = str_contains($line, 'is_scalar(') || str_contains($line, 'is_string(');
            $lineNo   = $startLine + $offset;
            $this->assertTrue($hasGuard, "$method() line $lineNo calls self::getRawValue() without an inline is_scalar()/is_string() fast-path guard:\n" . trim($line));
        }
        $this->assertGreaterThan(0, $callCount, "$method() no longer calls self::getRawValue() - remove it from this test's hot-method list");
    }

    public function testIsMissingBodyMatchesTheInlinedCopies(): void
    {
        $this->assertSame(
            ['return ' . self::IS_MISSING_EXPR . ';'],
            self::rawDataComparisonLines('isMissing'),
            "isMissing()'s body changed - update the inlined copies in every method listed in inlinedIsMissingProvider(), and this test's IS_MISSING_EXPR/IS_NOT_MISSING_EXPR constants",
        );
    }

    /** @return array<string, array{string, string}> */
    public static function inlinedIsMissingProvider(): array
    {
        $ifMissing    = 'if (' . self::IS_MISSING_EXPR . ') {';
        $ifNotMissing = 'if (' . self::IS_NOT_MISSING_EXPR . ') {';

        $methodToGuardLine = [
            // methods that inline the "value is missing" form
            'appendHtml'  => $ifMissing,
            'wrapHtml'    => $ifMissing,
            'textOnly'    => $ifMissing,
            'trim'        => $ifMissing,
            'maxWords'    => $ifMissing,
            'maxChars'    => $ifMissing,
            'or'          => '$newValue = ' . self::IS_MISSING_EXPR, // ternary condition, result lines follow
            'orDie'       => $ifMissing,
            'orThrow'     => $ifMissing,
            'orRedirect'  => $ifMissing,
            'pregReplace' => $ifMissing,
            // methods that inline the negated "value is present" form
            'append'      => $ifNotMissing,
            'prepend'     => $ifNotMissing,
            'wrap'        => $ifNotMissing,
            'or404'       => $ifNotMissing,
        ];
        $cases = [];
        foreach ($methodToGuardLine as $method => $guardLine) {
            $cases[$method] = [$method, $guardLine];
        }
        return $cases;
    }

    #[DataProvider('inlinedIsMissingProvider')]
    public function testMethodInlinesIsMissing(string $method, string $guardLine): void
    {
        $this->assertSame(
            [$guardLine],
            self::rawDataComparisonLines($method),
            "$method()'s missing-value guard no longer matches isMissing()'s comparisons exactly - restore the inline form, or remove the method from this test's list if it no longer checks for missing values",
        );
    }

    /** Behavioral backstop for the guard pins: "0" is a present value, not a missing one */
    public function testZeroStringIsPresent(): void
    {
        $this->assertSame('0<br>', SmartString::new('0')->appendHtml('<br>'));
        $this->assertSame('0', SmartString::new('0')->or('fallback')->value());
    }
}
