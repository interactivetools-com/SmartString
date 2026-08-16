# Performance: At Least 2.5x Faster Than Calling `htmlspecialchars()` Yourself

Our automatic encoding produces byte-identical output to `htmlspecialchars()`
and is faster: at least 2.5x on a real-world page on every platform we
measure. Most values don't need encoding, and proving that with a scan costs
less than encoding them anyway. This page shows how that works, the
measurements, and the tests that keep the shortcut honest.

The multiplier depends on the platform: our scans cost about the same
everywhere, so the win tracks how slowly each platform's `htmlspecialchars()`
runs. Based on the real-world page measured below:

- **Dedicated Linux x64** - the fastest `htmlspecialchars()` we measure, and
  the source of every table on this page: **3.3x**
- **Cloud Linux x64** - GitHub's standard runners, closer to typical
  hosting: **5x and up**
- **Linux ARM** - Graviton-class hosts: **2.9x and up**
- **Windows** - its PHP builds encode slowest: **10x and up**, with long
  clean fields from 41x

The cloud, ARM, and Windows numbers were measured against the faster
two-flag `htmlspecialchars()` call quantified in The Fine Print; against the
matching full-flag baseline used everywhere on this page they only grow, so
they are floors.

And you can benchmark your own machine any time with this command:

```bash
php -d opcache.enable_cli=1 -d xdebug.mode=off .github/scripts/speed-page-table.php
```

In a Composer project the script is at
`vendor/itools/smartstring/.github/scripts/speed-page-table.php`.

Contents:

