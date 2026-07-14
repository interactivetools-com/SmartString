<?php
declare(strict_types=1);

namespace Tests\Integration;

use Itools\SmartArray\SmartArray;
use Itools\SmartString\SmartString;
use Tests\Support\SmartStringTestCase;

/**
 * Every README and help.txt example with a claimed output, executed and
 * asserted exactly. Docs are the spec: when one of these fails, either the
 * code broke or the docs went stale - both are findings.
 *
 * Examples without a pinned output (loops over sample data, "e.g." comments,
 * exit-path guards) are exercised by the unit files and ProductionRecipesTest.
 */
class DocsExamplesTest extends SmartStringTestCase
{
    //region README: Quick Start

    public function testQuickStart(): void
    {
        $user = SmartArray::new(['name' => "John O'Reilly", 'id' => 123])->asHtml();

        $this->assertSame('Hello, John O&apos;Reilly!', "Hello, $user->name!");
        $this->assertSame(123, $user->id->value());
    }

    //endregion
    //region README: Creating SmartStrings

    public function testCreatingSmartStrings(): void
    {
        $name = SmartString::new("John O'Reilly");
        $user = SmartArray::new(['name' => 'Jane Doe', 'age' => 25, 'isStudent' => true])->asHtml();

        $this->assertSame('John O&apos;Reilly', (string)$name);
        $this->assertSame('25', (string)$user->age);
    }

    //endregion
    //region README: Automatic HTML-encoding / Accessing Values

    public function testAutomaticHtmlEncodingInStringContexts(): void
    {
        $str = SmartString::new("It's easy!<hr>");

        $this->assertSame('It&apos;s easy!&lt;hr&gt;', (string)$str);
        $this->assertSame("It&apos;s easy!&lt;hr&gt;\n", $str . "\n");
        $this->assertSame("It's easy!<hr>", $str->value());
    }

    //endregion
    //region README: Working with SmartArrays

    public function testWorkingWithSmartArrays(): void
    {
        $user = SmartArray::new(['name' => 'John', 'age' => 30])->asHtml();

        $this->assertSame('Name: John, Age: 30', "Name: $user->name, Age: $user->age");
        $this->assertSame('Hello, John!', "Hello, {$user['name']}!");
        $this->assertSame('Hello, John!', "Hello, {$user->name->or('User')}!");
    }

    //endregion
    //region README: Type Conversion

    public function testTypeConversion(): void
    {
        $value = SmartString::new('123.45');

        $this->assertSame(123, $value->int());
        $this->assertSame(123.45, $value->float());
        $this->assertTrue($value->bool());
        $this->assertSame('123.45', $value->string());
    }

    //endregion
    //region README: Encoding Values

    public function testEncodingValues(): void
    {
        $title = SmartString::new('<10% OFF "SALE"');

        $this->assertSame('<10% OFF "SALE"', $title->value());
        $this->assertSame('&lt;10% OFF &quot;SALE&quot;', $title->htmlEncode());
        $this->assertSame('add.php?title=%3C10%25+OFF+%22SALE%22', "add.php?title={$title->urlEncode()}");
        $this->assertSame('let title="\u003C10% OFF \u0022SALE\u0022"', "let title={$title->jsonEncode()}");
        $this->assertSame('Title: <10% OFF "SALE"', "Title: {$title->rawHtml()}");

        $text = SmartString::new("Hello\nWorld");
        $this->assertSame("Hello<br>\nWorld", "{$text->nl2br()}");
    }

    //endregion
    //region README: String Manipulation

