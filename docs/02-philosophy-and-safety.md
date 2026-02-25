# Philosophy and Safety

SmartString is built around a single idea: **the easiest way to output
data should also be the safest way.** This page explains how that works,
what it guarantees, and where its boundaries are.

## The XSS Problem

Every time you output user data in HTML, you need to call
`htmlspecialchars()`. Every value. Every time. Miss one, and an
attacker can inject `<script>` tags into your page.

The traditional approach looks like this:

```php
// Manual encoding -- verbose and error-prone
echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8');
echo htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8');
```

Across hundreds of templates, thousands of echo statements, someone
will forget. This is not a question of discipline or code review. It
is a question of scale. At scale, everything that can go wrong will
go wrong — and one missed call is all it takes.

SmartString eliminates the problem entirely:

```php
$name  = SmartString::new("Jean O'Brien");
$title = SmartString::new("It's <b>easy</b> & fun!");

// Every echo is HTML-encoded automatically
echo $name;   // Jean O&apos;Brien
echo $title;  // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!
```

You never call `htmlspecialchars()` again. SmartString handles it
every time a value reaches output. You cannot produce unencoded output
by accident — you have to ask for it explicitly.

## How Auto-Encoding Works

SmartString's `__toString()` method calls `htmlspecialchars()`
automatically whenever the object is used in string context. All of
these trigger encoding:

```php
$str = SmartString::new("It's <b>easy</b> & fun!");

// All of these produce HTML-encoded output
echo $str;              // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!
print $str;             // same
$html = "Value: $str";  // interpolation encodes too
$cast = (string) $str;  // explicit cast encodes too
```

The encoding uses `htmlspecialchars()` with these flags:

`ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5`

and UTF-8 charset. This encodes the five characters that enable HTML
injection: `<`, `>`, `&`, `'`, and `"`. Invalid byte sequences are
replaced with the Unicode substitution character rather than silently
dropped.

Your original data stays intact inside the SmartString object. Only
when PHP converts the object to a string does encoding occur.

## When You Need the Raw Value

Sometimes you need the original, unencoded data — for math, database
queries, or passing data to another system. Use `value()`:

```php
$str = SmartString::new("It's <b>easy</b> & fun!");

echo $str;           // It&apos;s &lt;b&gt;easy&lt;/b&gt; &amp; fun!
echo $str->value();  // It's <b>easy</b> & fun!
```

`value()` returns the original value with its original type preserved.
An `int` goes in, an `int` comes back. Same for `float`, `bool`, and
`null`.

```php
$price = SmartString::new(1234567.89);

// Raw value for math
$total = $price->value() * 1.13;

// Raw value for database queries
$db->query("UPDATE products SET price = ?", [$price->value()]);
```

### Outputting trusted HTML — `rawHtml()`

`rawHtml()` is an alias for `value()`. Use it when you intentionally
output trusted HTML content like WYSIWYG editor fields:

```php
$content = SmartString::new("<p>Welcome to our <em>new</em> site!</p>");
$title   = SmartString::new("Jean's Update");

// WYSIWYG content -- trusted HTML, output raw
echo $content->rawHtml();
// <p>Welcome to our <em>new</em> site!</p>

// User-submitted title -- untrusted, auto-encode
echo $title;
// Jean&apos;s Update
```

Both methods return the same thing. `rawHtml()` signals intent to
other developers: "I know this is unencoded, and that is deliberate."

**Warning:** Never pass unsanitized user input directly to
`rawHtml()`. It bypasses encoding entirely.

## Common Mistakes

These are the most common things that go wrong when you first start
using SmartString, and how to fix each one.

### Double-encoding

If you already have SmartString handling your output and then wrap it
in `htmlspecialchars()` too, the value gets encoded twice:

```php
$name = SmartString::new("Jean O'Brien");

// WRONG -- double-encoded
echo htmlspecialchars($name);
// Jean O&amp;apos;Brien (encoded twice!)

// RIGHT -- SmartString already handles encoding
echo $name;
// Jean O&apos;Brien
```

The fix: remove the `htmlspecialchars()` call. SmartString already
encodes in string context, so the manual call encodes the already-
encoded output a second time.

### Using the encoded value in logic

When you compare or manipulate SmartString values in PHP, use
`value()` to get the raw data. String context triggers encoding,
which breaks comparisons:

