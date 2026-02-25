# Conditionals and Error Handling

SmartString provides conditional methods for fallbacks, validation
checks, and fail-fast error handling that stop execution when
required data is missing.

## The "Missing" Definition

A value is **missing when it is null or an empty string `""`** --
and nothing else. Zero is not missing. False is not missing. This
distinction drives `or()`, `and()`, `andPrefix()`, and every
fail-fast method on this page:

| Value     | `or("fallback")` | `isEmpty()` | `isMissing()` |
|-----------|-------------------|-------------|----------------|
| `null`    | `"fallback"`      | true        | true           |
| `""`      | `"fallback"`      | true        | true           |
| `0`       | `0`               | true        | false          |
| `"0"`     | `"0"`             | true        | false          |
| `false`   | `false`           | true        | false          |
| `"hello"` | `"hello"`         | false       | false          |

Notice how `0` and `"0"` survive `or()` — they are present values.
PHP's `empty()` considers them empty, but SmartString's conditional
methods do not treat them as missing. A price of $0.00 is real data,
not an absent value.

## Providing Fallbacks — `or()`

`or()` returns a SmartString with the fallback value when the
current value is missing (null or `""`). This is the most-used
conditional method. Reach for `or()` whenever you need a default.

```php
$str = SmartString::new("");
echo $str->or("N/A");
// N/A

$str = SmartString::new(null);
echo $str->or("Unknown");
// Unknown
```

Zero keeps its value because zero is not missing:

```php
$str = SmartString::new(0);
echo $str->or("fallback");
// 0
```

The fallback accepts `int`, `float`, `string`, or `SmartString`:

```php
$default = SmartString::new("Guest");
echo $name->or($default);
```

In templates, `or()` reads naturally inside interpolation:

```php
echo "Hello, {$user->name->or('Guest')}!";
```

## Conditional Append and Prepend — `and()` and `andPrefix()`

`and()` returns a SmartString with the given value appended when
the current value is present (not missing). `andPrefix()` does the
same in reverse — prepending instead. Both return the original
(missing) SmartString unchanged when the value is missing, so no
stray separators or labels appear.

The address pattern shows why this matters. Each line appends its
own separator only when it has a value:

```php
echo $user->address1->and(",<br>\n");
echo $user->address2->and(",<br>\n");
echo $user->city->and(", ");
echo $user->state->and(" ");
echo $user->zip;
```

If `address2` is empty, its line produces no output at all — no
dangling comma, no blank line.

Use `andPrefix()` to add labels only when the value exists:

```php
echo $user->phone->andPrefix("Phone: ");
// Phone: (234) 567-8901  (if phone is present)
//                         (nothing if phone is missing)
```

Both methods accept `int`, `float`, `string`, or `SmartString`
arguments, just like `or()`.

## Targeted Replacements — `ifBlank()`, `ifNull()`, `ifZero()`

These methods replace the value only for a specific condition,
giving you finer control than `or()`.

| Method        | Replaces when  | Example trigger            |
|---------------|----------------|----------------------------|
| `ifBlank($v)` | `=== ""`       | Empty string only          |
| `ifNull($v)`  | `=== null`     | Null only                  |
| `ifZero($v)`  | numeric zero   | `0`, `0.0`, `"0"`, `"0.0"` |
| `or($v)`      | null or `""`   | Both null and empty        |

```php
// ifBlank: replaces empty string, not null
$str = SmartString::new("");
echo $str->ifBlank("was blank");
// was blank

$str = SmartString::new(null);
echo $str->ifBlank("was blank")->or("was null");
// was null
```

```php
// ifNull: replaces null, not empty string
$str = SmartString::new(null);
echo $str->ifNull("was null");
// was null

$str = SmartString::new("");
echo $str->ifNull("was null")->or("was empty");
// was empty
```

```php
// ifZero: replaces any numeric zero
$str = SmartString::new(0);
echo $str->ifZero("none");
// none

$str = SmartString::new("0.0");
echo $str->ifZero("none");
// none

$str = SmartString::new(42);
echo $str->ifZero("none");
// 42
```

