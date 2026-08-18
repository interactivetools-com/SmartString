<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\Deprecations;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * The missing-value contract, pinned in one table: what every transformation
 * does with null and "" input.
 *
 * - String manipulation: missing (null or "") passes through unchanged
 * - Parse-based formatting and math: unparseable input becomes null, "" included
 * - Presence-conditional: missing skips the operation or triggers the fallback
 * - Terminal encoders: null and "" both output "" (jsonEncode outputs JSON literals)
 *
 * The method list comes from reflection, not from the table, so a new or
 * renamed public method fails here until it either has a table row or is
 * listed in NOT_A_TRANSFORMATION with the reason it has no null/"" contract.
 *
 * Per-method edge cases stay in the per-method test files; this table only
 * pins the null and "" columns.
 */
class MissingValueContractTest extends SmartStringTestCase
{
    // The deprecated shim list is closed: new code uses the current names, which the table
    // below covers. Pinned here so adding a shim fails testDeprecatedAliasListIsClosed()
    // instead of quietly inheriting the group exemption.
    private const DEPRECATED_ALIASES = [
        'and', 'andPrefix', 'apply', 'dateTimeFormat', 'help', 'if', 'ifBlank', 'phoneFormat', 'textToHtml',
    ];

    /**
     * Public methods with no null/"" contract of their own, and why.
     *
     * Deprecated aliases are exempt as a group (read by reflection from the
     * Deprecations trait). They are frozen shims kept for old code, pinned
     * against their current equivalents in DeprecationsTest.
     */
    private const NOT_A_TRANSFORMATION = [
        'new'           => 'static factory: wraps a value instead of transforming a stored one',
        'getRawValue'   => 'static unwrapper: reads the argument, not the stored value',
        'value'         => 'accessor: returns the stored value unchanged',
        'rawHtml'       => 'accessor: returns the stored value unchanged',
        'int'           => 'type cast: missing converts the way PHP casts, pinned in TypeConversionTest',
        'float'         => 'type cast: missing converts the way PHP casts, pinned in TypeConversionTest',
        'bool'          => 'type cast: missing converts the way PHP casts, pinned in TypeConversionTest',
        'string'        => 'type cast: missing converts the way PHP casts, pinned in TypeConversionTest',
        'isEmpty'       => 'reports missingness instead of acting on it, pinned in ValidationTest',
        'isNotEmpty'    => 'reports missingness instead of acting on it, pinned in ValidationTest',
        'isMissing'     => 'reports missingness instead of acting on it, pinned in ValidationTest',
        'isNull'        => 'reports missingness instead of acting on it, pinned in ValidationTest',
        'or404'         => 'exits the process when missing, pinned out of process in EmptyGuardsTest',
        'orDie'         => 'exits the process when missing, pinned out of process in EmptyGuardsTest',
        'orRedirect'    => 'exits the process when missing, pinned out of process in EmptyGuardsTest',
        'orThrow'       => 'throws when missing, pinned in EmptyGuardsTest',
        'set'           => 'replaces unconditionally, missingness is irrelevant',
        'ifTrue'        => 'replaces on its condition argument, not on missingness',
        'ifEquals'      => 'replaces on its match argument, not on missingness',
        'map'           => 'always runs the callback on the raw value, null included, pinned in StringManipulationTest',
        'getIterator'   => 'interface plumbing: only throws a foreach-misuse error',
        'jsonSerialize' => 'interface plumbing: called by json_encode(), never directly',
    ];

    #[DataProvider('transformationProvider')]
    public function testNullAndEmptyStringBehavior(string $method): void
    {
        $table = self::missingValueTable();
        $this->assertArrayHasKey($method, $table,
            "Public method $method() has no missing-value row - add one to missingValueTable(), or list it in NOT_A_TRANSFORMATION with the reason it has no null/\"\" contract");

        [$op, $expectedForNull, $expectedForEmpty] = $table[$method];
        $this->assertSame($expectedForNull, $op(SmartString::new(null)), 'null input');
        $this->assertSame($expectedForEmpty, $op(SmartString::new('')), '"" input');
    }

