# Troubleshooting and Gotchas

This page covers common error messages, behavioral surprises, and
debugging techniques.

## Common Error Messages

### "Method ->trim needs brackets()..."

**What happened:** You accessed a method as a property (without
parentheses) or used a method in a double-quoted string without
curly braces.

```php
// Wrong -- missing parentheses
echo $name->trim;

// Wrong -- missing curly braces in string
echo "Hello $name->trim()";

// Correct
echo $name->trim();
echo "Hello {$name->trim()}";
```

**Fix:** Add parentheses for direct calls. Add curly braces when
calling methods inside double-quoted strings.

### "Undefined property SmartString->xyz"

**What happened:** You accessed a property name that is not a
SmartString method. SmartString has no public properties --
everything is a method call.

```php
echo $str->length;   // Triggers error
```

**Fix:** Check the method name, or call `->help()` to see all
available methods.

### "Call to undefined method...did you mean?"

**What happened:** You called a method that does not exist, but
SmartString recognized a common alias and suggested the correct name.

```php
echo $str->append("!");
// Call to undefined method SmartString->append(), did you mean ->and()?
```

**Fix:** Use the suggested method. Common aliases SmartString
recognizes:

| You wrote | Suggestion |
|-----------|-----------|
| `->append()`, `->concat()` | `->and()` |
| `->prepend()`, `->prefix()` | `->andPrefix()` |
| `->default()`, `->fallback()` | `->or()` |
| `->toInt()`, `->getInt()` | `->int()` |
| `->toFloat()` | `->float()` |
| `->escapeHtml()`, `->e()` | `->htmlEncode()` |
| `->truncate()`, `->limit()` | `->maxChars()` |
| `->plainText()`, `->strip()` | `->textOnly()` |
| `->toJson()` | `->jsonEncode()` |
| `->raw()`, `->getvalue()` | `->value()` |
| `->plus()` | `->add()` |
| `->minus()` | `->subtract()` |
| `->times()` | `->multiply()` |
| `->div()` | `->divide()` |

This table is not exhaustive. SmartString recognizes dozens of
aliases from other string libraries and common naming conventions.
Call `->help()` to see the canonical method names.

### "Call to undefined method SmartString->foo()"

**What happened:** You called a method that does not exist and
SmartString could not find a likely alias.

```php
echo $str->foo();
// Call to undefined method SmartString->foo(), call ->help() for available methods.
```

**Fix:** Check your spelling and call `->help()`. This throws a PHP
`Error`, matching PHP's native behavior for undefined methods.

### "Function 'xyz' is not callable"

**What happened:** You passed a non-callable value to `apply()`.

```php
echo $str->apply('nonExistentFunc');
// Throws -- Function 'nonExistentFunc' is not callable
```

**Fix:** Pass a valid callable:

```php
echo $str->apply('strtoupper');
echo $str->apply(fn($v) => strtoupper($v));
```

### "apply() callback must return a scalar value..."

**What happened:** Your `apply()` callback returned an array, object,
or other non-scalar value. SmartString wraps scalar values only.

```php
echo $str->apply(fn($v) => ['result' => $v]);
// Throws -- apply() callback must return a scalar value (string, int, float, bool, or null), got array
```

**Fix:** Return a scalar from your callback:

```php
echo $str->apply(fn($v) => strtoupper($v));     // string -- OK
echo $str->apply(fn($v) => strlen($v));          // int -- OK
echo $str->apply(fn($v) => $v === 'yes');        // bool -- OK
echo $str->apply(fn($v) => null);                // null -- OK
```

### "orRedirect(): headers already sent"

**What happened:** `orRedirect()` was called, but PHP had already
sent output to the browser. SmartString checks `headers_sent()`
immediately — before even checking whether the value is missing --
so you find out right away.

```php
echo "some output";
$val->orRedirect('/login');
// Throws -- orRedirect(): headers already sent in /path/to/file.php on line 1
```

**Fix:** Ensure no output appears before the redirect. Common
culprits: `echo` statements, whitespace before `<?php`, and BOM
characters.

## Gotchas

### 1. Double-encoding

Wrapping SmartString output in `htmlspecialchars()` encodes it a
second time, producing garbled text with `&amp;` everywhere.

```php
$name = SmartString::new("Jean O'Brien");

// Wrong -- double-encodes
echo htmlspecialchars($name);   // Jean O&amp;apos;Brien

// Correct -- SmartString already encodes
echo $name;                     // Jean O&apos;Brien

// If you need the raw value for another encoder
echo htmlspecialchars($name->value());
```

**This is the most common mistake when adopting SmartString.** If
you see `&amp;` in your output, you are encoding twice.

### 2. or() vs isEmpty() — different "empty" definitions

`or()` uses `isMissing()` internally, which only treats `null` and
`""` as missing. `isEmpty()` uses PHP's `empty()`, which treats
zero, `"0"`, and `false` as empty too.

| Value | `or("fallback")` | `isEmpty()` | `isMissing()` |
|-------|-------------------|-------------|----------------|
| `null` | `"fallback"` | `true` | `true` |
| `""` | `"fallback"` | `true` | `true` |
| `0` | `0` | `true` | `false` |
| `"0"` | `"0"` | `true` | `false` |
| `false` | `false` | `true` | `false` |
| `"hello"` | `"hello"` | `false` | `false` |

**If you want zero to survive, use `or()`.** If you want zero
treated as empty, use `isEmpty()` in a conditional.

### 3. Curly braces required for method calls in strings

PHP requires `{$var->method()}` syntax inside double-quoted strings.
Without braces, PHP parses `$var->method` as a property access,
which triggers the `__get` error handler.

