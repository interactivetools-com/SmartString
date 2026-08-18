<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: runs one or*() guard on a missing-value
 * SmartString (null) so exit paths can be observed from outside the process.
 * The orRedirect-present and orRedirect-headers-sent variants use a present
 * value instead - those pin behavior that doesn't depend on missing values.
 *
 * Two ways to run it, same guard list:
 *
 *     php empty-guard.php <method> [message-or-url]     // CLI subprocess
 *     GET /empty-guard.php?method=...&arg=...           // served by php -S
 *
 * CLI stdout: whatever the guard echoes (404 page, die message), plus the
 *         chained ->value() for orRedirect-present
 * CLI stderr: "status=<int|false>" from a shutdown handler (http_response_code
 *         survives exit within the process), plus "NOT-REACHED" if the guard
 *         didn't exit - expected for orRedirect-present, a failure for the
 *         missing-value guards
 *
 * header() is a no-op and headers_list() is always empty under CLI, so the
 * Location and Content-Type headers are only observable in the web mode, where
 * they come back to the test as real response headers. headers_sent() DOES
 * work under CLI (true after any output) - the headers-sent variants rely on
 * that, and it's also why orRedirect's happy path must run here rather than
 * under PHPUnit, whose console output makes headers_sent() true in-process.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartString\SmartString;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$isWebRequest = PHP_SAPI === 'cli-server';
$method       = $isWebRequest ? (string)($_GET['method'] ?? '') : ($argv[1] ?? '');
$arg          = $isWebRequest ? (string)($_GET['arg'] ?? '')    : ($argv[2] ?? null);
$missing      = SmartString::new(null);
$present      = SmartString::new('ok');

$run = match ($method) {
    'or404-default'      => fn() => $missing->or404(),
    'or404'              => fn() => $missing->or404((string)$arg),
    'or404-headers-sent' => function () use ($missing) {
        echo "already-flushed\n"; // makes headers_sent() true before the call
        $missing->or404();        // page still renders; the status can't change
    },
    'or404-ob-discard'   => function () use ($missing) {
        ob_start();
        echo "partial page content"; // buffered, not sent: headers_sent() stays false
        $missing->or404();           // discards the buffer and sets the 404
    },
    'or404-locked-buffer' => function () use ($missing) {
        // a buffer PHP can't remove: or404() must stop discarding, not spin on it.
        // The time limit turns a spin regression into a fast fatal, not a hung test run
        set_time_limit(3);
        ob_start(fn(string $s) => $s, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
        echo "partial page content";
        $missing->or404();
    },
    'orDie'              => fn() => $missing->orDie((string)$arg),
    'orThrow'            => fn() => $missing->orThrow((string)$arg),
    'orRedirect'         => fn() => $missing->orRedirect((string)$arg),
    // -smart variants pass the argument as a SmartString: the guard must unwrap the raw
    // value, not encode the __toString output a second time
    'or404-smart-text'   => fn() => $missing->or404(SmartString::new((string)$arg)),
    'orDie-smart-text'   => fn() => $missing->orDie(SmartString::new((string)$arg)),
    'orRedirect-smart-url' => fn() => $missing->orRedirect(SmartString::new((string)$arg)),
    'orRedirect-present' => fn() => print $present->orRedirect((string)$arg)->value(),
    'orRedirect-headers-sent' => function () use ($present, $arg) {
        echo "output-sent\n"; // makes headers_sent() true before the call
        $present->orRedirect((string)$arg); // throws even though the value is present
    },
    default => fn() => fwrite(STDERR, "unknown method: $method"),
};
$run();

fwrite(STDERR, "NOT-REACHED"); // the missing-value guards above should exit or throw
