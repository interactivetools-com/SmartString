# SmartString Changelog

## [2.1.2] - 2025-04-29

> **Bundled with CMS Builder v3.76**  
> Roll-up release - every change from **v2.0.2 to v2.1.1** is now part of this version.

### Added
- **Methods & aliases**
  - `and()`, `andPrefix()` – conditional append / prepend.
  - `orDie()`, `or404()`, `orThrow()` – fail-fast helpers (die, 404 page, exception).
  - `isEmpty()`, `isNotEmpty()`, `isNull()`, `isMissing()` – value-inspection helpers.
  - `rawHtml()` – clearer alias for `value()`.
  - `if()` now accepts a `SmartString` object directly as its condition.
- **Configuration & logging**
  - `SmartString::$treatNullAsZero` – lets you decide if `null` should act like `0` in arithmetic.  
    **Default:** `false` (null stays null; chains short-circuit).
  - `SmartString::$logDeprecations` – enable/disable deprecation-warning logging (default `false`).
- Inline “Did you mean…?” hints for mistyped methods.
- Comprehensive tests and expanded help (verbose docs moved to `/src/help.txt`).

### Changed
- **Conditional helpers** – `or*()` / `and*()` treat only `null` or empty string `""` as missing;  
  `false` and `0` count as valid data.
- `percent()` now defaults to **0** decimal places and accepts an optional `$zeroFallback`.
- `or404()` now returns a full HTML 404 template (previously was plain text).
- `rawValue()` renamed → `getRawValue()` (old name still works).
- Deprecated `noEncode()` in favour of `rawHtml()`.
- Extensive documentation rewrites, stricter types, and internal refactors.

### Fixed
- Arithmetic functions now accept SmartNull (treated as null).
- Fixed Typo: `isZero()` → `ifZero()`
- Numerous minor bug fixes and optimisations.

### Migration Tips
1. `percent()` precision** – now 0 decimal points by default; call `->percent(2)` (etc.) for decimals.
2. **Missing-value helpers** – if you previously treated `false` as “missing”, update your checks or handle `false` explicitly.

---

## [2.0.1] - 2024-12-09

> **This version is bundled with CMS Builder v3.75

### Changed
- Switched SmartArray to a suggested dependency

### Notes
- Released with CMS Builder v3.75

## [2.0.0] - 2024-11-26

### Added
- New rawValue() static method for consistently extracting values from object or raw types

### Changed
- Updated all numeric operations to use new rawValue() method for consistent value extraction
- Merged all code into one file for easier inclusion in projects
- Misc code organization and other minor improvements

### Deprecated
- SmartString::new($array) - use SmartArray::new($array)->withSmartStrings() instead
- SmartString::fromArray() - use SmartArray::new($array)->withSmartStrings() instead
- stripTags() - use textOnly() instead

### Fixed
- dateTimeFormat now properly uses the dateFormat value

## [1.3.2] - 2024-11-07

- Fixed jsEscape() double escaping issue

## [1.3.1] - 2024-11-07

- Fixed dateTimeFormat() bug
- Improved error reporting messages
- Misc code and other minor improvements

## [1.3.0] - 2024-10-29

### Added
- SmartArray() class for handling arrays of SmartStrings, see: https://github.com/interactivetools-com/SmartArray

### Changed
- Refactored code and merged some files (multiple files didn't provide a memory or performance benefit)

### Deprecated
- SmartString::new($array): Use SmartArray::new($array) instead
- SmartString::fromArray(): Use SmartArray::new($array) instead

## [1.2.1] - 2024-09-16

### Added

- Numeric methods now accept null inputs, returning null instead of throwing errors 

## [1.2.0] - 2024-09-11

### Added

New Chainable Methods:

- if(\$condition, $valueIfTrue): Return valueIfTrue if condition is true, otherwise return the current value
- set(\$newValue): Assign a new value or expression result to the current object

### Fixed

- SmartString::$phoneFormat - fixed hard coded 1 in default 11-digit format

## [1.1.0] - 2024-09-11

### Added
 
- SmartString::fromArray(): Convert arrays to ArrayObjects of SmartStrings

New Chainable Methods:

- add(): Add a value to the current value
- multiply(): Multiply the current value by a given multiplier
- textOnly(): Remove HTML tags, decode entities, and trim the string
- maxWords(): Limit the string to a maximum number of words with optional ellipsis
- maxChars(): Limit the string to a maximum number of characters with optional ellipsis
- dateTimeFormat(): Format date and time using default or custom format
- phoneFormat(): Format phone numbers according to default rules

Customizable Defaults:

- Introduce ability to set custom defaults for various operations
- Can be defined at the top of your script or in an initialization file
- Example code: 
```php
SmartString::$numberFormatDecimal   = '.';             // Default decimal separator
SmartString::$numberFormatThousands = ',';             // Default thousands separator
SmartString::$dateFormat            = 'Y-m-d';         // Default dateFormat() format
SmartString::$dateTimeFormat        = 'Y-m-d H:i:s';   // Default dateTimeFormat() format
SmartString::$phoneFormat           = [                // Default phoneFormat() formats
    ['digits' => 10, 'format' => '(###) ###-####'],
    ['digits' => 11, 'format' => '# (###) ###-####'],
];
```

### Changed
- dateTime(): Now defaults to date-only format when no format is specified
- numberFormat(): Now uses default thousands separator and decimal only

### Deprecated
- stripTags(): Use textOnly() for improved functionality (removes tags, decodes entities, and trims)

### Removed
- createArrayObject(): Internal method removed. Use fromArray() method instead (same functionality)

## [1.0.0] - 2024-08-27
* Initial release
