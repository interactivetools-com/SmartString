<?php
header("Content-Type: text/plain");
require_once 'vendor/autoload.php';

use Itools\SmartString\SmartString;
use Itools\SmartArray\SmartArray;

$null  = SmartString::new(null);

SmartString::$treatNullAsZero = false;

$value = SmartString::new(7);
//$r = $value->add( SmartString::new("3000") )->add(1); // ->add($null)->add(10/3);
$r = $value->add( SmartString::new(null) )->add(1); // ->add($null)->add(10/3);
print_r($r); // outputs 1, should be NULL
exit;


// Create a sample string and encode it
$string = "Hello, 'World'!";
$smartString = SmartString::new($string);

echo "Content: {$smartString->htmlEncode()}\n";

$basket = SmartArray::new(["apples", "oranges", "bananas"]);
print_r($basket);

print "done";
exit;

// Output different formats of the string
echo "Original: {$smartString->value()}\n";
echo "HTML Encoded: {$smartString->htmlEncode()}\n";
echo "URL Encoded: {$smartString->urlEncode()}\n";
echo "JSON Encoded: {$smartString->jsonEncode()}\n";

// Demonstrate string manipulation
echo "Trimmed: {$smartString->trim()->value()}\n";
echo "Max Words (2): {$smartString->maxWords(2)->value()}\n";
echo "Max Chars (5): {$smartString->maxChars(5)->value()}\n";

// Demonstrate conditional operations
$emptyString = SmartString::new("");
echo "Empty string with fallback: {$emptyString->or("Fallback value")->value()}\n";

// Demonstrate numeric operations
$number = SmartString::new(1234.56);
echo "Number formatted: {$number->numberFormat(2)->value()}\n";
echo "Percentage: {$number->percent(1)->value()}\n";
