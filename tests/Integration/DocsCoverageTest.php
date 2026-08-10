<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Integration;

use Itools\SmartString\Deprecations;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * Every public method must appear in the docs method reference and in
 * ai-reference.md (the one docs file that ships in the Composer bundle),
 * so new methods can't ship undocumented.
 *
 * Exemptions:
 * - magic methods, jsonSerialize(), getIterator(): interface plumbing, never
 *   called directly (getIterator only throws a foreach-misuse error)
 * - everything in the Deprecations trait (derived by reflection): renamed and
 *   retired methods keep working but stay out of the docs by design - docs
 *   show only the current names
 */
class DocsCoverageTest extends SmartStringTestCase
{
    private const INTERFACE_PLUMBING = ['jsonSerialize', 'getIterator'];

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
    public function testMethodIsInAiReference(string $method): void
    {
        // ai-reference writes methods bare (`textOnly(): SmartString`), so there's no arrow or
        // colons to anchor on; the left boundary is what keeps map() from matching array_map()
        $reference = file_get_contents(dirname(__DIR__, 2) . '/docs/ai-reference.md');
        $this->assertTrue(
            preg_match('/(?<![A-Za-z0-9_])' . preg_quote($method, '/') . '\(/', $reference) === 1,
            "Public method $method() is not mentioned in docs/ai-reference.md"
        );
    }

    public static function publicMethodsProvider(): array
    {
        $deprecated = array_map(
            static fn(ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(Deprecations::class))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
        $exempt = [...$deprecated, ...self::INTERFACE_PLUMBING];

        $methods = (new ReflectionClass(SmartString::class))->getMethods(ReflectionMethod::IS_PUBLIC);

        $rows = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (str_starts_with($name, '__') || in_array($name, $exempt, true)) {
                continue;
            }
            $rows[$name] = [$name];
        }
        return $rows;
    }

    /**
     * The exemption list stays honest: exempt methods must still exist.
     * (The Deprecations trait names exist by definition, so only the
     * hand-listed plumbing needs checking.)
     */
    public function testExemptMethodsStillExist(): void
    {
        foreach (self::INTERFACE_PLUMBING as $method) {
            $this->assertTrue(method_exists(SmartString::class, $method), "Exempt method $method() no longer exists - remove it from the list");
        }
    }
}
