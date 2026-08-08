# SmartString Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

> **Bundled with CMS Builder v3.85**

The headlines: HTML-encoded output is ~4x faster than v2.7.0, method names now
match SmartArray (old names keep working), and new `wrap()`/`wrapHtml()`
replace the `if` statements around optional fields. Everything else is
hardening and fixes.

### Added

| Method                        | Returns     | Description                                                                                                                                                               |
|-------------------------------|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `appendHtml($html)`           | string      | Adds HTML after the value; missing values (null or "") return ""                                                                                                          |
| `ifEquals($match, $newValue)` | SmartString | Replaces the value on a loose match (`==`), for placeholder values: `->ifEquals('0000-00-00', null)`, `->ifEquals(-1, 'Unlimited')`                                       |
| `wrap($before, $after)`       | SmartString | Wraps the value only when present; missing (null or "") skips the wrapper. Both sides required; pass "" for a side you don't want                                         |
| `wrapHtml($before, $after)`   | string      | Like `appendHtml()` but adds HTML on both sides: `->wrapHtml('<h2>', '</h2>')` prints nothing when the value is missing, so you don't need an `if` around optional fields |

### Performance

- HTML-encoded output (echo and `htmlEncode()`) is ~4x faster than v2.7.0
  (six-field article page: 38.2 → 9.3 microseconds, both versions measured
  back to back on the same machine), and at least 2.5x faster than calling
  `htmlspecialchars()` yourself, up to 12x on Windows: values are scanned
  first and only encoded when something needs encoding. Output stays
  byte-identical to `htmlspecialchars()`. Benchmarks and verification:
  [docs/performance.md](docs/performance.md)

### Renamed

These renames give the library one consistent naming pattern (matching
SmartArray where methods overlap). Old names keep working - nothing to update.
IDEs like PHPStorm show them in strikethrough and offer a one-click rename.

| Old                        | New                        | Notes                                                                    |
|----------------------------|----------------------------|--------------------------------------------------------------------------|
| `and()`                    | `append()`                 |                                                                          |
| `andPrefix()`              | `prepend()`                |                                                                          |
| `apply()`                  | `map()`                    | matches `array_map()` and `SmartArray::map()`                            |
| `if()`                     | `ifTrue()`                 |                                                                          |
| `textToHtml()`             | `nl2br()`                  |                                                                          |
| `textToHtml(keepBr: true)` | (no new name - keep as-is) | preserves `<br>` tags already in the value; `nl2br()` takes no arguments |

### Parameter renames (named arguments only)

These only matter if you write parameter names in calls, e.g.
`->percent(2, ifZero: '-')` - calls using an old name fail with a clear
"Unknown named parameter" Error:

- `percent(ifZero:)` was `zeroFallback:`
- `ifTrue(newValue:)` was `valueIfTrue:`
- `or404()`/`orDie()`/`orThrow()` take `text:` (was `message:`)
- `map(callback:)` was `func:` - matches `array_map()` and `SmartArray::map()`

### Deprecated

These still work, they're just no longer featured in the docs - no changes required.

- `dateTimeFormat()` - same as `dateFormat()` with a different default; pass the format instead
- `ifBlank()` - use `or()`, which also covers null
- `phoneFormat()` - North-America formats by default; `pregReplace()` covers custom needs
- `help()` - the online docs replaced the built-in cheat sheet (`src/help.txt`); `help()` now prints doc links instead, and error messages say "see the SmartString docs" rather than suggesting it

### Removed

- Constructor/`new()` no longer accept a `$properties` array (carried an internal
  flag only; nothing outside the class used it)

### Behavior changes

- `nl2br()` ends the chain - it returns a string now, so echo it directly;
  anything chained after it (`->nl2br()->value()`) is an error - move those
  calls before it
- `percent()` and `percentOf()` format with your `$numberFormatDecimal` and
  `$numberFormatThousands` settings, same as `numberFormat()` - previously
  hardcoded '.' and ','
- `pregReplace()`:
  - passes `""` through unchanged, like null - previously an empty-matching
    pattern could turn a missing value into content, defeating a later `or()`
    fallback
  - throws on a bad pattern - was a PHP warning and a null result; now an
    InvalidArgumentException that includes PHP's compile error
- `dateFormat()` on booleans returns null - like any other value that isn't a date
- `textOnly()` converts non-breaking and other Unicode spaces to plain spaces
  (newlines and tabs untouched), so an "empty" WYSIWYG value like
  `<p>&nbsp;</p>` trims to `""` and a later `or()` fallback fires - previously
  it returned a single invisible character that counted as content
- `textOnly()` replaces invalid UTF-8 bytes with � like `maxChars()` and
  `maxWords()`, so its text is always valid UTF-8 and safe to store -
  previously bad bytes passed through unchanged
- Math: a failed step (missing value, non-numeric input, divide by zero) returns
  null, and a fallback like `or()` now fully recovers the chain - previously any
  math after the fallback still returned null:
  - `SmartString::new(null)->add(5)->or(10)->add(5)` returns 15 (was null)
  - `null`, `bool`, and `SmartNull` arguments no longer throw TypeError (same
    fix for the conditional methods)
