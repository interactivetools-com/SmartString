# Upgrading SmartString

Most old code keeps working after an upgrade:

- **Renamed methods keep their old names forever.** IDEs like PHPStorm show
  the old name in strikethrough with a one-click rename.
- **Breaking changes produce clear errors.** Removed methods and wrong named
  arguments throw an Error with your file and line, and deprecated calls log
  their exact replacement, e.g. "Replace ->stripTags() with ->textOnly() in
  listings.php:14".
- **Only the silent changes need checking.** This file lists them per
  version, each with a search.

Full lists of what changed per release: [CHANGELOG.md](CHANGELOG.md).

---

## v3.0.0

*Follow this section when upgrading from SmartString before v3.0.0
(or CMS Builder before 3.85).*

### Chains after `->nl2br()` or `->textToHtml()`

> These two methods used to return a SmartString you could keep chaining.
> They now return a plain string, so any call chained after them is fatal
> ("Call to a member function ... on string"):
>
> ```php
> $text->nl2br()->maxChars(100);   // fatal: nl2br() returns a string
> $text->maxChars(100)->nl2br();   // correct
> ```
>
> Fix:
>
> - Search `->nl2br(` and `->textToHtml(` and make sure each is the last
>   call in its chain (no `->` after it)
>
> Regex: `->(nl2br|textToHtml)\([^)]*\)->` matches only the calls that need fixing

### Parameter renames (named arguments only)

> PHP lets you write a parameter's name right in the call - the `ifZero:`
> part in `->percent(2, ifZero: '-')`. If you never do this, skip this
> check. If you do, a few parameter names changed, and calls using an old
> name fail with a clear "Unknown named parameter" Error:
>
> ```php
> ->percent(2, '-')                 // no parameter name - nothing changes
> ->percent(2, zeroFallback: '-')   // before
> ->percent(2, ifZero: '-')         // after (dropping the name works too)
>
> ->orDie(message: 'Not found')     // before (same for or404, orThrow)
> ->orDie(text: 'Not found')        // after
> ```
>
> Fix:
>
> - Search `zeroFallback:` and replace with `ifZero:`
> - Search `message:` and replace with `text:` on or404/orDie/orThrow calls
>
> Regex: `->percent\([^)]*zeroFallback:|->(orDie|or404|orThrow)\(\s*message:`

### Silent changes

> - `percent()` and `percentOf()` now use your `$numberFormatDecimal` and
> `$numberFormatThousands` settings like `numberFormat()` does - if you
> customize those, percentages change format too (previously hardcoded
> '.' and ',')
> - A fallback now recovers a failed math chain:
> `SmartString::new(null)->add(5)->or(10)->add(5)` returns 15 (was null).
> Only affects code that relied on null carrying past a mid-chain fallback.
> - `dateFormat()` on a boolean returns null (was undefined behavior) - a
> later `or()` fallback shows instead of a date
> - `orDie()` exits with code 1 (was 0) - cron and CLI wrappers that check
> exit codes now see the failure
> - `pregReplace()` with an invalid pattern throws an exception naming your
> file and line (was a PHP warning and a null result)
> - Constructor and `new()` no longer accept a `$properties` array (carried
> an internal flag; nothing outside the class used it)

## v2.6.3

*Follow this section when upgrading from SmartString before v2.6.3
(or CMS Builder before 3.83). Requires PHP 8.1+.*

### `<br>` tags encode like any other tag

> `<br>` in method arguments or data now prints as literal text. This is the
> only rendering change - all other output is identical, so these swaps are
> the complete check:
>
> | Before                                  | After                                   |
> |-----------------------------------------|-----------------------------------------|
> | `->and(",<br>\n")`                      | `->appendHtml(",<br>\n")`               |
> | `->andPrefix("<br>Tel: ")`              | `->wrapHtml('<br>Tel: ', '')`           |
> | `echo $field` where the data has `<br>` | `echo $field->textToHtml(keepBr: true)` |
>
> Regex: `->\w+\(['"][^'"]*<br` finds `<br` inside method arguments

### Removed settings (only existed v2.1.2 - v2.6.2)

> Both settings are gone, and leftovers fail loudly ("Access to undeclared
> static property") - if your pages load, you're clean.
>
> Fix:
>
> - Search `treatNullAsZero` - remove it; use `->ifNull(0)` before math instead
> - Search `logDeprecations` - remove it; notices always trigger now

### Silent changes

> - `dateFormat()` on the number 0 formats it as a real epoch date
> (1969-12-31 or 1970-01-01 depending on timezone) instead of returning
> null - fields holding 0 print a date instead of the `or()` fallback
> - `numberFormat()`, `percent()`, and `percentOf()` require an int for
> `$decimals` - passing an explicit null now errors instead of acting as 0

## v2.1.2

*Follow this section when upgrading from SmartString before v2.1.2
(or CMS Builder before 3.76).*

### `isZero()` renamed to `ifZero()`

> A typo fix with no alias: `->isZero()` is a fatal "Call to undefined
> method" Error (the message suggests the fix).
>
> Fix:
>
> - Search `->isZero(` and replace with `->ifZero(`

### Silent changes

> - `or()` and `and()` treat only null and `""` as missing - 0 and false now
> come through as real values instead of being replaced. Review `or()`
> fallbacks on fields that can legitimately hold 0 or false; use
> `ifTrue()` or `isEmpty()` where PHP-style emptiness is what you want.
> - `percent()` shows 0 decimal places by default - pass `percent(2)` to
> keep decimals

## v2.0.0

*Follow this section when upgrading from SmartString before v2.0.0
(or CMS Builder before 3.75).*

> No required changes: every v1 method name still works and logs its
> replacement to the error log. Renaming is optional cleanup:
>
> | Old name (still works)     | Current name                                                                                                  |
> |----------------------------|---------------------------------------------------------------------------------------------------------------|
> | `->stripTags()`            | `->textOnly()`                                                                                                |
> | `->toString()`             | `->htmlEncode()` or `->string()`                                                                              |
> | `->noEncode()`             | `->rawHtml()`                                                                                                 |
> | `->jsEncode()`             | `->jsonEncode()` - different output, refactor when convenient; the old name keeps the old behavior until then |
> | `SmartString::fromArray()` | `SmartArrayHtml::new()`                                                                                       |

---

*End of upgrade notes. There is nothing older to check: SmartString wasn't
bundled with CMS Builder before v3.72, and v1.x needs only the optional
renames above plus the sections that apply to it higher up.*
