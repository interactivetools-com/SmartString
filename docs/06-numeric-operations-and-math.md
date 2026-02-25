# Numeric Operations and Math

SmartString provides chainable arithmetic and percentage methods
that handle null propagation and type coercion automatically.

## How Numeric Operations Work

All arithmetic methods accept `int`, `float`, `string`,
`SmartString`, or `SmartNull` as their argument. Values are
converted to float internally via `getFloatOrNull()`, which uses
PHP's `is_numeric()` check — so you can freely mix raw numbers
with SmartString objects in the same expression.

Non-numeric values produce null. Strings like `"cat"` or `"1,234"`
(the comma makes it non-numeric to PHP) are not coerced — they
return null immediately. Every method returns a new SmartString,
so you can chain operations together.

### Quick reference

| Method | Returns | Null/non-numeric result |
|--------|---------|------------------------|
| `add($n)` | current + $n | null |
| `subtract($n)` | current - $n | null |
| `multiply($n)` | current * $n | null |
| `divide($n)` | current / $n | null (also for $n = 0) |
| `percent($dec, $zero)` | value * 100 with `%` | null |
| `percentOf($total, $dec)` | (value / $total) * 100 with `%` | null |

## Arithmetic

### Adding values — `add()`

`add()` returns a SmartString containing the sum of the current
value and the addend.

```php
$price = SmartString::new(100);

echo $price->add(50);
// 150

echo $price->add(25.99);
// 125.99
```

You can pass a SmartString as the argument:

```php
$price    = SmartString::new(100);
$shipping = SmartString::new(12.50);

echo $price->add($shipping);
// 112.5
```

### Subtracting values — `subtract()`

`subtract()` returns a SmartString containing the current value
minus the subtrahend.

```php
$price = SmartString::new(100);

echo $price->subtract(30);
// 70
```

### Multiplying values — `multiply()`

`multiply()` returns a SmartString containing the current value
times the multiplier.

```php
$price = SmartString::new(100);

echo $price->multiply(1.1);
// 110
```

### Dividing values — `divide()`

`divide()` returns a SmartString containing the current value
divided by the divisor. If the divisor is zero, it returns null
and sets the numeric error flag.

```php
$price = SmartString::new(100);

echo $price->divide(4);
// 25

echo $price->divide(0)->or("N/A");
// N/A
```

### Chaining multiple operations

You can chain arithmetic with formatting methods for practical
calculations like order totals:

```php
$price    = SmartString::new(89.99);
$taxRate  = 1.13;
$shipping = SmartString::new(12.50);

// Calculate total with tax and shipping
echo $price->multiply($taxRate)->add($shipping)->numberFormat(2);
// 114.19
```

Each method in the chain receives the result of the previous one,
so operations execute left to right: multiply first, then add,
then format.

## Percentages

### Converting ratios to display — `percent()`

`percent()` returns a SmartString with the value multiplied by 100
and a `%` sign appended. Pass an optional decimals parameter to
control decimal places (default 0).

```php
$ratio = SmartString::new(0.75);

echo $ratio->percent();
// 75%

echo $ratio->percent(2);
// 75.00%
```

The optional second parameter provides an alternative display when
the value is exactly zero:

```php
$zero = SmartString::new(0);

echo $zero->percent(2);
// 0.00%

echo $zero->percent(2, "N/A");
// N/A
```

Non-numeric values return null:

```php
$str = SmartString::new("cat");

echo $str->percent()->or("--");
// --
```

### Calculating percentage of total — `percentOf()`

`percentOf()` returns a SmartString showing what percentage the
current value is of the total. The formula is
`($value / $total * 100)%`. Pass an optional decimals parameter
as the second argument.

```php
$views = SmartString::new(24);

echo $views->percentOf(100);
// 24%

echo $views->percentOf(200, 1);
// 12.0%
```

Returns null when the total is zero (division by zero):

```php
$views = SmartString::new(24);

echo $views->percentOf(0)->or("N/A");
// N/A
```

## Null Handling

Null always stays null. When any operand is null, the entire
operation returns null — SmartString never silently coerces null
to zero. Non-numeric strings like `"cat"` or `"1,234"` behave the
same way.

```php
$val = SmartString::new(null);
echo $val->add(50)->value();    // null

$val = SmartString::new("cat");
echo $val->add(50)->value();    // null

$val = SmartString::new("1,234");
echo $val->add(50)->value();    // null
```

If you need null treated as zero, call `ifNull(0)` before the
arithmetic (see
[Targeted Replacements](05-conditionals-and-error-handling.md#targeted-replacements----ifblank-ifnull-ifzero)):

```php
$val = SmartString::new(null);
echo $val->ifNull(0)->add(50)->value(); // 50
```

## Error Propagation

Once a numeric error occurs — non-numeric input, division by
zero — the internal `hasNumericError` flag propagates through
the entire chain. Every subsequent operation returns null. You
never get a partial result.

```php
$val = SmartString::new("not a number");
echo $val->add(10)->multiply(2)->value();
// null (error propagated through entire chain)

// Division by zero poisons the rest of the chain
$price = SmartString::new(100);
echo $price->divide(0)->add(50)->multiply(2)->value();
// null
```

Use `->or()` at the end of a chain to provide a fallback when
any step in the calculation fails:

```php
$result = $price->divide($quantity)->numberFormat(2)->or("N/A");
```

## Floating-Point Precision

PHP uses IEEE 754 floating-point arithmetic, so internal float
values may carry tiny precision differences. For example, `0.1 +
0.2` internally equals `0.30000000000000004`. PHP's `echo` hides
this by rounding floats to 14 significant digits, but the
imprecision is still there — and it can surface in comparisons
or when chaining further arithmetic.

Use `numberFormat()` for display to round to your desired
precision:

```php
$val = SmartString::new(0.1);

echo $val->add(0.2);               // 0.3 (PHP rounds for display)
echo $val->add(0.2)->numberFormat(2); // 0.30 (explicit rounding)
```

See [PHP floating-point numbers](https://www.php.net/manual/en/language.types.float.php)
for more detail on precision behavior.

## Putting It Together

This example combines arithmetic, percentages, conditionals, and
formatting in a realistic order summary:

```php
$item     = DB::get('products', ['num' => $productNum]);
$quantity = SmartString::new($qty);
$taxRate  = 0.13;

// Calculate line totals
$subtotal = $item->price->multiply($quantity);
$tax      = $subtotal->multiply($taxRate);
$total    = $subtotal->add($tax);

// Display with formatting and fallbacks
echo "Item:     $item->name\n";
echo "Price:    {$item->price->numberFormat(2)->andPrefix('$')}\n";
echo "Qty:      $quantity\n";
echo "Subtotal: {$subtotal->numberFormat(2)->andPrefix('$')->or('N/A')}\n";
echo "Tax:      {$tax->numberFormat(2)->andPrefix('$')}\n";
echo "Total:    {$total->numberFormat(2)->andPrefix('$')}\n";
echo "Discount: {$item->discount->percent(1)->or('None')}\n";
```

This template demonstrates several features working together:

- `multiply()` and `add()` for arithmetic chains
- `numberFormat(2)` for consistent currency display
- `andPrefix("$")` to add the dollar sign only when present
- `or("N/A")` and `or("None")` for fallbacks on missing data
- `percent(1)` to convert a decimal ratio to display format
- Null propagation ensures any missing value produces a clean
  fallback rather than a broken calculation

---

[← Back to README](../README.md) | [← Conditionals & Error Handling](05-conditionals-and-error-handling.md) | [Next: Troubleshooting & Gotchas →](07-troubleshooting-and-gotchas.md)
