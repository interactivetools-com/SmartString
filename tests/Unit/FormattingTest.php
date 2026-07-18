<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * dateFormat(), dateTimeFormat(), numberFormat(), phoneFormat().
 *
 * Date tests pin America/Phoenix (no DST) via date_default_timezone_set();
 * the base class restores the timezone after each test.
 *
 * n/a dimensions: encoding (formatters transform the raw value), negative
 * numberFormat decimals (number_format() rounds to 0 decimals pre-PHP 8.3
 * and to tens/hundreds on 8.3+, so there is no stable contract to pin).
 */
class FormattingTest extends SmartStringTestCase
{
    //region dateFormat()

    #[DataProvider('dateFormatProvider')]
    public function testDateFormat($input, ?string $format, ?string $expected): void
    {
        date_default_timezone_set('America/Phoenix');
        $this->assertSmartString($expected, SmartString::new($input)->dateFormat($format));
    }

    public static function dateFormatProvider(): array
    {
        return [
            'MySQL datetime'            => ['2023-05-15 14:30:00', 'Y-m-d H:i:s T', '2023-05-15 14:30:00 MST'],
            'MySQL date'                => ['2023-05-15', 'Y-m-d T', '2023-05-15 MST'],
            'MySQL time'                => ['14:30:00', 'H:i:s T', '14:30:00 MST'],
            'Unix timestamp'            => [1684159800, 'Y-m-d H:i:s T', '2023-05-15 07:10:00 MST'],
            'Unix timestamp as string'  => ['1684159800', 'Y-m-d H:i:s T', '2023-05-15 07:10:00 MST'],
            'custom format output'      => ['2023-05-15 14:30:00', 'd/m/Y H:i T', '15/05/2023 14:30 MST'],
            'null input'                => [null, 'Y-m-d T', null],
            'zero timestamp'            => [0, 'Y-m-d H:i:s T', '1969-12-31 17:00:00 MST'],
            'epoch zero date string'    => ['1970-01-01 00:00:00 UTC', 'Y-m-d H:i:s T', '1969-12-31 17:00:00 MST'],
            'negative timestamp'        => [-1684159800, 'Y-m-d H:i:s T', '1916-08-19 02:50:00 MST'],
            'far future date'           => ['2123-05-15 14:30:00', 'Y-m-d H:i:s T', '2123-05-15 14:30:00 MST'],
            'invalid date string'       => ['not a date', 'Y-m-d T', null],
            'empty string'              => ['', 'Y-m-d T', null],
            'timezone offset in input'  => ['2023-05-15T14:30:00+02:00', 'Y-m-d H:i:s T', '2023-05-15 05:30:00 MST'],
            'bool true returns null'    => [true, 'Y-m-d T', null],  // bools take the null path
            'bool false returns null'   => [false, 'Y-m-d T', null], // bools take the null path
        ];
    }

    /**
     * Pinned: numeric values are unix timestamps, so a numeric string like
     * '2024' formats as epoch + 2024 seconds, not as a year.
     */
    public function testDateFormatTreatsNumericStringsAsTimestamps(): void
    {
        date_default_timezone_set('America/Phoenix');
        $this->assertSame('1969-12-31', SmartString::new('2024')->dateFormat()->value());
    }

    public function testDateFormatParsesRelativeStrings(): void
    {
        date_default_timezone_set('America/Phoenix');
        // routed through strtotime(); oracle computed the same way since "tomorrow" moves daily
        $expected = date('Y-m-d', strtotime('tomorrow'));
        $this->assertSame($expected, SmartString::new('tomorrow')->dateFormat()->value());
    }

    public function testDateFormatUsesGlobalDefault(): void
    {
        date_default_timezone_set('America/Phoenix');
        SmartString::$dateFormat = 'd/m/Y';
        $this->assertSame('15/05/2023', SmartString::new('2023-05-15 14:30:00')->dateFormat()->value());
    }

    //endregion
    //region dateTimeFormat()

    public function testDateTimeFormatUsesGlobalDefault(): void
    {
        date_default_timezone_set('America/Phoenix');
        SmartString::$dateTimeFormat = 'Y-m-d H:i:s T';
        $this->assertSame('2023-05-15 14:30:00 MST', SmartString::new('2023-05-15 14:30:00')->dateTimeFormat()->value());
    }

