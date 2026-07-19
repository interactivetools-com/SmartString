<!-- DIFFERENT FROM THE OTHER DOC PAGES: this file contains no zero-width spaces.
     The human doc pages insert a U+200B after "&" in example output like &apos; so
     PHPStorm's Markdown preview shows the entity instead of decoding it. This file
     is read by AI assistants as raw bytes, so it stays byte-exact: everything here
     is safe to copy into code and test assertions. Never add U+200B to this file. -->

# SmartString AI Reference

This is a consolidated reference for AI coding assistants. It contains
everything needed to write correct SmartString code in a single file, and
covers SmartString 3.0. For human-friendly docs with tutorials and
explanations, see [Getting Started](getting-started.md).

Contents:

- What is SmartString
- Creating Values
- Auto-Encoding Mechanics
- Type Conversion - value(), int(), float(), bool(), string(), getRawValue()
- Encoding Methods - htmlEncode(), urlEncode(), jsonEncode(), nl2br(), rawHtml(), appendHtml(), wrapHtml()
- String Manipulation - append(), prepend(), wrap(), textOnly(), trim(), maxWords(), maxChars(), pregReplace()
- Dates and Numbers - dateFormat(), numberFormat(), percent(), percentOf(), add(), subtract(), multiply(), divide()
- Conditional Replacement - or(), ifNull(), ifZero(), ifTrue(), ifEquals(), set()
- Guards - or404(), orDie(), orThrow(), orRedirect()
- Value Checks - isEmpty(), isNotEmpty(), isMissing(), isNull()
- Custom Functions - map()
- Static Configuration
- Debugging - help(), print_r()
- Errors and Exceptions
- Gotchas Quick Reference

---

## What is SmartString

SmartString is a PHP value object that **HTML-encodes itself in every string
context** (echo, print, interpolation, concatenation, `(string)` cast), so
template output is XSS-safe by default. The original value is stored
untouched and always recoverable. Transformation methods return new
SmartString objects (immutable, chainable); terminal methods return plain
PHP types and end the chain.

```php
use Itools\SmartString\SmartString;

$str = SmartString::new("It's easy!<hr>");
echo $str;                                    // It&apos;s easy!&lt;hr&gt;
echo $str->value();                           // It's easy!<hr>
echo $str->trim()->maxChars(60)->or('None');  // chains left to right
```

Key definitions used throughout:

- **missing** = null or `""` exactly. Zero (`0`, `"0"`) and `false` are NOT
  missing. Used by: `or()`, `append()`, `prepend()`, `wrap()`,
  `appendHtml()`, `wrapHtml()`, `isMissing()`, `or404()`, `orDie()`,
  `orThrow()`, `orRedirect()`.
- **empty** = PHP `empty()`: null, `""`, `0`, `"0"`, `false`. Used by:
  `isEmpty()`, `isNotEmpty()`.
- **numeric** = PHP `is_numeric()`. `"1,234"` (comma) and formatted output
  like `"0.00%"` are NOT numeric.
- **SmartNull** = SmartArray's placeholder for a missing field (so chained
  calls on `$row->missingField` don't fatal). SmartString methods accept it
  anywhere a plain value is accepted and treat it as null.

## Creating Values

```php
SmartString::new(string|int|float|bool|null $value): SmartString
```

Accepted types: string, int, float, bool, null. Objects/resources are not
accepted. Passing an array is deprecated and returns a `SmartArrayHtml`
(use `SmartArrayHtml::new($array)` from the SmartArray package directly).

Bulk creation is the SmartArray package's job; database rows from ZenDB are
already SmartArrays of SmartStrings:

```php
use Itools\SmartArray\SmartArrayHtml;
$user = SmartArrayHtml::new(['name' => "Jean O'Brien", 'age' => 25]);
echo $user->name;         // Jean O&apos;Brien
$request = SmartArrayHtml::new($_REQUEST);
```

## Auto-Encoding Mechanics

String context calls `__toString()`, which runs
`htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED |
ENT_HTML5, 'UTF-8')`. Encodes `< > & ' "`; malformed UTF-8
bytes become � (U+FFFD); HTML5-disallowed code points become � as well.
Null and false stringify to `""`; true stringifies to `"1"`.

Method calls inside double-quoted strings require curly braces (PHP syntax):
`"Hello {$name->trim()}"`. Property-style access on SmartArrayHtml rows does
not: `"Hello $user->name"`.

Passing a SmartString to PHP's `json_encode()` (via JsonSerializable)
returns the RAW value as JSON, with malformed UTF-8 substituted. For
script-safe embedding use the `jsonEncode()` method instead.

