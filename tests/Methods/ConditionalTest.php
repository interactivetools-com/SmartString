<?php

/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

namespace Tests\Methods;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\TestCase;
use Itools\SmartString\Methods\Conditional;

class ConditionalTest extends TestCase
{
    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testOrMethod($value, $fallback, $expected): void
    {
        $result = Conditional::or($value, $fallback);
        $this->assertSame($expected['or'], $result, "or() method failed for input: " . var_export($value, true));
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIfNullMethod($value, $fallback, $expected): void
    {
        $result = Conditional::ifNull($value, $fallback);
        $this->assertSame(
            $expected['ifNull'],
            $result,
            "ifNull() method failed for input: " . var_export($value, true),
        );
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIfBlankMethod($value, $fallback, $expected): void
    {
        $result = Conditional::ifBlank($value, $fallback);
        $this->assertSame(
            $expected['ifBlank'],
            $result,
            "ifBlank() method failed for input: " . var_export($value, true),
        );
    }

    /**
     * @dataProvider conditionalMethodsProvider
     */
    public function testIsZeroMethod($value, $fallback, $expected): void
    {
        $result = Conditional::isZero($value, $fallback);
        $this->assertSame(
            $expected['isZero'],
            $result,
            "isZero() method failed for input: " . var_export($value, true),
        );
    }

    public function conditionalMethodsProvider(): array
    {
        $fallback = 'fallback';

        return [
            'empty string'      => [
                '',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '',
                    'ifBlank' => $fallback,
                    'isZero'  => '',
                ],
            ],
            'integer zero'      => [
                0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0,
                    'ifBlank' => 0,
                    'isZero'  => $fallback,
                ],
            ],
            'string zero'       => [
                '0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0',
                    'ifBlank' => '0',
                    'isZero'  => $fallback,
                ],
            ],
            'float zero'        => [
                0.0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0.0,
                    'ifBlank' => 0.0,
                    'isZero'  => $fallback,
                ],
            ],
            'string float zero' => [
                '0.0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0.0',
                    'ifBlank' => '0.0',
                    'isZero'  => $fallback,
                ],
            ],
            'null'              => [
                null,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'isZero'  => null,
                ],
            ],
            'whitespace'        => [
                ' ',
                $fallback,
                [
                    'or'      => ' ',
                    'ifNull'  => ' ',
                    'ifBlank' => ' ',
                    'isZero'  => ' ',
                ],
            ],
            'non-empty string'  => [
                'Hello',
                $fallback,
                [
                    'or'      => 'Hello',
                    'ifNull'  => 'Hello',
                    'ifBlank' => 'Hello',
                    'isZero'  => 'Hello',
                ],
            ],
            'SmartString fallback Null'  => [
                NULL,
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => NULL,
                    'isZero'  => NULL,
                ],
            ],
            'SmartString fallback Blank'  => [
                "",
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => "",
                    'ifBlank' => $fallback,
                    'isZero'  => "",
                ],
            ],
            'SmartString fallback Zero'  => [
                "0",
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => "0",
                    'ifBlank' => "0",
                    'isZero'  => $fallback,
                ],
            ],
        ];
    }
}