    public function testStringManipulation(): void
    {
        $htmlText = SmartString::new(' <b> Some HTML </b> ');
        $this->assertSame('Some HTML', (string)$htmlText->textOnly());

        $whitespaceText = SmartString::new('  Trim me  ');
        $this->assertSame('Trim me', (string)$whitespaceText->trim());

        $longText = SmartString::new('The quick brown fox jumps over the lazy dog');
        $this->assertSame('The quick brown fox...', (string)$longText->maxWords(4));
        $this->assertSame('The quick...', (string)$longText->maxChars(10));

        $this->assertSame('Some HTML', (string)$htmlText->textOnly()->maxChars(10));

        $str = SmartString::new('  <p>More text and HTML than needed</p>  ');
        $this->assertSame('More text and...', (string)$str->textOnly()->maxWords(3));
    }

    //endregion
    //region README: Number Formatting

    public function testNumberFormatting(): void
    {
        $number = SmartString::new(1234567.89);
        $this->assertSame('1,234,568', (string)$number->numberFormat()); // rounded to 0 decimals

        SmartString::$numberFormatDecimal   = ',';
        SmartString::$numberFormatThousands = ' ';
        $this->assertSame('1 234 567,89', (string)$number->numberFormat(2));
    }

    //endregion
    //region README: Date Formatting

    public function testDateFormatting(): void
    {
        date_default_timezone_set('America/Phoenix');
        SmartString::$dateFormat     = 'F jS, Y';
        SmartString::$dateTimeFormat = 'F jS, Y g:i A';

        $date = SmartString::new('2024-05-15 14:30:00');
        $this->assertSame('May 15th, 2024', (string)$date->dateFormat());

        $dateTime = SmartString::new('2024-06-21 17:30:59');
        $this->assertSame('June 21st, 2024 5:30 PM', (string)$dateTime->dateTimeFormat());

        $this->assertSame('May 15, 2024', (string)$date->dateFormat('F j, Y'));
        $this->assertSame('Friday, June 21, 2024 5:30 PM', (string)$dateTime->dateTimeFormat('l, F j, Y g:i A'));

        $invalid = SmartString::new('not a date');
        $this->assertSame('Invalid date', (string)$invalid->dateFormat()->or('Invalid date'));
        $this->assertSame('not a date', (string)$invalid->dateFormat()->or($invalid));

        $timestamp = SmartString::new(1684159800);
        $this->assertSame('2023-05-15', (string)$timestamp->dateFormat('Y-m-d'));
    }

    //endregion
    //region README: Phone Number Formatting

    public function testPhoneNumberFormatting(): void
    {
        SmartString::$phoneFormat = [
            ['digits' => 10, 'format' => '1.###.###.####'],
            ['digits' => 11, 'format' => '#.###.###.####'],
        ];

        $this->assertSame('1.234.567.8901', (string)SmartString::new('(234)567-8901')->phoneFormat());
        $this->assertSame('1.888.123.4567', (string)SmartString::new('1-888-123-4567')->phoneFormat());

        $phone = SmartString::new('123');
        $this->assertSame('Invalid phone', (string)$phone->phoneFormat()->or('Invalid phone'));
        $this->assertSame('123', (string)$phone->phoneFormat()->or($phone));
    }

    //endregion
    //region README: Numeric Operations

    public function testNumericOperations(): void
    {
        $ratio = SmartString::new(0.75);
        $this->assertSame('75%', (string)$ratio->percent());

        $score = SmartString::new(24);
        $this->assertSame('24%', (string)$score->percentOf(100));

        $base = SmartString::new(100);
        $this->assertSame('150', (string)$base->add(50));

        // null propagates through math - rescue with or() or ifNull() at the end
        $value = SmartString::new(null);
        $this->assertSame('', (string)$value->add(50));
        $this->assertSame('n/a', (string)$value->add(50)->or('n/a'));
        $this->assertSame('50', (string)$value->ifNull(0)->add(50));

        $this->assertSame('70', (string)SmartString::new(100)->subtract(30));
        $this->assertSame('25', (string)SmartString::new(100)->divide(4));
        $this->assertSame('100', (string)SmartString::new(25)->multiply(4));

        $price = SmartString::new(100);
        $this->assertSame('55.00', (string)$price->multiply(1.1)->divide(2)->numberFormat(2));
    }

