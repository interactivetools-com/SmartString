<?php
declare(strict_types=1);

namespace Tests\Integration;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\SmartStringTestCase;

/**
 * Every public method must appear in the docs method reference and in
 * help.txt, so new methods can't ship undocumented.
 *
 * Exemptions:
 * - magic methods and jsonSerialize(): interface plumbing, never called directly
 * - everything in the DeprecatedAliases trait: renamed and retired methods
 *   keep working but stay out of the docs by design - docs show only the
 *   current names
 */
class DocsCoverageTest extends SmartStringTestCase
{
    private const EXEMPT = ['and', 'andPrefix', 'apply', 'dateTimeFormat', 'if', 'ifBlank', 'phoneFormat', 'textToHtml', 'jsonSerialize'];

    #[DataProvider('publicMethodsProvider')]
    public function testMethodIsInMethodReference(string $method): void
    {
        $reference = file_get_contents(dirname(__DIR__, 2) . '/docs/method-reference.md');
        $this->assertTrue(
            str_contains($reference, "->$method(") || str_contains($reference, "::$method("),
            "Public method $method() is not mentioned in docs/method-reference.md"
        );
    }

    #[DataProvider('publicMethodsProvider')]
    public function testMethodIsInHelpTxt(string $method): void
    {
        $helpText = file_get_contents(dirname(__DIR__, 2) . '/src/help.txt');
        $this->assertTrue(
            str_contains($helpText, "->$method(") || str_contains($helpText, "::$method("),
            "Public method $method() is not mentioned in src/help.txt"
        );
    }

    public static function publicMethodsProvider(): array
    {
        $methods = (new ReflectionClass(SmartString::class))->getMethods(ReflectionMethod::IS_PUBLIC);

        $rows = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (str_starts_with($name, '__') || in_array($name, self::EXEMPT, true)) {
                continue;
            }
            $rows[$name] = [$name];
        }
        return $rows;
    }

    /**
     * The exemption list stays honest: exempt methods must still exist.
     */
    public function testExemptMethodsStillExist(): void
    {
        foreach (self::EXEMPT as $method) {
            $this->assertTrue(method_exists(SmartString::class, $method), "Exempt method $method() no longer exists - remove it from the list");
        }
    }
}
