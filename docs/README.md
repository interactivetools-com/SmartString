# SmartString Documentation

Welcome to the SmartString docs. SmartString is a PHP string library whose
output is XSS-safe by default: values HTML-encode themselves in string
contexts, and chainable methods handle the everyday formatting around them.
New to SmartString? Read the first four pages in order; each builds on the
one before. The rest are standalone: open whichever matches your task.

## The Basics (read in order)

1. [Getting Started](getting-started.md) - Install, echo your first auto-encoded value, and the mental model: raw value for logic, encoded output for HTML.
2. [Encoding and HTML](encoding-and-html.md) - How auto-encoding works, URL and JSON encoding, and the named methods that let real markup through: `nl2br()`, `rawHtml()`, `appendHtml()`, `wrapHtml()`.
3. [Text and Formatting](text-and-formatting.md) - Stripping HTML, truncating, regex replacement, dates, numbers, percentages, math, and custom functions with `map()`.
4. [Conditionals and Error Checking](conditionals-and-error-checking.md) - Fallbacks with `or()`, targeted replacements, true/false checks, and the `or404()`/`orDie()`/`orThrow()`/`orRedirect()` guards.

## Everyday Use

- [Common Patterns](common-patterns.md) - Copy-paste recipes for everyday template tasks: dates, text previews, address blocks, report tables.

## Lookup

- [Method Reference](method-reference.md) - Every method in one place, grouped by what it returns.
- [Troubleshooting](troubleshooting.md) - Common error messages and behavior gotchas, with fixes.
- [AI Reference](ai-reference.md) - The complete API in one dense file, written for AI coding assistants.

---

[← Back to main README](../README.md)
