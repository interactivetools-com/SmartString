<?php
declare(strict_types=1);

namespace Tests\Unit;

use Error;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\CallerException;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Support\SmartStringTestCase;

/**
 * __get(), __call(), __callStatic(), __debugInfo().
 *
 * Warning and Error texts are asserted exactly after stripping the
 * "Occurred in file:line" location block (the line number shifts every
 * edit; the block's format is asserted separately).
 *
 * n/a dimensions: global settings, immutability, argument matrix.
 */
class MagicMethodsTest extends SmartStringTestCase
{
    //region __get()

    public function testGetWarnsWhenMethodAccessedWithoutBrackets(): void
    {
        $expected = <<<'__TEXT__'
        $str->htmlEncode needs brackets() everywhere and {curly braces} in strings:
            ✓ Outside strings:         $str->htmlEncode()
            ✗ Missing brackets:        $str->htmlEncode
            ✓ Inside strings:          "Hello {$str->htmlEncode()}"
            ✗ Missing { } in string:   "Hello $str->htmlEncode()"
        __TEXT__ . "\n";

        $result = $this->expectUserWarning(fn() => SmartString::new('x')->htmlEncode, $expected);
        $this->assertSmartString(null, $result); // null wrapper instead of a fatal
    }

    public function testGetWarnsOnUnknownProperty(): void
    {
        $result = $this->expectUserWarning(
            fn() => SmartString::new('x')->bogusProperty,
            "Undefined property: SmartString->bogusProperty\n"
        );
        $this->assertSmartString(null, $result);
    }

    public function testGetInterpolatesAsEmptyStringAfterWarning(): void
    {
        // "$str->htmlEncode" in a string triggers __get, which returns
        // SmartString(null), so the page renders "" where the value was
        $str    = SmartString::new('x');
        $result = $this->expectUserWarning(
            fn() => "Hello $str->htmlEncode",
            <<<'__TEXT__'
            $str->htmlEncode needs brackets() everywhere and {curly braces} in strings:
                ✓ Outside strings:         $str->htmlEncode()
                ✗ Missing brackets:        $str->htmlEncode
                ✓ Inside strings:          "Hello {$str->htmlEncode()}"
                ✗ Missing { } in string:   "Hello $str->htmlEncode()"
            __TEXT__ . "\n"
        );
        $this->assertSame('Hello ', $result);
    }

    //endregion
    //region __call() Deprecation Shims

