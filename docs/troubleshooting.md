<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Troubleshooting

The error messages SmartString produces and what each one means, plus the
behavior gotchas that don't produce an error at all: double encoding,
vanishing math results, and markup that prints as text. Headings quote the
message or symptom so you can find them by search.

## Error Messages

### "Method ->trim needs brackets() everywhere and {curly braces} in strings"

**What happened:** A method was accessed like a property (no parentheses),
or called inside a double-quoted string without curly braces. PHP parses
`"$name->trim()"` as the property `$name->trim` followed by a literal `()`,
so SmartString sees a property access either way:

```php
echo $name->trim;            // missing parentheses
echo "Hello $name->trim()";  // missing curly braces in string
```

**Fix:** Parentheses for direct calls, curly braces around method calls in
strings:

```php
echo $name->trim();
echo "Hello {$name->trim()}";
```

The warning includes all four forms with checkmarks. It is a PHP warning
(the page keeps running and the expression evaluates as an empty value),
so check your error log if output looks blank where a chain should be.

### "Undefined property: ..."

**What happened:** A property name that isn't a SmartString method.
SmartString has no public properties; everything is a method call.

**Fix:** Check the spelling, or call `$str->help()` to list every method.

### "Call to undefined method SmartString->truncate(), did you mean ->maxChars()?"

**What happened:** The method doesn't exist, but the name matches a common
alias from another library (or another memory), so the error suggests the
SmartString name. A few of the recognized aliases:

| You wrote                          | Suggestion       |
|------------------------------------|------------------|
| `->truncate()`, `->limit()`        | `->maxChars()`   |
| `->default()`, `->fallback()`      | `->or()`         |
| `->escapeHtml()`, `->e()`          | `->htmlEncode()` |
| `->plainText()`, `->strip()`       | `->textOnly()`   |
| `->raw()`, `->unsafe()`            | `->rawHtml()`    |
| `->plus()`, `->minus()`            | `->add()`, `->subtract()` |

Dozens more are recognized. When no alias matches, the error says
"call ->help() for available methods." instead. Either way it throws a PHP
`Error`, matching PHP's native behavior for undefined methods.

### "Call to a member function or() on string"

**What happened:** Chaining after a terminal method. The encoding methods
(`htmlEncode()`, `urlEncode()`, `jsonEncode()`, `nl2br()`, `appendHtml()`,
`wrapHtml()`) return plain strings, not SmartStrings, so nothing can chain
after them; that is what makes double encoding impossible.

```php
echo $bio->nl2br()->or('No bio');   // throws - nl2br() returned a string
```

**Fix:** Do the conditional work first, encode last:

```php
echo $bio->or('No bio')->nl2br();
```

### "pregReplace(): No ending delimiter '/' found"

