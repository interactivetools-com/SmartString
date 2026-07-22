# Performance: 3x Faster Than Calling `htmlspecialchars()` Yourself

Our automatic encoding produces byte-identical output to `htmlspecialchars()`
and is faster: 3x or better on a real-world page, up to 11x on Windows. Most
values don't need encoding, and proving that with a scan costs less than encoding
them anyway. This page shows how that works, the
measurements, and the tests that keep the shortcut honest.

The multiplier depends on the platform: our scans cost about the same
everywhere, so the win tracks how slowly each platform's `htmlspecialchars()`
runs. Based on the real-world page measured below:

- **Linux x64** - the most common web platform: **3.9x**
- **Linux ARM** - Graviton-class hosts: **2.9x**
- **Windows** - its PHP builds encode slowest: **11x**, and long clean fields
  reach 41x

And you can benchmark your own machine any time with this command:

```bash
php -d opcache.enable_cli=1 -d xdebug.mode=off .github/scripts/speed-page-table.php
```

In a Composer project the script is at
`vendor/itools/smartstring/.github/scripts/speed-page-table.php`.

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
// The helper being timed - the standard safe call, wrapped once per project
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

echo e("Annual Report 2026");                  // helper: runs the full encoder on every call
echo new SmartString("Annual Report 2026");    // SmartString: object created, value scanned, output
```

Measured on Linux x64, PHP 8.5 with opcache, on GitHub Actions' standard cloud
servers; output was verified byte-identical before every timing run. Timings are identical
whether you write `<?= $title ?>` or `echo $title;`. The test content matches
the character mix of real English and French writing, measured on both classic
and current text.

| Content                          | Size  | Example                         | `htmlspecialchars()` | SmartString | Speed vs `htmlspecialchars()` |
|----------------------------------|-------|---------------------------------|----------------------|-------------|-------------------------------|
| Create a SmartString - no output | any   | `new SmartString($value)`       | -                    | 64 ns       | -                             |
| Empty - null or ""               | any   | a blank optional field          | 33 ns                | 140 ns      | 0.2x                          |
| Numbers - int                    | any   | `1499`                          | 103 ns               | 132 ns      | 0.8x                          |
| Numbers - float                  | any   | `24.99`                         | 225 ns               | 263 ns      | 0.9x                          |
| Numbers - via `->int()`          | any   | `1499`                          | 104 ns               | 100 ns      | 1.0x                          |
| Numbers - via `->float()`        | any   | `24.99`                         | 225 ns               | 231 ns      | 1.0x                          |
| Clean text - no `& < > " '`      | 16 B  | `Annual Report 2026`            | 132 ns               | 176 ns      | 0.8x                          |
| Clean text - no `& < > " '`      | 100 B | a short sentence                | 566 ns               | 236 ns      | 2.4x                          |
| Clean text - no `& < > " '`      | 200 B | a sentence or two               | 1,044 ns             | 290 ns      | 3.6x                          |
| Clean text - no `& < > " '`      | 1 KB  | a plain-text paragraph          | 4,988 ns             | 586 ns      | 8.5x                          |
| Clean text - no `& < > " '`      | 10 KB | a long field, nothing to encode | 49,146 ns            | 3,856 ns    | 13x                           |
| Has `& < > " '`                  | 16 B  | `O'Brien & Co Ltd`              | 139 ns               | 335 ns      | 0.4x                          |
| Has `& < > " '`                  | 100 B | a sentence with quotes          | 600 ns               | 451 ns      | 1.3x                          |
| Has `& < > " '`                  | 200 B | a sentence or two with quotes   | 1,107 ns             | 673 ns      | 1.6x                          |
| Has `& < > " '`                  | 1 KB  | a paragraph with quotes         | 5,292 ns             | 1,586 ns    | 3.3x                          |
| Has `& < > " '`                  | 10 KB | a 1,500-word article            | 52,126 ns            | 12,114 ns   | 4.3x                          |
| Accented text - no `& < > " '`   | 16 B  | `Café Montréal QC`              | 126 ns               | 331 ns      | 0.4x                          |
| Accented text - no `& < > " '`   | 100 B | a short French sentence         | 536 ns               | 426 ns      | 1.3x                          |
| Accented text - no `& < > " '`   | 200 B | a French sentence or two        | 1,017 ns             | 618 ns      | 1.6x                          |
| Accented text - no `& < > " '`   | 1 KB  | a French paragraph              | 4,813 ns             | 1,363 ns    | 3.5x                          |
| Accented text - no `& < > " '`   | 10 KB | a French article                | 47,358 ns            | 9,949 ns    | 4.8x                          |
| News-article page                | mixed | *                               | 53,800 ns            | 13,900 ns   | 3.9x                          |