    public function testNoEncodeShimReturnsRawValue(): void
    {
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::new('<b>x</b> & y')->noEncode(),
            'Replace ->noEncode() with ->rawHtml()'
        );
        $this->assertSame('<b>x</b> & y', $result);
    }

    public function testToStringShimReturnsHtmlEncoded(): void
    {
        // the message names htmlEncode() first because that is the
        // behavior-preserving replacement
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::new('<b>x</b>')->toString(),
            'Replace ->toString() with ->htmlEncode() or ->string()'
        );
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', $result);
    }

    public function testJsEncodeShimEscapesForJavaScript(): void
    {
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::new("O'Brien <b>\n")->jsEncode(),
            'Replace ->jsEncode() with ->jsonEncode() (not identical functionality, code refactoring required)'
        );
        $this->assertSame("O\\'Brien \\<b\\>\\n", $result);
    }

    public function testStripTagsShimReturnsSmartString(): void
    {
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::new('<p>Hi <b>there</b></p>')->stripTags(),
            'Replace ->stripTags() with ->textOnly()'
        );
        $this->assertSmartString('Hi there', $result);
    }

    public function testStripTagsShimPassesArgsAndNullThrough(): void
    {
        [$results, $messages] = $this->captureDeprecations(fn() => [
            SmartString::new('<p>Hi <b>bold</b></p>')->stripTags('<b>'),
            SmartString::new(null)->stripTags(),
        ]);
        $this->assertCount(2, $messages);
        $this->assertSmartString('Hi <b>bold</b>', $results[0]); // allowed-tags arg passes through
        $this->assertSmartString(null, $results[1]);
    }

    //endregion
    //region __call() Unknown Methods

    #[DataProvider('aliasSuggestionProvider')]
    public function testUnknownMethodSuggestsCanonicalName(string $alias, string $suggested): void
    {
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString->$alias(), did you mean ->$suggested()?\n",
            fn() => SmartString::new('x')->$alias()
        );
    }

    public static function aliasSuggestionProvider(): array
    {
        // one alias per group flavor: truncation, encoding, stripping,
        // fallbacks, json, formatting, math
        return [
            'truncate → maxChars'      => ['truncate', 'maxChars'],
            'e → htmlEncode'           => ['e', 'htmlEncode'],
            'plaintext → textOnly'     => ['plaintext', 'textOnly'],
            'default → or'             => ['default', 'or'],
            'json → jsonEncode'        => ['json', 'jsonEncode'],
            'formatnumber → numberFormat' => ['formatnumber', 'numberFormat'],
            'plus → add'               => ['plus', 'add'],
            'raw → rawHtml'            => ['raw', 'rawHtml'],
            'iszero → ifZero'          => ['iszero', 'ifZero'], // pre-2.1.2 name; UPGRADING.md says the error suggests the fix
            'replace → pregReplace'    => ['replace', 'pregReplace'], // NOT set(): set('a','b') silently keeps only 'a'
            'prependHtml → wrapHtml'   => ['prependHtml', 'wrapHtml'], // no prepend-side method by design; wrapHtml($before, '') covers it
        ];
    }

    public function testUnknownMethodPointsToHelp(): void
    {
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString->fooBar(), call ->help() for available methods.\n",
            fn() => SmartString::new('x')->fooBar()
        );
    }

    //endregion
    //region __callStatic()

    public function testFromArrayShimReturnsSmartArrayHtml(): void
    {
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::fromArray(['a' => 1]),
            'Replace SmartString::fromArray() with SmartArrayHtml::new($array)'
        );
        $this->assertInstanceOf(SmartArrayHtml::class, $result);
        $this->assertSame(['a' => 1], $result->toArray());
    }

    public function testRawValueShimReturnsRawValue(): void
    {
        $result = $this->expectDeprecationMessage(
            fn() => SmartString::rawValue(SmartString::new('x')),
            'Replace SmartString::rawValue() with SmartString::getRawValue()'
        );
        $this->assertSame('x', $result);
    }

    public function testUnknownStaticMethodPointsToHelp(): void
    {
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString::bogusStatic(), call ->help() for available methods.\n",
            fn() => SmartString::bogusStatic()
        );
    }

    //endregion
    //region __debugInfo()

    /**
     * The README hint appears only on the first print_r per process
     * (static counter), so this test needs its own process.
     */
    #[RunInSeparateProcess]
    public function testDebugInfoShowsReadmeHintOnlyOnFirstCall(): void
    {
        $first  = print_r(SmartString::new('first'), true);
        $second = print_r(SmartString::new('second'), true);

        $this->assertStringContainsString('README:private', $first);
        $this->assertStringContainsString('Call $obj->help() for more information and method examples.', $first);
        $this->assertStringNotContainsString('README:private', $second);
    }

    public function testDebugInfoFormatsRawDataByType(): void
    {
        SmartString::new('burn')->__debugInfo(); // consume the one-time README hint if this process hasn't yet

        $rawDataFor = static fn($value) => SmartString::new($value)->__debugInfo()['rawData:private'];

        $this->assertSame('"test value"', $rawDataFor('test value'));
        $this->assertSame(42, $rawDataFor(42));
        $this->assertSame(3.14, $rawDataFor(3.14));
        $this->assertSame('TRUE', $rawDataFor(true));
        $this->assertSame('FALSE', $rawDataFor(false));
        $this->assertSame("NULL, // Either value is NULL or field doesn't exist", $rawDataFor(null));
    }

    //endregion
    //region getIterator()

    /**
     * foreach over a SmartString throws instead of PHP's silent zero-iteration
     * loop (no accessible properties). The message shows the value so the
     * field-vs-row mixup is obvious.
     */
    public function testForeachThrowsWithValueAndHint(): void
    {
        try {
            foreach (SmartString::new('red,green,blue') as $tag) {
                $this->fail("foreach body should never run, got: $tag");
            }
            $this->fail('Expected CallerException was not thrown');
        } catch (CallerException $e) {
            $this->assertStringContainsString('Can\'t foreach over SmartString "red,green,blue"', $e->getMessage());
            $this->assertStringContainsString('single value, not a collection', $e->getMessage());
        }
    }

    //endregion
    //region Helpers

    /**
     * Assert $fn throws Error with exactly $expectedBody before the
     * "Occurred in" location block, and that the block is present.
     */
    private function assertUndefinedMethodError(string $expectedBody, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected Error was not thrown');
        } catch (Error $e) {
            $this->assertMatchesRegularExpression('/Occurred in .+:\d+/', $e->getMessage(), 'Error should include the "Occurred in file:line" location block');
            $this->assertSame($expectedBody, preg_replace('/Occurred in .*$/s', '', $e->getMessage()));
        }
    }

    //endregion
}
