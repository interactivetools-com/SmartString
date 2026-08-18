# SmartString Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - 2026-08-16

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

- **HTML-encoded output is ~4x faster** than v2.7.0 (six-field article
  page: 38.2 → 9.3 microseconds), and at least 3x faster than calling
  `htmlspecialchars()` yourself: values are scanned first and only encoded
  when something needs encoding. Output stays byte-identical. Benchmarks
  and verification: [docs/performance.md](docs/performance.md)

### Renamed

These renames give the library one consistent naming pattern (matching
SmartArray where methods overlap). Old names keep working - nothing to update.
IDEs like PHPStorm show them in strikethrough and offer a one-click rename.

| Old                        | New                        | Notes                                                                    |
|----------------------------|----------------------------|--------------------------------------------------------------------------|
| `and()`                    | `append()`                 |                                                                          |
| `andPrefix()`              | `prepend()`                |                                                                          |
| `apply()`                  | `map()`                    | matches `array_map()` and `SmartArray::map()`                            |
| `if()`                     | `ifTrue()`                 | the parameter is `newValue:` on `ifTrue()`; `if()` keeps `valueIfTrue:`  |
| `textToHtml()`             | `nl2br()`                  |                                                                          |
| `textToHtml(keepBr: true)` | (no new name - keep as-is) | preserves `<br>` tags already in the value; `nl2br()` takes no arguments |

### Parameter renames (named arguments only)

These only matter if you write parameter names in calls, e.g.
`->percent(2, ifZero: '-')` - calls using an old name fail with a clear
"Unknown named parameter" Error:

- `percent(ifZero:)` was `zeroFallback:`
- `or404()`/`orDie()`/`orThrow()` take `text:` (was `message:`)
- `map(callback:)` was `func:` - matches `array_map()` and `SmartArray::map()`

### Deprecated

These still work, they're just no longer featured in the docs - no changes required.

- `dateTimeFormat()` - same as `dateFormat()` with a different default; pass the format instead
- `ifBlank()` - use `or()`, which also covers null
- `phoneFormat()` - North-America formats by default; `pregReplace()` covers custom needs
- `help()` - the online docs replaced the built-in cheat sheet; it now prints doc links instead

### Behavior changes

- **`nl2br()` ends the chain** - it returns a string now, so echo it
  directly and move other calls before it. See
  [UPGRADING.md](UPGRADING.md)
- **Math chains recover after a fallback** -
  `SmartString::new(null)->add(5)->or(10)->add(5)` returns 15 (was null);
  null, bool, and SmartNull arguments no longer throw
- **`percent()` and `percentOf()` use your number-format settings** - same
  as `numberFormat()`; previously hardcoded `.` and `,`
- **`textOnly()` output is always clean text** - Unicode spaces become
  plain spaces, so an "empty" WYSIWYG value like `<p>&nbsp;</p>` trims to
  `""` and `or()` fallbacks fire, and invalid UTF-8 bytes become �
- **`jsonEncode()` survives hostile input** - malformed UTF-8 becomes �
  instead of throwing, and invisible Unicode (zero-width, bidi, tag chars)
  is re-escaped as visible `\uXXXX` - lossless, JavaScript sees the
  identical value
- **`orDie()` and `or404()` exit with status 1** - CLI and cron scripts
  see the failure

### Fixed

- **No more double-encoded arguments** - SmartString arguments unwrap
  everywhere (`new()`, the guard messages, `orRedirect()`,
  `pregReplace()`, `appendHtml()`/`wrapHtml()`), so pages no longer show
  `Bob &amp; Sons` as literal text; a SmartNull argument counts as null
- **`textOnly()` keeps prose containing `<`** - "Kids <12 eat free"
  survives; only `<` starting a real tag counts, the same rule browsers
  use
- **`maxChars()` and `maxWords()` are more robust** - they handle invalid
  UTF-8, values over 65535 characters, and negative limits, and ignore
  `mb_internal_encoding()` changes from other includes
- **`dateFormat()` edge cases** - the exact Unix epoch formats as a date,
  while booleans and timestamps outside years 1000-9999 return null, so
  `or()` fallbacks fire instead of printing a nonsense year like 55338
- **Errors name your file, not the library's** - including calls routed
  through a missing SmartArray field, and error messages HTML-encode
  caller-supplied names (dynamic names can carry request data)

### Minor

Also: `pregReplace()` returns null on PCRE runtime limits so `or()`
fallbacks fire, throws clearly on broken patterns, and passes `""`
through unchanged; `percent()` overflow and INF/NAN store as null instead
of printing `inf`; `orRedirect()` throws on a blank URL so a
misconfigured redirect fails on the first request; `or404()` mid-page
renders a clean 404 without header warnings; `foreach` over a SmartString
throws instead of silently looping zero times; `print_r()` and
`var_dump()` show just the stored value; the deprecated `jsEncode()` shim
escapes `<` `>` `&` as `\uXXXX` so a stored `</script>` can't end the
script block; the constructor no longer takes a `$properties` array; and
a dozen more small fixes (static `help()`, CLI-friendly output,
SmartString-typed arguments, clearer setup errors).

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
