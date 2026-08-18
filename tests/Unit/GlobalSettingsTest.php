<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\Depends;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

/**
 * The five public statics: documented defaults, each honored by its methods,
 * and a self-test of the base class snapshot/restore infrastructure.
 *
 * n/a dimensions: encoding, argument matrix (statics take assignments, not
 * method arguments).
 */
class GlobalSettingsTest extends SmartStringTestCase
{
    //region Documented Defaults

    public function testDefaultsMatchDocumentation(): void
    {
        $this->assertSame('.', SmartString::$numberFormatDecimal);
        $this->assertSame(',', SmartString::$numberFormatThousands);
        $this->assertSame('Y-m-d', SmartString::$dateFormat);
        $this->assertSame('Y-m-d H:i:s', SmartString::$dateTimeFormat);
        $this->assertSame([
            ['digits' => 10, 'format' => '(###) ###-####'],
            ['digits' => 11, 'format' => '# (###) ###-####'],
        ], SmartString::$phoneFormat);
    }

    //endregion
    //region Each Setting Honored

    public function testNumberFormatUsesSeparatorSettings(): void
    {
        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = '.';
        $this->assertSame('1.234.567,89', SmartString::new(1234567.89)->numberFormat(2)->value());
    }

    public function testPercentUsesSeparatorSettings(): void
    {
        // percent()/percentOf() honor the separators like numberFormat()
        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = '.';
        $this->assertSame('1.234,50%', SmartString::new(12.345)->percent(2)->value());
        $this->assertSame('50,00%', SmartString::new(1)->percentOf(2, 2)->value());
    }

    public function testDateFormatUsesFormatSetting(): void
    {
        date_default_timezone_set('America/Phoenix');
        SmartString::$dateFormat = 'd/m/Y';
        $this->assertSame('15/05/2023', SmartString::new('2023-05-15')->dateFormat()->value());
    }

    public function testDateTimeFormatUsesFormatSetting(): void
    {
        date_default_timezone_set('America/Phoenix');
        SmartString::$dateTimeFormat = 'd/m/Y H:i';
        $this->assertSame('15/05/2023 14:30', SmartString::new('2023-05-15 14:30:00')->dateTimeFormat()->value());
    }

    public function testPhoneFormatUsesFormatSetting(): void
    {
        SmartString::$phoneFormat = [['digits' => 7, 'format' => '###-####']];
        $this->assertSame('123-4567', SmartString::new('1234567')->phoneFormat()->value());
        $this->assertNull(SmartString::new('2345678901')->phoneFormat()->value()); // 10 digits no longer configured
    }

    //endregion
    //region Snapshot/Restore Self-Test

    /**
     * First half of the infrastructure self-test: mutate every setting and
     * the timezone, restore nothing. Returns the timezone the process had on
     * entry, the exact value tearDown must put back (the five settings have
     * documented defaults to compare against, the timezone has none).
     */
    public function testSnapshotSelfTestMutatesEverySetting(): string
    {
        $timezoneBeforeMutation = date_default_timezone_get();

        SmartString::$numberFormatDecimal   = '#';
        SmartString::$numberFormatThousands = '~';
        SmartString::$dateFormat            = 'D';
        SmartString::$dateTimeFormat        = 'D H';
        SmartString::$phoneFormat           = [['digits' => 1, 'format' => '#']];
        date_default_timezone_set('Australia/Eucla');

        $this->assertSame('#', SmartString::$numberFormatDecimal); // mutation took; tearDown must undo it
        return $timezoneBeforeMutation;
    }

    /**
     * Second half: everything the previous test changed is back to defaults.
     */
    #[Depends('testSnapshotSelfTestMutatesEverySetting')]
    public function testSnapshotSelfTestRestoredEverySetting(string $timezoneBeforeMutation): void
    {
        $this->assertSame('.', SmartString::$numberFormatDecimal);
        $this->assertSame(',', SmartString::$numberFormatThousands);
        $this->assertSame('Y-m-d', SmartString::$dateFormat);
        $this->assertSame('Y-m-d H:i:s', SmartString::$dateTimeFormat);
        $this->assertSame(10, SmartString::$phoneFormat[0]['digits']);
        $this->assertSame($timezoneBeforeMutation, date_default_timezone_get());
    }

    /**
     * The pair above can only compare against the timezone the harness happens to
     * run under, so it cannot tell a restore from tearDown() hardcoding that same
     * zone. Driving setUp()/tearDown() by hand pins the exact value. Two different
     * saved zones go through the cycle, so no single hardcoded literal can satisfy
     * both rounds.
     */
    public function testSnapshotSelfTestRestoresTheTimezoneItSaved(): void
    {
        $timezoneOnEntry = date_default_timezone_get();

        try {
            foreach (['America/Phoenix', 'Antarctica/Troll'] as $savedZone) {
                date_default_timezone_set($savedZone);
                $this->setUp();                  // snapshot a zone this test picked, not the harness default
                date_default_timezone_set('Australia/Eucla');
                $this->tearDown();
                $this->assertSame($savedZone, date_default_timezone_get());
            }
        } finally {
            date_default_timezone_set($timezoneOnEntry);
            $this->setUp();                      // re-arm so the harness tearDown leaves the process as we found it
        }
    }

    //endregion
}
