<?php
/**
 * SEC-012: Explicit SSL verification in embedding API requests.
 *
 * Verifies that CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST are
 * explicitly set in the EmbeddingInterfaces.php source, using the
 * curl_setopt_array() form (not individual curl_setopt() calls),
 * and that the proxy option remains a separate curl_setopt() call.
 */

$source = file_get_contents(__DIR__ . '/../src/embeddings/EmbeddingInterfaces.php');

$hasSslVerifyPeer = str_contains($source, 'CURLOPT_SSL_VERIFYPEER');
$hasSslVerifyHost = str_contains($source, 'CURLOPT_SSL_VERIFYHOST');
$hasCurlSetoptArray = str_contains($source, 'curl_setopt_array');

echo "CURLOPT_SSL_VERIFYPEER present: " . ($hasSslVerifyPeer ? 'yes' : 'no') . "\n";
echo "CURLOPT_SSL_VERIFYHOST present: " . ($hasSslVerifyHost ? 'yes' : 'no') . "\n";
echo "curl_setopt_array used: " . ($hasCurlSetoptArray ? 'yes' : 'no') . "\n";

// Verify the values are set to secure defaults (curl_setopt_array format)
$peerMatch = preg_match('/CURLOPT_SSL_VERIFYPEER\s*=>\s*true/', $source);
$hostMatch = preg_match('/CURLOPT_SSL_VERIFYHOST\s*=>\s*2/', $source);

echo "SSL_VERIFYPEER set to true: " . ($peerMatch ? 'yes' : 'no') . "\n";
echo "SSL_VERIFYHOST set to 2: " . ($hostMatch ? 'yes' : 'no') . "\n";

// Verify options are in the post() method specifically (not just anywhere)
$postMethodStart = strpos($source, 'protected function post(');
$postMethodEnd = strpos($source, 'abstract protected function getHeaders(');
$postSource = substr($source, $postMethodStart, $postMethodEnd - $postMethodStart);

$postHasPeer = str_contains($postSource, 'CURLOPT_SSL_VERIFYPEER');
$postHasHost = str_contains($postSource, 'CURLOPT_SSL_VERIFYHOST');

echo "SSL options in post() method: " . ($postHasPeer && $postHasHost ? 'yes' : 'no') . "\n";

// Verify options are set before the proxy check (correct ordering)
$peerPos = strpos($postSource, 'CURLOPT_SSL_VERIFYPEER');
$proxyPos = strpos($postSource, 'CURLOPT_PROXY');
$correctOrder = $peerPos < $proxyPos;

echo "SSL options before proxy: " . ($correctOrder ? 'yes' : 'no') . "\n";

// Verify proxy uses separate curl_setopt (not in the array)
$proxyInArray = preg_match('/CURLOPT_PROXY\s*=>/', $postSource);
echo "Proxy NOT in curl_setopt_array: " . ($proxyInArray ? 'no' : 'yes') . "\n";

$allPass = $hasSslVerifyPeer && $hasSslVerifyHost && $hasCurlSetoptArray
    && $peerMatch && $hostMatch
    && $postHasPeer && $postHasHost
    && $correctOrder && !$proxyInArray;

echo ($allPass ? "PASS" : "FAIL") . "\n";
