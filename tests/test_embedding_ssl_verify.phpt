--TEST--
SEC-012: Explicit SSL verification in embedding API requests
--FILE--
<?php
// Verify CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST are explicitly set
// in the EmbeddingInterfaces.php source. This test is a static analysis check —
// it does not make network requests or require FFI.
$source = file_get_contents(__DIR__ . '/../src/embeddings/EmbeddingInterfaces.php');

$hasVerifyPeer = strpos($source, 'CURLOPT_SSL_VERIFYPEER') !== false;
$hasVerifyHost = strpos($source, 'CURLOPT_SSL_VERIFYHOST') !== false;

if (!$hasVerifyPeer) {
    echo "FAIL: CURLOPT_SSL_VERIFYPEER not found in EmbeddingInterfaces.php\n";
    exit(1);
}
if (!$hasVerifyHost) {
    echo "FAIL: CURLOPT_SSL_VERIFYHOST not found in EmbeddingInterfaces.php\n";
    exit(1);
}

echo "PASS: CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST are explicitly set\n";

// Verify the values are true and 2 respectively
$patternPeer = '/CURLOPT_SSL_VERIFYPEER\s*=>\s*true/';
$patternHost = '/CURLOPT_SSL_VERIFYHOST\s*=>\s*2/';

if (!preg_match($patternPeer, $source)) {
    echo "FAIL: CURLOPT_SSL_VERIFYPEER is not set to true\n";
    exit(1);
}
if (!preg_match($patternHost, $source)) {
    echo "FAIL: CURLOPT_SSL_VERIFYHOST is not set to 2\n";
    exit(1);
}

echo "PASS: CURLOPT_SSL_VERIFYPEER => true and CURLOPT_SSL_VERIFYHOST => 2\n";

// Verify curl_setopt_array is used (not individual curl_setopt calls for SSL)
if (strpos($source, 'curl_setopt_array') === false) {
    echo "FAIL: curl_setopt_array not used (should use array form for SSL options)\n";
    exit(1);
}
echo "PASS: curl_setopt_array is used for setting curl options\n";
?>
--EXPECT--
PASS: CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST are explicitly set
PASS: CURLOPT_SSL_VERIFYPEER => true and CURLOPT_SSL_VERIFYHOST => 2
PASS: curl_setopt_array is used for setting curl options
