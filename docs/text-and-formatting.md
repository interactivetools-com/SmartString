<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Text and Formatting

With encoding handled for you, the rest of template work is formatting:
trim this, shorten that, make the date readable. This page covers those
transformations: stripping HTML, truncating, regex replacement, dates,
numbers, percentages, math, and custom functions via `map()`. Every method
returns a new SmartString, so you can keep chaining and the result is
always safe to echo.

Two group contracts to know up front:

- **String manipulation** (`textOnly`, `trim`, `maxWords`, `maxChars`,
  `pregReplace`, `append`, `prepend`, `wrap`): missing values (null or `""`)
  come through unchanged, so a later `or()` fallback still works.
- **Dates, numbers, and math** (`dateFormat`, `numberFormat`, `percent`,
  `percentOf`, `add`, `subtract`, `multiply`, `divide`): missing or invalid
  input makes the result null; add `or()` after to show a fallback.

## Stripping HTML - `textOnly()`

Removes HTML tags, decodes entities, and trims whitespace. Use it whenever
you need the text of an HTML field: previews, meta descriptions, plain-text
email:

```php
$content = SmartString::new("<p>Hello <b>World</b></p>");
echo $content->textOnly();  // Hello World
```

The order matters: entities are decoded first, then tags are stripped, so an
encoded `&lt;script&gt;` can't survive as a literal tag in the text.

## Trimming - `trim()`

Removes leading and trailing whitespace, or the characters you pass, with
the same parameters as PHP's `trim()`:

```php
echo SmartString::new("  Trim me  ")->trim();     // Trim me
echo SmartString::new("...Hello...")->trim('.');  // Hello
```

## Truncating - `maxWords()` and `maxChars()`

Both methods limit length, and if text gets cut off they add an ellipsis
(`...` by default, or pass your own as the second parameter):

```php
$text = SmartString::new("The quick brown fox jumps over the lazy dog");

// maxWords($max, $ellipsis = '...')
echo $text->maxWords(4);             // The quick brown fox...
echo $text->maxWords(4, ' [more]');  // The quick brown fox [more]

// maxChars($max, $ellipsis = '...')
echo $text->maxChars(19);            // The quick brown fox...
echo $text->maxChars(200);           // The quick brown fox jumps over the lazy dog
```

With `maxChars($max)` the cut lands at the last whole word within the
limit, and both methods strip trailing punctuation before adding the
ellipsis (so you never get "Hello,...").

Truncate text, not HTML: cutting raw HTML can leave broken tags in your
output. Chain `textOnly()` first:

```php
echo $article->content->textOnly()->maxChars(120);  // safe text preview
```

## Adding Text Around Values - `append()`, `prepend()`, and `wrap()`

These add text around the value, but only when a value is present (not null
or `""`); missing values pass through unchanged, so no stray labels or
separators appear. Zero counts as present:

```php
// append($value), prepend($value), wrap($before, $after)
echo $office->city->append(', ');            // "Vancouver, ", or "" when missing
echo $user->phone->prepend('Phone: ');       // "Phone: (604) 555-1234", or "" when missing
echo $user->extension->wrap('(ext. ', ')');  // "(ext. 204)", or "" when missing
```

Both sides of `wrap()` are required; pass `""` for a side you don't want.
And because missing values pass through unchanged, a later `or()` still
works:

```php
echo $user->extension->wrap('(ext. ', ')')->or('(no extension)');  // "(ext. 204)", or "(no extension)" when missing
```