\* News-article page: a 16 B quoted headline; author, category, and date (16 B plain); a 200 B caption; and a 10 KB body with quotes. This row is the whole page - all six fields together.

Per call (per page for the News-article row), best of 7, measured on Linux x86_64, PHP 8.5.8.

The News-article page row is those rows combined - here it is field by field,
every line taken from the table above:

| Field                        | Table row                          | `htmlspecialchars()` | SmartString  | Speed vs `htmlspecialchars()` |
|------------------------------|------------------------------------|----------------------|--------------|-------------------------------|
| Headline - `Mayor Says 'No'` | Has `& < > " '`, 16 B              | 0.14 µs              | 0.34 µs      | 0.4x                          |
| Author                       | Clean text - no `& < > " '`, 16 B  | 0.13 µs              | 0.18 µs      | 0.8x                          |
| Category                     | Clean text - no `& < > " '`, 16 B  | 0.13 µs              | 0.18 µs      | 0.8x                          |
| Date                         | Clean text - no `& < > " '`, 16 B  | 0.13 µs              | 0.18 µs      | 0.8x                          |
| Photo caption                | Clean text - no `& < > " '`, 200 B | 1.0 µs               | 0.29 µs      | 3.6x                          |
| Article body with quotes     | Has `& < > " '`, 10 KB             | 52.1 µs              | 12.1 µs      | 4.3x                          |
| **Whole page**               | All of the above                   | **53.8 µs** (0.0000538 s) | **13.9 µs** (0.0000139 s) | **3.9x**                      |

The longer the text, the bigger the win. This page is 3.9x faster because
almost all of its time is in the 10 KB body - short fields roughly break
even, and by 100 B per field everything is ahead.

Where SmartString is slower: short fields, where creating the object costs
more than the tiny encoding it replaces - about 0.05 microseconds extra on a
clean short field, about 0.2 when quotes or accents force a full encode.
You'd need 5,000 of the worst case on one page to lose a millisecond, and a
single clean 1 KB paragraph repays about 20 of them.

These numbers come from the repo's Speed Page Table workflow
([this run](https://github.com/interactivetools-com/SmartString/actions/runs/29882935220));
the command at the top of the page reproduces them on any machine. JIT on or
off makes no difference (see The Fine Print).

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

Three benchmark choices, stated plainly:

- **Every number above understates the win.** The helper is timed with the
  common flags, but SmartString always produces the stronger full-flag
  output:

  ```php
  htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');                                   // what we time against
  htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');      // what we actually produce
  ```

  The extra flags slow `htmlspecialchars()` itself by roughly 40% (they check
  every character). Timed against the matching full-flag call, the worked
  example's 3.9x page is roughly 5.5x.
- **Timings include creating the object.** Every SmartString in the loop is
  built fresh (`new SmartString($value)`) and then output - the multiplier is
  the full cost of each approach per value, nothing left out.
- **JIT changes nothing.** A full pass of the benchmark matrix with opcache's
  tracing JIT enabled produced the same verdicts as the JIT-off runs shown
  here.

Most libraries and frameworks run the full encoder on every value, every time.
Checking first and skipping the work when there is nothing to do is, as far as
we know, unique to SmartString - the kind of care we try to put into everything
we build.

---

[← Documentation Index](README.md)
