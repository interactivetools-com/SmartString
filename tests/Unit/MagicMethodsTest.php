<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Error;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Itools\SmartString\Tests\Support\SmartStringTestCase;

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

    public function testGetEncodesAttackerSuppliedPropertyName(): void
    {
        // SECURITY: dynamic property names can carry request data
        // ($col = $_GET['sort']; $row->title->$col) and error handlers often
        // echo messages into pages, so the name must arrive encoded
        $property = '<script>alert(1)</script>';
        $result   = $this->expectUserWarning(
            fn() => SmartString::new('x')->$property,
            "Undefined property: SmartString->&lt;script&gt;alert(1)&lt;/script&gt;\n"
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

    public function testDeprecationViaSmartNullDelegationNamesTheRealCaller(): void
    {
        // A missing field routes through SmartArray's SmartNull, which forwards
        // the shim call to SmartString. The notice must name this file - the
        // developer's code - not the delegating SmartNull.php, or there is no
        // file and line to go fix before the shims are removed.
        [, $messages] = $this->captureDeprecations(fn() => SmartArrayHtml::new([])->missingField->noEncode());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('MagicMethodsTest.php', $messages[0]);
        $this->assertStringNotContainsString('SmartNull.php', $messages[0]);
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
        $this->assertSame('O\u0027Brien \u003Cb\u003E\n', $result);
    }

    public function testJsEncodeShimEscapesCharactersThatEscapeTheScriptBlock(): void
    {
        // A backslash escape like \< keeps the raw < in the output: JavaScript reads it
        // as <, and so does the HTML parser, which ends the script early at </script.
        // & matters for the same reason in inline handlers, where the attribute value is
        // entity-decoded before the JavaScript is compiled.
        [$results, $messages] = $this->captureDeprecations(fn() => [
            SmartString::new('</script><img src=x onerror=alert(1)>')->jsEncode(),
            SmartString::new('&#39;+alert(1)+&#39;')->jsEncode(),
        ]);
        $this->assertCount(2, $messages);
        $this->assertSame('\u003C/script\u003E\u003Cimg src=x onerror=alert(1)\u003E', $results[0]);
        $this->assertSame('\u0026#39;+alert(1)+\u0026#39;', $results[1]);
    }

    public function testJsEncodeShimEscapesTemplateLiteralBreakout(): void
    {
        // v2.7.0's addcslashes list included the backtick, and legacy code embeds this
        // shim's output in `template literals`, so the jsonEncode()-based version must
        // escape it too. $ is escaped as well: ${} interpolation executes without any
        // backtick, so escaping only the backtick would still leave an injection open.
        [$results, $messages] = $this->captureDeprecations(fn() => [
            SmartString::new('`;alert(1);//')->jsEncode(),
            SmartString::new('${alert(1)}')->jsEncode(),
        ]);
        $this->assertCount(2, $messages);
        $this->assertSame('\u0060;alert(1);//', $results[0]);
        $this->assertSame('\u0024{alert(1)}', $results[1]);
    }

    public function testJsEncodeShimConvertsNonStringsBeforeEscaping(): void
    {
        [$results, ] = $this->captureDeprecations(fn() => [
            SmartString::new(null)->jsEncode(),
            SmartString::new(42)->jsEncode(),
        ]);
        $this->assertSame('', $results[0]); // null stays empty, never the string "null"
        $this->assertSame('42', $results[1]);
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

    public function testUnknownMethodPointsToDocs(): void
    {
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString->fooBar(), see the SmartString docs for available methods.\n",
            fn() => SmartString::new('x')->fooBar()
        );
    }

    public function testUnknownMethodNameWithPercentReportsCleanly(): void
    {
        // '%' in a dynamic method name must not parse as a format specifier
        $method = 'get50%offPrice';
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString->get50%offPrice(), see the SmartString docs for available methods.\n",
            fn() => SmartString::new('x')->$method()
        );
    }

    public function testUnknownMethodEncodesAttackerSuppliedName(): void
    {
        // SECURITY: same rule as __get() - exception handlers often echo
        // messages into pages, so the name must arrive encoded
        $method = '<script>alert(1)</script>';
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString->&lt;script&gt;alert(1)&lt;/script&gt;(), see the SmartString docs for available methods.\n",
            fn() => SmartString::new('x')->$method()
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

    public function testUnknownStaticMethodPointsToDocs(): void
    {
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString::bogusStatic(), see the SmartString docs for available methods.\n",
            fn() => SmartString::bogusStatic()
        );
    }

    public function testUnknownStaticMethodEncodesAttackerSuppliedName(): void
    {
        // SECURITY: same rule as __get() - exception handlers often echo
        // messages into pages, so the name must arrive encoded
        $method = '<script>alert(1)</script>';
        $this->assertUndefinedMethodError(
            "Call to undefined method SmartString::&lt;script&gt;alert(1)&lt;/script&gt;(), see the SmartString docs for available methods.\n",
            fn() => SmartString::$method()
        );
    }

    //endregion
    //region __debugInfo()

    /**
     * Dumps show the stored value as-is under the public accessor's name
     * (->value()) - no injected help text, no type formatting.
     */
    public function testDebugInfoReturnsRawValueOnly(): void
    {
        $debugInfoFor = static fn($value) => SmartString::new($value)->__debugInfo();

        $this->assertSame(['value' => 'test value'], $debugInfoFor('test value'));
        $this->assertSame(['value' => 42], $debugInfoFor(42));
        $this->assertSame(['value' => 3.14], $debugInfoFor(3.14));
        $this->assertSame(['value' => true], $debugInfoFor(true));
        $this->assertSame(['value' => false], $debugInfoFor(false));
        $this->assertSame(['value' => null], $debugInfoFor(null));
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
        // PHPUnit's AssertionFailedError is itself a RuntimeException, so record what
        // happened and assert after the catch instead of failing inside the try
        $threw      = false;
        $loopedTags = [];

        try {
            foreach (SmartString::new('red,green,blue') as $tag) {
                $loopedTags[] = $tag;
            }
        } catch (RuntimeException $e) {
            $threw = true;
            // fully encoded, same flags as orThrow: exception handlers echo messages into pages
            $this->assertStringContainsString('Can\'t foreach over SmartString &quot;red,green,blue&quot;', $e->getMessage());
            $this->assertStringContainsString('single value, not a collection', $e->getMessage());
        }

        $this->assertSame([], $loopedTags, 'foreach body should never run');
        $this->assertTrue($threw, 'Expected RuntimeException was not thrown');
    }

    public function testForeachExceptionEncodesQuotesInTheValue(): void
    {
        // a raw quote in a DB value must not survive into the message, or an error
        // page echoing it into an attribute gets attribute breakout
        $threw      = false;
        $loopedTags = [];

        try {
            foreach (SmartString::new('" onmouseover=alert(1) x') as $tag) {
                $loopedTags[] = $tag;
            }
        } catch (RuntimeException $e) {
            $threw = true;
            $this->assertStringNotContainsString('"', $e->getMessage());
            $this->assertStringContainsString('&quot; onmouseover=', $e->getMessage()); // preview truncates at 20 chars
        }

        $this->assertSame([], $loopedTags, 'foreach body should never run');
        $this->assertTrue($threw, 'Expected RuntimeException was not thrown');
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
