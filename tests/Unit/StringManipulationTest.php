<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use InvalidArgumentException;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use Itools\SmartString\Tests\Support\SmartStringTestCase;
use TypeError;

/**
 * textOnly(), trim(), maxWords(), maxChars(), pregReplace(), apply().
 *
 * n/a dimensions: encoding (these transform the raw value without encoding -
 * pinned in testTextOnlyDoesNotEncode), global settings.
 */
class StringManipulationTest extends SmartStringTestCase
{
    //region textOnly()

    #[DataProvider('textOnlyProvider')]
    public function testTextOnly($input, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->textOnly());
    }

    /** @noinspection SpellCheckingInspection */
    public static function textOnlyProvider(): array
    {
        return [
            'basic HTML removal'          => ['<p>Hello <b>World</b>!</p>', 'Hello World!'],
            'malformed HTML'              => ['<p>Hello <b>World!</p>', 'Hello World!'],
            'script tag removal'          => ['<script>alert("XSS");</script>Hello', 'alert("XSS");Hello'],
            'br with newlines'            => [
                "The<br>\nquick<BR />\nbrown<BR/>\nfox<bR> jumps<BR>over<BR/>the<BR   />lazy<BR   >dog",
                "The\nquick\nbrown\nfox jumpsoverthelazydog",
            ],
            'text-only input'             => ['Plain text', 'Plain text'],
            'empty string'                => ['', ''],
            'null input'                  => [null, null],
            'wysiwyg entities decoded'    => ["O'Reilly said &quot;this &gt; that&quot;", 'O\'Reilly said "this > that"'],
            'leading/trailing whitespace' => [' <b> Hello World </b>', 'Hello World'],
            'encoded < number is prose'   => ['Kids &lt;12 eat free', 'Kids <12 eat free'],
            'raw < number is prose'       => ['Kids <12 eat free', 'Kids <12 eat free'],
            'heart emoticon is prose'     => ['I <3 <b>bold</b> moves', 'I <3 bold moves'],
            'encoded script still strips' => ['&lt;script&gt;alert(1)&lt;/script&gt;Hi', 'alert(1)Hi'],
            '< letter still strips'       => ['price <ten dollars', 'price'],
            'nbsp becomes space'          => ['&nbsp;hello&nbsp;', 'hello'],
            'empty wysiwyg paragraph'     => ['<p>&nbsp;</p>', ''],
            'thin space becomes space'    => ["a\xE2\x80\x89b", 'a b'],
            'newlines and tabs kept'      => ["line1\nline2\ttab", "line1\nline2\ttab"],
        ];
    }

    public function testTextOnlyDoesNotEncode(): void
    {
        // strips and decodes, never encodes: raw apostrophes and ampersands survive
        $this->assertSame("O'Brien & Sons", SmartString::new("O'Brien &amp; Sons")->textOnly()->value());
    }

    public function testTextOnlyIsImmutable(): void
    {
        $original = SmartString::new('<b>bold</b>');
        $original->textOnly();
        $this->assertSame('<b>bold</b>', $original->value());
    }

    public function testTextOnlyMarkerCannotBeForged(): void
    {
        // Prose "<" hides behind a U+E000 marker while strip_tags runs. Input supplying its own
        // U+E000 must not come back out as "<", which would rebuild a tag after stripping.
        // Entities decode first, so &#xE000; reaches the marker logic the same way a raw one does.
        $marker = "\xEE\x80\x80"; // U+E000

        $this->assertSame("{$marker}img src=x onerror=alert(1)>", SmartString::new('&#xE000;img src=x onerror=alert(1)&gt;')->textOnly()->value());
        $this->assertSame("{$marker}img src=x onerror=alert(1)>", SmartString::new("{$marker}img src=x onerror=alert(1)>")->textOnly()->value());

        // A marker followed by the byte we pair it with: the doubled pair must be consumed as a
        // pair, not have its second half read as the start of one of ours.
        $this->assertSame("{$marker}\x01img src=x onerror=alert(1)>", SmartString::new("{$marker}\x01img src=x onerror=alert(1)>")->textOnly()->value());
        $this->assertSame("{$marker}{$marker}{$marker}\x01x", SmartString::new("{$marker}{$marker}{$marker}\x01<b>x</b>")->textOnly()->value());
    }

    public function testTextOnlyPreservesPrivateUseCharacters(): void
    {
        // U+E000 is data, not markup, so it survives even when adjacent to a prose "<"
        $marker = "\xEE\x80\x80"; // U+E000

        $this->assertSame("hi $marker there",     SmartString::new("hi $marker there")->textOnly()->value());
        $this->assertSame("two $marker$marker",   SmartString::new("two $marker$marker")->textOnly()->value());
        $this->assertSame("$marker<12",           SmartString::new("$marker<12")->textOnly()->value());
        $this->assertSame("<12$marker",           SmartString::new("<12$marker")->textOnly()->value());
    }

    public function testTextOnlyKeepsInvalidUtf8(): void
    {
        // space normalization runs byte-level: a /u pattern returns null on bad bytes and would blank the value
        $this->assertSame("caf\xE9 bad bytes", SmartString::new("caf\xE9 bad bytes")->textOnly()->value());
    }

    //endregion
    //region trim()

    #[DataProvider('trimProvider')]
    public function testTrim($input, ?string $characterMask, ?string $expected): void
    {
        $smartString = SmartString::new($input);
        $result      = $characterMask !== null ? $smartString->trim($characterMask) : $smartString->trim();
        $this->assertSmartString($expected, $result);
    }

    public function testTrimAcceptsSmartStringCharList(): void
    {
        // char list unwraps like every other value parameter (was TypeError: trim() under strict_types)
        $this->assertSame('abc', SmartString::new('xxabcxx')->trim(SmartString::new('x'))->value());
    }

    public static function trimProvider(): array
    {
        return [
            'basic whitespace trim'        => ['  Hello World  ', null, 'Hello World'],
            'trim specific characters'     => ['...Hello World...', '.', 'Hello World'],
            'trim mixed characters'        => ['...  Hello World  ...', ' .', 'Hello World'],
            'no trimming needed'           => ['Hello World', null, 'Hello World'],
            'trim all characters'          => ['aaaaaHelloa Worldaaaaa', 'a', 'Helloa World'],
            'empty string'                 => ['', null, ''],
            'string of only trimmed chars' => ['   ', null, ''],
            'null input'                   => [null, null, null],
        ];
    }

    /**
     * Pinned: args pass through untyped to PHP's trim(), so a wrong-type
     * mask is PHP's TypeError, not a silent coercion.
     */
    public function testTrimRejectsNonStringMask(): void
    {
        $this->expectException(TypeError::class);
        SmartString::new(' x ')->trim(5);
    }

    public function testTrimIsImmutable(): void
    {
        $original = SmartString::new('  padded  ');
        $original->trim();
        $this->assertSame('  padded  ', $original->value());
    }

    //endregion
    //region maxWords()

    #[DataProvider('maxWordsProvider')]
    public function testMaxWords($input, int $max, string $ellipsis, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->maxWords($max, $ellipsis));
    }

    public static function maxWordsProvider(): array
    {
        return [
            'normal input'              => ['The quick brown fox jumps over the lazy dog', 5, '...', 'The quick brown fox jumps...'],
            'input less than max words' => ['Hello world', 5, '...', 'Hello world'],
            'input equal to max words'  => ['One two three four five', 5, '...', 'One two three four five'],
            'empty string'              => ['', 3, '...', ''],
            'null input'                => [null, 3, '...', null],
            'numeric input'             => [12345, 2, '...', '12345'],
            'very large max words'      => ['Short sentence', 1000, '...', 'Short sentence'],
            'max words 0 shows ellipsis' => ['Test sentence', 0, '...', '...'], // nothing shown, ellipsis marks the hidden content
            'max words 0 on empty'      => ['', 0, '...', ''], // missing passes through before ellipsis logic
            'negative max clamps to 0'  => ['Test sentence', -5, '...', '...'], // same answer as 0: even less than none
            'whitespace-only, max 0'    => ['   ', 0, '...', ''], // no words exist, so no ellipsis
            'custom ellipsis'           => ['The quick brown fox jumps over the lazy dog', 4, ' [...]', 'The quick brown fox [...]'],
            'multiple spaces collapse'  => ['Word1    Word2     Word3', 2, '...', 'Word1 Word2...'],
            'leading/trailing spaces'   => ['  Trimmed input test  ', 2, '...', 'Trimmed input...'],
            'trailing punctuation'      => ['Hello, world! How are you?', 2, '~~~', 'Hello, world~~~'],
            'multibyte characters'      => ['こんにちは 世界 テスト', 2, '...', 'こんにちは 世界...'],
            'mixed ascii and multibyte' => ['Hello こんにちは World 世界', 3, '...', 'Hello こんにちは World...'],
            'empty ellipsis'            => ['One two three four', 2, '', 'One two'],
            'html content'              => ['<p>First</p> <div>Second</div> <span>Third</span>', 2, '...', '<p>First</p> <div>Second</div>...'],
            'invalid utf8 becomes �'    => ["caf\xE9 latte and more words here", 2, '...', 'caf� latte...'],
        ];
    }

    //endregion
    //region maxChars()

    #[DataProvider('maxCharsProvider')]
    public function testMaxChars($input, int $max, string $ellipsis, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->maxChars($max, $ellipsis));
    }

    public static function maxCharsProvider(): array
    {
        return [
            'normal input'              => ['The quick brown fox jumps over the lazy dog', 20, '...', 'The quick brown fox...'],
            'input less than max chars' => ['Hello world', 20, '...', 'Hello world'],
            'input equal to max chars'  => ['Exactly twenty chars', 20, '...', 'Exactly twenty chars'],
            'empty string'              => ['', 10, '...', ''],
            'null input'                => [null, 10, '...', null],
            'numeric input'             => [12345, 3, '...', '123...'],
            'very large max chars'      => ['Short sentence', 1000, '...', 'Short sentence'],
            'max chars 0 shows ellipsis' => ['Test sentence', 0, '...', '...'], // nothing shown, ellipsis marks the hidden content
            'max chars 0 on empty'      => ['', 0, '...', ''], // missing passes through before ellipsis logic
            'negative max clamps to 0'  => ['Test sentence', -5, '...', '...'], // was 'Test sen...' via mb_substr negative-length semantics
            'whitespace-only, max 0'    => ['   ', 0, '...', ''], // collapses to no content, so no ellipsis
            'custom ellipsis'           => ['The quick brown fox!', 15, ' [...]', 'The quick brown [...]'],
            'multiple spaces collapse'  => ['Word1    Word2     Word3', 12, '...', 'Word1 Word2...'],
            'leading/trailing spaces'   => ['  Trimmed  input test  ', 10, '...', 'Trimmed...'],
            'multibyte characters'      => ['こんにちは世界', 5, '...', 'こんにちは...'],
            'word boundary'             => ['The quick brown fox', 12, '...', 'The quick...'],
            'punctuation at cut-off'    => ['Hello, world! How are you?', 13, '...', 'Hello, world...'],
            'very short max chars'      => ['Testing', 1, '...', 'T...'],
            'exact boundary'            => ['1234567890', 10, '...', '1234567890'],
            'one over boundary'         => ['12345678901', 10, '...', '1234567890...'],
            'empty ellipsis'            => ['The quick brown fox', 10, '', 'The quick'],
            'html entities as chars'    => ['&amp; &lt; &gt;', 5, '...', '&amp...'],
            'invalid utf8 becomes �'    => ["caf\xE9 latte and more words here", 10, '...', 'caf� latte...'],
            'invalid utf8 no truncate'  => ["caf\xE9", 10, '...', 'caf�'],
            'punctuation at hard cut'   => ['abcde,fghijklmnop', 6, '...', 'abcde...'], // trailing punctuation strips on hard cuts too
        ];
    }

    /**
     * PCRE caps {1,n} quantifiers at 65535. The old regex-based word cut emitted
     * "Compilation failed: number too big in {} quantifier" above that and fell
     * back to a mid-word hard cut.
     */
    public function testMaxCharsAbovePcreQuantifierLimit(): void
    {
        $text = trim(str_repeat('word ', 20000)); // 99,999 chars

        foreach ([65535 => 65534, 65536 => 65534, 70000 => 69999] as $max => $cutAt) {
            $expected = substr($text, 0, $cutAt) . '...';
            $this->assertSame($expected, SmartString::new($text)->maxChars($max)->value(), "max=$max");
        }
    }

    /**
     * maxChars() counts UTF-8 characters no matter what mb_internal_encoding() is set
     * to - any legacy include can change that global mid-request. Under ISO-8859-1 the
     * old code counted bytes (13), falsely truncating this 11-character string.
     */
    public function testMaxCharsIgnoresMbInternalEncoding(): void
    {
        $original = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
        try {
            $this->assertSame('héllo wörld', SmartString::new('héllo wörld')->maxChars(12)->value());
        } finally {
            mb_internal_encoding($original);
        }
    }

    //endregion
    //region pregReplace()

    #[DataProvider('pregReplaceProvider')]
    public function testPregReplace($input, string $pattern, string $replacement, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->pregReplace($pattern, $replacement));
    }

    public static function pregReplaceProvider(): array
    {
        return [
            'strip non-digits'     => ['(555) 123-4567', '/\D/', '', '5551234567'],
            'normalize whitespace' => ["hello   \t world", '/\s+/', ' ', 'hello world'],
            'backreference'        => ['my-slug', '/(.+)/', 'pre-$1', 'pre-my-slug'],
            'no match (unchanged)' => ['hello', '/\d+/', 'X', 'hello'],
            'empty string input'   => ['', '/./', 'X', ''],
            'null input'           => [null, '/./', 'X', null],
            'empty skips regex'    => ['', '/^\d*/', 'ID-', ''],   // "" is missing: the regex never runs, or() fallbacks still fire
            'null skips regex'     => [null, '/^$/', 'X', null],
            'html in raw value'    => ['<b>bold</b>', '/<[^>]+>/', '', 'bold'],
            'integer input'        => [12345, '/(\d{3})(\d+)/', '$1-$2', '123-45'],
        ];
    }

    /**
     * An invalid pattern throws InvalidArgumentException
     * (message UX beats a PHP warning). The PCRE reason text varies by PCRE2
     * version, so only the stable message prefix is asserted.
     */
    public function testPregReplaceThrowsOnInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pregReplace(): preg_replace(): Compilation failed');
        SmartString::new('test')->pregReplace('/[/', 'X');
    }

    public function testPregReplaceNullInputSkipsPatternCheck(): void
    {
        // null propagates before the pattern runs, even an invalid one
        $this->assertNull(SmartString::new(null)->pregReplace('/[/', 'X')->value());
    }

    public function testPregReplaceIsImmutable(): void
    {
        $original = SmartString::new('hello world');
        $original->pregReplace('/world/', 'there');
        $this->assertSame('hello world', $original->value());
    }

    //endregion
    //region map()

    #[DataProvider('mapProvider')]
    public function testMap($input, $function, array $args, ?string $expected): void
    {
        $this->assertSmartString($expected, SmartString::new($input)->map($function, ...$args));
    }

    public static function mapProvider(): array
    {
        return [
            'strtoupper'          => ['hello world', 'strtoupper', [], 'HELLO WORLD'],
            'trim'                => ['  spaced  ', 'trim', [], 'spaced'],
            'trim with argument'  => ['xxxhelloxxx', 'trim', ['x'], 'hello'],
            'arrow function'      => ['hello', fn($s) => $s . ' world', [], 'hello world'],
            'arrow fn with arg'   => ['hello', fn($s, $suffix) => $s . $suffix, [' universe'], 'hello universe'],
            'first-class callable' => ['  x  ', trim(...), [], 'x'],
            'blank is a string'   => ['', fn($s) => str_pad($s, 5, '*'), [], '*****'],
        ];
    }

    /**
     * The callback always runs and receives the raw value, null included -
     * same contract as array_map() and SmartArray::map(). Built-ins that
     * reject null need ->ifNull('') first.
     */
    public function testMapCallbackAlwaysRunsIncludingNull(): void
    {
        $this->assertSmartString('X', SmartString::new(null)->map(fn($v) => $v ?? 'X'));

        $received = 'sentinel';
        SmartString::new(null)->map(function ($v) use (&$received) {
            $received = $v;
            return $v;
        });
        $this->assertNull($received, 'callback must receive raw null, not a coerced value');

        $this->expectException(TypeError::class); // strict built-ins reject null - the docs say to convert with map('strval') first
        SmartString::new(null)->map('strtoupper');
    }

    public function testMapRescueFirstRecipe(): void
    {
        // the documented recipe: map('strval') converts null AND numerics before strict built-ins
        $this->assertSmartString('', SmartString::new(null)->map('strval')->map('strtoupper'));
        $this->assertSmartString('42', SmartString::new(42)->map('strval')->map('strtoupper'));
        $this->assertSmartString('DEFAULT', SmartString::new(null)->ifNull('default')->map('strtoupper'));
    }

    public function testMapRejectsUncallableName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'non_existent_function' is not callable");
        SmartString::new('test')->map('non_existent_function');
    }

    public function testMapRejectsNonScalarReturn(): void
    {
        // message names no method: the deprecated apply() forwards here, and an error
        // shouldn't name a method the caller never used
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The callback must return a scalar value (string, int, float, bool, or null), got array');
        SmartString::new('test')->map(fn($s) => [$s]);
    }

    public function testMapCallbackReceivesRawValue(): void
    {
        $received = null;
        SmartString::new('<b>raw</b>')->map(function ($value) use (&$received) {
            $received = $value;
            return $value;
        });
        $this->assertSame('<b>raw</b>', $received);
    }

    //endregion
}
