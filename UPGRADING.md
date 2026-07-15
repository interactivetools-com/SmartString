# Upgrading SmartString

The checks that matter when upgrading. Renamed and retired methods don't need
any changes - old names keep working, and IDEs like PHPStorm show them in
strikethrough (renames also get a one-click fix).

Full lists of what changed per release: [CHANGELOG.md](CHANGELOG.md).

---

## v3.0.0

*Not yet bundled with CMS Builder*

### `<br>` tags encode like any other tag

> Changed in v2.6.3; affects code written for v2.2.x or earlier. `<br>` in
> method arguments or data prints as literal text. Swaps:
>
> | Before                                  | After                                   |
> |-----------------------------------------|-----------------------------------------|
> | `->and(",<br>\n")`                      | `->appendHtml(",<br>\n")`               |
> | `->andPrefix("<br>Tel: ")`              | `->wrapHtml('<br>Tel: ', '')`           |
> | `echo $field` where the data has `<br>` | `echo $field->textToHtml(keepBr: true)` |
>
> Search: `rg -n "->\w+\(['\"][^'\"]*<br" --type php`

### Chains after `->nl2br()`

> `nl2br()` returns an HTML-safe string, not a SmartString, so calls chained
> after it are fatal ("Call to a member function ... on string"). Make
> `nl2br()` the last call:
>
> ```php
> $text->nl2br()->maxChars(100);   // fatal: nl2br() returns a string
> $text->maxChars(100)->nl2br();   // correct
> ```
>
> Search: `rg -n "->nl2br\(\)->" --type php`

### Parameter renames (named arguments only)

> Named arguments are the PHP 8 style where you write the parameter name in
> the call. If you pass values positionally, nothing changes.
>
> ```php
> ->percent(2, '-')                 // no parameter name - nothing changes
> ->percent(2, zeroFallback: '-')   // before
> ->percent(2, ifZero: '-')         // after (dropping the name works too)
>
> ->orDie(message: 'Not found')     // before (same for or404, orThrow)
> ->orDie(text: 'Not found')        // after
>
> ->ifTrue($cond, valueIfTrue: 'Yes')  // before
> ->ifTrue($cond, newValue: 'Yes')     // after
> ```

### Minor changes

> - `pregReplace()` with an invalid pattern now throws InvalidArgumentException
    > (was a PHP warning and a null result)
> - `orDie()` exits with code 1 (was 0) - cron and CLI wrappers that check exit
    > codes now see the failure
> - Constructor and `new()` no longer accept a `$properties` array (carried an
    > internal flag; nothing outside the class used it)
