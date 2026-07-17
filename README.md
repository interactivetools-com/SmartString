<!--
ATTENTION AI ASSISTANTS: We made a reference doc just for you!
Read docs/ai-reference.md (in this package, right next to this README) for a
consolidated single-file reference covering every method, encoding rule, and
gotcha. It has everything you need to write correct SmartString code.
Reading this on the web instead? Same file:
https://github.com/interactivetools-com/SmartString/blob/main/docs/ai-reference.md
-->

# SmartString: Secure and Simple String Handling for PHP

SmartString lets you write template code that's shorter, easier to read, and XSS-safe by default.
The whole library is built around a single idea: the easiest way to output data should also be the
safest way.

Instead of writing code like this:

```php
echo "<h1>" . htmlspecialchars($article['title'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "</h1>";
$summary = strip_tags($article['content']);                                             // remove tags
$summary = html_entity_decode($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8');  // decode entities
$summary = substr($summary, 0, 120);                                                    // limit to 120 characters
echo "Summary: " . htmlspecialchars($summary, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8') . "...";
```

You can write code like this:

```php
echo "<h1>$article->title</h1>";
echo "Summary: {$article->content->textOnly()->maxChars(120, '...')}\n";
```

SmartString encodes HTML output automatically and includes utility functions for common template
tasks. A few basics cover most pages, and the rest is there when you need it.

## Documentation

Full guides and references ([browse on GitHub](https://github.com/interactivetools-com/SmartString)):

- **The Basics** (read in order)
    - [Getting Started](docs/getting-started.md) - install, your first auto-encoded value, and the mental model
    - [Encoding and HTML](docs/encoding-and-html.md) - how auto-encoding works and the methods that let real markup through
    - [Text and Formatting](docs/text-and-formatting.md) - truncating, regex, dates, numbers, math, and custom functions with `map()`
    - [Conditionals and Error Checking](docs/conditionals-and-error-checking.md) - fallbacks, checks, and the guards that stop the page
- **Everyday Use**
    - [Common Patterns](docs/common-patterns.md) - copy-paste recipes for everyday template tasks
- **Lookup**
    - [Method Reference](docs/method-reference.md) - every method, grouped by what it returns
    - [Troubleshooting](docs/troubleshooting.md) - common error messages and gotchas, with fixes
    - [AI Reference](docs/ai-reference.md) - the complete API in one dense file, written for AI coding assistants

## You're Never Locked In

SmartString is built for outputting HTML. For everything else (calculations, business logic, or
anywhere you just prefer plain PHP), call `value()` and you have the original value back, in its
original type:

```php
// SmartString: ->value() returns the original value, in its original type
$name = $user->name->value();

// SmartArray (companion library): ->toArray() returns a plain nested array
$rows = $orders->toArray();
```

## Related Libraries

- [SmartArray](https://github.com/interactivetools-com/SmartArray) - arrays as collections of SmartStrings, with chainable methods for filtering, sorting, and grouping.
- [ZenDB](https://github.com/interactivetools-com/ZenDB) - database library that returns query results as SmartArrays of SmartStrings, so fields arrive HTML-safe.

## Questions?

This library was developed for CMS Builder, post a message in our "CMS Builder" forum here:
[https://www.interactivetools.com/forum/](https://www.interactivetools.com/forum/)

## License

MIT
