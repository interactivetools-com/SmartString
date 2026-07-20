<?php
declare(strict_types=1);

namespace Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * Every docs/ page and help.txt example with a claimed output, executed and
 * asserted exactly. Docs are the spec: when one of these fails, either the
 * code broke or the docs went stale - both are findings.
 *
 * Examples without a pinned output (loops over sample data, "e.g." comments,
 * exit-path guards) are exercised by the unit files and ProductionRecipesTest.
 *
 * One region per page, in docs/ reading order.
 */
class DocsExamplesTest extends SmartStringTestCase
{
    //region docs/getting-started.md

    public function testGettingStartedYourFirstSmartString(): void
    {
        $name = SmartString::new("Jean O'Brien");

        $this->assertSame("Hello, Jean O&apos;Brien!", "Hello, $name!");
    }

    public function testGettingStartedTheMentalModel(): void
    {
        $str = SmartString::new("It's easy!<hr>");

        $this->assertSame('It&apos;s easy!&lt;hr&gt;', (string)$str);
        $this->assertSame("It's easy!<hr>", $str->value());

        $price = SmartString::new(1234567.89);

        $this->assertTrue($price->value() > 1000);
        $this->assertSame('1,234,567.89', (string)$price->numberFormat(2));
    }

    public function testGettingStartedChainingMethods(): void
    {
        $article = SmartString::new("  <p>Hello <b>World</b></p> and more text here that keeps going  ");

        $this->assertSame('Hello World and more...', (string)$article->textOnly()->maxChars(20));

        $date  = SmartString::new("2026-09-10 14:30:00");
        $price = SmartString::new(1234567.89);

        $this->assertSame('Posted Sep 10th, 2026', "Posted {$date->dateFormat('M jS, Y')}");
        $this->assertSame('Total: 1,234,567.89', "Total: {$price->numberFormat(2)}");
    }

    public function testGettingStartedFallbacksForMissingValues(): void
    {
        $name = SmartString::new(null);
        $this->assertSame('Hello, Guest!', "Hello, {$name->or('Guest')}!");

        $price = SmartString::new(0);
        $this->assertSame('0', (string)$price->or("N/A"));
    }

    public function testGettingStartedWorkingWithSmartArray(): void
    {
        $user = SmartArrayHtml::new([
            'name'      => "Jean O'Brien",
            'city'      => 'Vancouver',
            'lastLogin' => '2026-09-10 14:30:00',
        ]);

        $this->assertSame("Hello, Jean O&apos;Brien from Vancouver!", "Hello, $user->name from $user->city!");
        $this->assertSame('Last login: September 10, 2026', "Last login: {$user->lastLogin->dateFormat('F j, Y')}");
    }

    public function testGettingStartedConvertingToPlainPhpTypes(): void
    {
        $value = SmartString::new("123.45");

        $this->assertSame(123, $value->int());
        $this->assertSame(123.45, $value->float());
        $this->assertTrue($value->bool());
        $this->assertSame("123.45", $value->string());
        $this->assertSame("123.45", $value->value());

        $this->assertSame("hello", SmartString::getRawValue(SmartString::new("hello")));
        $this->assertSame(42, SmartString::getRawValue(42));
        $this->assertNull(SmartString::getRawValue(null));
    }

    public function testGettingStartedConfiguringDefaults(): void
    {
        $this->assertSame('.', SmartString::$numberFormatDecimal);
        $this->assertSame(',', SmartString::$numberFormatThousands);
        $this->assertSame('Y-m-d', SmartString::$dateFormat);
    }

    public function testGettingStartedDebuggingPrintR(): void
    {
        $name   = SmartString::new("Jean O'Brien");
        $output = print_r($name, true);

        // README:private appears only on the first print_r per process, so
        // only the always-present parts are asserted here
        $this->assertStringContainsString('rawData:private', $output);
        $this->assertStringContainsString('"Jean O\'Brien"', $output);
    }

    //endregion
    //region docs/encoding-and-html.md

    public function testHowAutoEncodingWorks(): void
    {
        $str = SmartString::new("It's <b>easy</b> & fun!");

        $this->assertSame('It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!', (string)$str);
        $this->assertSame('Value: It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!', "Value: $str");
    }

    public function testHtmlEncodeMethod(): void
    {
        $title = SmartString::new('<10% OFF "SALE"');

        $this->assertSame('&lt;10% OFF &quot;SALE&quot;', $title->htmlEncode());
        $this->assertSame('&lt;10% OFF &quot;SALE&quot;', (string)$title);
    }