## Type Conversion

Terminal methods; they return plain PHP values and end the chain.

| Method                                            | Returns                                                                                                                                                                 | Null input |
|---------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------|
| `value(): string\|int\|float\|bool\|null`         | Original value, original type                                                                                                                                           | null       |
| `int(): int`                                      | `(int)` cast                                                                                                                                                            | 0          |
| `float(): float`                                  | `(float)` cast                                                                                                                                                          | 0.0        |
| `bool(): bool`                                    | `(bool)` cast                                                                                                                                                           | false      |
| `string(): string`                                | `(string)` cast, NOT HTML-encoded                                                                                                                                       | ""         |
| `SmartString::getRawValue(mixed): mixed` (static) | Unwraps SmartString → value, SmartArray → array, SmartNull → null; scalars/null/arrays pass through (arrays unwrapped recursively); other objects throw CallerException | null       |

## Encoding Methods

Terminal: all return plain `string` and end the chain (nothing downstream
can double-encode). Missing values (null or `""`) return `""` for all of
these except where noted.

| Method                                            | Behavior                                                                                                                                                                                                                                                                                                        |
|---------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `htmlEncode(): string`                            | Same encoding as echo, as an explicit call                                                                                                                                                                                                                                                                      |
| `urlEncode(): string`                             | PHP `urlencode()`; use for query-string values                                                                                                                                                                                                                                                                  |
| `jsonEncode(): string`                            | JSON with `JSON_HEX_TAG\|HEX_APOS\|HEX_QUOT\|HEX_AMP\|UNESCAPED_SLASHES\|UNESCAPED_UNICODE\|INVALID_UTF8_SUBSTITUTE\|THROW_ON_ERROR`. Always a valid JS expression: null → `null`, 123 → `123`. Malformed UTF-8 → �. Invisible Unicode (zero-width, bidi controls, variation selectors) re-escaped as `\uXXXX`. |
| `nl2br(): string`                                 | HTML-encodes FIRST, then converts newlines to `<br>`; only tags in output are the added `<br>` tags                                                                                                                                                                                                             |
| `rawHtml(): string\|int\|float\|bool\|null`       | Alias for `value()`; signals intentional raw HTML output. Null stays null (does not return `""`)                                                                                                                                                                                                                |
| `appendHtml(string $html): string`                | Encoded value + `$html` appended AS-IS (trusted, never user input). Missing → `""` (markup suppressed too)                                                                                                                                                                                                      |
| `wrapHtml(string $before, string $after): string` | `$before` + encoded value + `$after`, markup as-is. Missing → `""` (whole wrapper vanishes). Both args required                                                                                                                                                                                                 |

```php
echo $text->nl2br();                    // "Bob & Sons\nSuite 5" → "Bob &amp; Sons<br>\nSuite 5"
echo $addr->appendHtml(",<br>\n");      // "12 High St,<br>\n" or "" when missing
echo $head->wrapHtml('<h2>', '</h2>');  // "<h2>Our Story</h2>" or ""
echo "?q={$title->urlEncode()}";        // %3C10%25+OFF...
echo "<script>let t = {$title->jsonEncode()};</script>";
```

## String Manipulation

Chainable: return a new SmartString. Missing values (null or `""`) pass
through unchanged, so a later `or()` still works.