- [How It Works](#how-it-works)
- [The Numbers](#the-numbers)
- [How We Know It's Safe](#how-we-know-its-safe)
- [The Fine Print](#the-fine-print)

## How It Works

Every `<?= $row->title ?>` runs the cheapest check that can settle the value.

- **Numbers, booleans, and null** (ids, counts, prices, empty fields) return
  immediately: their string casts contain only digits, signs, and a decimal
  point, which encoding never touches.
- **Text with nothing to encode** - none of `& < > " '`, no accents - returns
  as-is after a single scan confirms that. Most database fields (titles, names,
  dates) are this.
- **Text containing `& < > " '`** gets a `str_replace()` that swaps those five
  characters for their HTML entities.
- **Accented and other Unicode text** (é, 中, emoji) gets one extra scan to
  validate the UTF-8, then returns as-is or runs the same `str_replace()`.
- **Anything suspect** (invalid UTF-8, control bytes) falls through to
  `htmlspecialchars()` itself.

Every path either does less work than `htmlspecialchars()` or *is*
`htmlspecialchars()`.

## The Numbers

This section compares the common approach, calling `htmlspecialchars()` through
a small helper function (Laravel's `e()`, Twig's escaper, or one you wrote
yourself), against creating a SmartString and outputting it - it encodes itself
automatically. Both sides are timed in full: the helper call, and the
SmartString's construction plus output.

```php
// The helper being timed - htmlspecialchars() with the same full flags
// SmartString uses, so both sides produce byte-identical output
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
}

echo e("Annual Report 2026");                  // helper: runs the full encoder on every call
echo new SmartString("Annual Report 2026");    // SmartString: object created, value scanned, output
```

Measured on Linux x64, PHP 8.5 with opcache, on a dedicated Intel Xeon
E-2386G server; output was verified byte-identical before every timing run,
and a repeat pass agreed within 0.1x on every multiplier. Timings are identical
whether you write `<?= $title ?>` or `echo $title;`. The test content matches
the character mix of real English and French writing, measured on both classic
and current text.

| Content                          | Size  | Example                         | `htmlspecialchars()` | SmartString | Speed vs `htmlspecialchars()` |
|----------------------------------|-------|---------------------------------|----------------------|-------------|-------------------------------|
| Create a SmartString - no output | any   | `new SmartString($value)`       | -                    | 40 ns       | -                             |
| Empty - null or ""               | any   | a blank optional field          | 27 ns                | 89 ns       | 0.3x                          |
| Numbers - int                    | any   | `1499`                          | 83 ns                | 87 ns       | 1.0x                          |
| Numbers - float                  | any   | `24.99`                         | 180 ns               | 196 ns      | 0.9x                          |
| Numbers - via `->int()`          | any   | `1499`                          | 84 ns                | 71 ns       | 1.2x                          |
| Numbers - via `->float()`        | any   | `24.99`                         | 180 ns               | 184 ns      | 1.0x                          |
| Clean text - no `& < > " '`      | 16 B  | `Annual Report 2026`            | 92 ns                | 118 ns      | 0.8x                          |
| Clean text - no `& < > " '`      | 100 B | a short sentence                | 409 ns               | 141 ns      | 2.9x                          |
| Clean text - no `& < > " '`      | 200 B | a sentence or two               | 755 ns               | 162 ns      | 4.6x                          |
| Clean text - no `& < > " '`      | 1 KB  | a plain-text paragraph          | 3,704 ns             | 331 ns      | 11x                           |
| Clean text - no `& < > " '`      | 10 KB | a long field, nothing to encode | 37,213 ns            | 2,202 ns    | 17x                           |
| Has `& < > " '`                  | 16 B  | `O'Brien & Co Ltd`              | 90 ns                | 260 ns      | 0.3x                          |
| Has `& < > " '`                  | 100 B | a sentence with quotes          | 357 ns               | 354 ns      | 1.0x                          |
| Has `& < > " '`                  | 200 B | a sentence or two with quotes   | 655 ns               | 426 ns      | 1.5x                          |
| Has `& < > " '`                  | 1 KB  | a paragraph with quotes         | 2,992 ns             | 1,075 ns    | 2.8x                          |
| Has `& < > " '`                  | 10 KB | a 1,500-word article            | 27,973 ns            | 8,153 ns    | 3.4x                          |
| Accented text - no `& < > " '`   | 16 B  | `Café Montréal QC`              | 94 ns                | 250 ns      | 0.4x                          |
| Accented text - no `& < > " '`   | 100 B | a short French sentence         | 330 ns               | 340 ns      | 1.0x                          |
| Accented text - no `& < > " '`   | 200 B | a French sentence or two        | 620 ns               | 416 ns      | 1.5x                          |
| Accented text - no `& < > " '`   | 1 KB  | a French paragraph              | 2,906 ns             | 927 ns      | 3.1x                          |
| Accented text - no `& < > " '`   | 10 KB | a French article                | 27,814 ns            | 6,377 ns    | 4.4x                          |
| News-article page                | mixed | *                               | 29,650 ns            | 8,971 ns    | 3.3x                          |

\* News-article page: a 16 B quoted headline; author, category, and date (16 B plain); a 200 B caption; and a 10 KB body with quotes. This row is the whole page - all six fields together.

Per call (per page for the News-article row), best of 7, measured on Linux x86_64, PHP 8.5.9.

The News-article page row is those rows combined - here it is field by field,
every line taken from the table above:

| Field                        | Table row                          | `htmlspecialchars()` | SmartString  | Speed vs `htmlspecialchars()` |
|------------------------------|------------------------------------|----------------------|--------------|-------------------------------|
| Headline - `Mayor Says 'No'` | Has `& < > " '`, 16 B              | 0.09 µs              | 0.26 µs      | 0.3x                          |
| Author                       | Clean text - no `& < > " '`, 16 B  | 0.09 µs              | 0.12 µs      | 0.8x                          |
| Category                     | Clean text - no `& < > " '`, 16 B  | 0.09 µs              | 0.12 µs      | 0.8x                          |
| Date                         | Clean text - no `& < > " '`, 16 B  | 0.09 µs              | 0.12 µs      | 0.8x                          |
| Photo caption                | Clean text - no `& < > " '`, 200 B | 0.76 µs              | 0.16 µs      | 4.6x                          |
| Article body with quotes     | Has `& < > " '`, 10 KB             | 28.0 µs              | 8.2 µs       | 3.4x                          |
| **Whole page**               | All of the above                   | **29.7 µs** (0.0000297 s) | **9.0 µs** (0.0000090 s) | **3.3x**                      |

The longer the text, the bigger the win. This page is 3.3x faster because
almost all of its time is in the 10 KB body - short fields break even by
100 B, and by 200 B per field everything is ahead.

Where SmartString is slower: fields under 100 B, where creating the object
costs more than the tiny encoding it replaces - about 0.03 microseconds
extra on a clean short field, about 0.17 when quotes or accents force a
full encode. You'd need about 6,000 of the worst case on one page to lose a
millisecond, and a single clean 1 KB paragraph repays about 20 of them.

These numbers come from the benchmark script run on the dedicated server
described above; the command at the top of the page reproduces them on any
machine. They are measured with JIT off, like production (see The Fine
Print).

## How We Know It's Safe

The output is exactly identical, byte for byte, to
`htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5,
'UTF-8')`. It follows the same mechanical
rules `htmlspecialchars()` follows: every `<` becomes `&lt;`, every `&` becomes
`&amp;`, no matter what surrounds them.

We test that the thorough way: we ran every possible string up to 4 bytes long,
4.3 billion inputs covering the entire utf8mb4 space plus every invalid byte
combination, through both and compared -
[zero mismatches on PHP 8.1 through 8.5](https://github.com/interactivetools-com/SmartString/actions/runs/29689289822).
A 106,000-string collection of edge cases re-checks the same thing on every
code change; one mismatch fails the build.

SmartString also encodes more than the common call: HTML5-disallowed characters
(NUL, control bytes, the C1 range, Unicode noncharacters) become a visible `�`
instead of passing invisibly into your page, and invalid UTF-8 loses only the
bad bytes, never the whole value. Plenty of legacy code passes `ENT_COMPAT`,
which leaves single quotes unencoded and attribute injection open; SmartString
always uses the full flags above.

## The Fine Print

It is fair to ask how PHP code in front of a C function makes anything faster.
Both sides are C: `htmlspecialchars()` has no fast path of its own (it decodes
and copies every character, plain ASCII included, on every PHP release), while
the scans use CPU instructions that check 16 bytes at a time, and they only
read - they never build anything.
Reading is cheaper than transforming, and most values only need to be read. Every
scan threshold comes from A/B benchmarks across PHP 8.1-8.5 on five OS and CPU
combinations, recorded in the repo at `.github/scripts/speed-results.md`.

Four benchmark choices, stated plainly:

- **Both sides produce identical output.** The helper is timed with the same
  full flags SmartString uses, so the race is work-for-work:

  ```php
  htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');      // both what we time against and what we produce
  ```

  Many projects call `htmlspecialchars()` with fewer flags (`ENT_QUOTES |
  ENT_SUBSTITUTE`, or PHP's default), skipping the per-character
  `ENT_DISALLOWED` check; that weaker call runs 10-60% faster depending on
  length and content. Timed against it, the worked example's 3.3x page
  measures 2.7x, still with every field encoded to the stronger full-flag
  output.
- **A fast server shrinks the multiplier.** On the worked-example page, the
  dedicated Xeon behind these tables runs `htmlspecialchars()` about 2.7x
  faster than GitHub's standard cloud runners, while SmartString's scans
  speed up only about 1.4x - so the same benchmark measures 3.3x here and
  over 5x there. Slower hosting widens every multiplier.
- **Timings include creating the object.** Every SmartString in the loop is
  built fresh (`new SmartString($value)`) and then output - the multiplier is
  the full cost of each approach per value, nothing left out.
- **JIT is off, matching production.** JIT ships disabled in every PHP
  release and almost no hosts turn it on, so that is what the numbers above
  measure. Enabling opcache's tracing JIT shaves a few tens of nanoseconds
  off the short-field rows (in SmartString's favor) and leaves the rest
  unchanged - a full pass of the benchmark matrix with JIT on kept every
  verdict.

Most libraries and frameworks run the full encoder on every value, every time.
Checking first and skipping the work when there is nothing to do is, as far as
we know, unique to SmartString - the kind of care we try to put into everything
we build.

---

[← Documentation Index](README.md) | [← Prev: Troubleshooting](troubleshooting.md) | [Next: AI Reference →](ai-reference.md)
