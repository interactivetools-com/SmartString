<?php
declare(strict_types=1);

namespace Itools\SmartString\Tests\Unit;

use Itools\SmartString\SmartString;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Itools\SmartString\Tests\Support\SmartStringTestCase;
use Throwable;

/**
 * Guards the immutability contract: no method changes the stored value after
 * construction. The $rawData property is untyped for speed (see its docblock),
 * which means PHP won't let it be readonly - this test enforces the same
 * guarantee behaviorally by calling every public method on instances of every
 * value type and asserting ->value() is unchanged on the original object.
 *
 * New public methods are picked up automatically via reflection.
 */
class ImmutabilityTest extends SmartStringTestCase
{
    // These exit the process when the value is missing, so they can't run under PHPUnit
    private const EXITING_METHODS = ['or404', 'orDie', 'orRedirect'];

    // getIterator() throws for every value by design, so it can never reach $rawData
    private const ALWAYS_THROWING_METHODS = ['getIterator'];

    public function testNoMethodChangesTheStoredValue(): void
    {
        // Both spellings earn their place: "caf\u{E9}" is valid UTF-8 and is the only sample
        // reaching htmlEncode()'s str_replace path, while "caf\xE9" is invalid and reaches
        // htmlspecialchars() plus the U+FFFD substitution in textOnly(), maxChars() and maxWords()
        $samples = ['Hello & <b>World</b>', '', "caf\u{E9}", "caf\xE9", '2024-01-15', 42, 0, 3.14, true, false, null];
        $methods = array_filter(
            (new ReflectionClass(SmartString::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn(ReflectionMethod $method): bool => !$method->isStatic()
                && !$method->isConstructor()
                && !in_array($method->getName(), self::EXITING_METHODS, true),
        );
        $completed = [];

        foreach ($samples as $sample) {
            $isMissing = $sample === null || $sample === '';

            foreach ($methods as $method) {
                $obj  = new SmartString($sample);
                $args = array_map(self::dummyArg(...), $method->getParameters());

                ob_start();                              // some methods echo (help, print_r hooks)
                set_error_handler(static fn(): bool => true); // some methods warn on junk args (__get('x'))
                try {
                    $method->invoke($obj, ...$args);
                    if (!$isMissing) {
                        $completed[$method->getName()] = true;
                    }
                } catch (Throwable) {
                    // Throwing is fine (e.g. orThrow on a missing value); only mutation fails the test
                } finally {
                    restore_error_handler();
                    ob_end_clean();
                }

                $this->assertSame($sample, $obj->value(),
                    sprintf('%s() changed the stored value for %s input', $method->getName(), get_debug_type($sample)));
            }
        }

        // A method that throws before it reads $rawData leaves the assertion above re-reading an
        // untouched value, which reports a guarantee nothing tested. Missing values don't count:
        // most methods return early on those without doing any work.
        $names    = array_map(static fn(ReflectionMethod $method): string => $method->getName(), $methods);
        $neverRan = array_values(array_diff($names, array_keys($completed), self::ALWAYS_THROWING_METHODS));
        $this->assertSame([], $neverRan,
            'These methods threw on every present value, so the sweep never tested them - give dummyArg() an argument they accept');
    }

    /**
     * A plausible argument for a required parameter, picked by declared type.
     *
     * A few methods check an argument before they look at the stored value, so a generic 'x'
     * makes them throw before the sweep can see anything. Those get a usable value by name.
     */
    private static function dummyArg(ReflectionParameter $param): mixed
    {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        $byName = [
            'pregReplace.pattern' => '/x/',       // a bare 'x' has no delimiters and fails to compile
            '__call.method'       => 'stripTags', // a deprecated shim, so the call reaches real work
            '__call.args'         => [],          // __call() takes the argument list as an array
        ];
        $key = sprintf('%s.%s', $param->getDeclaringFunction()->getName(), $param->getName());
        if (array_key_exists($key, $byName)) {
            return $byName[$key];
        }

        $type      = $param->getType();
        $typeNames = match (true) {
            $type instanceof ReflectionUnionType => array_map(static fn(ReflectionNamedType $member): string => $member->getName(), $type->getTypes()),
            $type instanceof ReflectionNamedType => [$type->getName()],
            default                              => ['string'], // untyped params, e.g. trim()'s variadic $args
        };

        // map() and apply() declare callable|string, so a callable member decides the whole union.
        // The callback returns a value no sample holds: an identity callback would hide a write-back.
        if (array_intersect(['callable', 'Closure'], $typeNames) !== []) {
            return static fn(mixed $value): string => 'mapped';
        }

        return match ($typeNames[0]) {
            'int'   => 2,
            'float' => 2.5,
            'bool'  => true,
            'array' => [],
            default => 'x', // every remaining union in the class accepts a string
        };
    }
}
