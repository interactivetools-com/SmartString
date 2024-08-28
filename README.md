# SmartString: PHP String Manipulation Library

## Overview

SmartString is a powerful library that simplifies string manipulation while prioritizing web security.
It offers a fluent, chainable interface for expressive code and provides automatic HTML encoding to
protect against XSS attacks. Designed to streamline common tasks and support complex operations,
SmartString enables you to write clearer, more concise, secure code.

## Quick Start

Install via Composer: `composer require itools/smartstring` and then require the Composer autoloader:

```php
require 'vendor/autoload.php'; // load Composer autoloader
use Itools\SmartString\SmartString;

// Use new() to convert your array
$dbRecord = ['id' => 68, 'name' => "John O'Reilly", "lastLogin" => "2024-08-27 11:56:22"];  // mock record for testing
$user    = SmartString::new($dbRecord); // $user is now an ArrayObject of SmartStrings

// Automatic HTML encoding in string contexts
echo "Hello, $user->name!\n";                                      // Hello, John O&apos;Reilly
echo "Last login: {$user->lastLogin->dateFormat('M jS, Y')}}\n"; // Aug 27th, 2024

// Access values when needed
echo $user->name->value(); // John O'Reilly
```

Read on for more details and examples.

## Features

### Automatic XSS Protection

<div style="margin-left: 30px;">
SmartString prioritizes web security by automatically HTML-encoding output by default. This greatly simplifies your
code and helps prevent Cross-Site Scripting (XSS) vulnerabilities.

Here's an example of a simple email form that shows the last value submitted:

```php
// Old way: Remembering to manually encode everything and typing a lot of code 
$html .= "<input type='text' name='name' value='" . htmlspecialchars($_REQUEST['name'], ENT_QUOTES|ENT_HTML5, 'UTF-8', false); . "'>";
$html .= "<input type='text' name='email' value='" . htmlspecialchars($_REQUEST['name'], ENT_QUOTES|ENT_HTML5, 'UTF-8', false); . "'>";

// New Way: HTML-encoding is the default with SmartString
$req = SmartString::new($_REQUEST);  // Create a copy of $_REQUEST as an object with all values encoded as SmartStrings
$html .= "<input type='text' name='name' value='$req->name'>";
$html .= "<input type='text' name='name' value='$req->email'>";
```

SmartString objects automatically return HTML-encoded output in any string contexts, such as the following:

```php
// String contexts
$name = SmartString::new($_REQUEST['name']);  // e.g., O'Reilly & Sons
echo $name;                                   // returns "O&apos;Reilly &amp;amp; Sons"
print $name;                                  // returns "O&apos;Reilly &amp;amp; Sons"
$encoded = (string) $name;                    // returns "O&apos;Reilly &amp;amp; Sons"
$greeting = "Hello, $name";                   // returns "Hello, O&apos;Reilly &amp;amp; Sons"
$greeting = "Hello, " . $name;                // returns "Hello, O&apos;Reilly &amp;amp; Sons"
```

But you can always access the original value with the `value()` method:

```php
// accessing the value directly
$value = $name->value();         // returns "O'Reilly & Sons"

// outputting the value in a string, noEncode() is an alias for value()
echo "Hello, {$name->noEncode()}";  // returns "Hello, O'Reilly & Sons"
```

</div>

### Fluent Chainable Interface

<div style="margin-left: 30px;">

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
```
<!-- Future
// Combining multiple operations to create a text summary
echo "Summary: {$article->content->stripTags()->maxChars(200, '...')}";
-->

The fluent interface allows you to build complex transformations step-by-step, making your code more intuitive
and easier to maintain.
</div>

### Flexible Encoding

<div style="margin-left: 30px;">

While SmartString automatically HTML-encodes output in string contexts, it also provides methods
for explicit encoding in different scenarios:

```php
// HTML Encoding - this is the default, but you can call it explicitly when you need encoded output in non-string contexts
$htmlSafe = $smartString->htmlEncode();

// URL Encoding - use this when you need to prepare strings for URL parameters
$promoTitle = SmartString::new("Save 10%+ off");
$promoUrl   = "/promo.php?title={$promoTitle->urlEncode()}"; // Outputs Save+10%25%2B+off

// JavaScript Encoding - for outputting content within JavaScript strings
$message    = SmartString::new("O\'Reilly said \"Hello\"");
echo "<script>var message = {$message->jsonEncode()};</script>"; // Outputs: <script>alert('O\'Reilly said \"Hello\"');</script>
```

</div>

### Non-String Support

<div style="margin-left: 30px;">

SmartString supports non-string values, allowing you to store and manipulate any type of data (int, float, bool,
or null). You can also cast SmartStrings to these types using terminal methods.

```php
$user->id = SmartString::new(1234);  // Store an integer

// cast to different types as needed
$user->id->value();     // returns original value: 1234 (int)
$user->id->string();    // returns value as string: "1234"
$user->id->int();       // returns value as int: 1234
$user->id->float();     // returns value as float: 1234.0
$user->id->bool();      // returns value as bool: true
```

</div>

### Custom Functions

<div style="margin-left: 30px;">

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
$spacesToUnderscores = function($str) { return str_replace(' ', '_', $str); }); // anonymous function
$spacesToUnderscores = fn($str) => str_replace(' ', '_', $str);                 // arrow function
$urlSlug = $name->apply($spacesToUnderscores);   // returns "John_Doe"

// Applying inline arrow functions
$boldName = $name->apply(fn($val) => "<b>$name</b>"); // returns "<b>John Doe</b>" 
```

</div>

### Inline Developer Help

