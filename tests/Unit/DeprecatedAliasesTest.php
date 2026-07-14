<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * The DeprecatedAliases trait: old names delegate to the new methods with
 * identical output and zero runtime notices - the deprecation surface is
 * IDE/static-analysis only.
 *
 * textToHtml()'s output contract (incl. keepBr) stays in HtmlOutputTest;
 * this file only pins its silence and delegation.
 */
class DeprecatedAliasesTest extends SmartStringTestCase
{

    #[DataProvider('aliasProvider')]
    public function testAliasMatchesNewMethodSilently(string $alias, string $method, array $args, mixed $input): void
    {
        $aliasResult = $this->assertNoOutput(fn() => SmartString::new($input)->$alias(...$args));
        $newResult   = SmartString::new($input)->$method(...$args);

        $this->assertSame(
            SmartString::getRawValue($newResult),
            SmartString::getRawValue($aliasResult),
            "$alias() diverged from $method() for input: " . var_export($input, true)
        );
    }

    public static function aliasProvider(): array
    {
        return [
            'and → append (present)'        => ['and', 'append', [' items'], 5],
            'and → append (missing)'        => ['and', 'append', [' items'], null],
            'andPrefix → prepend (present)' => ['andPrefix', 'prepend', ['$'], 100],
            'andPrefix → prepend (missing)' => ['andPrefix', 'prepend', ['$'], ''],
            'apply → map'                   => ['apply', 'map', ['strtoupper'], 'hello'],
            'apply → map (with args)'       => ['apply', 'map', ['str_pad', 5, '*'], 'ab'],
            'if → ifTrue (fires)'           => ['if', 'ifTrue', [true, 'FB'], 5],
            'if → ifTrue (keeps)'           => ['if', 'ifTrue', [false, 'FB'], 5],
            'textToHtml → nl2br'            => ['textToHtml', 'nl2br', [], "a<b\nc"],
        ];
    }

    public function testAliasesReturnSmartStringAndChain(): void
    {
        $result = SmartString::new('x')->and('!')->andPrefix('>')->or('n/a');
        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame('>x!', $result->value());
    }

    /**
     * ifBlank() is retired with no direct replacement (or() covers the usual
     * intent; ifEquals('', ...) is loose). It keeps its own implementation
     * here: strictly '', not null. The full falsy matrix in ConditionalTest
     * pins its behavior; this just pins the silence.
     */
    public function testIfBlankStillWorksSilently(): void
    {
        $result = $this->assertNoOutput(fn() => SmartString::new('')->ifBlank('FB'));
        $this->assertSame('FB', $result->value());
        $this->assertNull(SmartString::new(null)->ifBlank('FB')->value()); // null does not fire
    }
}
