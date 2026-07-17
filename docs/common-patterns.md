# Common Patterns

These are some of the most common tasks that come up on websites: format
a date, trim a preview, print an address block without blank lines, and
more. Grab whichever you need: every recipe is copy-paste ready and built
from methods covered on earlier pages.

The examples assume database rows from
[ZenDB](https://github.com/interactivetools-com/ZenDB) or
`SmartArrayHtml::new($record)`, where every field is already a SmartString.

## Formatting Dates

If the whole site uses one date format, set `SmartString::$dateFormat` once
in your init file and call `dateFormat()` with no arguments everywhere
else. Until you change it, the default format is `'Y-m-d'` (displays as
"2026-09-10"):

```php
SmartString::$dateFormat = 'M j, Y';

echo $race->date->dateFormat();  // Sep 10, 2026
```

For anything else, pass any combination of
[date() format characters](https://www.php.net/manual/en/datetime.format.php#refsect1-datetime.format-parameters).
Formatting never modifies the original, so one field can feed display,
URLs, and filenames:

```php
$race = SmartArrayHtml::new(['date' => '2026-09-10 14:30:00']);

echo $race->date->dateFormat('M j, Y');                      // Sep 10, 2026
echo "race.php?date={$race->date->dateFormat('Y-m-d')}";     // race.php?date=2026-09-10
$filename = "results-{$race->date->dateFormat('dmY')}.csv";  // results-10092026.csv
```

When you need several date formats, a nice pattern is to define a constant
per date style in your init file and reference those in templates; everything
stays consistent, and all your date formats are in one place:

```php
const DATE_DISPLAY  = 'M j, Y';
const DATE_FILENAME = 'dmY';

echo $race->date->dateFormat(DATE_DISPLAY);   // Sep 10, 2026
echo $race->date->dateFormat(DATE_FILENAME);  // 10092026
```

## Text Previews and Excerpts

When you want to show just the start of an article (listing pages, search
results, "read more" teasers), limit it by characters or by words. For HTML
content be sure to call `textOnly()` first to remove any HTML; otherwise
`maxChars()` and `maxWords()` can cut the text off in the middle of a tag,
which isn't what you want:

```php
echo $article->content->textOnly()->maxChars(120);                 // adds "..." if cut off
echo $article->content->textOnly()->maxWords(30, ' [read more]');  // custom suffix
```

## Address Blocks

Multi-line data often has optional parts: not every address has a company
name or a second address line. The `appendHtml()` method adds markup after
a value only when there is one; blank and null fields output nothing at
all (no dangling comma, no blank line), and zero still counts as a value:

```php
echo $member->company->appendHtml("<br>\n");
echo $member->address1->appendHtml("<br>\n");
echo $member->address2->appendHtml("<br>\n");   // "" when address2 is empty
echo $member->city->appendHtml(", ");
echo $member->province->appendHtml(" ");
echo $member->postcode;
```

For a single multiline text field (hours, bio, plain-text address), use
`nl2br()` instead; it HTML-encodes the text and adds a `<br>` at every
line break:

```php
echo $office->hours->nl2br();
// Mon-Fri 9-5<br>
// Sat 10-4
```

## Labels Only When Present

A label is only useful when there's a value after it. The `prepend()` and
`wrap()` methods skip missing values, so an empty field shows nothing
instead of a dangling label:

```php
echo $user->phone->prepend("Phone: ");       // "Phone: (604) 555-1234", or ""
echo $user->extension->wrap('(ext. ', ')');  // "(ext. 204)", or ""
```

## Clickable Phone Numbers

A `tel:` href needs digits only; the visible text keeps the pretty
formatting. Strip non-digits with `pregReplace()`:

```php
echo "<a href='tel:{$office->phone->pregReplace('/\D/', '')}'>$office->phone</a>";
// <a href='tel:6045551234'>(604) 555-1234</a>
```

## Show a 404 for Missing Records

When a visitor follows a bad link or an old bookmark to a record that no
longer exists, show a 404 instead of a half-empty page. Call `or404()` on
a required field right after the query; if the record doesn't exist it
shows a 404 error page and stops there, otherwise the rest of the page
runs normally:

```php
$article = DB::selectOne('articles', ['num' => $articleNum]);
$article->num->or404("Article not found");

echo "<h1>$article->title</h1>";
echo "<p>{$article->content->textOnly()->maxChars(200)}</p>";
```

Swap `or404()` for `orDie($text)`, `orThrow($text)`, or `orRedirect($url)`
for other outcomes; all four use the same missing rules (null or `""`,
zero counts as present).

## Displaying WYSIWYG or HTML Content

SmartString HTML-encodes everything by default, but sometimes the value is
supposed to be HTML: WYSIWYG editor content, or a field holding an HTML
snippet. Output those with `rawHtml()` and everything around them stays
encoded as usual. Save it for trusted content like your editors' work,
never for anything a visitor typed:

```php
echo <<<__HTML__
    <h1>$article->title</h1>
    {$article->wysiwygContent->rawHtml()}
__HTML__;
```

## Building URLs

When you're building links by hand, run query-string values through
`urlEncode()`; the rest of the URL auto-encodes as usual. Without it, an
`&` or `=` inside a value adds extra parameters to your link, and
characters like `#`, `%`, and spaces arrive broken:

```php
echo "<a href='read.php?id={$article->id->urlEncode()}'>Read more</a>";
echo "?start={$course->startDate->dateFormat('Y-m-d')->urlEncode()}";
```

## Formatting Currency

Prices usually need two decimals, a currency symbol, and a sensible
fallback for missing data:

```php
echo $order->total->numberFormat(2)->prepend('$');             // $1,234.50
echo $order->total->numberFormat(2)->prepend('$')->or('n/a');  // n/a when missing
```

The `prepend()` call skips missing values, so the fallback shows plain
"n/a", not "$n/a". A failed format (null, non-numeric) also produces null,
so the one `or()` covers both.

## Report Tables: Hiding Zeros and Nulls

When you're displaying numbers in a report, a blank cell or a dash often
reads better than a zero. The `numberFormat()` method only formats
numbers: give it `""` or null and it returns null, which echoes as
nothing. That's the trick behind all of these:

```php
echo $row->total->ifZero('')->numberFormat(2);           // 0 → blank, 1234.5 → 1,234.50
echo $row->count->numberFormat()->or('-');               // null → "-", 42 → 42
echo $row->total->ifZero('')->numberFormat(2)->or('-');  // 0 or null → "-", 1234.5 → 1,234.50
echo $row->hours->ifNull(0)->numberFormat(2);            // null → 0.00
```

## Report Tables: Inline Math

For a one-off calculation, plain PHP is fine. Chains earn their keep in
report rows: the math, the formatting, and the missing-data fallback read
as one line, and a zero or null anywhere in the chain becomes your
fallback instead of a warning or a divide-by-zero error. Nothing needs to
be calculated before the loop; the only prepared variable here is
`$grandTotal`:

```php
// columns: Product | Total | Price Each | Share of Sales
foreach ($orders as $order) {
    echo <<<__HTML__
        <tr>
            <td>$order->product</td>                                                 <!-- Widgets -->
            <td>{$order->total->numberFormat(2)->prepend('$')}</td>                  <!-- $1,234.50 -->
            <td>{$order->total->divide($order->qty)->numberFormat(2)->or('-')}</td>  <!-- 123.45 (total / qty) -->
            <td>{$order->total->percentOf($grandTotal)->or('-')}</td>                <!-- 25% (total / grand total) -->
        </tr>
        __HTML__;
}
```

## Run Any Function with map()

Anything without a built-in method goes through `map()`: your function
gets the raw value, and whatever it returns becomes a new SmartString. In
PHP, map usually runs over an array; a SmartString is one value, so your
function is called just once (the name matches `array_map()` and
`SmartArray::map()`):

```php
echo $province->code->map('strtoupper');                              // BC
echo $user->name->ifNull('')->map('ucwords');                         // built-ins like ucwords() reject null, so convert it first
echo $sku->map(fn($v) => str_pad((string)$v, 6, '0', STR_PAD_LEFT));  // zero-pads to 6 digits: 000042
```

A closure even works inside a template string; wrap the whole chain in
curly braces:

```php
echo <<<__HTML__
    <td>{$order->sku->map(fn($v) => str_pad((string)$v, 6, '0', STR_PAD_LEFT))}</td>
    __HTML__;
```

That said, complex PHP reads better away from the HTML. A good rule of
thumb: if the chain makes sense at a glance, keep it in the template; if
you have to stop and puzzle it out, do the work in PHP first and echo the
variable.

## Where or() Goes Changes What It Means

Formatting a missing value (null or `""`) produces nothing, so where you
put `or()` decides what the reader sees instead. Before the format, `or(0)` fills in
a real number that formats like any other ("0.00"); after the format,
`or('n/a')` shows display text when there was nothing to format:

```php
$value = SmartString::new(null);

echo $value->or(0)->numberFormat(2);      // 0.00 (fallback number, then formatted)
echo $value->numberFormat(2)->or('n/a');  // n/a  (format failed, then display text)
```

## Methods or Plain PHP?

It's easy to reach for the native PHP functions you already know, and they
work. Side by side, though, the method version is usually shorter, handles
null, and encodes correctly on output.

| Instead of                                          | Write                             |
|-----------------------------------------------------|-----------------------------------|
| `date('M j, Y', strtotime($x->string()))`           | `$x->dateFormat('M j, Y')`        |
| `mb_substr(strip_tags($x->string()), 0, 200).'...'` | `$x->textOnly()->maxChars(200)`   |
| `number_format($x->float(), 2)`                     | `$x->numberFormat(2)`             |
| `(int)(string)$row['cnt']`                          | `$row->cnt->int()`                |
| `htmlspecialchars($x->value())`                     | `$x` (echo already encodes)       |

Pick whichever reads better at a glance; templates that are easier to read
and maintain are why the library exists. And when you'd rather write plain
PHP, unwrap the value first with `value()`, `int()`, or `float()` and
carry on. Just remember an unwrapped value is plain PHP again: if you echo
it, you're responsible for encoding it yourself.

---

[← Documentation Index](README.md) | [← Prev: Conditionals and Error Checking](conditionals-and-error-checking.md) | [Next: Method Reference →](method-reference.md)
