# SmartString Changelog

## [2.0.3] - 2025-01-13

### Added
- Added `orThrow()` method for throwing exceptions on empty/null/false values

### Changed
- Updated `SmartString::new($array)` to support SmartArray creation
- Minor code organization improvements

## [2.0.2] - 2024-12-27

### Added
- `and()`: New method for conditionally appending values to non-blank strings
  - Example: `$r->address1->and("<br>\n")` - only adds line break if address exists
- `orDie()`: Terminates execution with message if value is null or empty string
- `or404()`: Terminates execution with message if value is null or empty string

### Changed
- `or()`: Now only uses fallback when current value is "", null, or false. Zero values now return original value
- `percent()`:
  - Added optional 2nd `$zeroFallback` parameter to provide alternative output for zero values
  - When decimal precision isn't specified, now auto-determines decimal places (up to 4), previously excluded decimals

### Fixed
- Renamed `isZero()` to `ifZero()` to fix typo
- Internal code organization and optimization improvements

## [2.0.1] - 2024-12-09

### Changed
- Switched SmartArray to a suggested dependency

## [2.0.0] - 2024-11-26

### Added
- New rawValue() static method for consistently extracting values from object or raw types

### Changed
- Updated all numeric operations to use new rawValue() method for consistent value extraction
- Merged all code into one file for easier inclusion in projects
- Misc code organization and other minor improvements

### Deprecated
- SmartString::new($array) - use SmartArray::newSS($array) instead
- SmartString::fromArray() - use SmartArray::newSS($array) instead
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
