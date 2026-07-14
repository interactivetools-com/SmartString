<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\Support\SmartStringTestCase;

/**
 * or(), append(), prepend(), wrap(), ifNull(), ifZero(), ifTrue(),
 * ifEquals(), set().
 *
 * The falsy matrix is the library's core promise: only null and '' are
 * missing; zero in every form is present. The retired ifBlank() keeps its
 * column in the matrix - the matrix IS its behavior spec.
 *
 * The and()/andPrefix()/if() aliases are covered in DeprecatedAliasesTest.
 *
 * n/a dimensions: encoding (conditionals transform the raw value), global
 * settings.
 */
class ConditionalTest extends SmartStringTestCase
{
    //region Falsy Matrix (or / ifNull / ifBlank / ifZero)

    #[DataProvider('falsyMatrixProvider')]
    public function testOr($value, $fallback, array $expected): void
    {
        $this->assertSmartString($expected['or'], SmartString::new($value)->or($fallback));
    }

    #[DataProvider('falsyMatrixProvider')]
    public function testIfNull($value, $fallback, array $expected): void
    {
        $this->assertSmartString($expected['ifNull'], SmartString::new($value)->ifNull($fallback));
    }

    /** ifBlank() is retired to DeprecatedAliases but keeps working - strictly '', not null */
    #[DataProvider('falsyMatrixProvider')]
    public function testIfBlank($value, $fallback, array $expected): void
    {
        $this->assertSmartString($expected['ifBlank'], SmartString::new($value)->ifBlank($fallback));
    }

    #[DataProvider('falsyMatrixProvider')]
    public function testIfZero($value, $fallback, array $expected): void
    {
        $this->assertSmartString($expected['ifZero'], SmartString::new($value)->ifZero($fallback));
    }

    public static function falsyMatrixProvider(): array
    {
        $fallback = 'fallback';

        // value, fallback, expected per method - side by side so the
        // missing/blank/zero distinctions read as one table
        return [
            'empty string'      => ['', $fallback, ['or' => $fallback, 'ifNull' => '', 'ifBlank' => $fallback, 'ifZero' => '']],
            'integer zero'      => [0, $fallback, ['or' => 0, 'ifNull' => 0, 'ifBlank' => 0, 'ifZero' => $fallback]],
            'string zero'       => ['0', $fallback, ['or' => '0', 'ifNull' => '0', 'ifBlank' => '0', 'ifZero' => $fallback]],
            'float zero'        => [0.0, $fallback, ['or' => 0.0, 'ifNull' => 0.0, 'ifBlank' => 0.0, 'ifZero' => $fallback]],
            'string float zero' => ['0.0', $fallback, ['or' => '0.0', 'ifNull' => '0.0', 'ifBlank' => '0.0', 'ifZero' => $fallback]],
            'null'              => [null, $fallback, ['or' => $fallback, 'ifNull' => $fallback, 'ifBlank' => null, 'ifZero' => null]],
            'false'             => [false, $fallback, ['or' => false, 'ifNull' => false, 'ifBlank' => false, 'ifZero' => false]],
            'whitespace'        => [' ', $fallback, ['or' => ' ', 'ifNull' => ' ', 'ifBlank' => ' ', 'ifZero' => ' ']],
            'non-empty string'  => ['Hello', $fallback, ['or' => 'Hello', 'ifNull' => 'Hello', 'ifBlank' => 'Hello', 'ifZero' => 'Hello']],

            // SmartString fallbacks unwrap to their raw value
            'SmartString fallback on null'  => [null, SmartString::new($fallback), ['or' => $fallback, 'ifNull' => $fallback, 'ifBlank' => null, 'ifZero' => null]],
            'SmartString fallback on blank' => ['', SmartString::new($fallback), ['or' => $fallback, 'ifNull' => '', 'ifBlank' => $fallback, 'ifZero' => '']],
            'SmartString fallback on zero'  => ['0', SmartString::new($fallback), ['or' => '0', 'ifNull' => '0', 'ifBlank' => '0', 'ifZero' => $fallback]],
        ];
    }

    public function testFallbackTypeIsPreserved(): void
    {
        $this->assertSame(0, SmartString::new(null)->or(0)->value());
        $this->assertSame(false, SmartString::new(null)->or(false)->value());
        $this->assertSame(3.14, SmartString::new('')->or(3.14)->value());
    }

    //endregion
    //region append() / prepend() / wrap()

