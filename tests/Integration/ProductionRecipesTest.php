<?php
declare(strict_types=1);

namespace Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use RuntimeException;
use Tests\Support\SmartStringTestCase;

/**
 * Template idioms collected from a 31-site production sweep (2026-07-08),
 * as end-to-end chains with pinned outputs. If these chains still produce
 * the same output after a refactor, real sites survive it. The B/C/D
 * numbers in test names are the sweep's catalog labels: B = template
 * output, C = guards and fallbacks, D = math chains.
 */
class ProductionRecipesTest extends SmartStringTestCase
{
    //region B. Template Output

    public function testB1MoneyFormatting(): void
    {
        $amount = SmartString::new(1234.5);
        $this->assertSame('$1,234.50', (string)$amount->numberFormat(2)->andPrefix('$'));

        // empty amount: the prefix vanishes with the value
        $this->assertSame('', (string)SmartString::new(null)->numberFormat(2)->andPrefix('$'));
    }

    public function testB2TelLinks(): void
    {
        $phone = SmartString::new('(555) 123-4567');

        $link = "<a href=\"tel:{$phone->pregReplace('/\D/', '')}\">$phone</a>";
        $this->assertSame('<a href="tel:5551234567">(555) 123-4567</a>', $link);
    }

    /**
     * B3: multiline plain text via nl2br(), including the recipe that
     * replaces pre-2.6.3 ->and(",<br>\n"): attach a plain newline separator,
     * then nl2br() the result.
     */
    public function testB3MultilineTextFields(): void
    {
        $this->assertSame("Bob &amp; Sons<br>\nSuite 5", SmartString::new("Bob & Sons\nSuite 5")->nl2br());

        // address block: present line gets separator + <br>, missing line vanishes
        $this->assertSame("Acme Ltd,<br>\n", SmartString::new('Acme Ltd')->and(",\n")->nl2br());
        $this->assertSame('', SmartString::new(null)->and(",\n")->nl2br());
    }

    public function testB4TrustedWysiwygPassthrough(): void
    {
        $wysiwyg = SmartString::new('<p>Hello <b>World</b></p>');
        $this->assertSame('<p>Hello <b>World</b></p>', "{$wysiwyg->rawHtml()}");
    }

    public function testB5UrlBuilding(): void
    {
        $company = SmartString::new("O'Brien & Sons");
        $this->assertSame('?company=O%27Brien+%26+Sons', "?company={$company->urlEncode()}");
    }

    /**
     * B6: one date field, three masks - display, URL, and filename.
     */
    public function testB6DateThreeWays(): void
    {
        date_default_timezone_set('America/Phoenix');
        $date = SmartString::new('2023-05-15 14:30:00');

        $this->assertSame('May 15, 2023', (string)$date->dateFormat('M j, Y'));   // display
        $this->assertSame('2023-05-15', (string)$date->dateFormat('Y-m-d'));      // URL
        $this->assertSame('15052023', (string)$date->dateFormat('dmY'));          // filename
    }

    public function testB7TruncatedPreview(): void
    {
        $content = SmartString::new('<p>The quick brown fox jumps over the lazy dog</p>');
        $this->assertSame('The quick brown fox...', (string)$content->textOnly()->maxChars(20));
    }

    /**
     * B8: the pre-2.6.3 ->and(",<br>\n") idiom now HTML-encodes the attached
     * <br> on output - pinned so the upgrade behavior is explicit. The
     * modern form is B3's ->and(",\n")->nl2br().
     */
    public function testB8ConditionalSuffixEncodesAttachedHtml(): void
    {
        $this->assertSame("Acme Ltd,&lt;br&gt;\n", (string)SmartString::new('Acme Ltd')->and(",<br>\n"));
    }

    public function testB9ReportCellSuppression(): void
    {
        // zero cells blank out AFTER formatting: ifZero() recognizes '0.00'
        $this->assertSame('', (string)SmartString::new(0)->numberFormat(2)->ifZero(''));
        $this->assertSame('12.50', (string)SmartString::new(12.5)->numberFormat(2)->ifZero(''));

        // failed math shows a dash
        $this->assertSame('-', (string)SmartString::new(null)->numberFormat(2)->ifNull('-'));
    }

    public function testB10Callables(): void
    {
        $this->assertSame('VANCOUVER', (string)SmartString::new('Vancouver')->apply('strtoupper'));

        // sign-prefix closure from the annual-report tables
        $plusPrefix = fn($v) => $v > 0 ? "+$v" : "$v";
        $this->assertSame('+20', (string)SmartString::new(20.0)->apply($plusPrefix));
        $this->assertSame('-5', (string)SmartString::new(-5.0)->apply($plusPrefix));
    }

    //endregion
    //region C. Guards and Fallbacks

