# Conditionals and Error Checking

Real-world data has gaps: empty fields, nulls, records that don't exist.
This page covers what the library considers "missing", the methods
that care about missing values, and other conditional/error checking features.

Contents:

- [What "Missing" Means](#what-missing-means)
- [Fallbacks - `or()`](#fallbacks---or)
- [Targeted Replacements - `ifNull()`, `ifZero()`, `ifEquals()`, `ifTrue()`, `set()`](#targeted-replacements---ifnull-ifzero-ifequals-iftrue-set)
- [Run Conditionals Before Formatting](#run-conditionals-before-formatting)
- [True/False Checks - `isEmpty()`, `isNotEmpty()`, `isMissing()`, `isNull()`](#truefalse-checks---isempty-isnotempty-ismissing-isnull)
- [Requiring a Value - `or404()`, `orDie()`, `orThrow()`, `orRedirect()`](#requiring-a-value---or404-ordie-orthrow-orredirect)
- [Putting It Together](#putting-it-together)

## What "Missing" Means

All the methods on this page react to missing values: `or()` fills them
in, [prepend()](text-and-formatting.md#adding-text-around-values---append-prepend-and-wrap)
won't put a label on one, and the guards stop the page
rather than run without one. Missing simply means there is no value: null
or an empty string `""`, and nothing else. Zero is not missing. False is
not missing:

| Value     | `or("fallback")` | `isEmpty()` | `isMissing()` |
|-----------|------------------|-------------|---------------|
| `null`    | `"fallback"`     | true        | true          |
| `""`      | `"fallback"`     | true        | true          |
| `0`       | `0`              | true        | false         |
| `"0"`     | `"0"`            | true        | false         |
| `false`   | `false`          | true        | false         |
| `"hello"` | `"hello"`        | false       | false         |

Both `0` and `"0"` come through `or()` unchanged: they are real
values, not missing ones. PHP's `empty()` considers them empty, but
SmartString's conditional methods do not treat them as missing. A price of
zero (what your template shows as $0.00) is real data, not a missing value.

## Fallbacks - `or()`

Replaces missing values with `$fallback`. This is the most used conditional
method; reach for it whenever a field might be blank:

```php
echo SmartString::new('')->or('N/A');        // N/A
echo SmartString::new(null)->or('Unknown');  // Unknown
echo SmartString::new(0)->or('N/A');         // 0 (zero counts as present)

echo "Hello, {$user->name->or('Guest')}!";       // reads naturally in templates
```

Because failed dates, numbers, and math all produce null (see
[Text and Formatting](text-and-formatting.md)), one `or()` at the end of a
chain covers every failure in it:

```php
echo $article->date->dateFormat('M j, Y')->or('Date not set');
echo $order->total->add($order->shipping)->numberFormat(2)->or('n/a');
```

## Targeted Replacements - `ifNull()`, `ifZero()`, `ifEquals()`, `ifTrue()`, `set()`

Each replaces the value when one specific condition matches, for finer
control than `or()`.

The `ifNull()` method replaces null only, leaving `""` alone. Its everyday
job is turning null into a real value before math:

```php
echo SmartString::new(null)->ifNull(0)->add(50);  // 50 (null would have made it null)
```

The `ifZero()` method replaces numeric zero (`0`, `0.0`, `"0"`, `"0.00"`,
`"-0"`). Non-numeric values never match, so text and missing values pass
through untouched:

```php
echo $account->balance->ifZero('No balance');       // "No balance" when 0
echo SmartString::new(null)->ifZero('No balance');  // "" (null is not zero)
```

The `ifEquals()` method replaces the value when it matches the one you
specify. Matching is loose (`==`), so the string `"5"` matches the number
`5` - handy, since numbers often arrive as text, like form input or numbers
stored in text fields:

```php
// ifEquals($match, $newValue)
// legacy zero-dates aren't real dates: null them, then fall back
echo $record->date->ifEquals('0000-00-00', null)->dateFormat('M j, Y')->or('Not set');

echo $plan->maxUsers->ifEquals(-1, 'Unlimited');  // fires on -1 and "-1"
```

For null, use `ifNull()` instead; PHP treats `null == 0` and `null == ""`
as true, so `ifEquals(null)` would match those too.

The `ifTrue()` method replaces the value when a condition you computed is
truthy. The condition is a plain value, not a callback, and it replaces the
value only; the rest of the chain still runs:

```php
// ifTrue($condition, $newValue)
$qty = SmartString::new(150);
echo $qty->ifTrue($qty->int() > 99, '99+');  // 99+
```

None of these methods take an else value; when the condition doesn't match,
the original value passes through. For a different value in each branch,
write a ternary or `match` - and `set()` (below) if the expression has to
sit inside an interpolated string.

`set()` replaces the value unconditionally, putting the result of any PHP
expression into a chain. It works best in heredocs and double-quoted strings:
`{$...}` interpolation accepts property and method access but not operators,
so a ternary or `match` written directly inside `{$...}` is a parse error.
Inside a method call's arguments it parses fine:

```php
// set($newValue)
echo "Gift wrap: {$order->giftWrap->set($order->giftWrap->bool() ? 'Yes' : 'No')}";
// Gift wrap: Yes

echo <<<__HTML__
    <span class="badge">{$order->status->set(match($order->status->string()) {
        'P'     => 'Pending',
        'S'     => 'Shipped',
        default => 'Unknown',
    })}</span>
    __HTML__;
// <span class="badge">Shipped</span>
```

The result re-enters the chain, so it encodes on output and can take a
further conditional or formatting calls. That is what `set()` adds
over computing the label first: a precomputed `$label` is a plain string
and echoes unencoded, while a `set()` result stays HTML-safe.

When the expression interpolates a SmartString, use `->value()` inside
it to avoid double-encoding.

## Run Conditionals Before Formatting

Formatted output is display text, not a number: once `numberFormat(2)` has
produced `"1,234.00"` or `percent()` has produced `"50%"`, math methods and
zero checks can no longer read it. Anything that needs to read the number
(math, `percentOf()`, `ifZero()`) goes before formatting in the chain:

```php
// WRONG - "$0.00" is not numeric, so ifZero never fires; prints $0.00
echo $price->numberFormat(2)->prepend('$')->ifZero('Free!');
```

When the replacement is display text like "Free!", it can't simply move
before the formatter either: `numberFormat()` returns null for non-numeric
values, so "Free!" would become null and display nothing. Match the
finished text instead with `ifEquals()`, using exactly what your format
produces:

```php
// RIGHT - match the formatted text
echo $price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!');
// $0     → Free!
// $19.99 → $19.99
```

The one formatter that needs a zero rule often enough gets it as a
parameter: `percent(2, ifZero: 'N/A')` (see
[Percentages](text-and-formatting.md#percentages---percent-and-percentof)).

Placement also changes what `or()` means. Before formatting, it supplies a
fallback number; after formatting it supplies fallback display text:

```php
$value = SmartString::new(null);

echo $value->or(0)->numberFormat(2);      // 0.00 (fallback number, then formatted)
echo $value->numberFormat(2)->or('n/a');  // n/a  (format failed, then display text)
```

## True/False Checks - `isEmpty()`, `isNotEmpty()`, `isMissing()`, `isNull()`

These return a plain `bool` for use in `if` statements. You need them
because a SmartString is an object, and PHP objects are always truthy:
testing the object itself always gives the same answer, no matter what
value is inside:

```php
// WRONG - an object is always truthy; this branch runs even when bio is null
if ($user->bio) {
    echo "<h2>About</h2><p>{$user->bio->nl2br()}</p>";
}

// RIGHT - ask the object about its value
if ($user->bio->isNotEmpty()) {
    echo "<h2>About</h2><p>{$user->bio->nl2br()}</p>";
}
```

The same applies to `empty($user->bio)` and `!$user->bio`, which are always
false on an object; use the check methods instead, or `->value()` to test the
raw value with plain PHP. The difference between the checks is what happens
to zero:

```php
$balance = SmartString::new(0);

$balance->isEmpty();    // true  (PHP empty() rules: 0, "0", false, "", null)
$balance->isMissing();  // false (zero is a real value; matches or() and the guards)
$balance->isNull();     // false (null only)
```

## Requiring a Value - `or404()`, `orDie()`, `orThrow()`, `orRedirect()`

Where `or()` quietly substitutes a fallback, the guards stop the page. Use
them for values that must exist, like a record ID from the URL. If the value
is missing they act; otherwise they do nothing and return the same object,
so the chain continues. Zero counts as present.

```php
$article = DB::selectOne('articles', ['num' => $articleNum]);

$article->num->or404("Article not found");    // 404 header + message, then exits
$article->num->orDie("Article not found");    // prints message, then exits
$article->num->orThrow("Article not found");  // throws RuntimeException
$article->num->orRedirect("/articles/");      // 302 redirect, then exits
```

When the query returns no row, `$article` is an empty
[SmartArray](https://github.com/interactivetools-com/SmartArray) record and
`->num` reads as missing (same missing rules), so the guard fires.

The messages are HTML-encoded automatically. They often interpolate user
input (`->orDie("Bad id: $id")`) and may be echoed into a page, so the
guards encode rather than trusting every error handler to do it.

How each guard differs:

- **`or404($text)`** sends the 404 status and a minimal HTML error page. The
  default message is "The requested URL was not found on this server." The
  page is deliberately plain; for a designed not-found page, use
  `orRedirect()` to send visitors to your own.
- **`orDie($text)`** exits with code 1, so CLI scripts and cron jobs see a
  failure instead of success.
- **`orThrow($text)`** throws a `RuntimeException` for your error handler.
  Handlers that want plain text (CLI, logs) can decode the message with
  `htmlspecialchars_decode($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)`.
- **`orRedirect($url)`** checks `headers_sent()` and a blank `$url` (null or
  `""`) immediately and throws if either fails, even when the value is
  present, so a misplaced redirect fails on the first request instead of
  only when a value goes missing.

### The two-stage guard

Guards pass the value through untouched, so one chain can distinguish "no
row matched" from "row found, but the column is empty" and report which one
happened:

```php
$memberId = DB::selectOne('users', ['email' => $email])
    ->orThrow("No user found for $email")
    ->memberId
    ->orThrow("User $email has no member ID")
    ->int();
```

The first `orThrow()` fires when the query returns no row; the second fires
when the row exists but the column is null or `""`. (Row-level guards like the
first one are SmartArray methods; same names, same missing rules.)

## Putting It Together

A record detail page: guard first, then fallbacks for every optional field:

```php
$product = SmartArrayHtml::new([
    'name'     => 'Deluxe Widget',
    'price'    => 0,
    'summary'  => '',
    'updated'  => '2026-09-10',
]);

$product->name->or404("Product not found");   // guard: required field

echo <<<__HTML__
    <h1>$product->name</h1>
    <p>Price: {$product->price->numberFormat(2)->prepend('$')->ifEquals('$0.00', 'Free!')}</p>
    <p>{$product->summary->textOnly()->maxChars(120)->or('No description yet.')}</p>
    <p>Updated: {$product->updated->dateFormat('M j, Y')->or('never')}</p>
    __HTML__;
```

Output:

```
<h1>Deluxe Widget</h1>
<p>Price: Free!</p>
<p>No description yet.</p>
<p>Updated: Sep 10, 2026</p>
```

A passing guard leaves no trace in the output; a missing `name` would have
ended the page with a 404 before any of it printed. The empty summary stays
`""` through `textOnly()->maxChars()`, which is what lets the trailing `or()`
fire.

---

[← Documentation Index](README.md) | [← Prev: Text and Formatting](text-and-formatting.md) | [Next: Common Patterns →](common-patterns.md)