    #[DataProvider('appendProvider')]
    public function testAppend($value, $suffix, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->append($suffix));
    }

    public static function appendProvider(): array
    {
        return [
            'non-empty string'   => ['Hello', ' World', 'Hello World'],
            'empty string'       => ['', 'World', ''],
            'null'               => [null, 'World', null],
            'false attaches alone' => [false, 'World', 'World'], // false is present but stringifies to ''
            'zero is present'    => [0, ' items', '0 items'],
            'numeric value'      => [100, '%', '100%'],
            'SmartString suffix' => ['Price: ', SmartString::new('$10.00'), 'Price: $10.00'],
        ];
    }

    #[DataProvider('prependProvider')]
    public function testPrepend($value, $prefix, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->prepend($prefix));
    }

    public static function prependProvider(): array
    {
        return [
            'non-empty string'   => ['World', 'Hello ', 'Hello World'],
            'empty string'       => ['', 'Hello ', ''],
            'null'               => [null, 'Hello ', null],
            'false attaches alone' => [false, 'Hello ', 'Hello '], // false is present but stringifies to ''
            'zero is present'    => [0, '$', '$0'],
            'numeric value'      => [100, '$', '$100'],
            'SmartString prefix' => ['items', SmartString::new('10 '), '10 items'],
        ];
    }

    #[DataProvider('wrapProvider')]
    public function testWrap($value, $before, $after, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->wrap($before, $after));
    }

    public static function wrapProvider(): array
    {
        return [
            'non-empty string'    => ['Sale', '[', ']', '[Sale]'],
            'empty string'        => ['', '(', ')', ''],
            'null'                => [null, '(', ')', null],
            'false attaches alone' => [false, '(', ')', '()'], // false is present but stringifies to ''
            'zero is present'     => [0, '(', ')', '(0)'],
            'numeric value'       => [19.99, '(', ')', '(19.99)'],
            'empty before'        => ['note', '', '!', 'note!'],
            'empty after'         => ['ext. 204', '(', '', '(ext. 204'],
            'SmartString sides'   => ['x', SmartString::new('<'), SmartString::new('>'), '<x>'],
        ];
    }

    /**
     * Pinned: attaching mutates the value type to string (string
     * concatenation), except when nothing attaches.
     */
    public function testAttachMutatesTypeToStringWhenAttaching(): void
    {
        $this->assertSame('0 items', SmartString::new(0)->append(' items')->value());
        $this->assertNull(SmartString::new(null)->append(' items')->value()); // null stays null, not ''
        $this->assertNull(SmartString::new(null)->wrap('(', ')')->value());   // wrapper vanishes, null preserved
    }

    //endregion
    //region ifTrue() / ifEquals() / set()

    #[DataProvider('ifTrueProvider')]
    public function testIfTrue($value, $condition, $valueIfTrue, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->ifTrue($condition, $valueIfTrue));
    }

    public static function ifTrueProvider(): array
    {
        return [
            'true condition'          => [5, true, 10, 10],
            'false condition'         => [5, false, 10, 5],
            'null condition'          => [5, null, 10, 5],
            'zero condition'          => [5, 0, 10, 5],
            'string condition true'   => [5, '1', 10, 10],
            'string condition false'  => [5, '0', 10, 5],
            'SmartNull condition'     => [5, new SmartNull(), 99, 5], // falsy, no TypeError
            'SmartString condition'   => [5, SmartString::new('x'), 10, 10],
            'SmartString valueIfTrue' => [5, true, new SmartString('replaced'), 'replaced'],
        ];
    }

    #[DataProvider('ifEqualsProvider')]
    public function testIfEquals($value, $match, $newValue, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->ifEquals($match, $newValue));
    }

    public static function ifEqualsProvider(): array
    {
        return [
            'exact match'              => ['unknown', 'unknown', '', ''],
            'no match keeps value'     => ['Vancouver', 'unknown', '', 'Vancouver'],
            'loose: string vs int'     => ['5', 5, 'five', 'five'],
            'loose: int vs string'     => [-1, '-1', 'Unlimited', 'Unlimited'],
            'sentinel date'            => ['0000-00-00', '0000-00-00', null, null],
            'zero vs string zero'      => [0, '0', 'none', 'none'],
            'loose: false matches 0'   => [false, 0, 'zero', 'zero'],     // pinned: PHP loose ==, docs say use ifNull/ifZero for these
            'blank does not match 0'   => ['', 0, 'zero', ''],            // PHP 8 saner comparisons
            'replacement type kept'    => ['9', 9, 42, 42],
            'SmartString match'        => ['x', SmartString::new('x'), 'hit', 'hit'],
            'SmartNull match on null'  => [null, new SmartNull(), 'hit', 'hit'], // null == null
        ];
    }

    #[DataProvider('setProvider')]
    public function testSet($value, $newValue, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->set($newValue));
    }

    public static function setProvider(): array
    {
        return [
            'set integer'     => [5, 10, 10],
            'set string'      => ['old', 'new', 'new'],
            'set null'        => [5, null, null],
            'set false'       => [5, false, false],
            'set SmartString' => ['original', new SmartString('wrapped'), 'wrapped'],
            'set SmartNull'   => [5, new SmartNull(), null],
        ];
    }

    //endregion
    //region Argument Matrix

    /**
     * Every fallback shape accepted by or(), unwrapped to its raw value -
     * one row per shape from the shared matrix.
     */
    #[DataProvider('argumentMatrixProvider')]
    public function testOrAcceptsEveryArgumentShape($argument, $expectedRaw): void
    {
        $this->assertSmartString($expectedRaw, SmartString::new(null)->or($argument));
    }

    public static function argumentMatrixProvider(): array
    {
        return Fixtures::argumentMatrix();
    }

    public function testConditionalsAcceptSmartNull(): void
    {
        $smartNull = new SmartNull();
        $this->assertSame('x', SmartString::new('x')->or($smartNull)->value());
        $this->assertNull(SmartString::new(null)->or($smartNull)->value());
        $this->assertNull(SmartString::new(null)->ifNull($smartNull)->value());
        $this->assertSame('kept', SmartString::new('kept')->append($smartNull)->value());
    }

    //endregion
    //region Immutability

    public function testConditionalsAreImmutable(): void
    {
        $original = SmartString::new(null);
        $original->or('fallback')->set('other');
        $this->assertNull($original->value());

        $present = SmartString::new('Hi');
        $present->append('!')->prepend('>')->wrap('[', ']');
        $this->assertSame('Hi', $present->value());
    }

    //endregion
}
