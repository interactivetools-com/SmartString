# Common Patterns

Copy-paste recipes for the tasks that come up in real templates: money
cells, phone links, address blocks, report tables, text previews, and date
handling. Each one combines methods covered on earlier pages, and each came
from production template code.

The examples assume database rows from
[ZenDB](https://github.com/interactivetools-com/ZenDB) or
`SmartArrayHtml::new($record)`, where every field is already a SmartString.

## Record Detail with 404

Guard the required field straight off the query, then output freely:

```php
$article = DB::selectOne('articles', ['num' => $articleNum]);
$article->num->or404("Article not found");

echo "<h1>$article->title</h1>";
echo "<p>{$article->content->textOnly()->maxChars(200)}</p>";
```

Swap `or404()` for `orDie($text)`, `orThrow($text)`, or `orRedirect($url)`
for other outcomes; all four use the same missing rules (null or `""`,
zero counts as present).

## Money Cells

Format with two decimals, prefix the currency symbol only when there is a
value, and pick a fallback for missing data:

```php
echo $order->total->numberFormat(2)->prepend('$');             // $1,234.50
echo $order->total->numberFormat(2)->prepend('$')->or('n/a');  // n/a when missing
```

The `prepend()` call skips missing values, so the fallback shows plain
"n/a", not "$n/a". A failed format (null, non-numeric) also produces null,
so the one `or()` covers both.

## Phone Links

A `tel:` href needs digits only; the visible text keeps the pretty
formatting. Strip non-digits with `pregReplace()`:

```php
echo "<a href=\"tel:{$office->phone->pregReplace('/\D/', '')}\">$office->phone</a>";
// <a href="tel:6045551234">(604) 555-1234</a>
```

## Address Blocks

Each line appends its own separator with `appendHtml()`, so empty lines
produce no output at all: no dangling comma, no blank line:

```php
echo $member->company->appendHtml("<br>\n");
echo $member->address1->appendHtml("<br>\n");
echo $member->address2->appendHtml("<br>\n");   // "" when address2 is empty
echo $member->city->appendHtml(", ");
echo $member->province->appendHtml(" ");
echo $member->postcode;
```

For a single multiline text field (hours, bio, plain-text address), use
`nl2br()` instead:

```php
echo $office->hours->nl2br();
// Mon-Fri 9-5<br>
// Sat 10-4
```

## Labels Only When Present

Prefix a label so that missing values show nothing instead of a label with
nothing after it:

```php
echo $user->phone->prepend("Phone: ");       // "Phone: (604) 555-1234", or ""
echo $user->extension->wrap('(ext. ', ')');  // "(ext. 204)", or ""
```

## Report Tables: Hiding Zeros and Nulls

Report columns read better when zero rows are blank and missing data shows
a placeholder. Replace zero before formatting (the replacement `""` fails
the format, which yields null, which echoes as nothing):

```php
echo $row->total->ifZero('')->numberFormat(2);  // blank cell for 0, "1,234.50" otherwise
echo $row->count->numberFormat()->or('-');      // "-" for missing, formatted otherwise
```

## Text Previews

Strip the HTML, then truncate at a word boundary. Order matters: truncating
raw HTML can cut a tag in half:

```php
echo $article->content->textOnly()->maxChars(120);
echo $article->content->textOnly()->maxWords(30, ' [read more]');
```

## Trusted WYSIWYG Content

Editor-produced HTML outputs raw on purpose; everything around it stays
encoded:

```php
echo <<<__HTML__
    <h1>$article->title</h1>
    {$article->wysiwygContent->rawHtml()}
__HTML__;
```

## One Date Field, Three Formats

Format the same field differently for display, URLs, and filenames;
`dateFormat()` never modifies the original:

```php
$race = SmartArrayHtml::new(['date' => '2026-09-10 14:30:00']);

echo $race->date->dateFormat('M j, Y');                      // Sep 10, 2026
echo "race.php?date={$race->date->dateFormat('Y-m-d')}";     // race.php?date=2026-09-10
$filename = "results-{$race->date->dateFormat('dmY')}.csv";  // results-10092026.csv
```

## URL Building

Use `urlEncode()` for values inside query strings; the rest of the URL
auto-encodes as usual:

```php
echo "<a href=\"read.php?id={$article->id->urlEncode()}\">Read more</a>";
echo "?start={$course->startDate->dateFormat('Y-m-d')->urlEncode()}";
```

## Math in Templates

Per-unit rates, shares of a total, and running totals read as one chain,
with a fallback covering the whole calculation:

```php
// per-unit rate
echo $rental->total->divide($rental->days)->numberFormat(2)->or('-');

// share of total (also handy as a CSS width)
echo $source->visits->percentOf($totalVisits);   // 25%

// year-over-year change; percentOf(0) returns null, so a zero prior year shows "-"
echo $sales->thisYear->subtract($sales->lastYear)->percentOf($sales->lastYear, 1)->or('-');
// 20.0%

// running total
echo SmartString::new(0)->add($order->subtotal)->add($order->shipping)->numberFormat(2);
```

## Custom Transformations

Anything without a built-in method goes through `map()`:

```php
echo $province->code->map('strtoupper');                              // BC
echo $user->name->ifNull('')->map('ucwords');                         // ifNull('') first: built-ins reject null
echo $sku->map(fn($v) => str_pad((string)$v, 6, '0', STR_PAD_LEFT));  // 000042
```

## Where or() Goes Changes What It Means

Both placements are common in the same codebase and easy to misread. Before
formatting, the fallback is a number that gets formatted; after formatting,
it is display text shown when the format failed:

```php
$value = SmartString::new(null);

echo $value->or(0)->numberFormat(2);      // 0.00 (fallback number, then formatted)
echo $value->numberFormat(2)->or('n/a');  // n/a  (format failed, then display text)
```

## Prefer the Method Over the Workaround

All of these pairs appear side by side in real templates. The left column
works; the right column is shorter, encodes correctly, and handles null:

| Instead of                                          | Write                             |
|-----------------------------------------------------|-----------------------------------|
| `date('M j, Y', strtotime($x->string()))`           | `$x->dateFormat('M j, Y')`        |
| `mb_substr(strip_tags($x->string()), 0, 200).'...'` | `$x->textOnly()->maxChars(200)`   |
| `number_format($x->float(), 2)`                     | `$x->numberFormat(2)`             |
| `(int)(string)$row['cnt']`                          | `$row->cnt->int()`                |
| `htmlspecialchars($x->value())`                     | `$x` (echo already encodes)       |

One of these is a bug, not just longer: using `htmlEncode()` to "unwrap" a
value for math or `number_format()` feeds encoded text (`&amp;` and friends)
into a numeric function. Unwrap with `value()`, `int()`, or `float()`;
encode only at output.

---

[← Documentation Index](README.md) | [← Prev: Conditionals and Error Checking](conditionals-and-error-checking.md) | [Next: Troubleshooting →](troubleshooting.md)
