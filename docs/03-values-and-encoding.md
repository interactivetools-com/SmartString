# Values and Encoding

SmartString preserves your original data and provides multiple ways
to get it back: raw for logic and storage, HTML-encoded for safe
output, URL-encoded for query parameters, and JSON-encoded for
JavaScript embedding.

## Creating SmartStrings — `SmartString::new()`

`SmartString::new()` returns a SmartString that wraps a single
scalar or null value. Pass any string, int, float, bool, or null:

```php
$str   = SmartString::new("It's <b>easy</b> & fun!");
$num   = SmartString::new(1234567.89);
$flag  = SmartString::new(true);
$empty = SmartString::new(null);
```

Passing an array to `SmartString::new()` is deprecated. Use
[SmartArray](https://github.com/interactivetools-com/SmartArray)
for working with collections.

## Accessing the Raw Value — `value()`

`value()` returns the original value with its PHP type preserved.
What goes in is exactly what comes out — no encoding, no casting:

```php
$str   = SmartString::new("It's <b>easy</b> & fun!");
$num   = SmartString::new(1234567.89);
$flag  = SmartString::new(true);
$empty = SmartString::new(null);

$str->value();   // "It's <b>easy</b> & fun!" (string)
$num->value();   // 1234567.89 (float)
$flag->value();  // true (bool)
$empty->value(); // null (null)
```

Use `value()` when you need the raw data for database queries,
math, comparisons, or functions that expect plain strings:

```php
$price = SmartString::new(1234567.89);
$db->query("UPDATE products SET price = ?", [$price->value()]);
$total = $price->value() * 1.13;
if ($price->value() > 1000) { /* ... */ }

$name = SmartString::new("Jean O'Brien");
str_contains($name->value(), "'"); // true
```

**The raw value is for logic. The auto-encoded output is for
HTML.** You never need to think about encoding when you echo, and
you never get encoded data where you don't want it.

## Outputting Trusted HTML — `rawHtml()`

`rawHtml()` returns the raw value, just like `value()`. The
difference is intent — it signals to other developers that you
are deliberately outputting unencoded HTML.

Use it for trusted content like WYSIWYG editor fields:

```php
$title   = SmartString::new("Jean O'Brien's Article");
$content = SmartString::new("<p>Trusted <b>HTML</b> from editor</p>");

// Title auto-encodes, content outputs raw
echo "<h1>$title</h1>";                  // encoded
echo "<div>{$content->rawHtml()}</div>"; // raw HTML
```

**Warning:** Never pass user input directly to `rawHtml()`. It
bypasses all encoding.

## Type Conversion — `int()`, `float()`, `bool()`, `string()`

These are terminal methods that return plain PHP types. You
cannot chain after them:

```php
$val = SmartString::new("123.45");

$val->int();    // 123 (int)
$val->float();  // 123.45 (float)
$val->bool();   // true (bool)
$val->string(); // "123.45" (string, NOT html-encoded)
```

`string()` is not the same as `echo`. It returns the raw string
without HTML encoding, just like `value()` but always cast to
string:

```php
$str = SmartString::new("It's <b>easy</b> & fun!");

echo $str;           // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!
echo $str->string(); // It's <b>easy</b> & fun!
echo $str->value();  // It's <b>easy</b> & fun!
```

## Encoding Methods

SmartString provides three encoding methods for different output
contexts. In most cases you don't need these — `echo` handles
HTML encoding automatically. These methods exist for the cases
where you need explicit control.

| Method         | Returns             | Use case               |
|----------------|---------------------|------------------------|
| `htmlEncode()` | HTML-encoded string | Explicit HTML encoding |
| `urlEncode()`  | URL-encoded string  | Query parameters       |
| `jsonEncode()` | JSON-encoded string | JavaScript embedding   |

### HTML Encoding — `htmlEncode()`

`htmlEncode()` returns the same HTML-encoded output as `echo`,
but as an explicit method call. It uses `htmlspecialchars()` with
`ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5` and
UTF-8:

```php
$str = SmartString::new("It's <b>easy</b> & fun!");

echo $str->htmlEncode(); // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!
echo $str;               // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun! (same)
```

You rarely need `htmlEncode()` directly because `echo` already
does the same thing. It exists for cases where you need an
explicit string return value rather than relying on `__toString()`.

### URL Encoding — `urlEncode()`

`urlEncode()` returns a string safe for use in URL query
parameters. It uses PHP's `urlencode()`:

```php
$name = SmartString::new("Jean O'Brien");
echo "?name=" . $name->urlEncode();
// ?name=Jean+O%27Brien

$query = SmartString::new("It's <b>easy</b> & fun!");
echo "?q=" . $query->urlEncode();
// ?q=It%27s+%3Cb%3Eeasy%3C%2Fb%3E+%26+fun%21
```

### JSON Encoding — `jsonEncode()`

`jsonEncode()` returns a value safe for embedding in JavaScript.
It escapes HTML-sensitive characters to prevent XSS when
embedding data in `<script>` tags.

The flags used are: `JSON_THROW_ON_ERROR | JSON_HEX_TAG |
JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP |
JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.

```php
$str = SmartString::new("It's <b>easy</b> & fun!");
echo $str->jsonEncode();
// "It\u0027s \u003Cb\u003Eeasy\u003C/b\u003E \u0026 fun!"
```

Use it when embedding values in inline JavaScript:

```php
$title = SmartString::new($record['title']);
echo "<script>var title = {$title->jsonEncode()};</script>";
```

## Extracting Values from Mixed Types — `SmartString::getRawValue()`

`getRawValue()` is a static method that unwraps Smart* objects
while leaving plain values unchanged. Use it in library code or
utility functions that accept both wrapped and unwrapped values:

```php
// Unwraps SmartString to its raw value
SmartString::getRawValue(SmartString::new("hello")); // "hello"

// SmartNull becomes null
SmartString::getRawValue($smartNull); // null

// Scalars, arrays, and null pass through unchanged
SmartString::getRawValue("hello"); // "hello"
SmartString::getRawValue(42);      // 42
SmartString::getRawValue(null);    // null
SmartString::getRawValue([1, 2]);  // [1, 2]
```

A typical use case is a function that does not know whether it
will receive a SmartString or a plain value:

```php
function saveToDatabase(mixed $value): void {
    $raw = SmartString::getRawValue($value);
    $db->query("INSERT INTO log (value) VALUES (?)", [$raw]);
}
```

---

[← Back to README](../README.md) | [← Philosophy & Safety](02-philosophy-and-safety.md) | [Next: Text & Formatting →](04-text-and-formatting.md)
