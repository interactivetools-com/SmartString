<?php
declare(strict_types=1);

/**
 * Subprocess target for StandaloneInstallTest: loads SmartString WITHOUT the
 * Composer autoloader to simulate a standalone itools/smartstring install
 * (itools/smartarray is a suggested dependency, not a required one). Scalar
 * values must work normally; the deprecated array paths must throw a fix-it
 * RuntimeException instead of PHP's "Class not found" fatal.
 *
 *     php standalone-install.php <scalar|new-array|from-array>
 */

require dirname(__DIR__, 3) . '/src/SharedHelpers.php';
require dirname(__DIR__, 3) . '/src/Deprecations.php';
require dirname(__DIR__, 3) . '/src/SmartString.php';

use Itools\SmartString\SmartString;

try {
    switch ($argv[1] ?? '') {
        case 'scalar':
            echo SmartString::new('Bob & Sons')->htmlEncode();
            break;
        case 'new-array':
            SmartString::new(['a' => 1]);
            echo 'NO-EXCEPTION';
            break;
        case 'from-array':
            SmartString::fromArray(['a' => 1]);
            echo 'NO-EXCEPTION';
            break;
        default:
            echo 'UNKNOWN TEST: ', $argv[1] ?? '(none)';
    }
} catch (RuntimeException $e) {
    echo 'RuntimeException: ', $e->getMessage();
}
