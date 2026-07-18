<!-- Example output like &apos; includes a zero-width space (U+200B) after the "&" so PHPStorm's Markdown preview displays it correctly instead of decoding it. -->

# Encoding and HTML

Auto-encoding is SmartString's main job, and this page covers all of it:
how encoding works, the encoding methods for URLs and JSON, and the named
methods that let real markup through: `nl2br()`, `rawHtml()`, and the
`appendHtml()`/`wrapHtml()` pair. Markup enters your page only through
those named methods, which is what makes SmartString output reviewable:
search for `rawHtml(` and `Html(` and you have found every place unencoded
markup can appear.

## How Auto-Encoding Works

Whenever a SmartString is used in a string context, PHP calls the object's
`__toString()` method, which runs `htmlspecialchars()` on the raw value. All
of these produce encoded output:

```php
$str = SmartString::new("It's <b>easy</b> & fun!");

echo $str;              // It&​apos;s &​lt;b&​gt;easy&​lt;/b&​gt; &​amp; fun!
print $str;             // same
$html = "Value: $str";  // interpolation encodes too
$cast = (string) $str;  // explicit cast encodes too
```

Every one of those lines replaces writing this by hand:

```php
echo htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8');
```

Those flags cover the five characters that enable HTML injection (`<`, `>`,
`&`, `'`, `"`), and substitute malformed UTF-8 and forbidden code points
with � instead of letting them hide in page source.

Your original data stays intact inside the object, and nothing is encoded
until the moment a value is printed. Wrapping a value just stores it, so it
costs next to nothing; encoding work happens only for values that actually
reach the page.

## Encoding Is Output, Not Storage

You never need to store encoded data. Encoding is an output step: store the
raw value, and it gets encoded automatically on its way into the page:

```php
// WRONG - database now contains "Jean O&​apos;Brien"
$name = htmlspecialchars($_POST['name']);
DB::insert('users', ['name' => $name]);

// RIGHT - store raw, encode on output
DB::insert('users', ['name' => $_POST['name']]);
```

Storing raw data preserves integrity: you can encode it differently for
different contexts (HTML, URL, JSON) without re-decoding stored data.
SmartString makes this natural: data goes in raw, comes out encoded.

## The Encoding Methods

These return the encoded value as a plain string, so they end the chain.
Missing values (null or `""`) return `""`, so echoing an empty field prints
nothing. The one exception is `jsonEncode()`, which encodes null as `null` and an
empty string as a quoted `""`, because its output must always be a valid
JavaScript expression.

### HTML Encoding - `htmlEncode()`

Same output as `echo`, as an explicit call. Use it when you want a plain
encoded string rather than an object, or when the encoding deserves to be
visible in the code:

```php
$title = SmartString::new('<10% OFF "SALE"');

echo $title->htmlEncode();  // &​lt;10% OFF &​quot;SALE&​quot;
echo $title;                // same output - echo already encodes
```

### URL Encoding - `urlEncode()`

Returns the value URL-encoded for use in query strings. HTML encoding is the
wrong encoding inside an `href` query parameter; use `urlEncode()` for the
query value - the rest of the URL is literal text you typed, which needs no
encoding:

```php
$title = SmartString::new('<10% OFF "SALE"');

echo "<a href='search.php?title={$title->urlEncode()}'>Search</a>";
// <a href='search.php?title=%3C10%25+OFF+%22SALE%22'>Search</a>
```

Linking to a file or path that might have spaces in the name? Use
`map('rawurlencode')`. The `+` from `urlEncode()` looks nicer in URLs, but
only query strings read it as a space - files and paths need `%20`:

```php
$file = SmartString::new('Annual Report 2026.pdf');

echo "<a href='/uploads/{$file->map('rawurlencode')}'>Download</a>";
// <a href='/uploads/Annual%20Report%202026.pdf'>Download</a>
```

### JSON Encoding - `jsonEncode()`

Returns the value as a JSON string safe to embed in a `<script>` block. The
characters that could break out of a script or a JS string (`"` `'` `<` `>`
`&`) are escaped as `\uXXXX`:

```php
$title = SmartString::new("It's <b>easy</b> & fun!");

echo "<script>let title = {$title->jsonEncode()};</script>";
// <script>let title = "It\u0027s \u003Cb\u003Eeasy\u003C/b\u003E \u0026 fun!";</script>
```

Types encode as themselves: null becomes `null`, `123` becomes `123`, so the
result is always a valid JavaScript expression. Two hardening details you
get for free:

