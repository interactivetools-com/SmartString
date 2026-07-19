<?php
declare(strict_types=1);

namespace Tests\Unit;

use Itools\SmartString\SmartString;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Support\SmartStringTestCase;
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

    public function testNoMethodChangesTheStoredValue(): void
    {
        $samples = ['Hello & <b>World</b>', '', "caf\u{E9}", '2024-01-15', 42, 0, 3.14, true, false, null];
        $methods = (new ReflectionClass(SmartString::class))->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($samples as $sample) {
            foreach ($methods as $method) {
                if ($method->isStatic() || $method->isConstructor() || in_array($method->getName(), self::EXITING_METHODS, true)) {
                    continue;
                }
                $obj  = new SmartString($sample);
                $args = array_map(self::dummyArg(...), $method->getParameters());

                ob_start();                              // some methods echo (help, print_r hooks)
                set_error_handler(static fn(): bool => true); // some methods warn on junk args (__get('x'))
                try {
                    $method->invoke($obj, ...$args);
                } catch (Throwable) {
                    // Throwing is fine (e.g. dateFormat on a non-date); only mutation fails the test
                } finally {
                    restore_error_handler();
                    ob_end_clean();
                }

                $this->assertSame($sample, $obj->value(),
                    sprintf('%s() changed the stored value for %s input', $method->getName(), get_debug_type($sample)));
            }
        }
    }

    /**
     * A plausible argument for a required parameter, picked by declared type.
     */
    private static function dummyArg(ReflectionParameter $param): mixed
    {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        $type = $param->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : 'string'; // unions: any member works, use string
        return match ($name) {
            'int'                 => 2,
            'float'               => 2.5,
            'bool'                => true,
            'array'               => [],
            'callable', 'Closure' => static fn(mixed $v): mixed => $v,
            default               => 'x',
        };
    }
}