- `print_r()` and `var_dump()` show just the stored value, keyed by the
  accessor that returns it (`[value] => Jean O'Brien`, see `->value()`) - no
  help line or added quotes/`TRUE`/`FALSE`/`NULL` annotations, so dumps read
  like native PHP. Matches the same change in SmartArray.
- `jsonEncode()` hardening:
  - malformed UTF-8 bytes become � instead of throwing - one corrupt byte no
    longer breaks the page (same fix for `json_encode($smartString)`, which
    returned false)
  - invisible Unicode (zero-width, bidi, tag chars) is re-escaped as visible
    `\uXXXX` so nothing can hide in page source - lossless, JavaScript sees
    the identical value
- `orDie()` and `or404()` exit with code 1 - CLI and cron scripts see the failure
- foreach over a SmartString throws a RuntimeException showing the value and
  suggesting the SmartArray row - previously the loop silently ran zero times

### Fixed

- `SmartString::help()` works as a static call (the documented form was a fatal error)
- `getRawValue()` unwraps `SmartArrayHtml` (previously "Unsupported value type")
- `maxChars()` and `maxWords()` handle invalid UTF-8 - bad bytes become � like
  `htmlEncode()` (previously a fatal TypeError)
- `textOnly()` keeps prose containing `<` ("Kids <12 eat free", "I <3 PHP") -
  only `<` followed by a letter, `/`, `!`, or `?` counts as a tag, the same rule
  browsers use (previously everything from the `<` to the next `>` or the end of
  the string was removed, whether the `<` arrived raw or entity-encoded)
- `maxChars()` works above 65535 characters - the word-boundary cut previously
  hit PCRE's quantifier limit above that, leaking a "Compilation failed" warning
  into the page or log and cutting mid-word
- `maxChars()` and `maxWords()` clamp negative limits to 0, showing just the
  ellipsis like a limit of 0 does - `maxChars(-5)` previously returned all but
  the last 5 characters via PHP's negative-length semantics, so a computed
  limit that went negative showed nearly the whole value
- `maxChars()` counts UTF-8 characters regardless of `mb_internal_encoding()` -
  that global can be changed by any include in the request (previously a
  non-UTF-8 setting made multibyte text falsely truncate or slice mid-character)
- `dateFormat()` formats date strings that parse to exactly the Unix epoch
  ("1970-01-01 00:00:00 UTC" previously returned null - timestamp 0 was
  mistaken for a parse failure)
- `dateFormat()` returns null for timestamps outside years 1000-9999, so
  `->or('Date not set')` fallbacks fire - a millisecond timestamp from
  JavaScript or an overflowed numeric previously formatted as a nonsense year
  like 55338
- INF and NAN store as null, like any other failed math step, so `or()`
  fallbacks fire - a float overflow previously printed "INF" into the page,
  made `json_encode()` return false, and made `jsonEncode()` throw
- `or404()` called mid-page discards the partial output and renders a clean
  404 page, without "Cannot modify header" warnings
- `percent(ifZero:)` and `trim()` accept SmartString arguments like every other
  value parameter (previously a TypeError under `strict_types`; in weak mode a
  SmartString `$ifZero` was stored HTML-encoded and double-encoded on output)
- `help()` prints plain text on the command line (previously wrapped the
  output in literal `<xmp>` tags, which only make sense in HTML)
- The deprecated `jsEncode()` shim escapes `<`, `>`, and `&` as `\uXXXX` instead
  of backslashing them, matching `jsonEncode()`. A backslash escape satisfies the
  JavaScript parser but leaves the raw character in the page, so a stored
  `</script>` still ended the script block early and the rest of the value parsed
  as HTML. `&` mattered the same way in inline event handlers, where the browser
  decodes entities before the JavaScript runs

### Other

- The `<xmp>` wrapper on `help()`'s web output escapes a literal `</xmp` as
  `<\/xmp`, same as CMSB's `xmp_safe()` - keeps the shared helper in sync
  with SmartArray's `debug()` fix (the help text itself is static)
- Misc internal code cleanup and modernization

## [2.6.3] - 2026-04-27

> **Bundled with CMS Builder v3.83**

### Added

- `pregReplace()` - Apply regex search-and-replace, returning a new SmartString
- `textToHtml()` - Encodes special chars and converts newlines to `<br>` tags in one step
    - `textToHtml(keepBr: true)` preserves existing `<br>` tags (for CMS text fields that already store them)
- `apply()` now validates callback return types (must be scalar or null)
- Unknown-method errors now suggest the correct method when a common alias is used (e.g., `->truncate()` suggests `->maxChars()`, `->fallback()` suggests `->or()`)

### Changed