The added text is a value like any other, so it HTML-encodes on output: a
`<br>` passed to `append()` prints as visible text, not a line break. To
add real markup, use
[appendHtml() and wrapHtml()](encoding-and-html.md#adding-markup-around-values---appendhtml-and-wraphtml)
instead.

## Regex Replacement - `pregReplace()`

Applies `preg_replace()` to the value: strip characters out, reformat with
capture groups, or clean a value up before using it:

```php
// pregReplace($pattern, $replacement)
echo $office->phone->pregReplace('/\D/', '');                            // "(604) 555-1234" → "6045551234" (for tel: links)
echo $user->phone->pregReplace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3'); // "6045551234" → "(604) 555-1234"
echo $row->amount->pregReplace('/[^0-9.]/', '')->add(100);               // "$1,234.56" → 1334.56 (strip formatting before math)
echo $comment->summary->pregReplace('/\s+/', ' ');                       // collapses runs of whitespace to single spaces
```

## Formatting Dates - `dateFormat()`

Formats the value as a date or datetime using PHP
[date() format characters](https://www.php.net/manual/en/datetime.format.php#refsect1-datetime.format-parameters).
With no argument it uses `SmartString::$dateFormat` (default `'Y-m-d'`);
pass a format to override per call:

```php
// dateFormat($format = null) - null uses SmartString::$dateFormat
$date = SmartString::new("2026-05-15 14:30:00");

echo $date->dateFormat();               // 2026-05-15
echo $date->dateFormat('M jS, Y');      // May 15th, 2026
echo $date->dateFormat('M j, Y g:ia');  // May 15, 2026 2:30pm
```

Set the default once in your init file and every plain `dateFormat()` call
uses it:

```php
SmartString::$dateFormat = 'F jS, Y';

echo $date->dateFormat();  // May 15th, 2026
```

There is one method for dates and datetimes; the format string decides what
shows. Numeric values are treated as unix timestamps; everything else is
parsed with `strtotime()`:

```php
echo SmartString::new(1684159800)->dateFormat('Y-m-d');  // 2023-05-15
```

Invalid dates return null rather than throwing, so a fallback chains
naturally:

```php
$invalid = SmartString::new("not a date");

echo $invalid->dateFormat()->or("Date not set");  // Date not set
echo $invalid->dateFormat()->or($invalid);        // not a date (show it unformatted)
```

## Formatting Numbers - `numberFormat()`

Formats the value with thousands separators and the decimal places you
specify (default 0). Non-numeric values, including already-formatted strings
like `"1,234"`, return null:

```php
// numberFormat($decimals = 0)
$number = SmartString::new(1234567.89);

echo $number->numberFormat();   // 1,234,568
echo $number->numberFormat(2);  // 1,234,567.89

echo SmartString::new("abc")->numberFormat(2)->or("N/A");  // N/A
```

The separators come from two static properties (defaults `'.'` and `','`).
Set them once for locale-specific output; `percent()` and `percentOf()` use
the same settings:

```php
SmartString::$numberFormatDecimal   = ',';
SmartString::$numberFormatThousands = ' ';

echo $number->numberFormat(2);  // 1 234 567,89
```

Like all formatters, the output is display text rather than a number, so
run math and zero checks before formatting; see
[Run Conditionals Before Formatting](conditionals-and-error-checking.md#run-conditionals-before-formatting).

## Percentages - `percent()` and `percentOf()`

Use `percent()` to display a stored ratio (0.75 means 75%) and
`percentOf($total)` to compute what share the value is of a total:

```php
// percent($decimals = 0, $ifZero = null)
echo SmartString::new(0.75)->percent();     // 75%
echo SmartString::new(0.1234)->percent(2);  // 12.34%

// percentOf($total, $decimals = 0)
echo SmartString::new(24)->percentOf(100);     // 24%
echo SmartString::new(24)->percentOf(200, 1);  // 12.0%

// or() catches failures: non-numeric input and division by zero return null
echo SmartString::new('abc')->percent()->or('N/A');  // N/A (not numeric)
echo SmartString::new(24)->percentOf(0)->or('N/A');  // N/A (division by zero)
```

Zero is different: it isn't a failure, it formats as `0.00%`. When zero
deserves other display ("N/A", "No data"), say so with the `$ifZero`
parameter:

```php
$conversionRate = SmartString::new(0);

echo $conversionRate->percent(2);                 // 0.00%
echo $conversionRate->percent(2, ifZero: "N/A");  // N/A
```

The zero rule is a parameter instead of a chained `->ifZero()` because by
the time `percent()` has run, zero has become the string `"0.00%"` and a
chained check can no longer recognize it. This is one instance of a general
rule: run conditionals before formatting (see
[Conditionals and Error Checking](conditionals-and-error-checking.md#run-conditionals-before-formatting)).

## Math - `add()`, `subtract()`, `multiply()`, `divide()`

Basic arithmetic for report columns and order totals, chainable and safe on
imperfect data. Arguments can be plain numbers or other SmartStrings; results
are floats:

```php
$price = SmartString::new(100);

echo $price->add(50);        // 150
echo $price->subtract(30);   // 70
echo $price->multiply(1.1);  // 110
echo $price->divide(4);      // 25

// chains run left to right: add tax, then split, then format
echo $price->multiply(1.13)->divide(2)->numberFormat(2);  // 56.50
```

### Null propagation and recovery

When either side of an operation is null or not numeric (`"cat"`, or
`"1,234"` since the comma makes it non-numeric to PHP), the result is null.
Division by zero also returns null. Null then flows through the rest of the
chain, so one fallback at the end covers every failure in it:

```php
$value = SmartString::new(null);

echo $value->add(50);                          // "" (null result, blank output)
echo $value->add(50)->multiply(2)->or('n/a');  // n/a (one or() covers the whole chain)
```

SmartString never silently coerces null to zero. When null should mean
zero, say so explicitly, before the math:

```php
echo $value->ifNull(0)->add(50);  // 50
```

A null result is a value like any other, so a mid-chain replacement fully
recovers the chain; nothing is poisoned:

```php
echo SmartString::new("cat")->add(10)->ifNull(0)->add(5);  // 5 (recovered mid-chain)
```

### Decimal precision

Computers store decimal numbers ("floats") in binary, and some decimals
have no exact binary form, the same way 1/3 has no exact decimal form
(0.333... forever). So the stored number can be a tiny bit off: ask for
`0.1 + 0.2` and the computer actually holds `0.30000000000000004`. Every
programming language works this way, not just PHP. Echo hides it by
rounding, but it can surface in comparisons and running totals. Use
`numberFormat()` to round for display:

```php
$val = SmartString::new(0.1);

echo $val->add(0.2);                   // 0.3 (PHP rounds for display)
echo $val->add(0.2)->numberFormat(2);  // 0.30 (explicit rounding)
```

## Custom Functions - `map()`

Need something there's no method for? Use `map()` to run any function on
the value: a PHP built-in or one of your own. Your function gets the raw
value, and whatever it returns becomes a new SmartString so you can keep
chaining:

```php
// map($func, ...$args)
$name = SmartString::new('John Doe');

echo $name->map('strtoupper');                         // JOHN DOE
echo $name->map(strtoupper(...));                      // JOHN DOE (first-class callable syntax, PHP 8.1+)
echo $name->map('str_pad', 15, '.');                   // John Doe....... (extra args pass through)
echo $name->map(fn($v) => str_replace(' ', '_', $v));  // John_Doe
```

The callback always runs and receives the raw value, null included, matching
`array_map()` and `SmartArray::map()`. PHP built-ins that require a string
will complain about null, so chain `ifNull('')` first when the value can be
missing:

```php
echo $user->nickname->ifNull('')->map('ucwords');
```

The callback must return a scalar or null; returning an array or object
throws, with the error reporting your file and line.

## Putting It Together

A product listing that combines methods from this page. Every value
auto-encodes; there is no `htmlspecialchars()` anywhere:

```php
$taxRate = 1.13;  // 13% sales tax
$product = SmartArrayHtml::new([
    'name'        => 'Widget & Sons Deluxe Kit',
    'description' => '<p>Our <b>best-selling</b> kit, now with more widgets.</p>',
    'price'       => 149.5,
    'updatedAt'   => '2026-09-10 14:30:00',
]);

echo <<<__HTML__
    <article>
        <h2>$product->name</h2>
        <time>{$product->updatedAt->dateFormat('M jS, Y')}</time>
        <p>{$product->description->textOnly()->maxChars(40)}</p>
        <span class="price">\${$product->price->multiply($taxRate)->numberFormat(2)} (tax included)</span>
    </article>
    __HTML__;
```

Output:

```
<article>
    <h2>Widget &​amp; Sons Deluxe Kit</h2>
    <time>Sep 10th, 2026</time>
    <p>Our best-selling kit, now with more...</p>
    <span class="price">$168.94 (tax included)</span>
</article>
```

- The `&` in the name encodes automatically in the heading
- `textOnly()->maxChars(40)` turns the HTML description into a text preview
- `multiply($taxRate)->numberFormat(2)` adds 13% tax and formats in one chain

---

[← Documentation Index](README.md) | [← Prev: Encoding and HTML](encoding-and-html.md) | [Next: Conditionals and Error Checking →](conditionals-and-error-checking.md)
