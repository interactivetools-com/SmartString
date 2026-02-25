# Getting Started

This guide walks you through installing SmartString, creating your
first safe output, and chaining methods for common formatting tasks.

## Requirements

- PHP ^8.1
- ext-mbstring

## Installation

```bash
composer require itools/smartstring
```

## Your First SmartString

Create a SmartString with `SmartString::new()`, then echo it. The
output is HTML-encoded automatically — you never call
`htmlspecialchars()` yourself.

```php
use Itools\SmartString\SmartString;

$name = SmartString::new("Jean O'Brien");

echo "Hello, $name!";
// Hello, Jean O&apos;Brien!
```

The single quote in "O'Brien" becomes `&apos;` in the output. This
happens every time a SmartString is used in a string context — `echo`,
`print`, string concatenation, and double-quoted interpolation all
trigger HTML encoding.

SmartString encodes with
`ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5`, covering
single quotes, double quotes, ampersands, angle brackets, and
malformed byte sequences.

## Your First value() Call

Use `value()` to retrieve the original value with its original type
(`string|int|float|bool|null`). This is for when you need the raw
data — database operations, comparisons, passing to other functions.

```php
$name = SmartString::new("Jean O'Brien");

echo $name->value();
// Jean O'Brien

echo $name;
// Jean O&apos;Brien
```

The alias `rawHtml()` does the same thing but reads better when you
are outputting trusted HTML content that should not be double-encoded:

```php
$html = SmartString::new('<em>Featured</em>');

echo $html->rawHtml();
// <em>Featured</em>

echo $html;
// &lt;em&gt;Featured&lt;/em&gt;
```

## Your First Chain

Every transformation method returns a new SmartString, so you chain
calls left to right. The result is always safe to echo.

```php
$name = SmartString::new("  Jean O'Brien  ");

// Trim whitespace
echo $name->trim();
// Jean O&apos;Brien

// Trim, then limit to one word
echo $name->trim()->maxWords(1);
// Jean...
```

Chains work inside double-quoted strings when wrapped in curly braces.
This is a PHP language requirement for method calls inside strings:

```php
$title   = SmartString::new("  New <b>Findings</b>  ");
$content = SmartString::new("<p>Hello <b>World</b></p> and more");
$date    = SmartString::new("2024-09-10 14:30:00");
$price   = SmartString::new(1234567.89);

// Strip HTML tags, then trim whitespace
echo "{$title->textOnly()}";
// New Findings

// Strip tags, then limit to 20 characters
echo "{$content->textOnly()->maxChars(20, '...')}";
// Hello World and...

// Format a date
echo "{$date->dateFormat('M jS, Y')}";
// Sep 10th, 2024

// Format a number with 2 decimal places
echo "{$price->numberFormat(2)}";
// 1,234,567.89
```

The output is HTML-safe at every step in the chain.

## Your First Fallback

Use `or()` to provide a default when a value is missing (null or
empty string). Zero is not considered missing — a price of $0.00
is real data, not an absent value.

```php
$name = SmartString::new(null);
echo "Hello, {$name->or('Guest')}!";
// Hello, Guest!

$price = SmartString::new(0);
echo $price->or("N/A");
// 0
```

Use `or404()` to return a 404 page when a database lookup comes back
empty. This is the most common fail-fast pattern:

```php
$article = DB::get('articles', ['num' => $articleNum]);
$article->num->or404();

// If we reach here, $article is valid
echo "<h1>$article->title</h1>";
```

For more conditional methods, see
[Conditionals and Error Handling](05-conditionals-and-error-handling.md).

## Working with SmartArray

In practice you rarely create SmartString objects one at a time.
Instead, you wrap an entire array or database result set through
[SmartArray](https://github.com/interactivetools-com/SmartArray),
which returns every value as a SmartString:

```php
use Itools\SmartArray\SmartArray;

$user = SmartArray::new([
    'name'      => "Jean O'Brien",
    'city'      => 'Vancouver',
    'lastLogin' => '2024-09-10 14:30:00',
])->asHtml();

echo "Hello, $user->name from $user->city!";
// Hello, Jean O&apos;Brien from Vancouver!

echo "Last login: {$user->lastLogin->dateFormat('F j, Y')}";
// Last login: September 10, 2024
```

With [ZenDB](https://github.com/interactivetools-com/ZenDB), this
happens automatically — every query returns SmartArrays of
SmartStrings:

```php
use Itools\ZenDB\DB;

$users = DB::select('users', ['status' => 'active']);
foreach ($users as $user) {
    echo "$user->name from $user->city\n";
}
```

## Configuring Defaults

SmartString has five static properties that control default
formatting. Set them once in your bootstrap file and they apply
to every SmartString for the rest of the request.

```php
use Itools\SmartString\SmartString;

// numberFormat() separators (defaults shown)
SmartString::$numberFormatDecimal   = '.';
SmartString::$numberFormatThousands = ',';

// dateFormat() and dateTimeFormat() defaults
SmartString::$dateFormat     = 'Y-m-d';
SmartString::$dateTimeFormat = 'Y-m-d H:i:s';

// phoneFormat() rules (defaults shown)
SmartString::$phoneFormat = [
    ['digits' => 10, 'format' => '(###) ###-####'],
    ['digits' => 11, 'format' => '# (###) ###-####'],
];
```

`$numberFormatDecimal` and `$numberFormatThousands` control
`numberFormat()` separators. European locales typically swap
these to `','` and `'.'`.

`$dateFormat` and `$dateTimeFormat` set the defaults for their
corresponding methods when called with no arguments.

`$phoneFormat` maps digit counts to format strings where `#` is
replaced by the next digit.

## Getting Help at Runtime

Call `print_r()` on any SmartString to see its stored value:

```php
$name = SmartString::new("Jean O'Brien");
print_r($name);
```

```
Itools\SmartString\SmartString Object
(
    [README:private] => "Call $obj->help() for more information and method examples."
    [rawData:private] => "Jean O'Brien"
)
```

Call `help()` on any SmartString instance for a full method
reference with examples:

```php
$name->help();         // prints method list and examples
```

The output includes every method with its return type and a
description. If you do not have an instance handy:

```php
SmartString::new(null)->help();
```

---

[← Back to README](../README.md) | [Next: Philosophy & Safety →](02-philosophy-and-safety.md)