- Malformed UTF-8 is substituted with � instead of throwing.
- Invisible Unicode (zero-width characters, bidi controls, variation
  selectors) is re-escaped as visible `\uXXXX` escapes, so nothing can hide
  in page source.

## Newlines to `<br>` - `nl2br()`

Multiline plain text (addresses, hours, bios) needs line breaks in HTML.
Encoding alone can't provide them, and PHP's native `nl2br()` doesn't encode.
SmartString's `nl2br()` does both in the safe order: encode first, then
convert newlines, so the only tags in the result are the `<br>` tags it
added:

```php
$address = SmartString::new("Bob & Sons\nSuite 5");

echo $address->nl2br();
// Bob &​amp; Sons<br>
// Suite 5
```

The result is a plain string, already encoded and ready to print, even with
hostile input:

```php
$comment = SmartString::new("Nice!\n<script>alert('xss')</script>");

echo $comment->nl2br();
// Nice!<br>
// &​lt;script&​gt;alert(&​apos;xss&​apos;)&​lt;/script&​gt;
```

## Trusted HTML - `rawHtml()`

Some fields legitimately contain HTML, most commonly WYSIWYG editor content.
For those, `rawHtml()` returns the original value unencoded. It behaves the
same as `value()`, the raw-value escape hatch from
[Getting Started](getting-started.md#converting-to-plain-php-types); the
different name signals intent to other developers:
"I know this is unencoded, and that is deliberate."

```php
echo <<<__HTML__
    <h1>{$article->title}</h1>
    {$article->wysiwygContent->rawHtml()}
__HTML__;
```

The title auto-encodes; the WYSIWYG content outputs as-is. Never pass
unreviewed user input through `rawHtml()`; it bypasses encoding entirely.
Reserve it for content produced by trusted editors, and let everything else
take the encoded default.

## Adding Markup Around Values - `appendHtml()` and `wrapHtml()`

The most common reason people reach for raw output is not trusted content,
it is wanting one tag next to an encoded value: a `<br>` after each address
line, an `<h2>` around a heading. These two methods handle that case without
giving up encoding. The value is HTML-encoded; your markup is appended or
wrapped as-is; and when the value is missing (null or `""`), the whole
result is `""` so no stray markup appears:

```php
// appendHtml($html): encoded value + your markup - addressLine2 is blank, so its line vanishes
echo $member->addressLine1->appendHtml("<br>\n");  // 12 High St<br>
echo $member->addressLine2->appendHtml("<br>\n");  // "" (blank: no stray <br>)
echo $member->city->appendHtml("<br>\n");          // Vancouver<br>
echo $member->country;                             // Canada

// wrapHtml($before, $after): markup + encoded value + markup - wrapper vanishes when missing
echo $page->subheading->wrapHtml('<h2 class="lead">', '</h2>');       // <h2 class="lead">Our Story</h2>
echo $member->email->wrapHtml('<a href="mailto:', '">Email me</a>');  // <a href="mailto:jean@example.com">Email me</a>
echo $page->tagline->wrapHtml('<h2>', '</h2>');                       // "" (tagline is empty: no empty <h2>)
```

Zero is a real value, not a missing one, so a legitimate `0` still gets its
markup.

Two rules keep these safe:

- **The markup arguments are trusted and output as-is.** Only pass literals
  you wrote; never pass user input as the markup argument.
- **They return a plain string, ending the chain.** Nothing downstream can
  re-encode the markup, so double encoding is structurally impossible.

When what you're adding is plain text (a label, a comma) rather than markup,
use the regular
[append(), prepend(), and wrap()](text-and-formatting.md#adding-text-around-values---append-prepend-and-wrap)
methods instead; their added text encodes like any other output and the
chain stays open.

Both sides of `wrapHtml()` are required, and the value is inserted exactly
once; that is why the email example above uses fixed link text. When the
value needs to appear more than once, or the block spans several fields,
check `isNotEmpty()` and write the HTML in plain PHP:

```php
// value used twice: the address is both the href and the link text
if ($member->email->isNotEmpty()) {
    echo "<a href='mailto:$member->email'>$member->email</a>";
}

// multiple fields in one block: photo with the name as alt text
if ($member->photo->isNotEmpty()) {
    echo "<img src='/photos/$member->photo' alt='$member->name'>";
}
```

---

[← Documentation Index](README.md) | [← Prev: Getting Started](getting-started.md) | [Next: Text and Formatting →](text-and-formatting.md)
