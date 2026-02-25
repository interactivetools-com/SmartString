# SmartString: Secure String Handling for PHP

SmartString wraps every value in an object that HTML-encodes
automatically when you echo it. You get XSS protection by default,
chainable formatting methods, and no manual calls to
`htmlspecialchars()`.

## What it protects you from

Instead of writing code like this:

```php
echo "<h1>" . htmlspecialchars($article['title'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "</h1>";
$summary = strip_tags($article['content']);
$summary = html_entity_decode($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8');
$summary = substr($summary, 0, 200);
echo "Summary: " . htmlspecialchars($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "...";
```

You write code like this:

```php
echo "<h1>$article->title</h1>";
echo "Summary: {$article->content->textOnly()->maxChars(120, '...')}\n";
```

**The easiest way to write your output code is also the safest way.**
SmartString handles encoding so you can focus on what you're building.

## 30-Second Quickstart

```bash
composer require itools/smartstring
```

```php
use Itools\SmartString\SmartString;

$name = SmartString::new("Jean O'Brien");
echo "Hello, $name!";              // Hello, Jean O&apos;Brien!
echo $name->value();               // Jean O'Brien (original value)
echo $name->trim()->maxWords(1);   // Jean...
```

## Documentation

| Guide                                                                                | Description                                                    |
|--------------------------------------------------------------------------------------|----------------------------------------------------------------|
| [Getting Started](docs/01-quickstart.md)                                             | Installation, first SmartString, chaining, configuration       |
| [Philosophy & Safety](docs/02-philosophy-and-safety.md)                              | Why auto-encoding matters, XSS prevention by design            |
| [Values & Encoding](docs/03-values-and-encoding.md)                                  | value(), rawHtml(), htmlEncode(), urlEncode(), jsonEncode()     |
| [Text & Formatting](docs/04-text-and-formatting.md)                                  | textOnly(), trim(), maxWords(), dateFormat(), numberFormat()    |
| [Conditionals & Error Handling](docs/05-conditionals-and-error-handling.md)           | or(), and(), ifBlank(), orDie(), or404(), orThrow()             |
| [Numeric Operations & Math](docs/06-numeric-operations-and-math.md)                  | add(), subtract(), multiply(), divide(), percent()             |
| [Troubleshooting & Gotchas](docs/07-troubleshooting-and-gotchas.md)                  | Common mistakes, debugging, help()                             |

You can also [browse the documentation on GitHub](https://github.com/interactivetools-com/SmartString/tree/main/docs).

## When you might NOT want SmartString

- You need raw string performance with no object overhead
- Your output never touches HTML (CLI tools, binary protocols)
- You already use a template engine with auto-escaping (Twig, Blade)
- You need deep multibyte string analysis (grapheme iteration,
  Unicode normalization) -- SmartString focuses on output safety,
  not linguistic parsing

## Quick Reference

**Values and Encoding** (return raw values)

- `->value()` - Original value with original type
- `->rawHtml()` - Alias for value(), reads better for trusted HTML output
- `->htmlEncode()` - HTML-encoded string (same as auto-encoding)
- `->textToHtml()` - Encode special chars and convert newlines to `<br>`
- `->urlEncode()` - URL-encoded string for query parameters
- `->jsonEncode()` - JSON-encoded value safe for embedding in HTML

**Type Conversion** (return raw values)

- `->string()` - Value as string (no HTML encoding)
- `->int()` - Value as integer
- `->float()` - Value as float
- `->bool()` - Value as boolean

**Text and Formatting** (return SmartString, chainable)

- `->textOnly()` - Strip HTML tags, decode entities, trim whitespace
- `->trim()` - Trim whitespace (same parameters as PHP `trim()`)
- `->maxWords($max)` - Limit word count, appends `...` if truncated
- `->maxChars($max)` - Limit character count, appends `...` if truncated
- `->numberFormat($decimals)` - Format with thousands separators
- `->dateFormat($format)` - Format date string or timestamp
- `->dateTimeFormat($format)` - Format date with time
- `->phoneFormat()` - Format phone number digits

**Conditionals** (return SmartString, chainable)

- `->or($fallback)` - Replace if missing (null or `""`)
- `->and($suffix)` - Append if present
- `->andPrefix($prefix)` - Prepend if present
- `->ifBlank($fallback)` - Replace if empty string
- `->ifNull($fallback)` - Replace if null
- `->ifZero($fallback)` - Replace if numeric zero
- `->if($condition, $value)` - Replace if condition is truthy
- `->set($value)` - Unconditionally replace the value

**Validation** (return bool)

- `->isEmpty()` - True if empty (uses PHP `empty()`)
- `->isNotEmpty()` - True if not empty
- `->isMissing()` - True if null or `""`
- `->isNull()` - True if null

**Error Handling** (return $this or exit)

- `->or404($message)` - Send 404 and exit if missing
- `->orDie($message)` - Output message and exit if missing
- `->orThrow($message)` - Throw RuntimeException if missing
- `->orRedirect($url)` - Redirect (302) if missing

**Math** (return SmartString, chainable)

- `->add($n)` - Add
- `->subtract($n)` - Subtract
- `->multiply($n)` - Multiply
- `->divide($n)` - Divide (zero returns null)
- `->percent($decimals)` - Convert ratio to percentage display
- `->percentOf($total, $decimals)` - Value as percentage of total

**Utilities**

- `->apply($callback)` - Apply any callable to the value
- `->help()` - Display all methods with examples
- `SmartString::getRawValue($v)` - Unwrap Smart* objects to raw values

## Related Libraries

- [SmartArray](https://github.com/interactivetools-com/SmartArray) - Enhanced collections with chainable methods and SmartString integration
- [ZenDB](https://github.com/interactivetools-com/ZenDB) - Injection-proof PHP/MySQL database layer returning SmartArrays of SmartStrings

## Questions?

Post a message in our [forum](https://www.interactivetools.com/forum/).

## License

MIT
