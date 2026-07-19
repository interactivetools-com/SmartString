<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * The missing-value contract, pinned in one table: what every transformation
 * does with null and "" input.
 *
 * - String manipulation: missing (null or "") passes through unchanged
 * - Parse-based formatting and math: unparseable input becomes null, "" included
 * - Presence-conditional: missing skips the operation or triggers the fallback
 * - Terminal encoders: null and "" both output "" (jsonEncode outputs JSON literals)
 *
 * Not in the table: set(), ifTrue(), and ifEquals() replace unconditionally
 * (missingness is irrelevant), and map() always runs the callback on the raw
 * value, null included - pinned in StringManipulationTest.
 *
 * Per-method edge cases stay in the per-method test files; this table only
 * pins the null and "" columns so a new or edited method can't silently
 * leave the pattern.
 */
class MissingValueContractTest extends SmartStringTestCase
{
    #[DataProvider('transformationProvider')]
    public function testNullAndEmptyStringBehavior(callable $op, ?string $expectedForNull, ?string $expectedForEmpty): void
    {
        $this->assertSame($expectedForNull, $op(SmartString::new(null)), 'null input');
        $this->assertSame($expectedForEmpty, $op(SmartString::new('')), '"" input');
    }

    public static function transformationProvider(): array
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
}