    public function testPercentIfZeroParameter(): void
    {
        $value = SmartString::new(0);
        $this->assertSame('N/A', (string)$value->percent(2, ifZero: 'N/A'));
    }

    //endregion
    //region README: Conditional Operations

    public function testConditionalOperations(): void
    {
        $this->assertSame('Default', (string)SmartString::new('')->or('Default'));

        $this->assertSame('John Doe', (string)SmartString::new('')->ifBlank('John Doe'));
        $this->assertSame('Alice', (string)SmartString::new('Alice')->ifBlank('John Doe'));

        $this->assertSame('Not Null', (string)SmartString::new(null)->ifNull('Not Null'));
        $this->assertSame('No balance', (string)SmartString::new(0)->ifZero('No balance'));

        $eggs = SmartString::new(12);
        $this->assertSame('Full Carton', (string)$eggs->if($eggs->int() === 12, 'Full Carton'));

        $price = SmartString::new(19.99);
        $this->assertSame('24.99', (string)$price->set('24.99'));
        $this->assertSame('Under 20', (string)$price->set($price->value() < 20 ? 'Under 20' : 'Over 20'));
    }

    public function testSetWithMatchExpressionInHeredoc(): void
    {
        $eggs = SmartString::new(12);
        $html = <<<__HTML__
        Eggs: {$eggs->set(match($eggs->int()) {
            12      => "Full Carton",
            6       => "Half Carton",
            default => "{$eggs->int()} Eggs"
        })}
        __HTML__;

        $this->assertSame('Eggs: Full Carton', $html);
    }

    //endregion
    //region README: Validation

    public function testValidation(): void
    {
        $this->assertTrue(SmartString::new('')->isEmpty());
        $this->assertTrue(SmartString::new('Hello')->isNotEmpty());
        $this->assertTrue(SmartString::new(null)->isNull());
        $this->assertTrue(SmartString::new('')->isMissing());

        // the documented zero note: isEmpty() true, isMissing() false
        $this->assertTrue(SmartString::new(0)->isEmpty());
        $this->assertFalse(SmartString::new(0)->isMissing());
    }

    //endregion
    //region README: Custom Functions

    public function testCustomFunctions(): void
    {
        $name = SmartString::new('John Doe');

        $this->assertSame('JOHN DOE', $name->apply('strtoupper')->value());
        $this->assertSame('John Doe.......', $name->apply('str_pad', 15, '.')->value());

        $spacesToUnderscores = fn($str) => str_replace(' ', '_', $str);
        $this->assertSame('John_Doe', $name->apply($spacesToUnderscores)->value());

        $this->assertSame('<b>John Doe</b>', $name->apply(fn($val) => "<b>$val</b>")->value());
    }

    //endregion
    //region README: Developer Debugging

    public function testPrintRShowsRawData(): void
    {
        $name   = SmartString::new("John O'Reilly");
        $output = print_r($name, true);

        // README:private appears only on the first print_r per process, so
        // only the always-present parts are asserted here
        $this->assertStringContainsString('rawData:private', $output);
        $this->assertStringContainsString('"John O\'Reilly"', $output);
    }

    //endregion
    //region help.txt: Working with Arrays

    public function testHelpTxtWorkingWithArrays(): void
    {
        $user = ['id' => 42, 'name' => "John O'Reilly", 'lastLogin' => '2024-09-10 14:30:00'];
        $u    = SmartArray::new($user)->asHtml();

        $this->assertSame('Hello, John O&apos;Reilly', "Hello, $u->name");
        $this->assertSame("Hello, John O'Reilly", "Hello, {$u->name->value()}");
        $this->assertSame('Last login: Sep 10, 2024', "Last login: {$u->lastLogin->dateFormat('M j, Y')}");
    }

    //endregion
}
