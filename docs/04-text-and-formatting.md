# Text and Formatting

This page covers the methods you use most often: stripping HTML,
limiting text, converting line breaks, formatting dates and
numbers, and applying custom transformations. Every method returns
a new SmartString (or a plain string where noted), so the result
is always safe to echo.

## Stripping HTML — `textOnly()`

`textOnly()` returns a SmartString with all HTML tags stripped and
entities decoded. The order matters: it calls
`html_entity_decode()` first, then `strip_tags()`, then `trim()`.
This prevents encoded tags like `&lt;script&gt;` from surviving
as literal text.

```php
$str = SmartString::new("<p>Hello <b>World</b></p>");
echo $str->textOnly(); // Hello World

$str = SmartString::new("It's <b>easy</b> &amp; fun!");
echo $str->textOnly(); // It's easy & fun!

// Null passes through, so you can chain with or()
$str = SmartString::new(null);
echo $str->textOnly()->or("No content"); // No content
```

You will often chain `textOnly()` with truncation methods to
create safe text previews of HTML content:

```php
echo "{$article->content->textOnly()->maxChars(120)}";
```

## Limiting Text — `maxWords()` and `maxChars()`

`maxWords()` returns a SmartString truncated to a maximum number
of words. It takes two arguments: the word limit and an optional
suffix (defaults to `'...'`):

```php
$str = SmartString::new("The quick brown fox jumps over the lazy dog");

echo $str->maxWords(4);            // The quick brown fox...
echo $str->maxWords(4, ' [more]'); // The quick brown fox [more]
echo $str->maxWords(100);          // The quick brown fox jumps over the lazy dog
```

`maxChars()` returns a SmartString truncated to a maximum number
of characters, breaking at the last whole word boundary:

```php
$str = SmartString::new("The quick brown fox jumps over the lazy dog");

echo $str->maxChars(19);        // The quick brown...
echo $str->maxChars(19, ' --'); // The quick brown --
echo $str->maxChars(200);       // The quick brown fox jumps over the lazy dog
```

Both methods strip trailing punctuation before appending the
suffix, and skip the suffix entirely when the text fits within
the limit.

**Warning:** Always call `textOnly()` before `maxWords()` or
`maxChars()` on HTML content. Truncating raw HTML can leave
broken tags in your output.

```php
// Safe: strip tags first, then truncate
echo "{$article->content->textOnly()->maxChars(200)}";
```

## Converting Text to HTML — `textToHtml()`

`textToHtml()` returns a plain string (not a SmartString) with
special characters encoded and newlines converted to `<br>` tags
in one step. Because the result is already encoded, it bypasses
automatic encoding when you echo it. This method is new in v2.6.0
and replaces the deprecated `nl2br()`.

```php
$str = SmartString::new("Line one\nLine two\nLine three");
echo $str->textToHtml();
// Line one<br>
// Line two<br>
// Line three
```

Special characters are encoded internally, so the output is safe
even with untrusted input:

```php
$str = SmartString::new("Bold & <script>alert('xss')</script>");
echo $str->textToHtml();
// Bold &amp; &lt;script&gt;alert(&apos;xss&apos;)&lt;/script&gt;
```

Use `keepBr: true` when your text already contains `<br>` tags --
for example, CMS text fields that store line breaks as HTML:

```php
$str = SmartString::new("Line one<br>Line two<br />Line three");
echo $str->textToHtml(keepBr: true);
// Line one<br>Line two<br />Line three
```

## Trimming Whitespace — `trim()`

`trim()` returns a SmartString with leading and trailing
whitespace removed, just like PHP's built-in `trim()`:

```php
$str = SmartString::new("  Jean O'Brien  ");
echo $str->trim(); // Jean O&apos;Brien

// Pass custom characters to strip
$str = SmartString::new("...Hello...");
echo $str->trim('.'); // Hello
```

## Formatting Dates — `dateFormat()` and `dateTimeFormat()`

`dateFormat()` returns a SmartString with the date formatted
using `SmartString::$dateFormat` (default `'Y-m-d'`).
`dateTimeFormat()` uses `SmartString::$dateTimeFormat` (default
`'Y-m-d H:i:s'`).

```php
$str = SmartString::new("2024-09-10 14:30:00");

echo $str->dateFormat();     // 2024-09-10
echo $str->dateTimeFormat(); // 2024-09-10 14:30:00
```

Configure the defaults once in your bootstrap file, or pass a
format string to override for a single call:

```php
SmartString::$dateFormat     = 'F jS, Y';
SmartString::$dateTimeFormat = 'M jS, Y g:ia';

$str = SmartString::new("2024-09-10 14:30:00");
echo $str->dateFormat();                     // September 10th, 2024
echo $str->dateTimeFormat();                 // Sep 10th, 2024 2:30pm
echo $str->dateFormat('D, M j');             // Tue, Sep 10
echo $str->dateTimeFormat('g:ia \o\n M jS'); // 2:30pm on Sep 10th
```

