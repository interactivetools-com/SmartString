<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Method Reference

Every method, grouped by what it returns and how it treats missing values.
Each group heading links to the guide page that teaches those methods.

### [Basic Usage](getting-started.md)

```php
$str = SmartString::new("It's easy!<hr>");

echo $str;           // It&​apos;s easy!&​lt;hr&​gt; (HTML-encoded automatically)
echo $str->value();  // It's easy!<hr> (the original value)

echo $str->trim()->maxChars(60)->or('None');  // methods chain left to right
SmartString::help();                          // print a quick reference of all methods (works on values too: $str->help())
```

Here `$str` is an object, not a string. Whenever PHP needs it as a string (echo, print,
`"$str"`, concatenation), the object converts itself to its HTML-encoded value,
which is why echo above prints `It&apos;s easy!&lt;hr&gt;`. The `value()` method is the
escape hatch: it returns the original, unencoded value in its original type,
ready for regular PHP code.

### [Type Conversion](getting-started.md#converting-to-plain-php-types)

*These return the value as a plain PHP type, so they end the chain.*

| Method                       | Description                                                                                                                                                  |
|------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `->value()`                  | Returns the original, unencoded value in its original type - the escape hatch                                                                                |
| `->int()`                    | Returns the value as an integer                                                                                                                              |
| `->float()`                  | Returns the value as a float                                                                                                                                 |
| `->bool()`                   | Returns the value as a boolean                                                                                                                               |
| `->string()`                 | Returns the value as a string (original value, not HTML-encoded)                                                                                             |
| `SmartString::getRawValue()` | Returns the original value when you don't know what you have: Smart* objects are converted to their original value, everything else passes through unchanged |

### [Encoding](encoding-and-html.md)

*These return the encoded value as a plain string, so they end the chain. Missing values (null or "") return "", so echoing an empty field prints nothing.*

