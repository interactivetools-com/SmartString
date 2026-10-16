# SmartString Changelog

> **Upgrading?** See [UPGRADING.md](UPGRADING.md) for the checks that matter,
> per version - tagged releases roll up every change since the previous tag.
> Versions bundled with CMS Builder are marked on their sections.

## [3.0.0] - [UNRELEASED]

> **Bundled with CMS Builder v3.85**

The headlines: HTML-encoded output is ~4x faster than v2.7.0, method names now
match SmartArray (old names keep working), and new `wrap()`/`wrapHtml()`
replace the `if` statements around optional fields. Everything else is
security and bug fixes.

### Security

- **`jsEncode()` output can't end a script block** - a crafted stored
  value could close the `<script>` tag, so the rest of it ran as HTML.
  `jsEncode()` is deprecated; use `jsonEncode()`

### Added

| Method                        | Returns     | Description                                                                                            |
|-------------------------------|-------------|--------------------------------------------------------------------------------------------------------|
| `appendHtml($html)`           | string      | Adds HTML after the value; returns "" when the value is missing (null or "")                           |
| `ifEquals($match, $newValue)` | SmartString | Replaces the value on a loose match (`==`): `->ifEquals('0000-00-00', null)`                           |
| `set404Handler($callback)`    | ?Closure    | Static. Shows your own page when `or404()` fires; SmartArray has its own, so set both                  |
| `wrap($before, $after)`       | SmartString | Wraps the value only when it's present; pass "" for a side you don't want                              |
| `wrapHtml($before, $after)`   | string      | Adds HTML on both sides, or prints nothing when the value is missing, so optional fields need no `if`  |

### Performance

- **HTML-encoded output is ~4x faster** than v2.7.0, and at least 3x
  faster than calling `htmlspecialchars()` yourself. Output is unchanged.
  Details: [docs/performance.md](docs/performance.md)

### Renamed

Old names keep working - nothing to update. IDEs like PHPStorm show them in
strikethrough and offer a one-click rename.

| Old                        | New                        | Notes                                      |
|----------------------------|----------------------------|--------------------------------------------|
| `and()`                    | `append()`                 |                                            |
| `andPrefix()`              | `prepend()`                |                                            |
| `apply()`                  | `map()`                    | like `array_map()` and `SmartArray::map()` |
| `if()`                     | `ifTrue()`                 | by name, the parameter is `newValue:`      |
| `textToHtml()`             | `nl2br()`                  |                                            |
| `textToHtml(keepBr: true)` | (no new name - keep as-is) | keeps `<br>` tags already in the value     |

### Parameter renames (named arguments only)

Only matters if you pass these by name; an old name throws an "Unknown
named parameter" Error. See [UPGRADING.md](UPGRADING.md)

- `percent(ifZero:)` was `zeroFallback:`
- `map(callback:)` was `func:`

### Deprecated

These still work - no changes required.

- `dateTimeFormat()` - use `dateFormat()` and pass the format
- `ifBlank()` - use `or()`, which also covers null
- `phoneFormat()` - use `pregReplace()`
- `help()` - prints links to the docs on GitHub

### Behavior changes

- **`nl2br()` ends the chain** - it returns a string, so it must be the
  last call. See [UPGRADING.md](UPGRADING.md)
- **Math chains recover after a fallback** -
  `SmartString::new(null)->add(5)->or(10)->add(5)` returns 15 (was null)
- **`percent()` and `percentOf()` use your number-format settings**, like
  `numberFormat()`
- **`textOnly()` treats `<p>&nbsp;</p>` as empty** - non-breaking and
  other Unicode spaces become plain spaces, so `or()` fallbacks fire on
  blank WYSIWYG fields
- **`orDie()` and `or404()` exit with status 1**, so CLI and cron scripts
  see the failure
- **`jsonEncode()` escapes every non-ASCII character** - `café` comes out
  as `"caf\u00e9"`. JavaScript gets the same string
  as before

### Fixed

- **No more double-encoded arguments** - passing a SmartString as an
  argument no longer shows `Bob &amp; Sons` on the page
- **`textOnly()` keeps a `<` that isn't a tag** - "Kids <12 eat free"
  stays intact
- **Invalid UTF-8 doesn't break text methods** - `textOnly()` replaces bad
  bytes with �, the same as `maxChars()` and `maxWords()`
- **`dateFormat()` returns null for values that aren't dates** - booleans
  and out-of-range timestamps no longer print a year like 55338, so `or()`
  fallbacks fire
- **Errors name your file and line**, not the library's

### Minor

Also: `pregReplace()` passes `""` through unchanged and returns null when
PCRE hits a limit, `int()` caps out-of-range floats at `PHP_INT_MAX` or
`PHP_INT_MIN`, `orRedirect()` throws on a blank URL, `or404()` works
mid-page, `foreach` over a SmartString throws, `print_r()` and
`var_dump()` show just the stored value, and error messages HTML-encode
the names they print.

## [2.7.0] - 2026-07-07

### Security

- `jsonEncode()` now substitutes malformed UTF-8 bytes with � (U+FFFD) instead of throwing JsonException, so one corrupt byte in a value no longer breaks the whole page
- `jsonEncode()` now re-escapes invisible Unicode (zero-width chars, bidi controls, Unicode tag chars, variation selectors) as visible \uXXXX escapes so nothing can hide in page source. Lossless: each escape decodes back to the identical character, so the value JavaScript sees never changes
- `json_encode($smartString)` now also substitutes malformed UTF-8 with � instead of returning false, matching `jsonEncode()`

### Changed

- `or404()`, `orDie()`, `orThrow()` message parameter renamed to `$text` and documented as HTML-encoded before output

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