Invalid dates return null, so you can chain with `or()`:

```php
$str = SmartString::new("not-a-date");
echo $str->dateFormat()->or("Invalid date"); // Invalid date
```

Both methods accept numeric timestamps as well as any string
recognized by PHP's `strtotime()`. See
[PHP date format characters](https://www.php.net/manual/en/datetime.format.php)
for the full list of format tokens.

## Formatting Numbers — `numberFormat()`

`numberFormat()` returns a SmartString with the number formatted
using PHP's `number_format()`. Pass the number of decimal places
as the first argument (defaults to `0`):

```php
$num = SmartString::new(1234567.89);

echo $num->numberFormat();  // 1,234,568
echo $num->numberFormat(2); // 1,234,567.89
```

The default separators are `'.'` for decimals and `','` for
thousands. Configure locale-specific separators with the static
properties:

```php
SmartString::$numberFormatDecimal   = ',';
SmartString::$numberFormatThousands = '.';

$num = SmartString::new(1234567.89);
echo $num->numberFormat(2); // 1.234.567,89

// Non-numeric values return null
$str = SmartString::new("abc");
echo $str->numberFormat(2)->or("N/A"); // N/A
```

## Formatting Phone Numbers — `phoneFormat()`

`phoneFormat()` returns a SmartString formatted according to
`SmartString::$phoneFormat` rules. All non-digit characters are
stripped before matching:

```php
$phone = SmartString::new("2345678901");
echo $phone->phoneFormat(); // (234) 567-8901
```

The default rules match 10-digit and 11-digit numbers. Add
custom rules for other formats:

```php
SmartString::$phoneFormat = [
    ['digits' => 7,  'format' => '###-####'],
    ['digits' => 10, 'format' => '(###) ###-####'],
    ['digits' => 11, 'format' => '# (###) ###-####'],
];

$phone = SmartString::new("5551234");
echo $phone->phoneFormat(); // 555-1234
```

When the digit count does not match any rule, `phoneFormat()`
returns null. Chain with `or()` to fall back to the original
value:

```php
$phone = SmartString::new("12345");
echo $phone->phoneFormat()->or($phone); // 12345
```

## Custom Functions — `apply()`

`apply()` returns a SmartString with the result of calling a
function on the raw value. Your callback receives the raw
(unencoded) value as its first argument and must return a scalar
value (string, int, float, bool, or null):

```php
$name = SmartString::new("Jean O'Brien");

// Built-in PHP functions
echo $name->apply('strtoupper');          // JEAN O&apos;BRIEN
echo $name->apply('ucwords');             // Jean O&apos;Brien

// Arrow functions
echo $name->apply(fn($v) => strrev($v)); // neirB&apos;O naeJ

// Extra arguments are passed after the function name
$id = SmartString::new("42");
echo $id->apply('str_pad', 6, '0', STR_PAD_LEFT); // 000042
```

If the callback is not callable, `apply()` throws an
`InvalidArgumentException`.

## Putting It Together

Here is a realistic template combining several methods from this
page. **Every value is auto-encoded — you never call
`htmlspecialchars()` anywhere in this code.**

```php
$title   = SmartString::new("New <b>Findings</b> in Science");
$content = SmartString::new("<p>Hello <b>World</b></p> and more text here that keeps going");
$date    = SmartString::new("2024-09-10 14:30:00");
$price   = SmartString::new(1234567.89);
$views   = SmartString::new(48521);

echo <<<__HTML__
    <article>
        <h2>{$title->textOnly()}</h2>
        <time>{$date->dateFormat('M jS, Y')}</time>
        <p>{$content->textOnly()->maxChars(40)}</p>
        <span class="price">\${$price->numberFormat(2)}</span>
        <span class="views">{$views->numberFormat()} views</span>
    </article>
    __HTML__;
```

Output:

```
<article>
    <h2>New Findings in Science</h2>
    <time>Sep 10th, 2024</time>
    <p>Hello World and more text here that...</p>
    <span class="price">$1,234,567.89</span>
    <span class="views">48,521 views</span>
</article>
```

- `textOnly()` strips the `<b>` tags from the title for a clean
  heading
- `dateFormat('M jS, Y')` overrides the default date format
- `textOnly()->maxChars(40)` strips HTML then truncates to 40
  characters
- `numberFormat(2)` formats the price with two decimal places
- `numberFormat()` adds thousands separators to the view count

---

[← Back to README](../README.md) | [← Values & Encoding](03-values-and-encoding.md) | [Next: Conditionals & Error Handling →](05-conditionals-and-error-handling.md)