    /**
     * C2: two-stage guard distinguishes "no row" from "row but null column".
     */
    public function testC2TwoStageGuard(): void
    {
        // stage 2 fires: row exists but the column is null
        $row = SmartArray::new(['id' => 5, 'name' => null])->asHtml();
        try {
            $row->orThrow('no row')->name->orThrow('row but null column');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('row but null column', $e->getMessage());
        }

        // stage 1 fires: no row at all
        try {
            SmartArray::new([])->asHtml()->orThrow('no row');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('no row', $e->getMessage());
        }

        // both present: value comes through
        $row = SmartArray::new(['id' => 5, 'name' => 'Alice'])->asHtml();
        $this->assertSame('Alice', $row->orThrow('no row')->name->orThrow('null column')->value());
    }

    /**
     * C6: unwrap at boundaries into strict-typed functions.
     */
    public function testC6BoundaryUnwraps(): void
    {
        $double     = static fn(int $n): int => $n * 2;
        $format     = static fn(float $f): string => sprintf('%.1f', $f);
        $upper      = static fn(string $s): string => strtoupper($s);

        $row = SmartArray::new(['qty' => '21', 'price' => '4.5', 'code' => 'abc'])->asHtml();

        $this->assertSame(42, $double($row->qty->int()));
        $this->assertSame('4.5', $format($row->price->float()));
        $this->assertSame('ABC', $upper($row->code->string()));
        $this->assertSame('21', $row->qty->value());
    }

    /**
     * C7: fallback then coerce, so downstream code always gets a string.
     */
    public function testC7FallbackThenCoerce(): void
    {
        $this->assertSame('2025-01-01', SmartString::new(null)->or('2025-01-01')->string());
        $this->assertSame('2026-06-15', SmartString::new('2026-06-15')->or('2025-01-01')->string());
    }

    //endregion
    //region D. Math Chains

    public function testD1PerUnitRate(): void
    {
        $total = SmartString::new(150);
        $this->assertSame('50.00', (string)$total->divide(3)->numberFormat(2));

        // zero days: divide returns null, cell shows a dash
        $this->assertSame('-', (string)$total->divide(0)->numberFormat(2)->ifNull('-'));
    }

    public function testD2YearOverYearDelta(): void
    {
        $current = SmartString::new(120);
        $prev    = SmartString::new(100);
        $this->assertSame('20%', (string)$current->subtract($prev)->percentOf($prev));

        // no prior-year data: every step propagates null, one ifNull covers it
        $noPrev = SmartString::new(null);
        $this->assertSame('-', (string)$current->subtract($noPrev)->percentOf($noPrev)->ifNull('-'));
    }

    public function testD3ShareOfTotal(): void
    {
        $calls = SmartString::new(24);
        $this->assertSame('25%', (string)$calls->percentOf(96));
        $this->assertSame('width: 25%', "width: {$calls->percentOf(96)}");
    }

    public function testD4RunningTotal(): void
    {
        $a = SmartString::new('1.5');
        $b = SmartString::new('2.25');
        $this->assertSame('3.75', (string)SmartString::new(0)->add($a->float())->add($b->float())->numberFormat(2));
    }

    public function testD5PercentDisplay(): void
    {
        $this->assertSame('50.00%', (string)SmartString::new(0.5)->percent(2)->or('0'));
        $this->assertSame('0', (string)SmartString::new(null)->percent(2)->or('0'));
    }

    /**
     * The or() placement gotcha, pinned both ways: before formatting the
     * fallback is a number that gets formatted; after formatting it's a
     * display string for when the original value was missing.
     */
    public function testOrPlacementGotcha(): void
    {
        $this->assertSame('0.00', (string)SmartString::new(null)->or(0)->numberFormat(2));
        $this->assertSame('0.00', (string)SmartString::new(null)->numberFormat(2)->or('0.00'));

        $this->assertSame('5.00', (string)SmartString::new(5)->or(0)->numberFormat(2));
        $this->assertSame('5.00', (string)SmartString::new(5)->numberFormat(2)->or('0.00'));

        // where they differ: a present but non-numeric value. or() before
        // formatting keeps it (present), so numberFormat() fails to blank;
        // or() after formatting catches the null result too.
        $this->assertSame('', (string)SmartString::new('n/a')->or(0)->numberFormat(2));
        $this->assertSame('0.00', (string)SmartString::new('n/a')->numberFormat(2)->or('0.00'));
    }

    //endregion
    //region SmartArray Interop

    public function testForeachOverHtmlRows(): void
    {
        $rows = [
            ['name' => "O'Brien & Sons", 'qty' => 0],
            ['name' => '<Web Shop>', 'qty' => 7],
        ];

        $rendered = [];
        foreach (SmartArray::new($rows)->asHtml() as $row) {
            $this->assertInstanceOf(SmartString::class, $row->name);
            $rendered[] = "$row->name: {$row->qty->or('none')}";
        }

        $this->assertSame([
            "O&apos;Brien &amp; Sons: 0", // zero is present, or() keeps it
            '&lt;Web Shop&gt;: 7',
        ], $rendered);
    }

    public function testGetRawValueOnRows(): void
    {
        $row = SmartArray::new(['name' => "O'Brien", 'qty' => 7])->asHtml();
        $this->assertSame(['name' => "O'Brien", 'qty' => 7], SmartString::getRawValue($row)); // html-mode rows unwrap to plain arrays
    }

    //endregion
}
