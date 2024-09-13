<?php

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
            'empty string'               => [
                '',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '',
                    'ifBlank' => $fallback,
                    'isZero'  => '',
                ],
            ],
            'integer zero'               => [
                0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0,
                    'ifBlank' => 0,
                    'isZero'  => $fallback,
                ],
            ],
            'string zero'                => [
                '0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0',
                    'ifBlank' => '0',
                    'isZero'  => $fallback,
                ],
            ],
            'float zero'                 => [
                0.0,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => 0.0,
                    'ifBlank' => 0.0,
                    'isZero'  => $fallback,
                ],
            ],
            'string float zero'          => [
                '0.0',
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => '0.0',
                    'ifBlank' => '0.0',
                    'isZero'  => $fallback,
                ],
            ],
            'null'                       => [
                null,
                $fallback,
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'isZero'  => null,
                ],
            ],
            'whitespace'                 => [
                ' ',
                $fallback,
                [
                    'or'      => ' ',
                    'ifNull'  => ' ',
                    'ifBlank' => ' ',
                    'isZero'  => ' ',
                ],
            ],
            'non-empty string'           => [
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
                null,
                SmartString::new($fallback),
                [
                    'or'      => $fallback,
                    'ifNull'  => $fallback,
                    'ifBlank' => null,
                    'isZero'  => null,
                ],
            ],
            'SmartString fallback Blank' => [
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


    /**
     * @dataProvider ifMethodProvider
     */
    public function testIfMethod($value, $condition, $valueIfTrue, $expected): void
    {
        $result = Conditional::if($value, $condition, $valueIfTrue);
        $this->assertSame($expected, $result);
    }

    public function ifMethodProvider(): array
    {
        return [
            'condition true'         => [
                'value'       => 5,
                'condition'   => true,
                'valueIfTrue' => 10,
                'expected'    => 10,
            ],
            'condition false'        => [
                'value'       => 5,
                'condition'   => false,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'null condition'         => [
                'value'       => 5,
                'condition'   => null,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'zero condition'         => [
                'value'       => 5,
                'condition'   => 0,
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'string condition true'  => [
                'value'       => 5,
                'condition'   => '1',
                'valueIfTrue' => 10,
                'expected'    => 10,
            ],
            'string condition false' => [
                'value'       => 5,
                'condition'   => '0',
                'valueIfTrue' => 10,
                'expected'    => 5,
            ],
            'object valueIfTrue'     => [
                'value'       => 5,
                'condition'   => true,
                'valueIfTrue' => new class {
                    public function value(): int
                    {
                        return 15;
                    }
                },
                'expected'    => 15,
            ],
        ];
    }

    /**
     * @dataProvider setMethodProvider
     */
    public function testSetMethod($value, $newValue, $expected): void
    {
        $result = Conditional::set($value, $newValue);
        $this->assertSame($expected, $result);
    }

    public function setMethodProvider(): array
    {
        return [
            'set integer' => [
                'value'    => 5,
                'newValue' => 10,
                'expected' => 10,
            ],
            'set string'  => [
                'value'    => 'old',
                'newValue' => 'new',
                'expected' => 'new',
            ],
            'set null'    => [
                'value'    => 5,
                'newValue' => null,
                'expected' => null,
            ],
            'set object'  => [
                'value'    => 5,
                'newValue' => new class {
                    public function value(): int
                    {
                        return 15;
                    }
                },
                'expected' => 15,
            ],
        ];
    }

}
