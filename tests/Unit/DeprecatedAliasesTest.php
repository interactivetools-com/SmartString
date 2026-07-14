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
            'textToHtml → nl2br'            => ['textToHtml', 'nl2br', [], "a<b\nc"],
        ];
    }

    public function testAliasesReturnSmartStringAndChain(): void
    {
        $result = SmartString::new('x')->and('!')->andPrefix('>')->or('n/a');
        $this->assertInstanceOf(SmartString::class, $result);
        $this->assertSame('>x!', $result->value());
    }
}
