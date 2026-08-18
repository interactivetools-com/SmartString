<?php
declare(strict_types=1);

/**
 * Web target for HelpTest: prints xmpWrap() output for text holding a
 * literal </xmp> closing tag in lower, upper and mixed case, since browsers
 * end the block on any spelling. Run under PHP's built-in server (php -S),
 * whose cli-server SAPI takes the <xmp>-wrapping web branch that CLI
 * tests can't reach. xmpWrap() is private, so this calls it by reflection
 * (help()'s own output is static doc links, nothing injectable).
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$xmpWrap = new ReflectionMethod(\Itools\SmartString\SmartString::class, 'xmpWrap');
echo $xmpWrap->invoke(null, '</xmp><script>alert(1)</script></XMP><script>alert(2)</script></Xmp><script>alert(3)</script>');