    public function testUrlEncodeMethod(): void
    {
        $title = SmartString::new('<10% OFF "SALE"');

        $this->assertSame(
            "<a href='search.php?title=%3C10%25+OFF+%22SALE%22'>Search</a>",
            "<a href='search.php?title={$title->urlEncode()}'>Search</a>"
        );

        // file/path links need %20, not + (query strings read + as a space; paths don't)
        $file = SmartString::new('Annual Report 2026.pdf');
        $this->assertSame(
            "<a href='/uploads/Annual%20Report%202026.pdf'>Download</a>",
            "<a href='/uploads/{$file->map('rawurlencode')}'>Download</a>"
        );
    }

    public function testJsonEncodeMethod(): void
    {
        $title = SmartString::new("It's <b>easy</b> & fun!");

        $this->assertSame(
            '<script>let title = "It\u0027s \u003Cb\u003Eeasy\u003C/b\u003E \u0026 fun!";</script>',
            "<script>let title = {$title->jsonEncode()};</script>"
        );
    }

    public function testNl2brMethod(): void
    {
        $address = SmartString::new("Bob & Sons\nSuite 5");
        $comment = SmartString::new("Nice!\n<script>alert('xss')</script>");

        $this->assertSame("Bob &amp; Sons<br>\nSuite 5", $address->nl2br());
        $this->assertSame("Nice!<br>\n&lt;script&gt;alert(&apos;xss&apos;)&lt;/script&gt;", $comment->nl2br());
    }

    public function testAppendHtmlAndWrapHtml(): void
    {
        $member = SmartArray::new([
            'addressLine1' => '12 High St',
            'addressLine2' => '',
            'city'         => 'Vancouver',
            'country'      => 'Canada',
            'email'        => 'jean@example.com',
        ])->asHtml();
        $page = SmartArray::new([
            'subheading' => 'Our Story',
            'tagline'    => '',
        ])->asHtml();

        $this->assertSame("12 High St<br>\n", $member->addressLine1->appendHtml("<br>\n"));
        $this->assertSame('', $member->addressLine2->appendHtml("<br>\n"));
        $this->assertSame("Vancouver<br>\n", $member->city->appendHtml("<br>\n"));
        $this->assertSame('Canada', (string)$member->country);

        $this->assertSame('<h2 class="lead">Our Story</h2>', $page->subheading->wrapHtml('<h2 class="lead">', '</h2>'));
        $this->assertSame('<a href="mailto:jean@example.com">Email me</a>', $member->email->wrapHtml('<a href="mailto:', '">Email me</a>'));
        $this->assertSame('', $page->tagline->wrapHtml('<h2>', '</h2>'));
    }

    //endregion
    //region docs/text-and-formatting.md

    public function testTextOnly(): void
    {
        $content = SmartString::new("<p>Hello <b>World</b></p>");

        $this->assertSame('Hello World', (string)$content->textOnly());
    }

    public function testTrim(): void
    {
        $this->assertSame('Trim me', (string)SmartString::new("  Trim me  ")->trim());
        $this->assertSame('Hello', (string)SmartString::new("...Hello...")->trim('.'));
    }

    public function testMaxWordsAndMaxChars(): void
    {
        $text = SmartString::new("The quick brown fox jumps over the lazy dog");

        $this->assertSame('The quick brown fox...', (string)$text->maxWords(4));
        $this->assertSame('The quick brown fox [more]', (string)$text->maxWords(4, ' [more]'));
        $this->assertSame('The quick brown fox...', (string)$text->maxChars(19));
        $this->assertSame('The quick brown fox jumps over the lazy dog', (string)$text->maxChars(200));
    }

    public function testAppendPrependWrap(): void
    {
        $this->assertSame('Vancouver, ', (string)SmartString::new('Vancouver')->append(', '));
        $this->assertSame('', (string)SmartString::new(null)->append(', '));
        $this->assertSame('Phone: (604) 555-1234', (string)SmartString::new('(604) 555-1234')->prepend('Phone: '));
        $this->assertSame('', (string)SmartString::new(null)->prepend('Phone: '));
        $this->assertSame('(ext. 204)', (string)SmartString::new(204)->wrap('(ext. ', ')'));
        $this->assertSame('', (string)SmartString::new(null)->wrap('(ext. ', ')'));

        $this->assertSame('(ext. 204)', (string)SmartString::new(204)->wrap('(ext. ', ')')->or('(no extension)'));
        $this->assertSame('(no extension)', (string)SmartString::new(null)->wrap('(ext. ', ')')->or('(no extension)'));
    }

