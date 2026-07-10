<?php
declare(strict_types=1);

namespace Tests\Integration;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\SmartStringTestCase;

/**
 * Every public method must appear in the README method reference and in
 * help.txt, so new methods can't ship undocumented.
 *
 * Exemptions:
 * - magic methods and jsonSerialize(): interface plumbing, never called directly
 * - textToHtml(): the one deliberate omission - a silent compatibility alias
 *   for nl2br(), kept undocumented by design
 */
class DocsCoverageTest extends SmartStringTestCase
{
    private const EXEMPT = ['textToHtml', 'jsonSerialize'];

    #[DataProvider('publicMethodsProvider')]
    public function testMethodIsInReadme(string $method): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');
        $this->assertTrue(
            str_contains($readme, "->$method(") || str_contains($readme, "::$method("),
            "Public method $method() is not mentioned in README.md"
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
