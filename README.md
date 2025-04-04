# SmartString: Secure and Simple String Handling for PHP

SmartString is a PHP string handling library that lets you write cleaner, simpler, more secure code faster and with less
effort.

Instead of writing code like this:

```php
echo "<h1>" . htmlspecialchars($article['title'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "</h1>";
$summary = strip_tags($article['content']); // remove tags
$summary = html_entity_decode($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8'); // decode entities
$summary = substr($summary, 0, 200); // limit to 200 characters
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
        * [Phone Number Formatting](#phone-number-formatting)
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

> **Requirements:** PHP 8.0 or higher with mbstring extension

Install via Composer:

```bash
composer require itools/smartstring
```

Start using SmartString:

```php
require 'vendor/autoload.php';
use Itools\SmartString\SmartString;

// Create SmartArray of SmartStrings (can be referenced as array or object)
// Call ->withSmartStrings() to convert all values to SmartStrings, or use SmartArray::new($array, true)
$user    = SmartArray::new(['name' => "John O'Reilly", 'id' => 123])->withSmartStrings();   // verbose syntax
$user    = SmartArray::new(['name' => "John O'Reilly", 'id' => 123], true);                 // shortcut syntax
$request = SmartArray::new($_REQUEST, true);

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

// Or use SmartArray::new()->withSmartStrings() to convert an existing array to a SmartArray of SmartStrings
$record  = ['name' => "Jane Doe", 'age' => 25, 'isStudent' => true ];
$user    = SmartArray::new($record)->withSmartStrings();
$request = SmartArray::new($_REQUEST, true);  // shortcut syntax, pass true as second arg, new($array, true)