| Method                                                           | Behavior                                                                                                                         |
|------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `append($value): SmartString`                                    | Adds `$value` to the end, only when present (zero counts as present; missing passes through)                                     |
| `prepend($value): SmartString`                                   | Adds `$value` to the beginning, only when present                                                                                |
| `wrap($before, $after): SmartString`                             | Wraps when present; both args required, pass `""` for an unwanted side                                                           |
| `textOnly(): SmartString`                                        | `html_entity_decode` → `strip_tags` → `trim` (entities decoded first so `&lt;script&gt;` can't survive as a tag)                 |
| `trim(...$args): SmartString`                                    | PHP `trim()` semantics incl. custom char list (a SmartString char list unwraps)                                                  |
| `maxWords(int $max, string $ellipsis = '...'): SmartString`      | Word limit; `$ellipsis` only if cut; trailing punctuation stripped before ellipsis                                               |
| `maxChars(int $max, string $ellipsis = '...'): SmartString`      | Char limit breaking at last whole word; whitespace runs collapse to single spaces; trailing punctuation stripped before ellipsis |
| `pregReplace(string $pattern, string $replacement): SmartString` | `preg_replace()`; invalid pattern throws CallerException                                                                         |

Arguments to `append`/`prepend`/`wrap` accept
`int|float|string|bool|null|SmartString|SmartNull`.

## Dates and Numbers

Chainable: return a new SmartString. Missing or invalid input → result is
null (echoes as `""`); add `or()` for a fallback. All formatting uses
`SmartString::$numberFormatDecimal`/`$numberFormatThousands`.

| Method                                                                     | Behavior                                                                                                                                                                                                                   |
|----------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `dateFormat(?string $format = null): SmartString`                          | PHP `date()` format; null format uses `SmartString::$dateFormat` (default `'Y-m-d'`). Numeric input = unix timestamp; strings parsed with `strtotime()`; invalid/bool/null → null. One method for dates and datetimes      |
| `numberFormat(int $decimals = 0): SmartString`                             | `number_format()` with configured separators; non-numeric → null                                                                                                                                                           |
| `percent(int $decimals = 0, $ifZero = null): SmartString`                  | value * 100 + `%` (0.24 → `24%`). Zero with `$ifZero` set returns `$ifZero` (parameter exists because a chained ifZero can't see zero inside `"0.00%"`); accepts plain values or SmartString/SmartNull. Non-numeric → null |
| `percentOf($total, int $decimals = 0): SmartString`                        | value / `$total` * 100 + `%`. Null when either non-numeric or `$total` is 0                                                                                                                                                |
| `add($value)` / `subtract($value)` / `multiply($value)` / `divide($value)` | Float arithmetic. Null when either side non-numeric; `divide` also null on zero divisor. Args accept plain values or SmartString/SmartNull                                                                                 |

Null propagation: null carries through subsequent operations but is not
permanent; a mid-chain `ifNull(0)` replaces it and later methods run on the
new value. SmartString never coerces null to zero implicitly.

```php
echo $price->multiply(1.13)->divide(2)->numberFormat(2);   // 56.50
echo SmartString::new(null)->add(50)->or('n/a');           // n/a
echo SmartString::new(null)->ifNull(0)->add(50);           // 50
echo SmartString::new("cat")->add(10)->ifNull(0)->add(5);  // 5 (recovered)
echo SmartString::new("1,234")->add(1);                    // "" (comma → non-numeric → null)
```

## Conditional Replacement

Chainable: return a new SmartString. Each replaces the value when its
condition matches. Args accept plain values or SmartString/SmartNull.

| Method                          | Fires when                                                                                                         |
|---------------------------------|--------------------------------------------------------------------------------------------------------------------|
| `or($fallback)`                 | Value is missing (null or `""`); zero and false survive                                                            |
| `ifNull($fallback)`             | Value is null exactly                                                                                              |
| `ifZero($fallback)`             | `is_numeric($value) && (float)$value === 0.0` (0, 0.0, "0", "0.00", "-0"); non-numeric values never match          |
| `ifTrue($condition, $newValue)` | `$condition` (a computed plain value, not a callback) is truthy. Replaces the value only; does not gate the chain  |
| `ifEquals($match, $newValue)`   | `$value == $match` (loose, so "5" matches 5). Never pass null as `$match` (null == 0 == "" == false); use ifNull() |
| `set($newValue)`                | Always; for storing match()/expression results                                                                     |

```php
echo $name->or('Guest');
echo $date->ifEquals('0000-00-00', null)->dateFormat('M j, Y')->or('Not set');
echo $eggs->set(match(true) { $eggs->int() >= 12 => "Full carton", default => "{$eggs->int()} eggs" });
```

Ordering rule: run conditionals and math while the value is still numeric,
BEFORE formatting; formatted output (`"1,234.00"`, `"50%"`) is display text.
For a text replacement after formatting, match the formatted text:
`$price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!')` (the
match string must be exactly what the format settings produce).

Placement of `or()` changes meaning: `->or(0)->numberFormat(2)` → `"0.00"`
(fallback number, formatted); `->numberFormat(2)->or('n/a')` → `"n/a"`
(fallback display text after failed format).

## Guards

Stop the page when the value is missing (null or `""`; zero passes).
Otherwise return `$this` unchanged for chaining. Message/`$text` params are
HTML-encoded automatically (messages often interpolate user input).

| Method                              | On missing                                                                                                                                               |
|-------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `or404(?string $text = null): self` | HTTP 404 + minimal HTML page + `exit`. Default text "The requested URL was not found on this server."                                                    |
| `orDie(string $text): self`         | Echo encoded text + `exit(1)` (failure code for CLI/cron)                                                                                                |
| `orThrow(string $text): self`       | `throw new RuntimeException($encodedText)`. Decode for logs/CLI with `htmlspecialchars_decode($msg, ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML5)`          |
| `orRedirect(string $url): self`     | 302 + `Location: $url` + `exit`. Checks `headers_sent()` IMMEDIATELY (throws CallerException even when value present, so misuse fails on first request)  |

```php
$article->num->or404("Article not found");
$row->orThrow("no row")->memberId->orThrow("row found but memberId empty")->int();  // two-stage guard ($row is a SmartArrayHtml row; its orThrow() works the same way)
```

## Value Checks

Terminal: return plain `bool`.

| Method         | True when                                      | Zero  |
|----------------|------------------------------------------------|-------|
| `isEmpty()`    | PHP `empty()`: null, "", 0, "0", false         | true  |
| `isNotEmpty()` | `!empty()`                                     | false |
| `isMissing()`  | null or `""` exactly (matches or() and guards) | false |
| `isNull()`     | null exactly                                   | false |

## Custom Functions

```php
map(callable|string $func, mixed ...$args): SmartString
```

Calls `$func($rawValue, ...$args)` and wraps the result. The callback ALWAYS
runs, null included (matches `array_map()`); chain `->ifNull('')` first for
built-ins that reject null. Callback must return scalar or null; other
return types throw CallerException. Non-callable `$func` throws
CallerException.

```php
echo $name->map('mb_strtoupper');
echo $name->map('str_pad', 15, '.');
echo $user->nickname->ifNull('')->map('mb_convert_case', MB_CASE_TITLE);
```

## Static Configuration

Set once at startup; they apply to all SmartStrings for the request.

```php
SmartString::$numberFormatDecimal   = '.';      // default '.'  (numberFormat, percent, percentOf)
SmartString::$numberFormatThousands = ',';      // default ','
SmartString::$dateFormat            = 'Y-m-d';  // default 'Y-m-d' (dateFormat() with no arg)
```

## Debugging

```php
SmartString::help();  // prints the method reference (also $str->help(); works on any instance)
print_r($str);        // shows rawData (original value) + one-time help() hint
```

## Errors and Exceptions

- **CallerException** (`Itools\SmartString\CallerException`, extends
  `InvalidArgumentException`): thrown for developer mistakes:
  `pregReplace()` invalid pattern, `map()` non-callable or non-scalar
  return, `getRawValue()` unsupported type, `orRedirect()` when headers
  already sent. Reports the CALLER's file:line
  via `getFile()`/`getLine()`; the library's real throw site is in public
  readonly `$thrownInFile`/`$thrownInLine`. Catch as
  InvalidArgumentException.
- **RuntimeException**: `orThrow()` (message HTML-encoded).
- **Error** (PHP native): undefined method calls, with did-you-mean
  suggestions for ~100 common alias names (`truncate` → `maxChars`,
  `escapeHtml` → `htmlEncode`, `fallback` → `or`, ...).
- **E_USER_WARNING**: method accessed without parentheses or without curly
  braces in a string (`$str->trim`, `"$str->trim()"`). Page continues;
  expression yields an empty SmartString.

Rule of thumb: RuntimeException (from `orThrow()`) is the only type to catch
in normal operation - everything else means code to fix.

## Gotchas Quick Reference

- Wrapping output in `htmlspecialchars()` double-encodes (`&amp;apos;` in
  output = encoding twice). SmartString already encodes.
- Writing `"$str->method()"` without curly braces is parsed by PHP as
  property access + literal `()`; write `"{$str->method()}"`.
- Comparisons need `->value()`; `(string)$str` compares the ENCODED value.
- Encoding methods return plain strings; chaining after them is a PHP Error
  ("Call to a member function ... on string"). Conditionals first, encode
  last.
- Formatted numbers like `"1,234"` are non-numeric → math/format returns
  null. Store plain numbers, format at output.
- The `string()`/`value()`/`rawHtml()` methods return RAW (unencoded) data.
  `string()` is not the encoded output; that is `htmlEncode()`.
- Markup through `append()`/`wrap()` gets encoded (prints as text); use
  `appendHtml()`/`wrapHtml()` for markup. Their markup args are trusted:
  literals only, never user input.
- Calling `ifEquals(null, ...)` matches 0, "", and false too (loose ==); use
  `ifNull()`.
- Zero counts as present to `or()`/`isMissing()`/guards but empty to
  `isEmpty()`.
- Null through `int()`/`float()`/`bool()`/`string()` becomes 0/0.0/false/"";
  use `value()` or `isNull()` when null must be distinguishable.
- The `dateFormat()` method treats numeric strings as unix timestamps
  (`"2026"` is epoch + 2026 seconds, not a year).

---

[← Documentation Index](README.md) | [← Prev: Troubleshooting](troubleshooting.md)