- `htmlEncode()` now encodes all tags including `<br>` (previously preserved `<br>` tags)
- Numeric operations now accept `string` type parameters for convenience
- Deprecation warnings now always trigger via `@trigger_error()`, so custom error handlers (e.g. CMS Builder's developer log) can catch them
- Error and deprecation messages now show the actual calling file:line instead of the library internals

### Deprecated

- `nl2br()` - use `textToHtml()` instead (still works, logs deprecation warning)
- `SmartString::new($array)` - use `SmartArray::new($array)->asHtml()` instead

### Removed

- `SmartString::$treatNullAsZero` setting (null always stays null in numeric operations)
- `SmartString::$logDeprecations` setting (use PHP's native error handling instead)

### Fixed

- `dateFormat()` now formats timestamp `0` as a real date instead of returning null
- `maxWords()` no longer strips trailing punctuation when text isn't actually truncated
- `getRawValue()` missing match arm for `is_scalar()`
- Error messages now show the short class name (`SmartString->foo()`) on Linux instead of the full namespace
- `SmartString::fromArray()` deprecation shim now returns HTML-safe values matching the documented migration target
- `SmartString::rawvalue()` static alias now logs a deprecation warning
- `pregReplace()` now preserves null input, matching other string-manipulation methods
- `numberFormat()`, `percent()`, and `percentOf()` now require `int $decimals` (passing null previously crashed inside `number_format()`)
- `dateTimeFormat()` no longer double-wraps the result of `dateFormat()`
- `or()`, `and()`, `andPrefix()`, `ifBlank()`, `ifNull()`, and `ifZero()` now accept `null` and `bool` fallbacks (previously TypeErrored under strict types)

## [2.2.0] - 2025-09-21

> **Bundled with CMS Builder v3.80**

### Added

- `orRedirect($url)` - Redirects to a URL if value is missing (null or ""), uses a temporary (HTTP 302) redirect

### Changed

- Minimum PHP version raised to 8.1 (from 8.0)

## [2.1.2] - 2025-04-29

> **Bundled with CMS Builder v3.76**

### Added

- `and()`, `andPrefix()` - conditional append / prepend
- `orDie()`, `or404()`, `orThrow()` - fail-fast helpers (die, 404 page, exception)
- `isEmpty()`, `isNotEmpty()`, `isNull()`, `isMissing()` - value-inspection helpers
- `rawHtml()` - clearer alias for `value()`
- `if()` now accepts a `SmartString` object directly as its condition
- `SmartString::$treatNullAsZero` - lets you decide if `null` should act like `0` in arithmetic (default: `false`)
- `SmartString::$logDeprecations` - enable/disable deprecation-warning logging (default `false`)
- Inline "Did you mean...?" hints for mistyped methods

### Changed

- `or*()` / `and*()` treat only `null` or empty string `""` as missing; `false` and `0` count as valid data
- `percent()` now defaults to **0** decimal places and accepts an optional `$zeroFallback`
- `or404()` now returns a full HTML 404 template (previously was plain text)
- `rawValue()` renamed → `getRawValue()` (old name still works)
- Deprecated `noEncode()` in favor of `rawHtml()`

### Fixed

- Arithmetic functions now accept SmartNull (treated as null)
- Fixed typo: `isZero()` → `ifZero()`

## [2.0.1] - 2024-12-09

> **Bundled with CMS Builder v3.75**

### Added

- `rawValue()` static method for consistently extracting values from object or raw types

### Changed

- Updated all numeric operations to use `rawValue()` for consistent value extraction
- Merged all code into one file for easier inclusion in projects
- Switched SmartArray to a suggested dependency

### Deprecated

- `SmartString::new($array)` - use `SmartArray::new($array)->withSmartStrings()` instead
- `SmartString::fromArray()` - use `SmartArray::new($array)->withSmartStrings()` instead
- `stripTags()` - use `textOnly()` instead

### Fixed

- `dateTimeFormat()` now properly uses the `dateFormat` value
- Fixed `jsEscape()` double escaping issue
- Improved error reporting messages

## [1.3.0] - 2024-10-29

> **Bundled with CMS Builder v3.74**

### Added

- SmartArray class for handling arrays of SmartStrings

### Changed

- Refactored code and merged files for simplicity

### Deprecated

- `SmartString::new($array)` - use `SmartArray::new($array)` instead
- `SmartString::fromArray()` - use `SmartArray::new($array)` instead

## [1.2.1] - 2024-09-16

> **Bundled with CMS Builder v3.72**

### Added

- `SmartString::fromArray()` - Convert arrays to ArrayObjects of SmartStrings
- New chainable methods: `if()`, `set()`, `add()`, `multiply()`, `textOnly()`, `maxWords()`, `maxChars()`, `dateTimeFormat()`, `phoneFormat()`
- Customizable defaults for date formats, number formats, and phone formats
- Numeric methods now accept null inputs, returning null instead of throwing errors

### Changed

- `dateFormat()` now defaults to date-only format when no format specified
- `numberFormat()` now uses default thousands separator and decimal

### Deprecated

- `stripTags()` - use `textOnly()` instead

### Fixed

- `SmartString::$phoneFormat` - fixed hard coded 1 in default 11-digit format

## [1.0.0] - 2024-08-27

- Initial release