**What happened:** The regex pattern passed to `pregReplace()` is invalid
(this example is missing its closing delimiter). The exception reports the
file and line of *your* call, not the library's internals; see
[CallerException](#callerexception-the-error-points-at-my-file) below.

**Fix:** Correct the pattern at the reported line. The message quotes PHP's
own description of what is wrong with it.

### "orRedirect(): headers already sent in /path/to/file.php on line 12"

**What happened:** Output had already been sent when `orRedirect()` was
called, so a `Location` header could no longer work. The check runs
immediately, even when the value is present, so the mistake surfaces on the
first request instead of only when a value goes missing.

**Fix:** Move the guard above any output. The message names the file and
line where output started; common culprits are `echo` statements, whitespace
before `<?php`, and a UTF-8 BOM.

### CallerException: the error points at my file

**What happened:** Nothing wrong; this is on purpose. Developer-mistake
exceptions (bad regex in `pregReplace()`, a `map()` callback that isn't
callable or returns a non-scalar, an unsupported type in `getRawValue()`)
throw `CallerException`, which reports the file and line of the code that
called SmartString instead of the library's internal throw site:

```
Uncaught CallerException: pregReplace(): No ending delimiter '/' found
in /var/www/templates/race.php:345
```

The named line is the one to fix. It is a normal
`InvalidArgumentException` subclass, so existing catch blocks work, and the
library's own throw site is preserved in `$e->thrownInFile` and
`$e->thrownInLine` if you need it.

## Behavior Gotchas

### Output shows &amp;amp; or &amp;apos; - encoded twice

If you see `&amp;` where an `&` should be, you are encoding twice.
SmartString already encodes in string context, so a manual
`htmlspecialchars()` around it encodes the encoded output again:

```php
$name = SmartString::new("Jean O'Brien");

echo htmlspecialchars($name);  // WRONG - Jean O&​amp;apos;Brien (double encoded)
echo $name;                    // RIGHT - Jean O&​apos;Brien
```

This is the most common mistake when adopting SmartString: delete the
`htmlspecialchars()` call. If some other encoder genuinely needs the value,
give it the raw one: `htmlspecialchars($name->value())`.

### Markup prints as text: the page shows a literal &lt;br&gt;

Markup passed through a regular (encoding) method gets encoded like any
other value:

```php
echo $address->append("<br>");      // WRONG - prints "12 High St<br>" as visible text
echo $address->appendHtml("<br>");  // RIGHT - markup stays markup
```

Markup goes through the HTML-aware methods (`appendHtml()`, `wrapHtml()`,
`nl2br()`, `rawHtml()`); everything else treats it as text to encode. See
[Encoding and HTML](encoding-and-html.md).

### Comparison never matches

String context encodes, so comparing against `(string)$status` compares the
encoded value. Compare raw values instead:

```php
$status = SmartString::new("it's active");

if ((string)$status === "it's active") { }   // WRONG - never true (left side is encoded)
if ($status->value() === "it's active") { }  // RIGHT
```

### Math chain outputs nothing

Math methods return null when either side is null or not numeric, and null
echoes as an empty string. The usual causes, in order of frequency:

```php
echo SmartString::new(null)->add(50);     // "" - null input
echo SmartString::new("1,234")->add(50);  // "" - the comma makes it non-numeric to PHP
echo SmartString::new(100)->divide(0);    // "" - division by zero
```

**Fix:** Decide what null should mean and say so: `->ifNull(0)` before the
math to treat missing as zero, or `->or('n/a')` at the end to show a
fallback. For pre-formatted strings like `"1,234"`, store plain numbers and
format on output instead.

### ifZero() after percent() or numberFormat() never fires

Formatters return display text, and `ifZero()` only recognizes numeric
zeros, so `"0.00%"` and `"$0.00"` never match. Use the `percent()`
parameter, or detect zero on the raw value:

```php
echo $rate->percent(2, ifZero: 'N/A');  // percent's zero rule is a parameter
echo $price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!');  // match the formatted text
```

See [Run Conditionals Before Formatting](conditionals-and-error-checking.md#run-conditionals-before-formatting).

### or() kept my zero / isEmpty() lost my zero

Working as designed, in both directions: `or()` treats zero as a present
value, and `isEmpty()` follows PHP's `empty()`, which treats `0`, `"0"`, and
`false` as empty. Pick the method that treats zero the way you want; the
[truth table](conditionals-and-error-checking.md#what-missing-means) shows
every combination.

## Deprecations

### Strikethrough method names in PHPStorm

The v3.0 renames keep the old names working forever, but PHPStorm shows them
struck through and offers a one-click rewrite to the new name:

| Old name         | New name    |
|------------------|-------------|
| `->and()`        | `->append()`  |
| `->andPrefix()`  | `->prepend()` |
| `->apply()`      | `->map()`     |
| `->if()`         | `->ifTrue()`  |
| `->textToHtml()` | `->nl2br()`   |

A few retired methods also still work but are no longer documented
(`ifBlank()`, `phoneFormat()`, `dateTimeFormat()`); their docblocks name the
replacement pattern.

### Deprecation notices in the error log

The oldest legacy names log an `E_USER_DEPRECATED` notice naming the
replacement when called, and keep working:

```
Replace ->noEncode() with ->rawHtml() in /var/www/templates/page.php:12
```

The notices cover `noEncode()`, `toString()`, `jsEncode()`, `stripTags()`,
`SmartString::fromArray()`, and passing an array to `SmartString::new()`.
Each message names its replacement; `jsEncode()` is the one to review by
hand, since `jsonEncode()` produces different output (a complete JSON
value, not backslash-escaped text).

## Debugging

Two tools cover most "what is this value?" questions:

```php
print_r($name);  // shows the raw stored value
$name->help();   // prints the full method reference (also: SmartString::help())
```

The `print_r()` output shows `rawData` (the original, unencoded value) plus
a one-time hint about `help()`. To see a value mid-chain, `print_r()` any
link of it; chains are just objects.

---

[← Documentation Index](README.md) | [← Prev: Common Patterns](common-patterns.md) | [Next: AI Reference →](ai-reference.md)
