# SmartString: Secure and Simple String Handling for PHP

SmartString is a PHP string handling library that lets you write cleaner, simpler, more secure code faster and with less
effort.

Instead of writing code like this:

```php
echo "<h1>" . htmlspecialchars($article['title'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "</h1>";
$summary = strip_tags($article['content']); // remove tags
$summary = html_entity_decode($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8'); // decode entities
$summary = substr($summary, 0, 120); // limit to 120 characters
echo "Summary: " . htmlspecialchars($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "...";
```

You can write code like this:

```php
echo "<h1>$article->title</h1>";
echo "Summary: {$article->content->textOnly()->maxChars(120, '...')}\n";
```

SmartString handles HTML encoding automatically and provides utility functions for common tasks.
This makes your code cleaner, more readable, and inherently more secure.

## Table of Contents

<!-- TOC -->

* [SmartString: Secure and Simple String Handling for PHP](#smartstring-secure-and-simple-string-handling-for-php)
    * [Table of Contents](#table-of-contents)
    * [Quick Start](#quick-start)
    * [Features and Usage Examples](#features-and-usage-examples)
        * [Creating SmartStrings](#creating-smartstrings)
        * [Fluent Chainable Interface](#fluent-chainable-interface)
        * [Automatic HTML-encoding](#automatic-html-encoding)
        * [Accessing Values](#accessing-values)
        * [Working with SmartArrays](#working-with-smartarrays)
        * [Type Conversion](#type-conversion)
        * [Encoding Values](#encoding-values)
        * [String Manipulation](#string-manipulation)
        * [Number Formatting](#number-formatting)
        * [Date Formatting](#date-formatting)
        * [Numeric Operations](#numeric-operations)
        * [Conditional Operations](#conditional-operations)
        * [Validation](#validation)
        * [Error Checking](#error-checking)
        * [Developer-Friendly Error Messages](#developer-friendly-error-messages)
        * [Custom Functions](#custom-functions)
        * [Developer Debugging &amp; Help](#developer-debugging--help)
    * [Customizing Defaults](#customizing-defaults)
    * [Method Reference](#method-reference)
    * [Questions?](#questions)

<!-- TOC -->

## Quick Start

> **Requirements:** PHP 8.1 or higher with mbstring extension

Install via Composer:

```bash
composer require itools/smartstring
```

Start using SmartString:

```php
require 'vendor/autoload.php';
use Itools\SmartString\SmartString;

// Create SmartArray of SmartStrings (can be referenced as array or object)
// Call ->asHtml() to convert all values to SmartStrings for HTML-safe output
$user    = SmartArray::new(['name' => "John O'Reilly", 'id' => 123])->asHtml();
$request = SmartArray::new($_REQUEST)->asHtml();

// Advanced: For direct instantiation with a specific type:
// $user = SmartArrayHtml::new(['name' => "John O'Reilly", 'id' => 123]);
// $data = SmartArray::new($rawData);  // For raw values without SmartStrings

// Use in string contexts for automatic HTML encoding
echo "Hello, $user->name!"; // Output: Hello, John O&apos;Reilly!

// Chain methods
echo $request->message->trim()->maxWords(50, '...');

// Access original values when needed
$userId = $user->id->value(); // Returns 123 as integer

// Check the actual value of a SmartString object (for debugging)
print_r($user->name);

// Access built-in help whenever you need it
SmartString::help();
$user->help();
```

## Features and Usage Examples

### Creating SmartStrings

SmartString offers simple ways to create objects from various data types. It can be especially
useful to convert `$_REQUEST`, or database record arrays to SmartString objects.

The automatic HTML-encoding feature means you don't need to call `htmlspecialchars()`
over and over again, and you get access to a variety of utility methods for common tasks.

```php
// Single values
$name      = SmartString::new("John O'Reilly");
$age       = SmartString::new(30);
$price     = SmartString::new(19.99);
$isActive  = SmartString::new(true);
$nullValue = SmartString::new(null);

// Or use SmartArray::new()->asHtml() to convert an existing array to a SmartArray of SmartStrings
$record  = ['name' => "Jane Doe", 'age' => 25, 'isStudent' => true ];
$user    = SmartArray::new($record)->asHtml();
$request = SmartArray::new($_REQUEST)->asHtml();

// Looping over a two-level array
foreach (SmartArray::new($articles)->asHtml() as $article) {
    echo <<<__HTML__
        <h1>$article->title</h1>
        <p>{$article->content->textOnly()->maxChars(200, '')}</p>
        <a href="read.php?id={$article->id->urlEncode()}">Read more</a>
    __HTML__;
}

// Usage
echo $name;               // John O&apos;Reilly
echo $user->age;          // 25
echo $request->username;  // html-encoded $_REQUEST['username']
```

### Fluent Chainable Interface

SmartString provides a fluent, chainable interface that allows you to perform one or more operations in a single,
readable line of code.

```php
// Providing a default value
echo "Hello, {$name->or('Guest')}!"; // e.g., "Hello, John!"

// Formatting a date
echo "Article date: {$article->date->dateFormat('M jS, Y')}"; // e.g., Jan 1st, 2024

// Formatting a number
echo "Total: {$order->total->numberFormat(2)}"; // e.g., 1,234.56

// Trimming whitespace
echo "<input type='text' name='username' value='{$user->name->trim()}'>";

// Combining multiple operations and providing a default value
echo "Order total: {$order->total->numberFormat(2)->or("none")}"; //

// Combining multiple operations
$url = "?startDate={$course->startDate->dateFormat('Y-m-d')->urlEncode()}";

// Combining multiple operations to create a text summary
echo "Summary: {$article->content->textOnly()->maxChars(200, '...')}";
```

The fluent chainable interface allows you to build complex transformations step-by-step, making your code more intuitive
and easier to read and maintain.

### Automatic HTML-encoding

SmartString prioritizes web security by automatically HTML-encoding output by default. This greatly simplifies your
code and helps prevent Cross-Site Scripting (XSS) vulnerabilities.

Whenever you use a SmartString object in a string context, it automatically HTML-encodes the output:

```php
$str = SmartString::new("It's easy!<hr>");

// SmartStrings return HTML-encoded output in string contexts
echo $str;             // "It&apos;s easy!&lt;hr&gt;"
print $str;            // "It&apos;s easy!&lt;hr&gt;"
(string) $str;         // "It&apos;s easy!&lt;hr&gt;"
$new = $str."\n";      // "It&apos;s easy!&lt;hr&gt;\n"
```

### Accessing Values

You can access the original value with the `value()` method:

```php
$str = SmartString::new("It's easy!<hr>");

// Access the original value
echo $str->value();    // "It's easy!<hr>"
```

Or you can also use the `rawHtml()` alias method for readability when outputting trusted HTML.
This is useful when you have WYSIWYG content that you don't want to double-encode:

```php
echo <<<__HTML__
    <h1>{$article->title}</h1>
    {$article->wysiwygContent->rawHtml()}
__HTML__;
```

### Working with SmartArrays

When you convert an array to SmartArray, you can use it like both an array and an object.

```php
$user = SmartArray::new(['name' => 'John', 'age' => 30])->asHtml();

// Simple, clean object-style access (no extra curly braces needed)
echo "Name: $user->name, Age: $user->age";

// Array-style access still works too
echo "Hello, {$user['name']}!";

// For calling methods in strings, you still need to use curly braces
echo "Hello, {$user->name->or("User")}!";
```

### Type Conversion

You can convert SmartString objects to different types using terminal methods.
This can be useful when you need to pass a SmartString value to a function that expects a specific type.

```php
$value = SmartString::new("123.45");

// Convert to integer
echo $value->int(); // 123

// Convert to float
echo $value->float(); // 123.45

// Convert to boolean
$isValid = $value->bool(); // true (note: echo would print "1")

// Convert to string
echo $value->string(); // "123.45"
```

### Encoding Values

Besides just HTML encoding, SmartString provides methods for explicit encoding in different scenarios:

```php
$title = SmartString::new('<10% OFF "SALE"');

// Original Value
echo $title->value();       // '<10% OFF "SALE"'

// HTML Encode (default) - can be called explicitly for readability
echo $title->htmlEncode();  // "&lt;10% OFF &quot;SALE&quot;"

// URL encode
echo "add.php?title={$title->urlEncode()}"; // add.php?title=%3C10%25+OFF+%22SALE%22

// JSON encode
echo "let title={$title->jsonEncode()}";    // let title="\u003C10% OFF \u0022SALE\u0022"

// raw HTML - This is an alias for value() for readability when outputting trusted HTML
echo "Title: {$title->rawHtml()}";         // 'Title: <10% OFF "SALE"'

// nl2br - encodes special chars, then converts newlines to <br> tags
$text = SmartString::new("Hello\nWorld");
echo "{$text->nl2br()}";                   // "Hello<br>\nWorld"

// appendHtml - encode the value, then append your markup as-is; missing values return ""
echo SmartString::new('12 High St')->appendHtml(',<br>');  // "12 High St,<br>"
echo SmartString::new(null)->appendHtml(',<br>');          // ""

// wrapHtml - encode the value, then wrap it in your markup; the whole wrapper vanishes when missing
echo SmartString::new('Our Story')->wrapHtml('<h2 class="lead">', '</h2>');  // "<h2 class=\"lead\">Our Story</h2>"
echo SmartString::new('')->wrapHtml('<h2 class="lead">', '</h2>');           // ""

// The markup arguments are trusted and output as-is - only pass literals you wrote, never user input
```

### String Manipulation

SmartString offers a variety of methods for common string operations, making it easy to modify and format your text:

```php
// Convert HTML to text - removes tags, decodes entities, and trims whitespace
$htmlText = SmartString::new(" <b> Some HTML </b> ");
echo $htmlText->textOnly(); // "Some HTML"

// Convert text to HTML - encodes special chars, then converts newlines to <br> tags
$multiLineText = SmartString::new("Hello\nWorld");
echo "{$multiLineText->nl2br()}"; // "Hello<br>\nWorld"

// Trim whitespace
$whitespaceText = SmartString::new("  Trim me  ");
echo $whitespaceText->trim(); // "Trim me"

// Limit to a specific number of words
$longText = SmartString::new("The quick brown fox jumps over the lazy dog");
echo $longText->maxWords(4); // "The quick brown fox..."

// Limit to a specific number of characters, up to the last whole word
echo $longText->maxChars(10); // "The quick..."

// Be sure to convert HTML to text before using maxChars or maxWords
echo $htmlText->textOnly()->maxChars(10); // "Some HTML"

```

And all of the above methods are chainable:

```php
$str = SmartString::new("  <p>More text and HTML than needed</p>  ");
echo $str->textOnly()->maxWords(3); // "More text and..."
```

### Number Formatting

SmartString provides a simple way to format numbers specifying the number of decimals and separators as needed.
You can customize the default formats at the top of your script or in an init file to use throughout your application.

```php
// Basic number formatting with default arguments
$number = SmartString::new(1234567.89);
echo $number->numberFormat(); // "1,234,568" (rounded to 0 decimals)

// Formatting options can be customized to match your locale or regional preferences
SmartString::$numberFormatDecimal   = ',';  // Decimal separator, default is '.'
SmartString::$numberFormatThousands = ' ';  // Thousands separator, default is ','

// Specify number of decimals
echo $number->numberFormat(2); // "1 234 567,89"
```

### Date Formatting

You can format dates with your default format or specify a custom format as needed.
You can customize the default format at the top of your script or in an init file to use throughout your application.

```php
// Set default date format
SmartString::$dateFormat = 'F jS, Y';  // Example: September 10th, 2024

// Using default format
$date = SmartString::new("2024-05-15 14:30:00");
echo $date->dateFormat(); // "May 15th, 2024"

// Custom formats
echo $date->dateFormat('F j, Y');            // "May 15, 2024"
echo $date->dateFormat('l, F j, Y g:i A');   // "Wednesday, May 15, 2024 2:30 PM"

// Handling invalid dates - returns null
$invalid = SmartString::new("not a date");
echo $invalid->dateFormat()->or("Invalid date"); // "Invalid date"
echo $invalid->dateFormat()->or($invalid);       // "not a date"

// Numeric values are treated as unix timestamps, everything else is parsed with strtotime()
$timestamp = SmartString::new(1684159800);
echo $timestamp->dateFormat('Y-m-d'); // "2023-05-15"
```

You can find a list of available date formats in the PHP documentation:
https://www.php.net/manual/en/datetime.format.php

### Numeric Operations

SmartString provides a set of methods for performing basic arithmetic and percentage calculations.
These methods are chainable, allowing for complex calculations to be expressed clearly and concisely.
Null and non-numeric values propagate: they make the result null instead of throwing, so one
`or()` or `ifNull()` at the end of a chain covers every failure in it. Results are floats.

```php
// Percentage conversion
$ratio = SmartString::new(0.75);
echo $ratio->percent(); // "75%"

// Percentage with 2 decimals, and fallback value for 0
$value = SmartString::new(0);
echo $value->percent(2, ifZero: "N/A"); // "N/A"

// Percentage of a total
$score = SmartString::new(24);
echo $score->percentOf(100); // "24%"

// Addition
$base = SmartString::new(100);
echo $base->add(50); // 150

// Null propagates through math - rescue with or() or ifNull() at the end
$value = SmartString::new(null);
echo $value->add(50);              // "" (null result, blank output)
echo $value->add(50)->or('n/a');   // "n/a"
echo $value->ifNull(0)->add(50);   // 50 (replace null BEFORE math to treat it as zero)

// or() placement changes meaning: before formatting supplies a number, after supplies display text
echo $value->or(0)->numberFormat(2);      // "0.00" - fallback number, then formatted
echo $value->numberFormat(2)->or('n/a');  // "n/a" - format failed, then display fallback

// Run conditionals before formatting: formatted output ("1,234.00", "50%") is no longer numeric,
// so ifZero() and math can't see it (percent() takes its zero rule as a parameter for this reason)

// Subtraction
$start = SmartString::new(100);
echo $start->subtract(30); // 70

// Division
$total = SmartString::new(100);
echo $total->divide(4); // 25

// Multiplication
$factor = SmartString::new(25);
echo $factor->multiply(4); // 100

// Chaining operations
$price = SmartString::new(100);
echo $price->multiply(1.1)->divide(2)->numberFormat(2); // "55.00" (add tax, divide by 2, format)

// Math operations can be useful for simple reporting, calculating totals, discounts, taxes, etc.
Order Total: $order->total->add( $order->shipping )->numberFormat(2)
```

**Note:** Be aware of decimal precision issues when performing calculations. Results may sometimes differ slightly
from expected values due to floating-point arithmetic limitations inherent in all programming languages.

For more information, see [PHP Floating Point Numbers](https://www.php.net/manual/en/language.types.float.php).

### Conditional Operations

Conditional operations provide a simple way to provide a fallback value when the value is missing ("", null)
or handle specific conditions like null or zero values.

```php
// or($newValue): Show default if value is missing ("", null). Zero is not considered missing.
$value = SmartString::new('');
echo $value->or('Default'); // "Default"

// append($value): Append a value if the value is present (not "" or null). Zero is considered present.
$city = SmartString::new('Vancouver');
echo $city->append(', ');                    // "Vancouver, "
echo SmartString::new(null)->append(', ');   // "" - nothing to append to, nothing appended

// prepend($value): Prepend a value if the value is present (not "" or null). Zero is considered present.
echo $record->phone->prepend("Phone: ");

// wrap($before, $after): Wrap the value if present; the whole wrapper vanishes when the value is missing.
$ext = SmartString::new(204);
echo $ext->wrap('(ext. ', ')');                    // "(ext. 204)"
echo SmartString::new(null)->wrap('(ext. ', ')');  // ""

// ifNull($newValue): Handling null values - SmartString will return nulls on failed operations
$nullable = SmartString::new(null);
echo $nullable->ifNull('Not Null'); // "Not Null"

// ifZero($newValue): Handling zero values (0, 0.0, "0", or "0.0")
$zero = SmartString::new(0);
echo $zero->ifZero('No balance'); // "No balance"

// ifTrue($condition, $newValue): Replace the value when your condition is truthy
$eggs = SmartString::new(12);
echo $eggs->ifTrue($eggs->int() === 12, "Full Carton"); // "Full Carton"

// ifEquals($match, $newValue): Replace sentinel values (loose ==, so "5" matches 5; use ifNull() for null)
$date = SmartString::new('0000-00-00');
echo $date->ifEquals('0000-00-00', null)->dateFormat('M j, Y')->or('Not set'); // "Not set"
$maxUsers = SmartString::new(-1);
echo $maxUsers->ifEquals(-1, 'Unlimited'); // "Unlimited"

// set($newValue): Assign a new value or expression result to the current object
$price = SmartString::new(19.99);
echo $price->set('24.99'); // 24.99
echo $price->set($price->value() < 20 ? "Under 20" : "Over 20"); // "Under 20"

// Or more complex operations using PHP match() expressions
$eggs = SmartString::new(12);
echo <<<__HTML__
Eggs: {$eggs->set(match($eggs->int()) {
    12      => "Full Carton",
    6       => "Half Carton",
    default => "{$eggs->int()} Eggs"
})}
__HTML__; // "Eggs: Full Carton"

// The above code is pretty complex, so it's best to use it sparingly.  Don't be afraid to
// use regular PHP code when needed.  We always recommend using the best tool for the job.
```

### Validation

Check if a value meets specific conditions using validation methods:

```php
$value = SmartString::new('');
if ($value->isEmpty()) {
    echo "Value is empty!";
}

$value = SmartString::new('Hello');
if ($value->isNotEmpty()) {
    echo "Value is not empty!";
}

$value = SmartString::new(null);
if ($value->isNull()) {
    echo "Value is specifically NULL!";
}

$value = SmartString::new('');
if ($value->isMissing()) {
    echo "Value is missing (null or empty string)!";
}
```

**Zero:** `isEmpty()` is true for 0 (PHP `empty()` rules) but `isMissing()` is false - `or()`,
the attach methods (append/prepend/wrap), and the or404/orDie/orThrow guards all treat 0 as
a real value. Use `isMissing()` when a legitimate zero must count as present.

### Error Checking

Show an error message, throw an exception, or display a 404 page for values that are missing (empty string "", null).
Zero is not considered missing.

```php
// Terminate with 404 if record not found
$article = DB::get('articles', 123);        // Assume SmartArray returned
$article->id->or404("Article not found");   // Sends 404 header and terminate script

// Terminate with custom message if value missing
$article = DB::get('articles', 123);           // Assume SmartArray returned
$article->id->orDie("Article not found");      // Output message and terminate script
$article->id->orThrow("Article not found");    // Throws Exception with error message
$article->id->orRedirect("/articles/list");    // Redirect to listing page if missing
```

**Note:** Messages are HTML-encoded automatically - they often interpolate user input
(e.g., `->orDie("Bad id: $id")`) and may be echoed into a page.

### Developer-Friendly Error Messages

SmartString provides detailed error messages when methods are used incorrectly. This makes debugging easier, especially
when working with strings:

```php
// Common error: Forgetting to use parentheses on methods
$name = SmartString::new("John");
echo $name->trim;  // Will show helpful error: "Method ->trim needs brackets() everywhere and {curly braces} in strings"

// Common error: Forgetting curly braces in strings
echo "Hello $name->trim()";  // Will show helpful error explaining you need to use: "Hello {$name->trim()}"
```

### Custom Functions

SmartString provides a `map()` method that allows you to use custom code or PHP's built-in functions.
The value of the SmartString object is passed as the first argument to the callback function
followed by any supplied arguments. The callback always runs, even on null - chain `->ifNull('')`
first when using built-in functions that require a string.

Examples of using map():

```php
$name = SmartString::new('John Doe');

// Using built-in PHP functions:
$uppercase = $name->map('strtoupper');  // returns "JOHN DOE"

// Passing arguments to built-in functions:
$paddedValue = $name->map('str_pad', 15, '.'); // returns "John Doe......."

// Writing your own custom function
$spacesToUnderscores = function($str) { return str_replace(' ', '_', $str); }; // anonymous function
$spacesToUnderscores = fn($str) => str_replace(' ', '_', $str);                 // arrow function (PHP 7.4+)
$urlSlug = $name->map($spacesToUnderscores);   // returns "John_Doe"

// Applying inline arrow functions
$boldName = $name->map(fn($val) => "<b>$val</b>"); // returns "<b>John Doe</b>"
```

### Developer Debugging &amp; Help

When you call print_r() on a SmartString object, it will display the original value with helpful information:

```php
$name = SmartString::new("John O'Reilly");
print_r($name);

// Output:
Itools\SmartString\SmartString Object
(
    [README:private] => "Call $obj->help() for more information and method examples."
    [rawData:private] => "John O'Reilly"
)
```

This enhanced debugging output makes it easy to see the actual value stored in the object and provides guidance about
how to get more detailed help.

Calling `SmartString::help()` or `$obj->help()` will display a list of available methods and examples:

```php
This 'SmartString' object automatically HTML-encodes output in string contexts for XSS protection.
It also provides access to the original value, alternative encoding methods, and various utility methods.

Creating SmartStrings
\$str = SmartString::new("It's easy!<hr>");
\$req = SmartArray::new(\$_REQUEST)->asHtml();  // SmartArray of SmartStrings

Automatic HTML-encoding in string contexts:
echo \$str;             // "It&apos;s easy!&lt;hr&gt;"

// ... continues with a list of available methods and examples
```

## Customizing Defaults

You can customize the defaults by adding the following to the top of your script or in an init file:

```php
SmartString::$numberFormatDecimal   = '.';             // Default decimal separator
SmartString::$numberFormatThousands = ',';             // Default thousands separator
SmartString::$dateFormat            = 'Y-m-d';         // Default dateFormat() format
```

## Method Reference

### Basic Usage

```php
$str = SmartString::new("It's easy!<hr>");

echo $str;             // It&apos;s easy!&lt;hr&gt; (HTML-encoded automatically)
echo $str->value();    // It's easy!<hr> (the original value)

echo $str->trim()->maxChars(60)->or('None');   // methods chain left to right
SmartString::help();   // print a quick reference of all methods (works on values too: $str->help())
```

Here `$str` is an object, not a string. Whenever PHP needs it as a string (echo, print,
`"$str"`, concatenation), the object converts itself to its HTML-encoded value,
which is why echo above prints `It&apos;s easy!&lt;hr&gt;`. The `value()` method is the
escape hatch: it returns the original, unencoded value in its original type,
ready for regular PHP code.

### Type Conversion

*These return the value as a plain PHP type, so they end the chain.*

| Method                       | Description                                                                                                                                                  |
|------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `->value()`                  | Returns the original, unencoded value in its original type - the escape hatch                                                                                |
| `->int()`                    | Returns the value as an integer                                                                                                                              |
| `->float()`                  | Returns the value as a float                                                                                                                                 |
| `->bool()`                   | Returns the value as a boolean                                                                                                                               |
| `->string()`                 | Returns the value as a string (original value, not HTML-encoded)                                                                                             |
| `SmartString::getRawValue()` | Returns the original value when you don't know what you have: Smart* objects are converted to their original value, everything else passes through unchanged |

### Encoding

*These return the encoded value as a plain string, so they end the chain. Missing values (null or "") return "", so echoing an empty field prints nothing.*

| Method           | Description                                                                                                                         |
|------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `->htmlEncode()` | Returns the value as an HTML-encoded string                                                                                         |
| `->urlEncode()`  | Returns the value as a URL-encoded string                                                                                           |
| `->jsonEncode()` | Returns the value as a JSON-encoded string (null encodes as `null`, malformed UTF-8 becomes � instead of throwing)                  |
| `->rawHtml()`    | Alias for `value()` - reads clearly when you're outputting trusted HTML on purpose (returns the original value, so null stays null) |
| `->nl2br()`      | HTML-encodes special chars, then converts newlines to `<br>` tags (unlike PHP's `nl2br()`, output is XSS-safe)                      |
| `->appendHtml($html)`         | HTML-encodes the value, then appends your trusted markup as-is                                                         |
| `->wrapHtml($before, $after)` | HTML-encodes the value, then wraps it in your trusted markup as-is                                                     |

### String Manipulation

*These return a new SmartString, so you can keep chaining. Missing values (null or "") come through unchanged, so a later `or()` fallback still works.*

| Method                                  | Description                                                                 |
|-----------------------------------------|-----------------------------------------------------------------------------|
| `->append($value)`                      | Adds `$value` to the end of the current value                               |
| `->prepend($value)`                     | Adds `$value` to the beginning of the current value                         |
| `->wrap($before, $after)`               | Wraps the value; pass "" for a side you don't want                          |
| `->textOnly()`                          | Removes HTML tags, decodes entities, and trims whitespace                   |
| `->trim()`                              | Trims whitespace (or the characters you specify) from both ends             |
| `->maxWords($max, $ellipsis = '...')`   | Limits the value to `$max` words; adds `$ellipsis` if text was cut off      |
| `->maxChars($max, $ellipsis = '...')`   | Limits the value to `$max` characters; adds `$ellipsis` if text was cut off |
| `->pregReplace($pattern, $replacement)` | Replaces text matching a regex pattern                                      |

### Dates & Numbers

*These return a new SmartString, so you can keep chaining. If the value is missing or not a valid date or number, the result is null - add `or()` after to show a fallback.*

| Method                                     | Description                                                                                   |
|--------------------------------------------|-----------------------------------------------------------------------------------------------|
| `->dateFormat($format = default)`          | Formats the value as a date, using the format you pass or the configured default              |
| `->numberFormat($decimals = 0)`            | Formats the value as a number with thousands separators and `$decimals` decimal places        |
| `->percent($decimals = 0, $ifZero = null)` | Converts a decimal to a percentage, e.g. 0.24 becomes 24%; `$ifZero` is shown for zero values |
| `->percentOf($total, $decimals = 0)`       | Calculates what percentage the value is of `$total`, e.g. 25 of 200 is 12.5%                  |
| `->add($value)`                            | Adds `$value` to the current number                                                           |
| `->subtract($value)`                       | Subtracts `$value` from the current number                                                    |
| `->multiply($value)`                       | Multiplies the current number by `$value`                                                     |
| `->divide($divisor)`                       | Divides the current number by `$divisor`                                                      |

### Conditional Replacement

*These return a new SmartString, so you can keep chaining. Each swaps in a
replacement value when its condition matches - most commonly `or()`, to show a
default when a field is empty.*

| Method                               | Description                                                                                          |
|--------------------------------------|------------------------------------------------------------------------------------------------------|
| `->or($fallback)`                    | Replaces missing values (null or "") with `$fallback`; zero counts as present                        |
| `->ifNull($fallback)`                | Replaces null with `$fallback`                                                                       |
| `->ifZero($fallback)`                | Replaces zero with `$fallback`                                                                       |
| `->ifTrue($condition, $newValue)`    | Replaces the value with `$newValue` when your condition is truthy                                    |
| `->ifEquals($match, $newValue)`      | Replaces the value with `$newValue` when it loosely equals `$match` (==, so "5" matches 5)           |
| `->set($newValue)`                   | Replaces the value unconditionally - useful for storing the result of a match() or a calculation     |

### Require a Value

*Use these for values that must exist, like a record ID from the URL. If the
value is missing (null or "") they stop the page; otherwise they do nothing and
the chain continues. Zero counts as present.*

| Method               | Description                                      |
|----------------------|--------------------------------------------------|
| `->orDie($text)`     | Outputs the message and exits                    |
| `->or404($text)`     | Outputs a 404 header and the message, then exits |
| `->orThrow($text)`   | Throws an Exception with the message             |
| `->orRedirect($url)` | Redirects to `$url` and exits                    |

### Value Checks

*These return a plain true or false, typically used in if statements. Note that
zero is "empty" but not "missing" - pick the check that treats zero the way you
want.*

| Method           | Description                                                                          |
|------------------|--------------------------------------------------------------------------------------|
| `->isEmpty()`    | Returns true when the value is empty ("", null, false, 0, "0") - same as PHP empty() |
| `->isNotEmpty()` | Returns true when the value has content - the exact opposite of isEmpty()            |
| `->isMissing()`  | Returns true when the value is missing (null or ""); zero counts as present          |
| `->isNull()`     | Returns true when the value is null                                                  |

### Custom Functions

| Method                   | Description                                                                                                                 |
|--------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `->map($func, ...$args)` | Calls your function with the original value and returns the result as a new SmartString - runs even when the value is null |

**Working with arrays?** [SmartArray](https://github.com/interactivetools-com/SmartArray)
is the companion library that handles arrays (like database rows) as collections of
SmartStrings - it's where `SmartArray::new($array)->asHtml()` in the examples above
comes from, and it has its own
[method reference](https://github.com/interactivetools-com/SmartArray?tab=readme-ov-file#method-reference).

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