```php
// Wrong -- PHP sees this as property access
echo "Name: $name->trim()";

// Correct -- curly braces around the expression
echo "Name: {$name->trim()}";
```

This is a PHP language requirement, not a SmartString limitation.

### 4. Non-numeric strings in math produce null silently

Formatted numbers with commas, currency symbols, or letters are not
numeric to PHP. All arithmetic methods return null for them.

```php
$val = SmartString::new("1,234");
echo $val->add(100)->value();      // null

$val = SmartString::new("cat");
echo $val->multiply(2)->value();   // null
```

Strip formatting before wrapping in SmartString:

```php
$cleaned = str_replace(',', '', '1,234');
$val     = SmartString::new((float) $cleaned);
echo $val->add(100);               // 1334
```

### 5. Floating-point precision in percent() and math

PHP uses IEEE 754 floating-point arithmetic. Internally,
`0.1 + 0.2` equals `0.30000000000000004` — not `0.3`. PHP's
`echo` hides this by rounding, but the imprecision can surface in
comparisons or chained arithmetic.

Use `numberFormat()` to round results for display:

```php
$val = SmartString::new(0.1);
echo $val->add(0.2);                  // 0.3 (PHP rounds for display)
echo $val->add(0.2)->numberFormat(2); // 0.30 (explicit rounding)
```

### 6. Null stays null in arithmetic

Null propagates through all arithmetic operations — it does not
become zero. Use `->ifNull(0)` before the arithmetic:

```php
$val = SmartString::new(null);

echo $val->add(50)->value();            // null
echo $val->ifNull(0)->add(50)->value(); // 50
```

**Non-numeric strings always produce null.** Strings like `"cat"`
are never coerced to zero.

### 7. textOnly() decodes entities before stripping tags

The order of operations is: `html_entity_decode()` then
`strip_tags()` then `trim()`. Encoded tags like `&lt;b&gt;` are
decoded back to `<b>` first, then stripped.

```php
$str = SmartString::new("Hello &lt;b&gt;World&lt;/b&gt;");
echo $str->textOnly();   // Hello World
```

This is correct behavior — it prevents encoded tags from surviving
as literal text.

### 8. dateFormat() returns null for invalid dates

Invalid date strings return null rather than throwing an error.
Chain with `->or()` to provide a fallback:

```php
$str = SmartString::new("not-a-date");
echo $str->dateFormat();                 // (no output -- null)
echo $str->dateFormat()->or("N/A");      // N/A
```

Empty strings and null also return null.

### 9. textToHtml() returns a raw string

`textToHtml()` returns a plain PHP string, not a SmartString. The
result is trusted HTML with encoded entities and `<br>` tags that
output directly without further encoding.

```php
$str = SmartString::new("Hello & <b>world</b>\nLine two");
echo $str->textToHtml();
// Hello &amp; &lt;b&gt;world&lt;/b&gt;<br>
// Line two
```

Because `htmlEncode()` encodes all tags including `<br>`, use
`textToHtml()` when you need line breaks preserved. Pass
`keepBr: true` to preserve existing `<br>` tags instead of
converting newlines.

### 10. SmartString::new($array) is deprecated

Passing an array to `SmartString::new()` triggers a deprecation
notice and returns a SmartArray instead of a SmartString. Use
[SmartArray](https://github.com/interactivetools-com/SmartArray)
directly when working with arrays:

```php
// Deprecated -- triggers E_USER_DEPRECATED
$val = SmartString::new(['name' => "Jean O'Brien"]);

// SmartString::new() is for scalar values
$name = SmartString::new("Jean O'Brien");
```

Similarly, `SmartString::fromArray()` is deprecated — use
`SmartArray::new($array)->asHtml()` instead.

## Debugging Tips

### 1. Inspect values with print_r()

`print_r()` on a SmartString shows the raw value and a reminder to
call `->help()`:

```php
$name = SmartString::new("Jean O'Brien");
print_r($name);
// Itools\SmartString\SmartString Object
// (
//     [README:private] => Call $obj->help() for more information and method examples.
//     [rawData:private] => "Jean O'Brien"
// )
```

The `README` hint only appears on the first `print_r()` call in a
request, so it does not clutter output when dumping collections.

### 2. List available methods with help()

Call `->help()` on any SmartString to display a full method
reference with descriptions and examples. It works on any
instance, even one wrapping null:

```php
SmartString::new(null)->help();
```

The output lists every available method grouped by category, making
it useful as a quick-reference while developing.

### 3. Check raw data with value()

Use `->value()` to bypass HTML encoding and see the stored value:

```php
echo $str;           // Jean O&apos;Brien (encoded)
echo $str->value();  // Jean O'Brien (raw)
```

### 4. Surface deprecated usage

Deprecated methods trigger `E_USER_DEPRECATED` notices via
`@trigger_error()`. No configuration needed — notices appear in
your error log or when `display_errors` is on.

| Deprecated | Replacement |
|------------|-------------|
| `->noEncode()` | `->rawHtml()` |
| `->toString()` | `->string()` or `->htmlEncode()` |
| `->jsEncode()` | `->jsonEncode()` (not identical — may need refactoring) |
| `->nl2br()` | `->textToHtml()` |
| `->stripTags()` | `->textOnly()` |
| `SmartString::fromArray()` | `SmartArray::new($array)->asHtml()` |
| `SmartString::new($array)` | `SmartArray::new($array)->asHtml()` |

---

[← Back to README](../README.md) | [← Numeric Operations & Math](06-numeric-operations-and-math.md)
