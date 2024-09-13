<?php
declare(strict_types=1);

namespace Tests\Methods;

use PHPUnit\Framework\TestCase;
use Itools\SmartString\SmartString;

class StringsTest extends TestCase
{
    //region stripTags

    /**
     * @dataProvider stripTagsProvider
     */
    public function testStripTags($input, $allowedTags, $expected): void
    {
        $result = SmartString::new($input)->stripTags($allowedTags)->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function stripTagsProvider(): array
    {
        return [
            'basic HTML removal'  => [
                '<p>Hello <b>World</b>!</p>',
                null,
                'Hello World!',
            ],
            'allow specific tags' => [
                '<p>Hello <b>World</b>!</p>',
                '<p>',
                '<p>Hello World!</p>',
            ],
            'nested tags'         => [
                '<div><p>Hello <b>World</b>!</p></div>',
                '<p>',
                '<p>Hello World!</p>',
            ],
            'malformed HTML'      => [
                '<p>Hello <b>World!</p>',
                null,
                'Hello World!',
            ],
            'script tag removal'  => [
                '<script>alert("XSS");</script>Hello',
                null,
                'alert("XSS");Hello',
            ],
            'br with nextlines'   => [
                "The<br>\nquick<BR />\nbrown<BR/>\nfox<bR> jumps<BR>over<BR/>the<BR   />lazy<BR   >dog",
                null,
                "The\nquick\nbrown\nfox jumpsoverthelazydog",
            ],
            'non-HTML input'      => [
                'Plain text',
                null,
                'Plain text',
            ],
            'empty string'        => [
                '',
                null,
                '',
            ],
            'null input'          => [
                null,
                null,
                null,
            ],
            'Wysiwyg content' => [
                "O'Reilly said &quot;this &gt; that&quot;",
                null,
                'O\'Reilly said &quot;this &gt; that&quot;', // doesn't decode HTML entities
            ],
        ];
    }

    //endregion
    //region textOnly

    /**
     * @dataProvider textOnlyProvider
     */
    public function testTextOnly($input, $expected): void
    {
        $result = SmartString::new($input)->textOnly()->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function textOnlyProvider(): array
    {
        return [
            'basic HTML removal'  => [
                '<p>Hello <b>World</b>!</p>',
                'Hello World!',
            ],
            'malformed HTML'      => [
                '<p>Hello <b>World!</p>',
                'Hello World!',
            ],
            'script tag removal'  => [
                '<script>alert("XSS");</script>Hello',
                'alert("XSS");Hello',
            ],
            'br with nextlines'   => [
                "The<br>\nquick<BR />\nbrown<BR/>\nfox<bR> jumps<BR>over<BR/>the<BR   />lazy<BR   >dog",
                "The\nquick\nbrown\nfox jumpsoverthelazydog",
            ],
            'text-only input'      => [
                'Plain text',
                'Plain text',
            ],
            'empty string'        => [
                '',
                '',
            ],
            'null input'          => [
                null,
                null,
            ],
            'Wysiwyg content' => [
                "O'Reilly said &quot;this &gt; that&quot;",
                'O\'Reilly said "this > that"',
            ],
            'leading/trailing whitespace'          => [
                " <b> Hello World </b>",
                "Hello World",
            ],
        ];
    }

    //endregion
    //region nl2br

    /**
     * @dataProvider nl2brProvider
     * @noinspection OneTimeUseVariablesInspection
     */
    public function testNl2br($input, $expected): void
    {
        $smartString = new SmartString($input);
        $result      = $smartString->nl2br()->value();
        $this->assertSame($expected, $result);
    }

    public function nl2brProvider(): array
    {
        return [
            'basic newline conversion'    => [
                "Hello\nWorld",
                "Hello<br>\nWorld",
            ],
            'multiple newlines'           => [
                "Hello\nWorld\nAgain",
                "Hello<br>\nWorld<br>\nAgain",
            ],
            'carriage return and newline' => [
                "Hello\r\nWorld",
                "Hello<br>\r\nWorld",
            ],
            'mixed newlines'              => [
                "Hello\nWorld\r\nAgain\rAnd\n\rAgain",
                "Hello<br>\nWorld<br>\r\nAgain<br>\rAnd<br>\n\rAgain",
            ],
            'no newlines'                 => [
                'Hello World',
                'Hello World',
            ],
            'empty string'                => [
                '',
                '',
            ],
            'null input'                  => [
                null,
                null,
            ],
            'newlines at start and end'   => [
                "\nHello World\n",
                "<br>\nHello World<br>\n",
            ],
            'consecutive newlines'        => [
                "Hello\n\n\nWorld",
                "Hello<br>\n<br>\n<br>\nWorld",
            ],
        ];
    }

    //endregion
    //region trim

    /**
     * @dataProvider trimProvider
     */
    public function testTrim($input, $characterMask, $expected): void
    {
        $smartString = new SmartString($input);
        $result      = $characterMask ? $smartString->trim($characterMask)->value() : $smartString->trim()->value();
        $this->assertSame($expected, $result);
    }

    /**
     * @return array
     * @noinspection SpellCheckingInspection
     */
    public function trimProvider(): array
    {
        return [
            'basic whitespace trim'        => [
                '  Hello World  ',
                null,
                'Hello World',
            ],
            'trim specific characters'     => [
                '...Hello World...',
                '.',
                'Hello World',
            ],
            'trim mixed characters'        => [
                '...  Hello World  ...',
                ' .',
                'Hello World',
            ],
            'no trimming needed'           => [
                'Hello World',
                null,
                'Hello World',
            ],
            'trim all characters'          => [
                'aaaaaHelloa Worldaaaaa',
                'a',
                'Helloa World',
            ],
            'empty string'                 => [
                '',
                null,
                '',
            ],
            'string of only trimmed chars' => [
                '   ',
                null,
                '',
            ],
            'null input'                   => [
                null,
                null,
                null
            ],
        ];
    }

    // endregion
    // region maxWords

    /**
     * @dataProvider maxWordsProvider
     */
    public function testMaxWords($input, $max, $ellipsis, $expected): void
    {
        $result = SmartString::new($input)->maxWords($max, $ellipsis);
        $this->assertSame($expected, $result->value(), "maxWords() method failed for input: " . var_export($input, true));
    }

    public function maxWordsProvider(): array
    {
        return [
            'normal input'              => ['The quick brown fox jumps over the lazy dog', 5, '...', 'The quick brown fox jumps...'],
            'input less than max words' => ['Hello world', 5, '...', 'Hello world'],
            'input equal to max words'  => ['One two three four five', 5, '...', 'One two three four five'],
            'empty string'              => ['', 3, '...', ''],
            'null input'                => [null, 3, '...', null],
            'numeric input'             => [12345, 2, '...', '12345'],
            'very large max words'      => ['Short sentence', 1000, '...', 'Short sentence'],
            'max words set to 0'        => ['Test sentence', 0, '...', '...'],
            'custom ellipsis'           => ['The quick brown fox jumps over the lazy dog', 4, ' [...]', 'The quick brown fox [...]'],
            'multiple spaces'           => ['Word1    Word2     Word3', 2, '...', 'Word1 Word2...'],
            'leading/trailing spaces'   => ['  Trimmed input test  ', 2, '...', 'Trimmed input...'],
            'trailing punctuation'      => ['Hello, world! How are you?', 2, '~~~', 'Hello, world~~~'],
        ];
    }

    // endregion
    // region maxChars

    /**
     * @dataProvider maxCharsProvider
     */
    public function testMaxChars($input, $max, $ellipsis, $expected): void
    {
        $result = SmartString::new($input)->maxChars($max, $ellipsis);
        $this->assertSame($expected, $result->value(), "maxChars() method failed for input: " . var_export($input, true));
    }

    public function maxCharsProvider(): array
    {
        return [
            'normal input'               => ['The quick brown fox jumps over the lazy dog', 20, '...', 'The quick brown fox...'],
            'input less than max chars'  => ['Hello world', 20, '...', 'Hello world'],
            'input equal to max chars'   => ['Exactly twenty chars', 20, '...', 'Exactly twenty chars'],
            'empty string'               => ['', 10, '...', ''],
            'null input'                 => [null, 10, '...', null],
            'numeric input'              => [12345, 3, '...', '123...'],
            'very large max chars'       => ['Short sentence', 1000, '...', 'Short sentence'],
            'max chars set to 0'         => ['Test sentence', 0, '...', '...'],
            'custom ellipsis'            => ['The quick brown fox!', 15, ' [...]', 'The quick brown [...]'],
            'multiple spaces'            => ['Word1    Word2     Word3', 12, '...', 'Word1 Word2...'],
            'leading/trailing spaces'    => ['  Trimmed  input test  ', 10, '...', 'Trimmed...'],
            'multibyte characters'       => ['こんにちは世界', 5, '...', 'こんにちは...'],
            'word boundary'              => ['The quick brown fox', 12, '...', 'The quick...'],
            'punctuation at cut-off'     => ['Hello, world! How are you?', 13, '...', 'Hello, world...'],
            'very short max chars'       => ['Testing', 1, '...', 'T...'],
        ];
    }

    // endregion


}
