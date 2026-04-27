# SmartString Changelog

## [2.6.3] - 2026-04-27
> **Bundled with CMS Builder v3.83**
> Roll-up release - every change from **v2.2.1 → v2.6.3** is now part of this version.

### Added
- `pregReplace()` - Apply regex search-and-replace, returning a new SmartString
- `textToHtml()` - Encodes special chars and converts newlines to `<br>` tags in one step
  - `textToHtml(keepBr: true)` preserves existing `<br>` tags (for CMS text fields that already store them)
- `apply()` now validates callback return types (must be scalar or null)
- Unknown-method errors now suggest the correct method when a common alias is used (e.g., `->truncate()` suggests `->maxChars()`, `->fallback()` suggests `->or()`)

### Changed
- `htmlEncode()` now encodes all tags including `<br>` (previously preserved `<br>` tags)
- Numeric operations now accept `string` type parameters for convenience
- Deprecation warnings now always trigger via `@trigger_error()`, controlled by PHP's `display_errors`
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

### Migration Tips
1. **`nl2br()` → `textToHtml()`** - The new method encodes *and* converts newlines. If you were chaining `->nl2br()` after manual encoding, you can simplify to just `->textToHtml()`.
2. **`htmlEncode()` and `<br>` tags** - If you relied on `htmlEncode()` preserving `<br>` tags, switch to `textToHtml(keepBr: true)`.
3. **`$treatNullAsZero` removed** - Null always stays null now. If you need zero, use `->ifNull(0)` before arithmetic.

---

## [2.2.0] - 2025-09-21
> **Bundled with CMS Builder v3.80**

### Added
- `orRedirect($url)` - Redirects to a URL if value is missing (null or ""), uses HTTP 302 Temporary Redirect

### Changed
- Minimum PHP version raised to 8.1 (from 8.0)

---

## [2.1.2] - 2025-04-29
> **Bundled with CMS Builder v3.76**
> Roll-up release - every change from **v2.0.2 → v2.1.1** is now part of this version.

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

### Migration Tips
1. **`percent()` precision** - now 0 decimal places by default; call `->percent(2)` for decimals
2. **Missing-value helpers** - if you previously treated `false` as "missing", update your checks or handle `false` explicitly

---

## [2.0.1] - 2024-12-09
> **Bundled with CMS Builder v3.75**
> Roll-up release - every change from **v1.3.1 → v2.0.1** is now part of this version.

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

---

## [1.3.0] - 2024-10-29
> **Bundled with CMS Builder v3.74**

### Added
- SmartArray class for handling arrays of SmartStrings

### Changed
- Refactored code and merged files for simplicity

### Deprecated
- `SmartString::new($array)` - use `SmartArray::new($array)` instead
- `SmartString::fromArray()` - use `SmartArray::new($array)` instead

---

## [1.2.1] - 2024-09-16
> **Bundled with CMS Builder v3.72**
> Roll-up release - every change from **v1.0.0 → v1.2.1** is now part of this version.

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

---

## [1.0.0] - 2024-08-27
- Initial release
