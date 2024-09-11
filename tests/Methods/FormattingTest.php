<?php

/** @noinspection UnknownInspectionInspection */
declare(strict_types=1);

namespace Tests\Methods;

use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;

class FormattingTest extends TestCase
{

    // region test setup/teardown
    private static string $originalTimezone;

    public static function setUpBeforeClass(): void
    {
        // Set timezone and save the original timezone
        self::$originalTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Phoenix'); // Timezone with no DST for consistent results
    }

    public static function tearDownAfterClass(): void
    {
        // Restore the original timezone after all tests in this class
        date_default_timezone_set(self::$originalTimezone);
    }

    // endregion
    // region dateFormat()

    /**
     * @dataProvider dateFormatProvider
     */
    public function testDateFormat($input, $format, $expected): void
    {
        // dateFormat
        $result = SmartString::new($input)->dateFormat($format)->value();
        $error  = "Failed for input: " . var_export($input, true) . ", output: " . var_export($result, true);
        $this->assertSame($expected, $result, $error);
    }

    /**
     * @dataProvider dateFormatProvider
     */
    public function testDateTimeFormat($input, $format, $expected): void
    {
        // dateFormat
        $result = SmartString::new($input)->dateFormat($format)->value();
        $error  = "Failed for input: " . var_export($input, true) . ", output: " . var_export($result, true);
        $this->assertSame($expected, $result, $error);
    }

    public function dateFormatProvider(): array
    {
        return [
            'MySQL datetime'            => [
                '2023-05-15 14:30:00',
                'Y-m-d H:i:s T',
                '2023-05-15 14:30:00 MST',
            ],
            'MySQL date'                => [
                '2023-05-15',
                'Y-m-d T',
                '2023-05-15 MST',
            ],
            'MySQL time'                => [
                '14:30:00',
                'H:i:s T',
                '14:30:00 MST',
            ],
            'Unix timestamp'            => [
                1684159800,
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Unix timestamp as string'  => [
                '1684159800',
                'Y-m-d H:i:s T',
                '2023-05-15 07:10:00 MST',
            ],
            'Custom format output'      => [
                '2023-05-15 14:30:00',
                'd/m/Y H:i T',
                '15/05/2023 14:30 MST',
            ],
            'Null input'                => [
                null,
                'Y-m-d T',
                null,
            ],
            'Zero timestamp'            => [
                0,
                'Y-m-d H:i:s T',
                null,
            ],
            'Negative timestamp'        => [
                -1684159800,
                'Y-m-d H:i:s T',
                '1916-08-19 02:50:00 MST',
            ],
            'Far future date'           => [
                '2123-05-15 14:30:00',
                'Y-m-d H:i:s T',
                '2123-05-15 14:30:00 MST',
            ],
            'Invalid date string'       => [
                'not a date',
                'Y-m-d T',
                null,
            ],
            'Empty string'              => [
                '',
                'Y-m-d T',
                null,
            ],
            'Different timezone format' => [
                '2023-05-15T14:30:00+02:00',
                'Y-m-d H:i:s T',
                '2023-05-15 05:30:00 MST',
            ],
        ];
    }


    public function testDefaultDateFormat(): void
    {
        $originalDateFormat = SmartString::$dateFormat;
        SmartString::$dateFormat = 'Y-m-d';

        $input = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateFormat()->value();

        $this->assertSame('2023-05-15', $result, "Default dateFormat failed");

        SmartString::$dateFormat = $originalDateFormat;
    }

    public function testDefaultDateTimeFormat(): void
    {
        $originalDateTimeFormat = SmartString::$dateTimeFormat;
        SmartString::$dateTimeFormat = 'Y-m-d H:i:s T';

        $input = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateTimeFormat()->value();

        $this->assertSame('2023-05-15 14:30:00 MST', $result, "Default dateTimeFormat failed");

        SmartString::$dateTimeFormat = $originalDateTimeFormat;
    }

    public function testCustomDefaultDateFormat(): void
    {
        $originalDateFormat = SmartString::$dateFormat;
        SmartString::$dateFormat = 'd/m/Y';

        $input = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateFormat()->value();

        $this->assertSame('15/05/2023', $result, "Custom default dateFormat failed");

        SmartString::$dateFormat = $originalDateFormat;
    }