```php
$status = SmartString::new("it's active");

// WRONG -- compares against HTML-encoded value
if ((string) $status === "it's active") { /* never true */ }

// RIGHT -- compare against the raw value
if ($status->value() === "it's active") { /* works */ }
```

### Missing parentheses on method calls

SmartString methods require `()` like any PHP method. Accessing a
method without parentheses triggers a helpful warning:

```php
$name = SmartString::new("Jean O'Brien");

// WRONG -- missing parentheses
echo $name->trim;
// Warning: "Jean O'Brien"->trim
// Method ->trim needs brackets() everywhere and {curly braces} in strings

// RIGHT
echo $name->trim();
// Jean O&apos;Brien
```

### Missing curly braces in string interpolation

Inside double-quoted strings, method calls need `{curly braces}`:

```php
$name = SmartString::new("Jean O'Brien");

// WRONG -- PHP parses this as $name->trim, not $name->trim()
echo "Hello $name->trim()";
// Warning: missing brackets or curly braces

// RIGHT -- curly braces let PHP see the full expression
echo "Hello {$name->trim()}";
// Hello Jean O&apos;Brien
```

## Encoding Is Output, Not Storage

**Never store HTML-encoded data in your database.** Encode at the
point of output, not before storage.

```php
// WRONG -- storing encoded data
$name = htmlspecialchars($_POST['name']);
$db->query("INSERT INTO users (name) VALUES (?)", [$name]);
// Database now contains: Jean O&apos;Brien (encoded!)

// RIGHT -- store raw, encode on output
$db->query("INSERT INTO users (name) VALUES (?)", [$_POST['name']]);
// Database contains: Jean O'Brien

// SmartString encodes automatically when you display it
$name = SmartString::new($record['name']);
echo $name;  // Jean O&apos;Brien
```

Storing raw data preserves integrity. You can encode it differently
for different contexts — HTML, URL, JSON — without re-decoding
stored data. SmartString makes this natural: data goes in raw, comes
out encoded.

## What Happens If...

### ...you echo a null SmartString?

Null values produce an empty string in HTML output, matching PHP's
default `(string)` cast behavior:

```php
$val = SmartString::new(null);
echo $val;           // (empty string)
echo $val->value();  // null (actual null)
```

### ...you chain methods on a null value?

Most methods pass null through rather than throwing errors. The chain
returns null gracefully:

```php
$val = SmartString::new(null);
echo $val->trim()->maxWords(5);  // (empty string)
echo $val->trim()->value();      // null
```

### ...you pass a non-scalar to `SmartString::new()`?

SmartString accepts `string`, `int`, `float`, `bool`, and `null`.
Passing an array triggers a deprecation notice directing you to
SmartArray instead. Other types throw an `InvalidArgumentException`.

### ...you call an undefined method?

SmartString throws a PHP `Error` with a helpful suggestion when it
recognizes a common alias:

```php
$str = SmartString::new("Hello");

// Throws -- with suggestion
echo $str->truncate(5);
// Call to undefined method SmartString->truncate(), did you mean ->maxChars()?
```

If the method is not recognized, the error suggests calling
`->help()` for available methods.

## What SmartString Guarantees

1. **Every string context produces HTML-encoded output.** `echo`,
   `print`, interpolation, and `(string)` casts all trigger
   `htmlspecialchars()` through `__toString()`.

2. **Original values are preserved and accessible via `value()`.**
   Nothing is lost or altered during storage.

3. **Type information is maintained.** An `int` goes in and `value()`
   returns an `int`. Same for `float`, `bool`, and `null`.

4. **Chained methods return new SmartString instances.** The original
   object is never modified (immutable pattern).

5. **Null propagates through chains.** If a value is `null`, most
   methods pass `null` through rather than throwing errors, so
   chains handle missing data gracefully.

## What SmartString Does Not Do

1. **Does not sanitize input.** It encodes output. You still need to
   validate and sanitize data before storage.

2. **Does not validate data.** Use PHP's `filter_var()`, type
   declarations, or your own validation logic.

3. **Does not handle file or resource types.** It accepts `string`,
   `int`, `float`, `bool`, and `null`.

4. **Does not replace a template engine.** For complex view logic
   with layouts, partials, and inheritance, use a dedicated template
   system.

---

[← Back to README](../README.md) | [← Getting Started](01-quickstart.md) | [Next: Values & Encoding →](03-values-and-encoding.md)