    public function testPregReplace(): void
    {
        $this->assertSame('6045551234', (string)SmartString::new('(604) 555-1234')->pregReplace('/\D/', ''));
        $this->assertSame('(604) 555-1234', (string)SmartString::new('6045551234')->pregReplace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3'));
        $this->assertSame('1334.56', (string)SmartString::new('$1,234.56')->pregReplace('/[^0-9.]/', '')->add(100));
    }

    public function testDateFormat(): void
    {
        date_default_timezone_set('America/Phoenix');

        $date = SmartString::new("2026-05-15 14:30:00");
        $this->assertSame('2026-05-15', (string)$date->dateFormat());
        $this->assertSame('May 15th, 2026', (string)$date->dateFormat('M jS, Y'));
        $this->assertSame('May 15, 2026 2:30pm', (string)$date->dateFormat('M j, Y g:ia'));

        SmartString::$dateFormat = 'F jS, Y';
        $this->assertSame('May 15th, 2026', (string)$date->dateFormat());

        $this->assertSame('2023-05-15', (string)SmartString::new(1684159800)->dateFormat('Y-m-d'));

        $invalid = SmartString::new("not a date");
        $this->assertSame('Date not set', (string)$invalid->dateFormat()->or("Date not set"));
        $this->assertSame('not a date', (string)$invalid->dateFormat()->or($invalid));
    }

    public function testNumberFormat(): void
    {
        $number = SmartString::new(1234567.89);

        $this->assertSame('1,234,568', (string)$number->numberFormat());
        $this->assertSame('1,234,567.89', (string)$number->numberFormat(2));
        $this->assertSame('N/A', (string)SmartString::new("abc")->numberFormat(2)->or("N/A"));

        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = ' ';
        $this->assertSame('1 234 567,89', (string)$number->numberFormat(2));
    }

    public function testPercentAndPercentOf(): void
    {
        $this->assertSame('75%', (string)SmartString::new(0.75)->percent());
        $this->assertSame('12.34%', (string)SmartString::new(0.1234)->percent(2));
        $this->assertSame('24%', (string)SmartString::new(24)->percentOf(100));
        $this->assertSame('12.0%', (string)SmartString::new(24)->percentOf(200, 1));

        $this->assertSame('N/A', (string)SmartString::new('abc')->percent()->or('N/A'));
        $this->assertSame('N/A', (string)SmartString::new(24)->percentOf(0)->or('N/A'));

        $conversionRate = SmartString::new(0);
        $this->assertSame('0.00%', (string)$conversionRate->percent(2));
        $this->assertSame('N/A', (string)$conversionRate->percent(2, ifZero: "N/A"));
    }

    public function testMathOperations(): void
    {
        $price = SmartString::new(100);

        $this->assertSame('150', (string)$price->add(50));
        $this->assertSame('70', (string)$price->subtract(30));
        $this->assertSame('110', (string)$price->multiply(1.1));
        $this->assertSame('25', (string)$price->divide(4));
        $this->assertSame('56.50', (string)$price->multiply(1.13)->divide(2)->numberFormat(2));
    }

    public function testMathNullPropagationAndRecovery(): void
    {
        $value = SmartString::new(null);

        $this->assertSame('', (string)$value->add(50));
        $this->assertSame('n/a', (string)$value->add(50)->multiply(2)->or('n/a'));
        $this->assertSame('50', (string)$value->ifNull(0)->add(50));

        $this->assertSame('5', (string)SmartString::new("cat")->add(10)->ifNull(0)->add(5));
    }

    public function testMathDecimalPrecision(): void
    {
        $val = SmartString::new(0.1);

        $this->assertSame('0.3', (string)$val->add(0.2));
        $this->assertSame('0.30', (string)$val->add(0.2)->numberFormat(2));
    }

    public function testMap(): void
    {
        $name = SmartString::new('John Doe');

        $this->assertSame('JOHN DOE', (string)$name->map('mb_strtoupper'));
        $this->assertSame('JOHN DOE', (string)$name->map(mb_strtoupper(...)));
        $this->assertSame('JOSÉ GARCÍA', (string)SmartString::new('josé garcía')->map('mb_strtoupper')); // the docs' accent claim
        $this->assertSame('John Doe.......', (string)$name->map('str_pad', 15, '.'));
        $this->assertSame('John_Doe', (string)$name->map(fn($v) => str_replace(' ', '_', $v)));
    }

    public function testTextFormattingPuttingItTogether(): void
    {
        $taxRate = 1.13;  // 13% sales tax
        $product = SmartArrayHtml::new([
            'name'        => 'Widget & Sons Deluxe Kit',
            'description' => '<p>Our <b>best-selling</b> kit, now with more widgets.</p>',
            'price'       => 149.99,
            'updatedAt'   => '2026-09-10 14:30:00',
        ]);

        $html = <<<__HTML__
            <article>
                <h2>$product->name</h2>
                <time>{$product->updatedAt->dateFormat('M jS, Y')}</time>
                <p>{$product->description->textOnly()->maxChars(40)}</p>
                <span class="price">\${$product->price->multiply($taxRate)->numberFormat(2)} (tax included)</span>
            </article>
            __HTML__;

        $expected = <<<__EXPECTED__
            <article>
                <h2>Widget &amp; Sons Deluxe Kit</h2>
                <time>Sep 10th, 2026</time>
                <p>Our best-selling kit, now with more...</p>
                <span class="price">\$169.49 (tax included)</span>
            </article>
            __EXPECTED__;

        $this->assertSame($expected, $html);
    }

    //endregion
    //region docs/conditionals-and-error-checking.md

    public function testWhatMissingMeans(): void
    {
        $this->assertSame('fallback', SmartString::new(null)->or('fallback')->value());
        $this->assertSame('fallback', SmartString::new('')->or('fallback')->value());
        $this->assertSame(0, SmartString::new(0)->or('fallback')->value());
        $this->assertSame('0', SmartString::new('0')->or('fallback')->value());
        $this->assertSame(false, SmartString::new(false)->or('fallback')->value());
        $this->assertSame('hello', SmartString::new('hello')->or('fallback')->value());

        $this->assertTrue(SmartString::new(null)->isEmpty());
        $this->assertTrue(SmartString::new('')->isEmpty());
        $this->assertTrue(SmartString::new(0)->isEmpty());
        $this->assertTrue(SmartString::new('0')->isEmpty());
        $this->assertTrue(SmartString::new(false)->isEmpty());
        $this->assertFalse(SmartString::new('hello')->isEmpty());

        $this->assertTrue(SmartString::new(null)->isMissing());
        $this->assertTrue(SmartString::new('')->isMissing());
        $this->assertFalse(SmartString::new(0)->isMissing());
        $this->assertFalse(SmartString::new('0')->isMissing());
        $this->assertFalse(SmartString::new(false)->isMissing());
        $this->assertFalse(SmartString::new('hello')->isMissing());
    }

    public function testFallbacksOr(): void
    {
        $this->assertSame('N/A', (string)SmartString::new('')->or('N/A'));
        $this->assertSame('Unknown', (string)SmartString::new(null)->or('Unknown'));
        $this->assertSame('0', (string)SmartString::new(0)->or('N/A'));
    }

    public function testTargetedReplacementIfNull(): void
    {
        $this->assertSame('50', (string)SmartString::new(null)->ifNull(0)->add(50));
    }

    public function testTargetedReplacementIfZero(): void
    {
        $balance = SmartString::new(0);
        $this->assertSame('No balance', (string)$balance->ifZero('No balance'));
        $this->assertSame('', (string)SmartString::new(null)->ifZero('No balance'));
    }

    public function testTargetedReplacementIfEquals(): void
    {
        $this->assertSame('Unlimited', (string)SmartString::new(-1)->ifEquals(-1, 'Unlimited'));
        $this->assertSame('Unlimited', (string)SmartString::new('-1')->ifEquals(-1, 'Unlimited'));
    }

    public function testTargetedReplacementIfTrue(): void
    {
        $qty = SmartString::new(150);
        $this->assertSame('99+', (string)$qty->ifTrue($qty->int() > 99, '99+'));
    }

    public function testTargetedReplacementSet(): void
    {
        $order = SmartArrayHtml::new(['status' => 'S', 'giftWrap' => 1]);

        $this->assertSame('Gift wrap: Yes', "Gift wrap: {$order->giftWrap->set($order->giftWrap->bool() ? 'Yes' : 'No')}");

        $html = <<<__HTML__
            <span class="badge">{$order->status->set(match($order->status->string()) {
                'P'     => 'Pending',
                'S'     => 'Shipped',
                default => 'Unknown',
            })}</span>
            __HTML__;

        $this->assertSame('<span class="badge">Shipped</span>', $html);
    }

    public function testRunConditionalsBeforeFormatting(): void
    {
        // WRONG order - "$0.00" is not numeric, so ifZero never fires
        $price = SmartString::new(0);
        $this->assertSame('$0.00', (string)$price->numberFormat(2)->prepend('$')->ifZero('Free!'));

        // RIGHT - match the formatted text
        $this->assertSame('Free!', (string)SmartString::new(0)->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!'));
        $this->assertSame('$19.99', (string)SmartString::new(19.99)->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!'));
    }

    public function testOrPlacementAroundFormatting(): void
    {
        $value = SmartString::new(null);

        $this->assertSame('0.00', (string)$value->or(0)->numberFormat(2));
        $this->assertSame('n/a', (string)$value->numberFormat(2)->or('n/a'));
    }

    public function testTrueFalseChecks(): void
    {
        $balance = SmartString::new(0);

        $this->assertTrue($balance->isEmpty());
        $this->assertFalse($balance->isMissing());
        $this->assertFalse($balance->isNull());
    }

    public function testPuttingItTogether(): void
    {
        $product = SmartArrayHtml::new([
            'name'     => 'Deluxe Widget',
            'price'    => 0,
            'summary'  => '',
            'updated'  => '2026-09-10',
        ]);

        $product->name->or404("Product not found");  // passes: name is present

        $html = <<<__HTML__
            <h1>$product->name</h1>
            <p>Price: {$product->price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!')}</p>
            <p>{$product->summary->textOnly()->maxChars(120)->or('No description yet.')}</p>
            <p>Updated: {$product->updated->dateFormat('M j, Y')->or('never')}</p>
            __HTML__;

        $expected = "<h1>Deluxe Widget</h1>\n"
            . "<p>Price: Free!</p>\n"
            . "<p>No description yet.</p>\n"
            . "<p>Updated: Sep 10, 2026</p>";
        $this->assertSame($expected, $html);
    }

    //endregion
    //region docs/common-patterns.md

    public function testCommonPatternsFormattingDates(): void
    {
        $origDateFormat = SmartString::$dateFormat;
        try {
            SmartString::$dateFormat = 'M j, Y';
            $race = SmartArrayHtml::new(['date' => '2026-09-10 14:30:00']);

            $this->assertSame('Sep 10, 2026', (string)$race->date->dateFormat());
            $this->assertSame('Sep 10, 2026', (string)$race->date->dateFormat('M j, Y'));
            $this->assertSame('race.php?date=2026-09-10', "race.php?date={$race->date->dateFormat('Y-m-d')}");
            $this->assertSame('results-10092026.csv', "results-{$race->date->dateFormat('dmY')}.csv");

            define('DATE_DISPLAY', 'M j, Y');
            define('DATE_FILENAME', 'dmY');

            $this->assertSame('Sep 10, 2026', (string)$race->date->dateFormat(DATE_DISPLAY));
            $this->assertSame('10092026', (string)$race->date->dateFormat(DATE_FILENAME));
        } finally {
            SmartString::$dateFormat = $origDateFormat;
        }
    }

    public function testCommonPatternsAddressBlockNl2br(): void
    {
        $office = SmartArrayHtml::new(['hours' => "Mon-Fri 9-5\nSat 10-4"]);

        $this->assertSame("Mon-Fri 9-5<br>\nSat 10-4", (string)$office->hours->nl2br());
    }

    public function testCommonPatternsLabelsOnlyWhenPresent(): void
    {
        $user  = SmartArrayHtml::new(['phone' => '(604) 555-1234', 'extension' => '204']);
        $blank = SmartArrayHtml::new(['phone' => '', 'extension' => '']);

        $this->assertSame('Phone: (604) 555-1234', (string)$user->phone->prepend('Phone: '));
        $this->assertSame('(ext. 204)', (string)$user->extension->wrap('(ext. ', ')'));
        $this->assertSame('', (string)$blank->phone->prepend('Phone: '));
        $this->assertSame('', (string)$blank->extension->wrap('(ext. ', ')'));
    }

    public function testCommonPatternsClickablePhoneNumbers(): void
    {
        $office = SmartArrayHtml::new(['phone' => '(604) 555-1234']);

        $this->assertSame(
            "<a href='tel:6045551234'>(604) 555-1234</a>",
            "<a href='tel:{$office->phone->pregReplace('/\D/', '')}'>$office->phone</a>"
        );
    }

    public function testCommonPatternsFormattingCurrency(): void
    {
        $order   = SmartArrayHtml::new(['total' => 1234.5]);
        $missing = SmartArrayHtml::new(['total' => null]);

        $this->assertSame('$1,234.50', (string)$order->total->numberFormat(2)->prepend('$'));
        $this->assertSame('n/a', (string)$missing->total->numberFormat(2)->prepend('$')->or('n/a'));
    }

    public function testCommonPatternsReportTablesHidingZerosAndNulls(): void
    {
        $rowZero = SmartArrayHtml::new(['total' => 0, 'count' => null, 'hours' => null]);
        $rowFull = SmartArrayHtml::new(['total' => 1234.5, 'count' => 42]);

        $this->assertSame('', (string)$rowZero->total->ifZero('')->numberFormat(2));
        $this->assertSame('1,234.50', (string)$rowFull->total->ifZero('')->numberFormat(2));
        $this->assertSame('-', (string)$rowZero->count->numberFormat()->or('-'));
        $this->assertSame('42', (string)$rowFull->count->numberFormat()->or('-'));
        $this->assertSame('-', (string)$rowZero->total->ifZero('')->numberFormat(2)->or('-'));
        $this->assertSame('1,234.50', (string)$rowFull->total->ifZero('')->numberFormat(2)->or('-'));
        $this->assertSame('0.00', (string)$rowZero->hours->ifNull(0)->numberFormat(2));
    }

    public function testCommonPatternsRunAnyFunctionWithMap(): void
    {
        $province = SmartArrayHtml::new(['code' => 'bc']);
        $sku      = SmartString::new(42);

        $this->assertSame('BC', (string)$province->code->map('mb_strtoupper'));
        $this->assertSame('000042', (string)$sku->map(fn($v) => str_pad((string)$v, 6, '0', STR_PAD_LEFT)));
    }

    public function testCommonPatternsWhereOrGoesChangesWhatItMeans(): void
    {
        $value = SmartString::new(null);

        $this->assertSame('0.00', (string)$value->or(0)->numberFormat(2));
        $this->assertSame('n/a', (string)$value->numberFormat(2)->or('n/a'));
    }

    //endregion
    //region docs/method-reference.md

    public function testMethodReferenceBasicUsage(): void
    {
        $str = SmartString::new("It's easy!<hr>");

        $this->assertSame('It&apos;s easy!&lt;hr&gt;', (string)$str);
        $this->assertSame("It's easy!<hr>", $str->value());
    }

    //endregion
    //region docs/troubleshooting.md

    public function testTroubleshootingComparisonsAndIfChecks(): void
    {
        $status  = SmartString::new("it's active");
        $price   = SmartString::new(2000);
        $missing = SmartString::new(null);

        $this->assertFalse($status == "it's active");
        $this->assertFalse((string)$status === "it's active");
        $this->assertFalse(@($price > 1000)); // @: PHP notices the int coercion; the docs' point is the silent wrong result
        $this->assertFalse($missing === null);
        $this->assertFalse(empty($missing));
        $this->assertTrue((bool)$missing);

        $this->assertTrue($status->value() === "it's active");
        $this->assertTrue($price->int() > 1000);
        $this->assertTrue($missing->isMissing());
        $this->assertTrue($missing->isEmpty());
    }

    public function testTroubleshootingHtmlTagsPrintAsText(): void
    {
        $address = SmartString::new('12 High St');

        $this->assertSame('12 High St&lt;br&gt;', (string)$address->append('<br>'));
        $this->assertSame('12 High St<br>', $address->appendHtml('<br>'));
    }

    public function testTroubleshootingDoubleEncoding(): void
    {
        $name = SmartString::new("Jean O'Brien");

        // (string) cast added: the test file runs under strict_types, the doc example assumes coercive mode
        $this->assertSame('Jean O&amp;apos;Brien', htmlspecialchars((string)$name));
        $this->assertSame('Jean O&apos;Brien', (string)$name);
    }

    public function testTroubleshootingMethodNeedsBracketsWarning(): void
    {
        $name = SmartString::new('Jean');
        $user = SmartArray::new(['name' => 'Jean'])->asHtml();

        $expectedWarning = <<<'__TEXT__'
            $str->trim needs brackets() everywhere and {curly braces} in strings:
                ✓ Outside strings:         $str->trim()
                ✗ Missing brackets:        $str->trim
                ✓ Inside strings:          "Hello {$str->trim()}"
                ✗ Missing { } in string:   "Hello $str->trim()"
            __TEXT__ . "\n";

        $this->assertSame('Hello Jean', "Hello {$name->trim()}");
        $this->assertSame('Hello Jean', "Hello {$user->name->trim()}");
        $this->assertSame('', $this->expectUserWarning(fn() => (string)$name->trim, $expectedWarning));
        $this->assertSame('Hello ()', $this->expectUserWarning(fn() => "Hello $name->trim()", $expectedWarning));
        $this->assertSame('Hello Jean->trim()', "Hello $user->name->trim()");
    }

    public function testTroubleshootingMathChainOutputsNothing(): void
    {
        $this->assertSame('', (string)SmartString::new(null)->add(50));
        $this->assertSame('', (string)SmartString::new('1,234')->add(50));
        $this->assertSame('', (string)SmartString::new(100)->divide(0));
    }

    public function testTroubleshootingChainingAfterNl2brThrows(): void
    {
        $bio = SmartString::new("First line\nSecond line");

        /** @var mixed $html deliberate mistake from the docs: nl2br() ends the chain */
        $html = $bio->nl2br();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function or() on string');
        $html->or('No bio');
    }

    //endregion
    //region docs/ai-reference.md

    public function testAiRefWhatIsSmartString(): void
    {
        $str = SmartString::new("It's easy!<hr>");

        $this->assertSame('It&apos;s easy!&lt;hr&gt;', (string)$str);
        $this->assertSame("It's easy!<hr>", $str->value());
    }

    public function testAiRefCreatingValues(): void
    {
        $user = SmartArrayHtml::new(['name' => "Jean O'Brien", 'age' => 25]);

        $this->assertSame('Jean O&apos;Brien', (string)$user->name);
    }

    public function testAiRefAutoEncodingMechanics(): void
    {
        $this->assertSame('', (string)SmartString::new(null));
        $this->assertSame('', (string)SmartString::new(false));
        $this->assertSame('1', (string)SmartString::new(true));
    }

    public function testAiRefEncodingMethods(): void
    {
        $text = SmartString::new("Bob & Sons\nSuite 5");
        $this->assertSame("Bob &amp; Sons<br>\nSuite 5", $text->nl2br());

        $addr = SmartString::new('12 High St');
        $this->assertSame("12 High St,<br>\n", $addr->appendHtml(",<br>\n"));
        $this->assertSame('', SmartString::new(null)->appendHtml(",<br>\n"));

        $head = SmartString::new('Our Story');
        $this->assertSame('<h2>Our Story</h2>', $head->wrapHtml('<h2>', '</h2>'));
        $this->assertSame('', SmartString::new(null)->wrapHtml('<h2>', '</h2>'));
    }

    public function testAiRefDatesAndNumbers(): void
    {
        $price = SmartString::new(100);

        $this->assertSame('56.50', (string)$price->multiply(1.13)->divide(2)->numberFormat(2));
        $this->assertSame('n/a', (string)SmartString::new(null)->add(50)->or('n/a'));
        $this->assertSame('50', (string)SmartString::new(null)->ifNull(0)->add(50));
        $this->assertSame('5', (string)SmartString::new('cat')->add(10)->ifNull(0)->add(5));
        $this->assertSame('', (string)SmartString::new('1,234')->add(1));
        $this->assertSame('24%', (string)SmartString::new(0.24)->percent());
    }

    public function testAiRefConditionalOrPlacement(): void
    {
        $this->assertSame('0.00', (string)SmartString::new(null)->or(0)->numberFormat(2));
        $this->assertSame('n/a', (string)SmartString::new(null)->numberFormat(2)->or('n/a'));
    }

    //endregion
    //region src/help.txt

    public function testHelpBasics(): void
    {
        $str = SmartString::new("It's easy!<hr>");

        $this->assertSame('It&apos;s easy!&lt;hr&gt;', (string)$str);
        $this->assertSame("It's easy!<hr>", $str->value());
    }

    //endregion
}
