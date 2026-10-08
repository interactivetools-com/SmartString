<?php
declare(strict_types=1);

/**
 * Subprocess target for EmptyGuardsTest: runs one or*() guard on a missing-value
 * SmartString (null) so exit paths can be observed from outside the process.
 * The orRedirect-headers-sent variant uses a present value instead - it pins
 * behavior that doesn't depend on missing values.
 *
 * Two ways to run it, same guard list:
 *
 *     php empty-guard.php <method> [message-or-url]     // CLI subprocess
 *     GET /empty-guard.php?method=...&arg=...           // served by php -S
 *
 * The or404-handler* methods set a set404Handler() handler first. It prints what
 * it saw instead of a page, in the body so the web mode sees it too:
 *
 *     handler-text=<var_export of the message>
 *     handler-status=<http_response_code() when it ran>
 *     handler-ob-level=<ob_get_level() when it ran>
 *
 * CLI stdout: whatever the guard echoes (the 404 page or the handler's report)
 * CLI stderr: "status=<int|false>" from a shutdown handler (http_response_code
 *         survives exit within the process), plus "NOT-REACHED" if the guard
 *         didn't exit - a failure for the missing-value guards
 *
 * header() is a no-op and headers_list() is always empty under CLI, so the
 * Location and Content-Type headers are only observable in the web mode, where
 * they come back to the test as real response headers. headers_sent() DOES
 * work under CLI (true after any output) - the headers-sent variants rely on
 * that.
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\SmartArrayHtml;
use Itools\SmartString\SmartString;

register_shutdown_function(function () {
    fwrite(STDERR, "status=" . var_export(http_response_code(), true));
});

$isWebRequest = PHP_SAPI === 'cli-server';
$method       = $isWebRequest ? (string)($_GET['method'] ?? '') : ($argv[1] ?? '');
$arg          = $isWebRequest ? (string)($_GET['arg'] ?? '')    : ($argv[2] ?? null);
$missing      = SmartString::new(null);
$present      = SmartString::new('ok');

if (str_starts_with($method, 'or404-handler') || $method === 'smartnull-or404-handler') {
    SmartString::set404Handler(function (?string $text): void {
        echo "handler-text=" . var_export($text, true) . "\n";
        echo "handler-status=" . var_export(http_response_code(), true) . "\n";
        echo "handler-ob-level=" . ob_get_level() . "\n";
    });
}

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
    'orRedirect'         => fn() => $missing->orRedirect((string)$arg),
    // -smart variants pass the argument as a SmartString: the guard must unwrap the raw
    // value, not encode the __toString output a second time
    'or404-smart-text'   => fn() => $missing->or404(SmartString::new((string)$arg)),
    'orRedirect-smart-url' => fn() => $missing->orRedirect(SmartString::new((string)$arg)),
    'orRedirect-headers-sent' => function () use ($present, $arg) {
        echo "output-sent\n"; // makes headers_sent() true before the call
        $present->orRedirect((string)$arg); // throws even though the value is present
    },
    'or404-handler'              => fn() => $missing->or404((string)$arg),
    'or404-handler-default'      => fn() => $missing->or404(),
    'or404-handler-empty-value'  => fn() => SmartString::new('')->or404((string)$arg), // "" is missing too
    'or404-handler-smart-text'   => fn() => $missing->or404(SmartString::new((string)$arg)),
    'or404-handler-smart-int'    => fn() => $missing->or404(SmartString::new(404)),
    'or404-handler-smartnull'    => fn() => $missing->or404(SmartArray::new([])->first()),
    'or404-handler-headers-sent' => function () use ($missing) {
        echo "already-flushed\n";
        $missing->or404();
    },
    'or404-handler-ob-discard'   => function () use ($missing) {
        ob_start();
        echo "partial page content";
        $missing->or404();
    },
    'or404-handler-reset'        => function () use ($missing) {
        SmartString::set404Handler(null);
        $missing->or404();
    },
    'or404-handler-throws'       => function () use ($missing) {
        SmartString::set404Handler(fn(?string $text) => throw new LogicException('handler threw'));
        $missing->or404();
    },
    'or404-handler-nested'       => function () use ($missing) {
        SmartString::set404Handler(function (?string $text): void {
            echo "handler-page\n";
            SmartString::new(null)->or404('nested call'); // prints the built-in page instead of calling this handler again
        });
        $missing->or404();
    },
    // first() on an empty HTML-mode result returns a SmartNull, which sends or404() to SmartString
    'smartnull-or404-handler'    => fn() => SmartArrayHtml::new([])->first()->or404((string)$arg),
    default => fn() => fwrite(STDERR, "unknown method: $method"),
};
$run();

fwrite(STDERR, "NOT-REACHED"); // the missing-value guards above should exit or throw
