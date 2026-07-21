# Performance: 4x Faster Than Calling `htmlspecialchars()` Yourself

Our automatic encoding produces byte-identical output to `htmlspecialchars()`
and is faster: about 4x on a real-world page. Most
values don't need encoding, and proving that with a scan costs less than encoding
them anyway. This page shows how that works, the
measurements, and the tests that keep the shortcut honest.

The exact multiplier depends on the platform: our scans cost about the same
everywhere, so the win tracks how slowly each platform's `htmlspecialchars()`
runs. On a realistic page mix:

- **Linux x64** - the most common web platform: **4.1x**
- **Linux ARM** - Graviton-class hosts: **2.9x**
- **Windows** - its PHP builds encode slowest: **11x**, and long clean fields
  reach 43x

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
yourself), against echoing a SmartString, which encodes itself automatically.

```php
// The helper being timed - the standard safe call, wrapped once per project
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$title = SmartString::new("Annual Report 2026");

echo e("Annual Report 2026");   // helper: runs the full encoder on every call
echo $title;                    // SmartString: scans first, encodes only if needed
```

Measured on Linux x64, PHP 8.5 with opcache, on GitHub Actions' standard cloud
servers; output was verified byte-identical before every timing run. Timings are identical
whether you write `<?= $title ?>` or `echo $title;`. The test content matches
the character mix of real English and French writing, measured on both classic
and current text.

| Content                        | Size  | Example                         | Speed vs `htmlspecialchars()` |
|--------------------------------|-------|---------------------------------|-------------------------------|
| Clean text - no `& < > " '`    | 16 B  | `Annual Report 2026`            | 1.0x                          |
| Clean text - no `& < > " '`    | 1 KB  | a plain-text paragraph          | 9.4x                          |
| Clean text - no `& < > " '`    | 10 KB | a long field, nothing to encode | 13x                           |
| Has `& < > " '`                | 16 B  | `O'Brien & Co Ltd`              | 0.5x                          |
| Has `& < > " '`                | 1 KB  | a paragraph with quotes         | 3.6x                          |
| Has `& < > " '`                | 10 KB | a 1,500-word article            | 4.4x                          |
| Accented text - no `& < > " '` | 16 B  | `Café Montréal QC`              | 0.4x                          |
| Accented text - no `& < > " '` | 1 KB  | a French paragraph              | 3.8x                          |
| Accented text - no `& < > " '` | 10 KB | a French article                | 4.8x                          |

Real pages combine those rows. Here is a news-article page priced field by
field (the ~200 B caption sits between the measured sizes, so its numbers are
interpolated):

| Field                        | Table row              | `htmlspecialchars()` | SmartString  | Speed vs `htmlspecialchars()` |
|------------------------------|------------------------|----------------------|--------------|-------------------------------|
| Headline - `Mayor Says 'No'` | Has `& < > " '`, 16 B  | 0.12 µs              | 0.26 µs      | 0.5x                          |
| Author                       | Clean text, 16 B       | 0.11 µs              | 0.12 µs      | 1.0x                          |
| Category                     | Clean text, 16 B       | 0.11 µs              | 0.12 µs      | 1.0x                          |
| Date                         | Clean text, 16 B       | 0.11 µs              | 0.12 µs      | 1.0x                          |
| Photo caption                | Clean text, ~200 B     | ~1 µs                | ~0.2 µs      | ~5x                           |
| Article body with quotes     | Has `& < > " '`, 10 KB | 52.1 µs              | 11.9 µs      | 4.4x                          |
| **Whole page**               |                        | **~53.6 µs**         | **~12.7 µs** | **4.2x**                      |

The body settles the outcome: the five short fields together cost under two
microseconds on either side, the quoted headline is the one field SmartString
loses (by 0.14 µs), and the body alone saves 40 µs. A body pasted with smart
quotes would contain none of `& < > " '`, land on the clean 10 KB row, and
save even more. The measured page mix behind the bullets at the top prices
out the same way.

The one case where SmartString is slower: short fields with quotes or accents,
where the scan finds something and full encoding has to run anyway. That costs
about 0.2 microseconds per field - you'd need 5,000 of them on one page to
lose a millisecond - and the wins everywhere else repay it many times over.

These numbers come from the repo's Speed Page Table workflow
([this run](https://github.com/interactivetools-com/SmartString/actions/runs/29868668475));
you can run it yourself any time to confirm the numbers.

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

Three notes for careful readers. The baseline helper runs the common faster
flags while SmartString always produces the stronger full-flag output; full
flags would slow the baseline about 50% on long strings, so the multipliers
above understate the win. Timings cover output, not construction - values
from SmartArray or ZenDB arrive already wrapped, and constructing one
yourself adds about 60ns per field. And a full pass of the benchmark matrix
with opcache's tracing JIT enabled produced the same verdicts as the JIT-off
runs shown here.

Most libraries and frameworks run the full encoder on every value, every time.
Checking first and skipping the work when there is nothing to do is, as far as
we know, unique to SmartString - the kind of care we try to put into everything
we build.

---

[← Documentation Index](README.md)
