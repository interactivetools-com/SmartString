<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SmartStringTestCase;

/**
 * isEmpty(), isNotEmpty(), isMissing(), isNull().
 *
 * n/a dimensions: encoding, global settings, immutability, argument matrix
 * (no parameters).
 */
class ValidationTest extends SmartStringTestCase
{
    /**
     * All four predicates against the shared falsy menu, side by side.
     * isEmpty uses PHP empty(); isMissing is null/'' only; the zero row is
     * where they disagree by design.
     */
    #[DataProvider('predicatesProvider')]
    public function testPredicates($input, bool $isEmpty, bool $isMissing, bool $isNull): void
    {
        $smartString = SmartString::new($input);
        $label       = "input: " . var_export($input, true);

        $this->assertSame($isEmpty, $smartString->isEmpty(), "isEmpty() failed for $label");
        $this->assertSame(!$isEmpty, $smartString->isNotEmpty(), "isNotEmpty() failed for $label");
        $this->assertSame($isMissing, $smartString->isMissing(), "isMissing() failed for $label");
        $this->assertSame($isNull, $smartString->isNull(), "isNull() failed for $label");
    }

    public static function predicatesProvider(): array
    {
        // input, isEmpty, isMissing, isNull
        return [
            'null'         => [null, true, true, true],
            'empty string' => ['', true, true, false],
            'zero int'     => [0, true, false, false],
            'zero string'  => ['0', true, false, false],
            'zero float'   => [0.0, true, false, false],
            'false'        => [false, true, false, false],
            'whitespace'   => [' ', false, false, false],
            'string'       => ['Hello', false, false, false],
            'number'       => [42, false, false, false],
            'true'         => [true, false, false, false],
            'string 0.0'   => ['0.0', false, false, false], // empty() is false: '0.0' is a non-'0' string
        ];
    }

    /**
     * The documented disagreement, pinned as executable documentation:
     * zero is empty to isEmpty() but present to isMissing().
     */
    public function testZeroIsEmptyButNotMissing(): void
    {
        $zero = SmartString::new(0);
        $this->assertTrue($zero->isEmpty());
        $this->assertFalse($zero->isMissing());
    }
}