<div style="margin-left: 30px;">

When you call print_r() or var_dump() on a SmartString object, it will display the original value and
some help text listing the available methods and properties.

```php
print_r($smartString); // Outputs the original value and help text
```

Outputs

```
// Output:
Itools\SmartString\SmartString Object ( [__DEVELOPERS__] =>
        This 'SmartString' object automatically HTML-encodes output in string contexts for XSS protection.
        It also provides access to the original value, alternative encoding methods, and various utility methods.
        
        Basic Usage:
        $name                 = Itools\SmartString\SmartString Object // Field object itself
        $name->value()        = O'Reilly &amp; Sons                   // Access original value
        "{$name->noEncode()}" = O'Reilly &amp; Sons                   // Output original value in string context
        "$name"               = O&apos;Reilly &amp;amp; Sons          // HTML-encoded in string contexts: "$f", $f."", echo $f, print $f, (string)$f
        
        Value retrieval and encoding (returns value):
        ->value()               Original unencoded value
        ->noEncode()            Alias for ->value() for readability, example: "{$record->wysiwyg->noEncode()}"
        ->htmlEncode()          HTML-encoded string (for readability and non-string contexts)
        ->urlEncode()           URL-encoded string, example: "?user={$user->name->urlEncode()}"
        ->jsonEncode()          JSON-encoded, example: "let user={$user->name->jsonEncode()};"
        
        Type conversion (returns value):
        ->bool()                Value as boolean
        ->int()                 Value as integer
        ->float()               Value as float
        ->string()              Value as string (returns original value, use ->htmlEncode() for HTML-encoded string)
        
        String Manipulation (returns object, chainable):
        ->stripTags()           Remove HTML tags
        ->nl2br()               Convert newlines to br tags
        ->trim(...)             Trim whitespace (default $characters = " \n\r\t\v\0")
        
        Date Formatting (returns object, chainable):
        ->dateFormat($format)   Format date using PHP date() function syntax (e.g., "Y-m-d H:i:s")
        
        Date Formatting (returns object, chainable):
        ->numberFormat(...)     Format number ($number, $decimals)
        ->percent()             Returns value as a percentage, e.g. 0.5 becomes 50%
        ->percentOf($total)     Returns value as a percentage of $total, e.g., 24 of 100 becomes 24%
        ->subtract($value)      Returns value minus $value
        ->divide($value)        Returns value divided by $value
                                
        Miscellaneous:
        ->or('new value')       Changes value if the Field is falsey (false, null, 0, or "")
        ->ifBlank('new value')  Changes value if the Field is blank (empty string)
        ->ifNull('new value')   Changes value if the Field is null or undefined (chainable)
        ->apply()               Apply a callback or function to the value, e.g. ->apply('strtoupper')
        ->help()                Output this help text
        
        Field Value:
        "O'Reilly &amp; Sons"
)
```

</div>

## Method Reference

|                **Basic Usage** |                                                                                                                                       |
|-------------------------------:|---------------------------------------------------------------------------------------------------------------------------------------|
|      SmartString::new(\$value) | Creates a new SmartString object from a single value (string, int, float, bool, null) or an ArrayObject of SmartStrings from an array |
|                        value() | Returns the original, unencoded value of any type (string, int, float, bool, null)                                                    |
|            **Type Conversion** |                                                                                                                                       |
|                          int() | Returns the value as an integer                                                                                                       |
|                        float() | Returns the value as a float                                                                                                          |
|                         bool() | Returns the value as a boolean                                                                                                        |
|                       string() | Returns the value as a string (original value, not HTML-encoded)                                                                      |
|           **Encoding Methods** |                                                                                                                                       |
|                   htmlEncode() | Returns HTML-encoded string                                                                                                           |
|                    urlEncode() | Returns URL-encoded string                                                                                                            |
|                   jsonEncode() | Returns JSON-encoded string                                                                                                           |
|                     noEncode() | Alias for value(), useful for readability in string contexts                                                                          |
|        **String Manipulation** |                                                                                                                                       |
|                 stripTags(...) | Removes HTML tags from the string with PHP strip_tags() function.<br>Optional arguments: $allowed_tags                                |
|                        nl2br() | Converts newlines to HTML line breaks with PHP nl2br() function                                                                       |
|                      trim(...) | Trims whitespace or specified characters from the string with PHP trim() function.<br>Optional arguments: $characters                 |
|                 **Formatting** |                                                                                                                                       |
|           dateFormat(\$format) | Formats the value as a date with PHP date() function.<br>Arguments: $format (e.g., "Y-m-d H:i:s")                                     |
|        numberFormat(...\$args) | Formats the value as a number with specified decimals and separators                                                                  |
|       **Numerical Operations** |                                                                                                                                       |
|            percent(\$decimals) | Converts the value to a percentage                                                                                                    |
| percentOf(\$total, \$decimals) | Calculates the percentage of the value relative to the given total                                                                    |
|              subtract(\$value) | Subtracts the given value from the current value                                                                                      |
|              divide(\$divisor) | Divides the current value by the given divisor                                                                                        |
|                **Conditional** |                                                                                                                                       |
|                    or(\$value) | Returns the alternative value if the current value is falsy                                                                           |
|                ifNull(\$value) | Returns the alternative value if the current value is null                                                                            |
|               ifBlank(\$value) | Returns the alternative value if the current value is an empty string                                                                 |
|              **Miscellaneous** |                                                                                                                                       |
|       apply(\$func, ...\$args) | Applies a custom function to the value                                                                                                |
|                         help() | Displays help information about available methods                                                                                     |

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)
