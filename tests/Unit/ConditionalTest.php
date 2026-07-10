<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures;
use Tests\Support\SmartStringTestCase;

/**
 * or(), and(), andPrefix(), ifBlank(), ifNull(), ifZero(), if(), set().
 *
 * The falsy matrix is the library's core promise: only null and '' are
 * missing; zero in every form is present.
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
    //region and() / andPrefix()

    #[DataProvider('andProvider')]
    public function testAnd($value, $suffix, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->and($suffix));
    }

    public static function andProvider(): array
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

    #[DataProvider('andPrefixProvider')]
    public function testAndPrefix($value, $prefix, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->andPrefix($prefix));
    }

    public static function andPrefixProvider(): array
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

    /**
     * Pinned: attaching mutates the value type to string (string
     * concatenation), except when nothing attaches.
     */
    public function testAndMutatesTypeToStringWhenAttaching(): void
    {
        $this->assertSame('0 items', SmartString::new(0)->and(' items')->value());
        $this->assertNull(SmartString::new(null)->and(' items')->value()); // null stays null, not ''
    }

    //endregion
    //region if() / set()

    #[DataProvider('ifProvider')]
    public function testIf($value, $condition, $valueIfTrue, $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($value)->if($condition, $valueIfTrue));
    }

    public static function ifProvider(): array
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
        $this->assertSame('kept', SmartString::new('kept')->and($smartNull)->value());
    }

    //endregion
    //region Immutability

    public function testConditionalsAreImmutable(): void
    {
        $original = SmartString::new(null);
        $original->or('fallback')->set('other');
        $this->assertNull($original->value());

        $present = SmartString::new('Hi');
        $present->and('!')->andPrefix('>');
        $this->assertSame('Hi', $present->value());
    }

    //endregion
}