    /**
     * Every public method that needs a missing-value row: the SmartString API
     * minus magic methods, deprecated aliases, and NOT_A_TRANSFORMATION.
     */
    public static function transformationProvider(): array
    {
        $deprecated = array_map(
            static fn(ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(Deprecations::class))->getMethods(ReflectionMethod::IS_PUBLIC)
        );

        $methods = (new ReflectionClass(SmartString::class))->getMethods(ReflectionMethod::IS_PUBLIC);

        $rows = [];
        foreach ($methods as $method) {
            $name = $method->getName();
            if (str_starts_with($name, '__') || in_array($name, $deprecated, true) || array_key_exists($name, self::NOT_A_TRANSFORMATION)) {
                continue;
            }
            $rows[$name] = [$name];
        }
        return $rows;
    }

    private static function missingValueTable(): array
    {
        // rows are [op, expected for null, expected for ""]; ops unwrap chainable results with ->value()
        return [
            // string manipulation: missing passes through unchanged
            'textOnly'     => [fn($s) => $s->textOnly()->value(), null, ''],
            'trim'         => [fn($s) => $s->trim()->value(), null, ''],
            'maxWords'     => [fn($s) => $s->maxWords(5)->value(), null, ''],
            'maxChars'     => [fn($s) => $s->maxChars(5)->value(), null, ''],
            'pregReplace'  => [fn($s) => $s->pregReplace('/^$/', 'X')->value(), null, ''], // pattern matches "" but never runs

            // parse-based formatting and math: unparseable becomes null, "" included
            'dateFormat'   => [fn($s) => $s->dateFormat('Y-m-d')->value(), null, null],
            'numberFormat' => [fn($s) => $s->numberFormat(2)->value(), null, null],
            'percent'      => [fn($s) => $s->percent(2)->value(), null, null],
            'percentOf'    => [fn($s) => $s->percentOf(100)->value(), null, null],
            'add'          => [fn($s) => $s->add(1)->value(), null, null],
            'subtract'     => [fn($s) => $s->subtract(1)->value(), null, null],
            'multiply'     => [fn($s) => $s->multiply(2)->value(), null, null],
            'divide'       => [fn($s) => $s->divide(2)->value(), null, null],

            // presence-conditional: missing triggers the fallback or skips the operation
            'or'           => [fn($s) => $s->or('fallback')->value(), 'fallback', 'fallback'],
            'ifNull'       => [fn($s) => $s->ifNull('fallback')->value(), 'fallback', ''], // "" is not null
            'ifZero'       => [fn($s) => $s->ifZero('fallback')->value(), null, ''],       // neither is numeric zero
            'append'       => [fn($s) => $s->append('x')->value(), null, ''],
            'prepend'      => [fn($s) => $s->prepend('x')->value(), null, ''],
            'wrap'         => [fn($s) => $s->wrap('(', ')')->value(), null, ''],

            // terminal encoders: null and "" both output "" (json outputs literals)
            'htmlEncode'   => [fn($s) => $s->htmlEncode(), '', ''],
            'nl2br'        => [fn($s) => $s->nl2br(), '', ''],
            'urlEncode'    => [fn($s) => $s->urlEncode(), '', ''],
            'jsonEncode'   => [fn($s) => $s->jsonEncode(), 'null', '""'],
            'appendHtml'   => [fn($s) => $s->appendHtml('<br>'), '', ''],
            'wrapHtml'     => [fn($s) => $s->wrapHtml('<b>', '</b>'), '', ''],
        ];
    }

    /**
     * The table and the exemption list stay honest: every name in them must
     * still be a method, so a removed or renamed method can't leave a row
     * that nothing runs or an exemption that hides nothing.
     */
    public function testTableRowsAndExemptionsStillExist(): void
    {
        $names = [...array_keys(self::missingValueTable()), ...array_keys(self::NOT_A_TRANSFORMATION)];
        foreach ($names as $method) {
            $this->assertTrue(method_exists(SmartString::class, $method),
                "$method() no longer exists - remove it from missingValueTable() or NOT_A_TRANSFORMATION");
        }
    }

    /**
     * transformationProvider() skips every method on the Deprecations trait, so a method
     * added there would inherit that exemption without anyone choosing it. Pinning the
     * list turns that into a failure that asks for the decision.
     */
    public function testDeprecatedAliasListIsClosed(): void
    {
        $actual = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(Deprecations::class))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
        sort($actual);
        $expected = self::DEPRECATED_ALIASES;
        sort($expected);

        $this->assertSame($expected, $actual,
            'The Deprecations trait changed. A new shim is exempt from the missing-value table by default - give it a row in missingValueTable(), or add it here with the reason it needs none');
    }
}
