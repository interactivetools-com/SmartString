<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Troubleshooting

Common error messages and how to fix them, plus the gotchas that don't
produce an error at all: double encoding, vanishing math results, and
markup that prints as text. Headings quote the message or symptom so you
can find them by search.

## Most Common Issues

### Comparisons and if() checks don't work as expected

A SmartString is an object wrapping your value, and PHP's native checks
look at the object, not the value inside. Casts and comparisons see the
encoded text, and an object is always truthy and never `empty()`, even
when the value inside is null:

```php
$status  = SmartString::new("it's active");
$missing = SmartString::new(null);

// none of these work the way they read
if ($status == "it's active") { }           // WRONG - false: compares the encoded text
if ((string)$status === "it's active") { }  // WRONG - false: the cast encodes too
if ($missing === null) { }                  // WRONG - false: the object itself isn't null
if (empty($missing)) { }                    // WRONG - false: objects are never empty
if ($missing) { }                           // WRONG - true: objects are always truthy

// check the value instead
if ($status->value() === "it's active") { }  // RIGHT
if ($missing->isMissing()) { }               // RIGHT - true for null or ""
if ($missing->isEmpty()) { }                 // RIGHT - true for null, "", and zeros
```

Loose `==` is the sneaky one: it passes for plain words like `"active"`
(nothing to encode) and fails the moment the value contains a quote or
an ampersand, so it can survive testing and break on real data.

### HTML tags print as text: the page shows a literal &lt;br&gt;

Markup passed through a regular (encoding) method gets encoded like any
other value:

```php
echo $address->append("<br>");      // WRONG - prints "12 High St<br>" as visible text
echo $address->appendHtml("<br>");  // RIGHT - markup stays markup
```

Markup goes through the HTML-aware methods (`appendHtml()`, `wrapHtml()`,
`nl2br()`, `rawHtml()`); everything else treats it as text to encode. See
[Encoding and HTML](encoding-and-html.md).

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

The other cause is a database that already contains encoded text, usually
from a form handler that encoded values before saving them. Whatever is
stored encoded gets encoded again on output. Save the raw text instead,
then clean up the existing rows with a one-time
`htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5)` (the flags
matter; the defaults leave `&apos;` behind).

### or() kept my zero / isEmpty() lost my zero

Working as designed, in both directions: `or()` treats zero as a real
value, not a missing one, and `isEmpty()` follows PHP's `empty()`, which
treats `0`, `"0"`, and `false` as empty. Pick the method that treats zero the way you want; the
[truth table](conditionals-and-error-checking.md#what-missing-means) shows
every combination.

## Other Issues

### "Method ->*methodName* needs brackets() everywhere and {curly braces} in strings"

**What happened:** A method was written like a field: brackets missing, or
a call inside a double-quoted string without curly braces. It's a PHP
warning, not an error: the page keeps running and the expression outputs
nothing, so it usually shows up as a blank spot where a chain should be.
When output goes blank, check the error log.

**Fix:** Fields never take brackets and work anywhere, strings included.
Methods always take brackets, plus curly braces around the call inside a
string; chains (more than one `->`) need the braces too:

```php
// fields - no brackets, work anywhere
echo $user->name;
echo "Hello $user->name";

// methods and chains - brackets everywhere, curly braces in strings
echo $name->trim();                  // works
echo "Hello {$name->trim()}";        // works
echo "Hello {$user->name->trim()}";  // works
echo $name->trim;                    // WRONG - written like a field, logs this warning
echo "Hello $name->trim()";          // WRONG - prints "Hello ()", logs this warning
echo "Hello $user->name->trim()";    // WRONG - prints "Hello Jean->trim()"
```

When in doubt, add the braces: `{$...}` works around anything in a string,
plain fields included.

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
format on output instead; when the stored data isn't yours to change, strip
the formatting in the chain first: `->pregReplace('/[^0-9.-]/', '')->add(50)`.

### "Call to a member function *methodName*() on string"

**What happened:** Something was chained after a method that ends the
chain. The encoding methods (`htmlEncode()`, `urlEncode()`, `jsonEncode()`,
`nl2br()`, `appendHtml()`, `wrapHtml()`) return a plain string rather than
another SmartString, so nothing can chain after them. That's on purpose:
once a value is encoded it's finished, which is what makes double encoding
impossible.

```php
echo $bio->nl2br()->or('No bio');   // throws - nl2br() returned a string
```

**Fix:** Do the conditional work first, encode last:

```php
echo $bio->or('No bio')->nl2br();
```

### ifZero() after percent() or numberFormat() never fires

Formatters return display text, and `ifZero()` only recognizes numeric
zeros, so `"0.00%"` and `"$0.00"` never match. Use the `percent()`
parameter, or detect zero on the raw value:

```php
echo $rate->percent(2, ifZero: 'N/A');  // percent's zero rule is a parameter
echo $price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!');  // match the formatted text
```

See [Run Conditionals Before Formatting](conditionals-and-error-checking.md#run-conditionals-before-formatting).

### "orRedirect(): headers already sent in /path/to/file.php on line 12"

**What happened:** `orRedirect()` redirects when the value is missing (null
or `""`), and redirects only work before any output has been sent - this
page had already sent some. The headers-sent check runs even when the value
is present, so the mistake shows up on the first request instead of waiting
for a missing value.

**Fix:** Move the guard above any output. The message names the file and
line where output started; the usual culprits are `echo` statements and
whitespace before `<?php`.

### CallerException Errors

**What happened:** A developer mistake, like a bad regex in
`pregReplace()`. The message says what's wrong, and the file and line
point at your call site rather than the library's internals, so the named
line is the one to fix. It's a normal `InvalidArgumentException` subclass,
so existing catch blocks work.

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

[← Documentation Index](README.md) | [← Prev: Method Reference](method-reference.md) | [Next: AI Reference →](ai-reference.md)
