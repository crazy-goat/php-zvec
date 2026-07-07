--TEST--
SEC-008: API key masking — runtime __debugInfo() and clone protection
--SKIPIF--
<?php
// Skip if zvec extension is loaded (which provides global classes without __debugInfo)
if (extension_loaded('zvec')) {
    // Check if the extension's class already has __debugInfo
    if (class_exists('OpenAIDenseEmbedding') && !(new ReflectionClass('OpenAIDenseEmbedding'))->hasMethod('__debugInfo')) {
        die('skip zvec extension loaded — extension class lacks __debugInfo');
    }
}
?>
--FILE--
<?php
require_once __DIR__ . '/../src/embeddings/EmbeddingInterfaces.php';
require_once __DIR__ . '/../src/embeddings/OpenAIDenseEmbedding.php';

use CrazyGoat\ZVec\OpenAIDenseEmbedding;

// Test 1: var_dump masks API key
$e = new OpenAIDenseEmbedding('sk-test-key-12345');
ob_start();
var_dump($e);
$output = ob_get_clean();

if (str_contains($output, 'sk-test-key-12345')) {
    echo "FAIL: Full API key visible in var_dump\n";
    exit(1);
}
if (!str_contains($output, '***2345')) {
    echo "FAIL: Masked key pattern not found in var_dump\n";
    exit(1);
}
echo "PASS: var_dump masks API key (shows ***2345)\n";

// Test 2: __debugInfo returns masked key
$debug = $e->__debugInfo();
if ($debug['apiKey'] !== '***2345') {
    echo "FAIL: __debugInfo apiKey expected '***2345', got '" . $debug['apiKey'] . "'\n";
    exit(1);
}
if (!isset($debug['baseUrl'])) {
    echo "FAIL: __debugInfo missing baseUrl\n";
    exit(1);
}
if (!isset($debug['timeout'])) {
    echo "FAIL: __debugInfo missing timeout\n";
    exit(1);
}
if (!array_key_exists('proxy', $debug)) {
    echo "FAIL: __debugInfo missing proxy\n";
    exit(1);
}
echo "PASS: __debugInfo returns masked apiKey + baseUrl + timeout + proxy\n";

// Test 3: clone is prevented (sodium_memzero shared buffer protection)
try {
    $clone = clone $e;
    echo "FAIL: clone should throw Error\n";
    exit(1);
} catch (\Error $err) {
    if (!str_contains($err->getMessage(), 'clone')) {
        echo "FAIL: clone error unexpected: " . $err->getMessage() . "\n";
        exit(1);
    }
    echo "PASS: clone() throws Error: " . $err->getMessage() . "\n";
}

// Test 4: Empty key shows ****
$e2 = new OpenAIDenseEmbedding('');
$debug2 = $e2->__debugInfo();
if ($debug2['apiKey'] !== '****') {
    echo "FAIL: Empty key not masked correctly\n";
    exit(1);
}
echo "PASS: Empty API key shows '****'\n";

// Test 5: Env var loading (OPENAI_API_KEY)
putenv('OPENAI_API_KEY=sk-env-test-key');
$e3 = new OpenAIDenseEmbedding();
$debug3 = $e3->__debugInfo();
if (!str_contains($debug3['apiKey'], '***-key')) {
    echo "FAIL: API key not loaded from OPENAI_API_KEY env var\n";
    exit(1);
}
echo "PASS: API key loaded from OPENAI_API_KEY env var\n";
putenv('OPENAI_API_KEY');

// Test 6: Explicit key takes priority over env var
putenv('OPENAI_API_KEY=sk-env-wrong');
$e4 = new OpenAIDenseEmbedding('sk-explicit-correct');
$debug4 = $e4->__debugInfo();
if ($debug4['apiKey'] !== '***rect') {
    echo "FAIL: Explicit key should override env var\n";
    exit(1);
}
echo "PASS: Explicit API key takes priority over env var\n";
putenv('OPENAI_API_KEY');

// Test 7: No key and no env var — empty string
$e5 = new OpenAIDenseEmbedding();
$debug5 = $e5->__debugInfo();
if ($debug5['apiKey'] !== '****') {
    echo "FAIL: Expected empty key, got '" . $debug5['apiKey'] . "'\n";
    exit(1);
}
echo "PASS: No key and no env var — empty API key\n";

echo "\nAll API key runtime tests passed!\n";
?>
--EXPECT--
PASS: var_dump masks API key (shows ***2345)
PASS: __debugInfo returns masked apiKey + baseUrl + timeout + proxy
PASS: clone() throws Error: Attempted to clone a non-clonable object class "CrazyGoat\ZVec\OpenAIDenseEmbedding".
PASS: Empty API key shows '****'
PASS: API key loaded from OPENAI_API_KEY env var
PASS: Explicit API key takes priority over env var
PASS: No key and no env var — empty API key

All API key runtime tests passed!