| Method           | Description                                                                                                                         |
|------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `->htmlEncode()` | Returns the value as an HTML-encoded string                                                                                         |
| `->urlEncode()`  | Returns the value as a URL-encoded string                                                                                           |
| `->jsonEncode()` | Returns the value as a JSON-encoded string (null encodes as `null`, malformed UTF-8 becomes � instead of throwing)                  |
| `->rawHtml()`    | Alias for `value()` - reads clearly when you're outputting trusted HTML on purpose (returns the original value, so null stays null) |
| `->nl2br()`      | HTML-encodes special chars, then converts newlines to `<br>` tags (unlike PHP's `nl2br()`, output is XSS-safe)                      |
| `->appendHtml($html)`         | HTML-encodes the value, then appends your trusted markup as-is                                                         |
| `->wrapHtml($before, $after)` | HTML-encodes the value, then wraps it in your trusted markup as-is                                                     |

### [String Manipulation](text-and-formatting.md)

*These return a new SmartString, so you can keep chaining. Missing values (null or "") come through unchanged, so a later `or()` fallback still works.*

| Method                                  | Description                                                                 |
|-----------------------------------------|-----------------------------------------------------------------------------|
| `->append($value)`                      | Adds `$value` to the end of the current value                               |
| `->prepend($value)`                     | Adds `$value` to the beginning of the current value                         |
| `->wrap($before, $after)`               | Wraps the value; pass "" for a side you don't want                          |
| `->textOnly()`                          | Removes HTML tags, decodes entities, and trims whitespace                   |
| `->trim()`                              | Trims whitespace (or the characters you specify) from both ends             |
| `->maxWords($max, $ellipsis = '...')`   | Limits the value to `$max` words; adds `$ellipsis` if text was cut off      |
| `->maxChars($max, $ellipsis = '...')`   | Limits the value to `$max` characters; adds `$ellipsis` if text was cut off |
| `->pregReplace($pattern, $replacement)` | Replaces text matching a regex pattern                                      |

### [Dates & Numbers](text-and-formatting.md#formatting-dates---dateformat)

*These return a new SmartString, so you can keep chaining. If the value is missing or not a valid date or number, the result is null - add `or()` after to show a fallback.*

| Method                                     | Description                                                                                   |
|--------------------------------------------|-----------------------------------------------------------------------------------------------|
| `->dateFormat($format = default)`          | Formats as a date or datetime, e.g. `dateFormat('Y-m-d H:i:s')`; default: `SmartString::$dateFormat`  |
| `->numberFormat($decimals = 0)`            | Formats the value as a number with thousands separators and `$decimals` decimal places        |
| `->percent($decimals = 0, $ifZero = null)` | Converts a decimal to a percentage, e.g. 0.24 becomes 24%; `$ifZero` is shown for zero values |
| `->percentOf($total, $decimals = 0)`       | Calculates what percentage the value is of `$total`, e.g. 25 of 200 is 12.5%                  |
| `->add($value)`                            | Adds `$value` to the current number                                                           |
| `->subtract($value)`                       | Subtracts `$value` from the current number                                                    |
| `->multiply($value)`                       | Multiplies the current number by `$value`                                                     |
| `->divide($divisor)`                       | Divides the current number by `$divisor`                                                      |

### [Conditional Replacement](conditionals-and-error-checking.md#fallbacks---or)

*These return a new SmartString, so you can keep chaining. Each swaps in a
replacement value when its condition matches - most commonly `or()`, to show a
default when a field is empty.*

| Method                               | Description                                                                                          |
|--------------------------------------|------------------------------------------------------------------------------------------------------|
| `->or($fallback)`                    | Replaces missing values (null or "") with `$fallback`; zero counts as present                        |
| `->ifNull($fallback)`                | Replaces null with `$fallback`                                                                       |
| `->ifZero($fallback)`                | Replaces zero with `$fallback`                                                                       |
| `->ifTrue($condition, $newValue)`    | Replaces the value with `$newValue` when your condition is truthy                                    |
| `->ifEquals($match, $newValue)`      | Replaces the value with `$newValue` when it loosely equals `$match` (==, so "5" matches 5)           |
| `->set($newValue)`                   | Replaces the value unconditionally - useful for storing the result of a match() or a calculation     |

### [Require a Value](conditionals-and-error-checking.md#requiring-a-value---or404-ordie-orthrow-orredirect)

*Use these for values that must exist, like a record ID from the URL. If the
value is missing (null or "") they stop the page; otherwise they do nothing and
the chain continues. Zero counts as present.*

| Method               | Description                                      |
|----------------------|--------------------------------------------------|
| `->orDie($text)`     | Outputs the message and exits                    |
| `->or404($text)`     | Outputs a 404 header and the message, then exits |
| `->orThrow($text)`   | Throws an Exception with the message             |
| `->orRedirect($url)` | Redirects to `$url` and exits                    |

### [Value Checks](conditionals-and-error-checking.md#truefalse-checks---isempty-isnotempty-ismissing-isnull)

*These return a plain true or false, typically used in if statements. Note that
zero is "empty" but not "missing" - pick the check that treats zero the way you
want.*

| Method           | Description                                                                          |
|------------------|--------------------------------------------------------------------------------------|
| `->isEmpty()`    | Returns true when the value is empty ("", null, false, 0, "0") - same as PHP empty() |
| `->isNotEmpty()` | Returns true when the value has content - the exact opposite of isEmpty()            |
| `->isMissing()`  | Returns true when the value is missing (null or ""); zero counts as present          |
| `->isNull()`     | Returns true when the value is null                                                  |

### [Custom Functions](text-and-formatting.md#custom-functions---map)

| Method                   | Description                                                                                                                 |
|--------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `->map($func, ...$args)` | Calls your function with the original value and returns the result as a new SmartString - runs even when the value is null |

**Working with arrays?** [SmartArray](https://github.com/interactivetools-com/SmartArray)
is the companion library that handles arrays (like database rows) as collections of
SmartStrings - it's where `SmartArray::new($array)->asHtml()` in the examples above
comes from, and it has its own
[method reference](https://github.com/interactivetools-com/SmartArray?tab=readme-ov-file#method-reference).

---

[← Documentation Index](README.md) | [← Prev: Common Patterns](common-patterns.md) | [Next: Troubleshooting →](troubleshooting.md)
