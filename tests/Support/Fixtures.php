<?php
declare(strict_types=1);

namespace Tests\Support;

use Itools\SmartArray\SmartNull;
use Itools\SmartString\SmartString;

/**
 * Shared test data for the Unit and Integration suites. Same style as
 * SmartArray's Fixtures so maintaining one suite teaches the other.
 */
class Fixtures
{
    /**
     * The standard menu of scalar edge values for any value-transforming method.
     * Keys are labels for data-provider names.
     */
    public static function edgeScalars(): array
    {
        return [
            'empty string'    => '',
            'zero string'     => '0',
            'zero int'        => 0,
            'zero float'      => 0.0,
            'negative zero'   => -0.0,
            'zero.zero str'   => '0.0',
            'false'           => false,
            'true'            => true,
            'null'            => null,
            'float'           => 3.14,
            'int'             => 42,
            'float string'    => '42.5',
            'int max'         => PHP_INT_MAX,
            'apostrophe'      => "O'Brien",
            'script tag'      => '<script>alert(1)</script>',
            'pre-encoded'     => '&amp; already encoded',
            'tab'             => "tab\tted",
            'newline'         => "line\nbreak",
            'padded numeric'  => ' 5 ',
            'exponent string' => '1e3',
            'invalid utf8'    => "caf\xE9",
            'unicode'         => 'ünïcödé 日本語 🚀',
        ];
    }

    /**
     * Input → expected htmlEncode() output pairs: the encoding contract for every
     * HTML output path (htmlEncode, __toString, nl2br pre-encoding, or*() messages).
     * Covers the HTML_ENCODE_FLAGS docblock promises: ENT_HTML5 quote entities,
     * ENT_SUBSTITUTE (invalid UTF-8 → �), ENT_DISALLOWED (C0/C1 controls → �), and
     * the invisible-Unicode set, which HTML encoding passes through unchanged (only
     * jsonEncode() makes those visible).
     *
     * Returns label => [input, expected].
     */
    public static function htmlPairs(): array
    {
        return [
            'less-than'          => ['<', '&lt;'],
            'greater-than'       => ['>', '&gt;'],
            'ampersand'          => ['&', '&amp;'],
            'double quote'       => ['"', '&quot;'],
            'apostrophe'         => ["'", '&apos;'], // ENT_HTML5: &apos;, not &#039;
            'script tag'         => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'already encoded'    => ['&lt;', '&amp;lt;'],
            'invalid utf8'       => ["caf\xE9", 'caf�'],
            'c0 control'         => ["a\x01b", 'a�b'],
            'c1 control'         => ["a\u{0085}b", 'a�b'],
            'zero-width space'   => ["a\u{200B}b", "a\u{200B}b"],
            'rtl override'       => ["a\u{202E}b", "a\u{202E}b"],
            'tag char'           => ["a\u{E0041}b", "a\u{E0041}b"],
            'variation selector' => ["a\u{FE0F}b", "a\u{FE0F}b"],
        ];
    }

    /**
     * Numeric boundary values for MathTest and FormattingTest. Values only - each
     * test pins its own expected literals.
     */
    public static function numericEdges(): array
    {
        return [
            'int max'          => PHP_INT_MAX,
            'int max overflow' => PHP_INT_MAX + 1, // float 9.2233720368547758E+18
            'large float'      => 1e15,
            'small float'      => 1e-6,
            'inf producer'     => '9e999',         // (float) is INF
            'negative zero'    => -0.0,
            'formatted number' => '3,000',         // non-numeric to PHP
            'numeric string'   => '42',
            'float string'     => '42.5',
        ];
    }

    /**
     * Every value shape a value-taking parameter accepts, with the raw value it
     * unwraps to. All value-shaped parameters take the full
     * int|float|string|bool|null|SmartString|SmartNull union, so one provider
     * drives the argument-acceptance tests for every method.
     *
     * Returns label => [argument, expected raw value].
     */
    public static function argumentMatrix(): array
    {
        return [
            'null'             => [null, null],
            'false'            => [false, false],
            'true'             => [true, true],
            'int'              => [42, 42],
            'float'            => [3.14, 3.14],
            'string'           => ['text', 'text'],
            'empty string'     => ['', ''],
            'SmartString'      => [new SmartString('wrapped'), 'wrapped'],
            'SmartString int'  => [new SmartString(5), 5],
            'SmartString null' => [new SmartString(null), null],
            'SmartNull'        => [new SmartNull(), null],
        ];
    }
}