// Looping over a two-level array
foreach (SmartArray::new($articles, true) as $article) {
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
$user = SmartArray::new(['name' => 'John', 'age' => 30])->withSmartStrings();  // shortcut syntax new($array, true)

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
echo $value->bool(); // true

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
```

### String Manipulation

SmartString offers a variety of methods for common string operations, making it easy to modify and format your text:

```php
// Convert HTML to text - removes tags, decodes entities, and trims whitespace
$htmlText = SmartString::new(" <b> Some HTML </b> ");
echo $htmlText->textOnly(); // "Some HTML"

// Convert newlines to <br> tags - useful for displaying multi-line text in HTML
$multiLineText = SmartString::new("Hello\nWorld");
echo $multiLineText->nl2br(); // "Hello<br>\nWorld"

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
echo $number->numberFormat(); // "1,234,567"

// Formatting options can be customized to match your locale or regional preferences
SmartString::$numberFormatDecimal   = ',';  // Decimal separator, default is '.'
SmartString::$numberFormatThousands = ' ';  // Thousands separator, default is ','

// Specify number of decimals
echo $number->numberFormat(2); // "1 234 567,89"
```

### Date Formatting

You can format dates with a standard format for date, datetime, or specify a custom format as needed.
You can customize the default formats at the top of your script or in an init file to use throughout your application.

```php
// Set default date and date-time formats
SmartString::$dateFormat     = 'F jS, Y';        // Example: September 10th, 2024
SmartString::$dateTimeFormat = 'F jS, Y g:i A';  // Example: September 10th, 2024 3:45 PM

// Using default date-only format
$date = SmartString::new("2024-05-15 14:30:00");
echo $date->dateFormat(); // "May 15th, 2024"

// Using default date-time format
$dateTime = SmartString::new("2024-06-21 17:30:59");
echo $dateTime->dateTimeFormat(); // "June 21st, 2024 5:30 PM"

// Custom format
echo $date->dateFormat('F j, Y'); // "May 15, 2024"
echo $dateTime->dateTimeFormat('l, F j, Y g:i A'); // "Friday, June 21, 2024 5:30 PM"

// Handling invalid dates - returns null
$invalid = SmartString::new("not a date");
echo $invalid->dateFormat()->or("Invalid date"); // "Invalid date"
echo $invalid->dateFormat()->or($invalid);       // "not a date"
```

You can find a list of available date formats in the PHP documentation:
https://www.php.net/manual/en/datetime.format.php

### Phone Number Formatting

SmartString formats phone numbers using customizable rules.
You can customize the default formats at the top of your script or in an init file to use throughout your application.

```php
// Specify preferred phone formats
SmartString::$phoneFormat = [
    ['digits' => 10, 'format' => '1.###.###.####'], // Automatically adds country code
    ['digits' => 11, 'format' => '#.###.###.####'],
];

// 10-digit phone number - only numbers are kept when formatting
$phone = SmartString::new("(234)567-8901");
echo $phone->phoneFormat(); // "1.234.567.8901"

// 11-digit phone number
$phone = SmartString::new("1-888-123-4567");
echo $phone->phoneFormat(); // "1.888.123.4567"

// Invalid phone number - returns null
$phone = SmartString::new("123");
echo $phone->phoneFormat()->or("Invalid phone"); // default message if null
echo $phone->phoneFormat()->or($phone);          // or show the original value "123"
```

### Numeric Operations

SmartString provides a set of methods for performing basic arithmetic and percentage calculations.
These methods are chainable, allowing for complex calculations to be expressed clearly and concisely.
Null values are treated as zero by default, making calculations more intuitive.

```php
// Percentage conversion
$ratio = SmartString::new(0.75);
echo $ratio->percent(); // "75%"

// Percentage with 2 decimals, and fallback value for 0
$value = SmartString::new(0);
echo $value->percent(2, "N/A"); // "N/A"

// Percentage of a total
$score = SmartString::new(24);
echo $score->percentOf(100); // "24%"

// Addition
$base = SmartString::new(100);
echo $base->add(50); // 150

// Handling null values (treated as zero by default)
$value = SmartString::new(null);
echo $value->add(50); // 50

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
echo $price->multiply(1.1)->divide(2)->percent(); // "55%" (add tax, divide by 2, show as percent)

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

// and($value): Append a value if the value is present (not "" or null). Zero is considered present.
echo $record->address1->and(",<br>\n");
echo $record->address2->and(",<br>\n");
echo $record->address3->and(",<br>\n");

// andPrefix($value): Prepend a value if the value is present (not "" or null). Zero is considered present.
echo $record->phone->andPrefix("Phone: ");

// ifBlank($newValue): Handling blank values (only on empty string "")
$name1 = SmartString::new('');
$name2 = SmartString::new('Alice');
echo $name1->ifBlank('John Doe'); // "John Doe"
echo $name2->ifBlank('John Doe'); // "Alice"

// ifNull($newValue): Handling null values - SmartString will return nulls on failed operations
$nullable = SmartString::new(null);
echo $nullable->ifNull('Not Null'); // "Not Null"

// ifZero($newValue): Handling zero values (0, 0.0, "0", or "0.0")
$zero = SmartString::new(0);
echo $zero->ifZero('No balance'); // "No balance"

// if($condition, $valueIfTrue): Change value if condition is true
$eggs = SmartString::new(12);
echo $eggs->if($eggs->int() === 12, "Full Carton"); // "Full Carton"

// set($newValue): Assign a new value or expression result to the current object
$price = SmartString::new(19.99);
echo $price->set('24.99'); // 24.99
echo $price->set($price->value() < 20 ? "Under 20" : "Over 20"); // "Over 20"

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

### Error Checking

Show an error message, throw an exception, or display a 404 page for values that are missing (empty string "", null).
Zero is not considered missing.

```php
// Terminate with 404 if record not found
$article = DB::get('articles', 123);        // Assume SmartArray returned
$article->id->or404("Article not found");   // Sends 404 header and terminate script

// Terminate with custom message if value missing
$article = DB::get('articles', 123);        // Assume SmartArray returned
$article->id->orDie("Article not found");   // Output message and terminate script
$article->id->orThrow("Article not found");  // Throws Exception with error message
```

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

SmartString provides an `apply()` method that allows you to use custom code or PHP's built-in functions.
The value of the SmartString object is passed as the first argument to the callback function
followed by any supplied arguments.

Examples of using apply():

```php
$name = SmartString::new('John Doe');

// Using built-in PHP functions:
$uppercase = $name->apply('strtoupper');  // returns "JOHN DOE"

// Passing arguments to built-in functions:
$paddedValue = $name->apply('str_pad', 15, '.'); // returns "John Doe......."

// Writing your own custom function
$spacesToUnderscores = function($str) { return str_replace(' ', '_', $str); }; // anonymous function
$spacesToUnderscores = fn($str) => str_replace(' ', '_', $str);                 // arrow function (PHP 7.4+)
$urlSlug = $name->apply($spacesToUnderscores);   // returns "John_Doe"

// Applying inline arrow functions
$boldName = $name->apply(fn($val) => "<b>$name</b>"); // returns "<b>John Doe</b>" 
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
\$req = SmartArray::new(\$_REQUEST)->withSmartStrings();  // SmartArray of SmartStrings

Automatic HTML-encoding in string contexts:
echo \$str;             // "It&apos;s easy!&lt;hr&gt;"

// ... continues with a list of available methods and examples
```

## Customizing Defaults

You can customize the defaults by adding the following to the top of your script or in an init file:

```php
SmartString::$treatNullAsZero       = true;            // Treat null as zero in numeric operations (default: true)
SmartString::$numberFormatDecimal   = '.';             // Default decimal separator
SmartString::$numberFormatThousands = ',';             // Default thousands separator
SmartString::$dateFormat            = 'Y-m-d';         // Default dateFormat() format
SmartString::$dateTimeFormat        = 'Y-m-d H:i:s';   // Default dateTimeFormat() format
SmartString::$phoneFormat           = [                // Default phoneFormat() formats
    ['digits' => 10, 'format' => '(###) ###-####'],
    ['digits' => 11, 'format' => '# (###) ###-####'],
];  
```

The `$treatNullAsZero` setting controls how null values are handled in numeric operations:

- When `true` (default): null values are treated as 0, making calculations more intuitive
- When `false`: operations with null values return null results, useful for detecting uninitialized values or using ->
  or()

## Method Reference

In addition to the methods below, you can customize the defaults by adding the following to the top of your script
or in an init file:

|                               **Basic Usage** |                                                                                                                                         |
|----------------------------------------------:|-----------------------------------------------------------------------------------------------------------------------------------------|
|                    `SmartString::new($value)` | Creates a new SmartString object from a single value                                                                                    |
| `SmartArray::new($array)->withSmartStrings()` | Creates a new SmartArray from a regular PHP array. All nested arrays and values are converted to SmartArray and SmartString objects     |
|               `SmartArray::new($array, true)` | Shortcut syntax to create a new SmartArray with SmartStrings from a regular PHP array                                                   |
|                                   `->value()` | Returns the original, unencoded value                                                                                                   |
|                           **Type Conversion** |                                                                                                                                         |
|                                     `->int()` | Returns the value as an integer                                                                                                         |
|                                   `->float()` | Returns the value as a float                                                                                                            |
|                                    `->bool()` | Returns the value as a boolean                                                                                                          |
|                                  `->string()` | Returns the value as a string (original value, not HTML-encoded)                                                                        |
|                  `SmartString::getRawValue()` | Returns original value from Smart* objects while leaving other types unchanged. Useful when working with mixed object/non-object values |
|                          **Encoding Methods** |                                                                                                                                         |
|                              `->htmlEncode()` | Returns HTML-encoded string                                                                                                             |
|                               `->urlEncode()` | Returns URL-encoded string                                                                                                              |
|                              `->jsonEncode()` | Returns JSON-encoded string                                                                                                             |
|                                 `->rawHtml()` | Alias for `value()`, useful for readability when outputting trusted HTML content                                                        |
|                       **String Manipulation** |                                                                                                                                         |
|                                `->textOnly()` | Removes HTML tags from the string, decodes entities, and trims whitespace                                                               |
|                                   `->nl2br()` | Converts newlines to HTML line breaks                                                                                                   |
|                                    `->trim()` | Trims whitespace or specified characters from the string                                                                                |
|           `maxWords($max, $ellipsis = '...')` | Limits the string to a specific number of words                                                                                         |
|           `maxChars($max, $ellipsis = '...')` | Limits the string to a specific number of characters                                                                                    |
|                                **Formatting** |                                                                                                                                         |
|             `->dateFormat($format = default)` | Formats the value as a date, using default or specified date format                                                                     |
|         `->dateTimeFormat($format = default)` | Formats the value as a date and time, using default or specified date format                                                            |
|               `->numberFormat($decimals = 0)` | Formats the value as a number                                                                                                           |
|                             `->phoneFormat()` | Formats the value as a phone number                                                                                                     |
|                        **Numeric Operations** |                                                                                                                                         |
|                    `->percent($decimals = 0)` | Converts value to percentage                                                                                                            |
|          `->percentOf($total, $decimals = 0)` | Calculates what percentage this number represents of $total                                                                             |
|                               `->add($value)` | Adds $value to current number                                                                                                           |
|                          `->subtract($value)` | Subtracts $value from current number                                                                                                    |
|                          `->multiply($value)` | Multiplies current number by $value                                                                                                     |
|                          `->divide($divisor)` | Divides current number by $divisor                                                                                                      |
|                    **Conditional Operations** |                                                                                                                                         |
|                             `->or($fallback)` | Returns the fallback if the value is missing ("", null), zero is not considered missing                                                 |
|                               `->and($value)` | Appends $value if the value is present (not "" or null), zero is considered present                                                     |
|                         `->andPrefix($value)` | Prepends $value if the value is present (not "" or null), zero is considered present                                                    |
|                         `->ifNull($fallback)` | Returns the fallback if the value is null                                                                                               |
|                        `->ifBlank($fallback)` | Returns the fallback if the value is an empty string                                                                                    |
|                         `->ifZero($fallback)` | Returns the fallback if the value is zero                                                                                               |
|                                **Validation** |                                                                                                                                         |
|                                 `->isEmpty()` | Returns true if the value is empty ("", null, false, 0, "0"), uses PHP empty()                                                          |
|                              `->isNotEmpty()` | Returns true if the value is NOT empty ("", null, false, 0, "0"), uses PHP !empty()                                                     |
|                               `->isMissing()` | Returns true if the value is missing (null or ""), zero is not considered missing                                                       |
|                                  `->isNull()` | Returns true if the value is null                                                                                                       |
|                            **Error Checking** |                                                                                                                                         |
|                           `->orDie($message)` | Outputs message and exits if the value is missing ("", null), zero is not considered missing                                            |                                                                 
|                           `->or404($message)` | Outputs 404 header, message and exits if the value is missing ("", null), zero is not considered missing                                |
|                         `->orThrow($message)` | Throws Exception with message if the value is missing ("", null), zero is not considered missing                                        |
|                             **Miscellaneous** |                                                                                                                                         |
|                    `->apply($func, ...$args)` | Applies a custom function to the value                                                                                                  |
|                                    `->help()` | Displays help information about available methods                                                                                       |

**See Also:** For array operations, check out our companion library `SmartArray`,
a powerful companion library that provides array operations with chainable methods and seamless `SmartString`
integration.
SmartString and SmartArray are designed to work together as a pair for enhanced data handling:
https://github.com/interactivetools-com/SmartArray?tab=readme-ov-file#method-reference

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
