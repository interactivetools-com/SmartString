<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: runs one or*() guard on a missing-value
 * SmartString (null) so exit paths can be observed from outside the process.
 * The orRedirect-present and orRedirect-headers-sent variants use a present
 * value instead - those pin behavior that doesn't depend on missing values.
 *
 *     php empty-guard.php <method> [message-or-url]
 *
 * stdout: whatever the guard echoes (404 page, die message), plus the chained
 *         ->value() for orRedirect-present
 * stderr: "status=<int|false>" from a shutdown handler (http_response_code
 *         survives exit within the process), plus "NOT-REACHED" if the guard
 *         didn't exit - expected for orRedirect-present, a failure for the
 *         missing-value guards
 *
 * CLI limits: header() is a no-op and headers_list() is always empty under
 * CLI, so the Location and Content-Type headers can't be asserted here, only
 * status codes, output, and exit behavior. headers_sent() DOES work under CLI
 * (true after any output) - the headers-sent variant relies on that, and it's
 * also why orRedirect's happy path must run here rather than under PHPUnit,
 * whose console output makes headers_sent() true in-process.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartString\SmartString;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$method  = $argv[1] ?? '';
$arg     = $argv[2] ?? null;
$missing = SmartString::new(null);
$present = SmartString::new('ok');

$run = match ($method) {
    'or404-default'      => fn() => $missing->or404(),
    'or404'              => fn() => $missing->or404((string)$arg),
    'orDie'              => fn() => $missing->orDie((string)$arg),
    'orThrow'            => fn() => $missing->orThrow((string)$arg),
    'orRedirect'         => fn() => $missing->orRedirect((string)$arg),
    'orRedirect-present' => fn() => print $present->orRedirect((string)$arg)->value(),
    'orRedirect-headers-sent' => function () use ($present, $arg) {
        echo "output-sent\n"; // makes headers_sent() true before the call
        $present->orRedirect((string)$arg); // throws even though the value is present
    },
    default => fn() => fwrite(STDERR, "unknown method: $method"),
};
$run();

fwrite(STDERR, "NOT-REACHED"); // the missing-value guards above should exit or throw