    public function testDateTimeFormatDelegatesToDateFormat(): void
    {
        date_default_timezone_set('America/Phoenix');
        // explicit format: identical behavior to dateFormat() with the same format
        $this->assertSame('15/05/2023 14:30 MST', SmartString::new('2023-05-15 14:30:00')->dateTimeFormat('d/m/Y H:i T')->value());
        $this->assertNull(SmartString::new('not a date')->dateTimeFormat('Y-m-d')->value());
        $this->assertNull(SmartString::new(null)->dateTimeFormat()->value());
    }

    //endregion
    //region numberFormat()

    #[DataProvider('numberFormatProvider')]
    public function testNumberFormat($input, int $decimals, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->numberFormat($decimals));
    }

    public static function numberFormatProvider(): array
    {
        return [
            'integer default'      => [1000, 0, '1,000'],
            'float rounds down'    => [1000.5, 0, '1,001'],
            'integer 2 decimals'   => [1000, 2, '1,000.00'],
            'float 2 decimals'     => [1234.56, 2, '1,234.56'],
            'negative float'       => [-1234.56, 2, '-1,234.56'],
            'round up'             => [1234.56789, 2, '1,234.57'],
            'rounding up'          => [1.999, 2, '2.00'],
            'rounding down'        => [1.994, 2, '1.99'],
            'large int'            => [1000000000000, 0, '1,000,000,000,000'],
            'small number'         => [0.0000001, 7, '0.0000001'],
            'zero'                 => [0, 2, '0.00'],
            'string numeric int'   => ['1000', 0, '1,000'],
            'string numeric float' => ['1000.5', 2, '1,000.50'],
            'scientific notation'  => [1e6, 2, '1,000,000.00'],
            'very large number'    => [1e15, 2, '1,000,000,000,000,000.00'],
            'very small number'    => [1e-6, 8, '0.00000100'],
            'null input'           => [null, 0, null],
            'empty string'         => ['', 0, null],
            'non-numeric string'   => ['abc', 0, null],
            'bool true'            => [true, 0, null],
            'bool false'           => [false, 0, null],
            'formatted number'     => ['3,000', 0, null], // commas make it non-numeric to PHP
            'INF float'            => [INF, 0, 'inf'],
            'INF producer string'  => ['9e999', 2, 'inf'], // (float)'9e999' overflows to INF
            'inf as word'          => ['inf', 0, null],    // is_numeric('inf') is false
        ];
    }

    public function testNumberFormatHonorsGlobalSeparators(): void
    {
        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = ' ';
        $this->assertSame('1 234 567,89', SmartString::new(1234567.89)->numberFormat(2)->value());
    }

    //endregion
    //region phoneFormat()

    #[DataProvider('phoneFormatProvider')]
    public function testPhoneFormat($input, ?string $expected): void
    {
        SmartString::$phoneFormat = [
            ['digits' => 10, 'format' => '1 (###) ###-####'],
            ['digits' => 11, 'format' => '#-###-###-####'],
        ];
        $this->assertSmartString($expected, SmartString::new($input)->phoneFormat());
    }

    public static function phoneFormatProvider(): array
    {
        return [
            'null input'                 => [null, null],
            'empty string'               => ['', null], // guarded against str_split('') PHP 8.1/8.2 difference
            '1 digit'                    => ['0', null],
            '9 digits'                   => ['123456789', null],
            '12 digits'                  => ['123456789012', null],
            'digits plus letters'        => ['123456789A', null], // letters stripped, 9 digits left
            '10 digits'                  => ['2345678901', '1 (234) 567-8901'],
            '11 digits'                  => ['12345678901', '1-234-567-8901'],
            '10 digits with separators'  => ['+1(2)3-4x5y6z7890 ', '1 (123) 456-7890'],
            '11 digits with separators'  => ['(12) 34 5 67-8901', '1-234-567-8901'],
            '10 digits as integer'       => [2345678901, '1 (234) 567-8901'],
            '11 digits as integer'       => [12345678901, '1-234-567-8901'],
        ];
    }

    public function testPhoneFormatDefaultFormats(): void
    {
        // ships with 10- and 11-digit North American formats
        $this->assertSame('(234) 567-8901', SmartString::new('2345678901')->phoneFormat()->value());
        $this->assertSame('1 (234) 567-8901', SmartString::new('12345678901')->phoneFormat()->value());
    }

    public function testPhoneFormatCustomFormats(): void
    {
        SmartString::$phoneFormat = [
            ['digits' => 10, 'format' => '###.###.####'],
            ['digits' => 12, 'format' => '+## ## #### ####'],
        ];
        $this->assertSame('234.567.8901', SmartString::new('2345678901')->phoneFormat()->value());
        $this->assertSame('+44 20 7123 4567', SmartString::new('442071234567')->phoneFormat()->value());
    }

    //endregion
}