    public function testCustomDefaultDateTimeFormat(): void
    {
        $originalDateTimeFormat = SmartString::$dateTimeFormat;
        SmartString::$dateTimeFormat = 'd/m/Y H:i T';

        $input = '2023-05-15 14:30:00';
        $result = SmartString::new($input)->dateTimeFormat()->value();

        $this->assertSame('15/05/2023 14:30 MST', $result, "Custom default dateTimeFormat failed");

        SmartString::$dateTimeFormat = $originalDateTimeFormat;
    }


// endregion
// region numberFormat()

    /**
     * @dataProvider numberFormatProvider
     */
    public function testNumberFormat($input, $args, $expected): void
    {
        $result = SmartString::new($input)->numberFormat(...$args)->value();
        $this->assertSame(
            $expected,
            $result,
            "Failed for input: " . var_export($input, true) . ", args: " . var_export(
                $args,
                true,
            ) . ", output: " . var_export($result, true),
        );
    }

    public function numberFormatProvider(): array
    {
        return [
            'Integer - default'         => [1000, [], '1,000'],
            'Float - default'           => [1000.5, [], '1,001'],
            'Integer - 2 decimals'      => [1000, [2], '1,000.00'],
            'Float - 2 decimals'        => [1000.5, [2], '1,000.50'],
            'Large number - 2 decimals' => [1000000.5, [2], '1,000,000.50'],
            'Negative number'           => [-1000.5, [2], '-1,000.50'],
            'Zero'                      => [0, [2], '0.00'],
            'String numeric - integer'  => ['1000', [], '1,000'],
            'String numeric - float'    => ['1000.5', [2], '1,000.50'],
            'Null input'                => [null, [], null],
            'Empty string'              => ['', [], null],
            'Non-numeric string'        => ['abc', [], null],
            'Scientific notation'       => [1e6, [2], '1,000,000.00'],
            'Very large number'         => [1e15, [2], '1,000,000,000,000,000.00'],
            'Very small number'         => [1e-6, [8], '0.00000100'],
            'Rounding up'               => [1.999, [2], '2.00'],
            'Rounding down'             => [1.994, [2], '1.99'],
        ];
    }

    // endregion
    // region phoneFormat()

    /**
     * @dataProvider phoneFormatProvider
     */
    public function testPhoneFormat($input, $expected): void
    {
        SmartString::$phoneFormat = [
            ['digits' => 10, 'format' => '1 (###) ###-####'],
            ['digits' => 11, 'format' => '#-###-###-####'],
        ];

        $result = SmartString::new($input)->phoneFormat()->value();
        $this->assertSame($expected, $result, "Phone format failed for input: " . var_export($input, true));
    }

    public function phoneFormatProvider(): array
    {
        return [
            'Null input'                            => [null, null],
            'Empty string input'                    => ['', null],
            'Invalid 1-digit number'                => ['0', null],
            'Invalid 2-digit number'                => ['12', null],
            'Invalid 3-digit number'                => ['123', null],
            'Invalid 4-digit number'                => ['1234', null],
            'Invalid 5-digit number'                => ['12345', null],
            'Invalid 6-digit number'                => ['123456', null],
            'Invalid 7-digit number'                => ['1234567', null],
            'Invalid 8-digit number'                => ['12345678', null],
            'Invalid 9-digit number'                => ['123456789', null],
            'Invalid 10-digit/char string'          => ['123456789A', null],
            'Valid 10-digit number'                 => ['2345678901', '1 (234) 567-8901'],
            'Valid 11-digit number'                 => ['12345678901', '1-234-567-8901'],
            'Valid 10-digit + chars number'         => ['+1(2)3-4x5y6z7890 ', '1 (123) 456-7890'],
            'Valid 11-digit + chars number'         => ['(12) 34 5 67-8901', '1-234-567-8901'],
            'Valid 10-digit number as integer'      => [2345678901, '1 (234) 567-8901'],
            'Valid 11-digit number as integer'      => [12345678901, '1-234-567-8901'],
            'Invalid 12-digit number'               => ['123456789012', null],
        ];
    }


    // endregion
}