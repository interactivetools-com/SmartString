# SmartString Changelog

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