`ifNull(0)` is especially useful before arithmetic — see
[Null Handling](06-numeric-operations-and-math.md#null-handling)
in the Numeric Operations page.

## Conditional Assignment — `if()` and `set()`

`if()` returns a SmartString set to the given value only when the
condition is truthy. Otherwise it keeps the current value unchanged.
The condition can be any scalar type, SmartString, or null.

```php
$views = SmartString::new(1234567.89);

echo $views->if($views->value() > 1000000, "1M+");
// 1M+

echo $views->if($views->value() < 100, "New");
// 1,234,567.89
```

`set()` unconditionally replaces the value:

```php
$str = SmartString::new("original");
echo $str->set("replaced");
// replaced
```

Combine `set()` with `match` expressions for multi-branch logic:

```php
$eggs = SmartString::new(12);

echo $eggs->set(match(true) {
    $eggs->value() >= 12 => "A dozen or more",
    $eggs->value() > 0   => "A few left",
    default               => "None",
});
// A dozen or more
```

For complex logic with many branches, use regular PHP variables
and control flow. SmartString conditionals are best for simple
inline replacements and template output — do not over-chain.

## Validation Checks

These methods return `bool` values for use in `if` statements and
control flow. The truth table below shows exactly where they differ:

| Value     | `isEmpty()` | `isNotEmpty()` | `isMissing()` | `isNull()` |
|-----------|-------------|----------------|---------------|------------|
| `null`    | true        | false          | true          | true       |
| `""`      | true        | false          | true          | false      |
| `0`       | true        | false          | false         | false      |
| `"0"`     | true        | false          | false         | false      |
| `false`   | true        | false          | false         | false      |
| `"hello"` | false       | true           | false         | false      |
| `42`      | false       | true           | false         | false      |

`isEmpty()` and `isNotEmpty()` use PHP's `empty()` function, so
they treat `0`, `"0"`, `false`, `null`, and `""` as empty.

`isMissing()` matches the same definition used by `or()`, `and()`,
and the fail-fast methods: null or empty string only.

```php
$str = SmartString::new(0);

$str->isEmpty();    // true  (PHP empty() considers 0 empty)
$str->isMissing();  // false (zero is not missing)
$str->isNull();     // false
```

**Choose the right check for your intent.** Use `isMissing()` when
you want the same logic as `or()`. Use `isEmpty()` when zero and
false should count as empty. Use `isNull()` when you need to
distinguish null from empty string.

## Fail-Fast Error Handling

These methods trigger only when the value is missing (null or `""`).
If the value is present, they return `$this` for continued chaining.
The most common pattern pairs a fail-fast method with a database
lookup:

```php
$article = DB::get('articles', ['num' => $articleNum]);
$article->num->or404();

// If we reach here, $article is valid
echo "<h1>$article->title</h1>";
echo "<p>{$article->content->textOnly()->maxChars(200)}</p>";
```

All four fail-fast methods use the same "missing" definition as
`or()` — null or empty string. Zero passes through safely.

### Sending a 404 page — `or404()`

`or404()` sends an HTTP 404 status header and outputs a minimal
HTML error page, then exits. The default message is "The requested
URL was not found on this server." You can pass a custom message.

```php
$article->num->or404();
$article->num->or404("Article not found");
```

The message is HTML-encoded before output to prevent XSS.

### Halting with a message — `orDie()`

`orDie()` outputs the HTML-encoded message and calls `die()` to
stop execution. Use this for quick debugging or simple error pages.

```php
$article->num->orDie("Article not found");
```

### Throwing an exception — `orThrow()`

`orThrow()` throws a `RuntimeException` with the HTML-encoded
message, letting your application's error handler take over.

```php
$article->num->orThrow("Article not found");
```

### Redirecting elsewhere — `orRedirect()`

`orRedirect()` sends a 302 `Location` header and exits when the
value is missing.

```php
$article->num->orRedirect("/articles");
```

One important detail: `orRedirect()` checks `headers_sent()`
immediately — before even checking whether the value is missing.
If headers have already been sent, it throws a `RuntimeException`
identifying the file and line where output started. You find out
about the problem right away, not only when the value is missing.

## Putting It Together

This example combines several conditional methods in a realistic
product display template:

```php
$product = DB::get('products', ['num' => $productNum]);
$product->num->or404();

// Price with tax, falling back to "Contact us" for missing prices
$price = $product->price
    ->ifNull(0)
    ->multiply(1.13)
    ->numberFormat(2)
    ->andPrefix("$")
    ->or("Contact us");

// Build a display line with conditional pieces
echo "<h1>{$product->name->or('Untitled Product')}</h1>";
echo "<p>$price</p>";
echo "<p>{$product->description->textOnly()->maxChars(200)}</p>";
echo $product->sku->andPrefix("SKU: ");
echo $product->sale_price->ifZero("Free!")->andPrefix("Sale: $");
```

This template demonstrates several features working together:

- `or404()` halts execution if the product does not exist
- `ifNull(0)` converts null to zero before arithmetic
- `numberFormat(2)` and `andPrefix("$")` format the result
- `or("Contact us")` provides a fallback for missing prices
- `andPrefix()` adds labels only when values are present
- `ifZero()` replaces zero sale price with "Free!"

---

[← Back to README](../README.md) | [← Text & Formatting](04-text-and-formatting.md) | [Next: Numeric Operations & Math →](06-numeric-operations-and-math.md)
