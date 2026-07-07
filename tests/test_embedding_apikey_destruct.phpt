--TEST--
SEC-008: API key destructor — __destruct with sodium_memzero
--FILE--
<?php
$source = file_get_contents(__DIR__ . '/../src/embeddings/EmbeddingInterfaces.php');

// Test 1: __destruct method exists
if (str_contains($source, 'function __destruct()')) {
    echo "PASS: __destruct method declared in EmbeddingInterfaces.php\n";
} else {
    echo "FAIL: __destruct method not found\n";
    exit(1);
}

// Test 2: __destruct uses sodium_memzero
if (str_contains($source, 'sodium_memzero($this->apiKey)')) {
    echo "PASS: __destruct calls sodium_memzero on apiKey\n";
} else {
    echo "FAIL: sodium_memzero call not found in __destruct\n";
    exit(1);
}

// Test 3: function_exists check for sodium_memzero
if (str_contains($source, 'function_exists(\'sodium_memzero\')')) {
    echo "PASS: __destruct checks if sodium_memzero exists before calling\n";
} else {
    echo "FAIL: function_exists check for sodium_memzero not found\n";
    exit(1);
}

// Test 4: Verify the destructor is in the ApiEmbeddingFunction class
$apiClassStart = strpos($source, 'abstract class ApiEmbeddingFunction');
$destructPos = strpos($source, 'function __destruct()');
if ($destructPos !== false && $apiClassStart !== false && $destructPos > $apiClassStart) {
    echo "PASS: __destruct method is inside ApiEmbeddingFunction class\n";
} else {
    echo "FAIL: __destruct not found inside ApiEmbeddingFunction class\n";
    exit(1);
}

// Test 5: Verify __debugInfo is also inside ApiEmbeddingFunction
$debugInfoPos = strpos($source, 'function __debugInfo()');
if ($debugInfoPos !== false && $debugInfoPos > $apiClassStart) {
    echo "PASS: __debugInfo method is inside ApiEmbeddingFunction class\n";
} else {
    echo "FAIL: __debugInfo not found inside ApiEmbeddingFunction class\n";
    exit(1);
}

echo "\nAll destructor source checks passed!\n";
?>
--EXPECT--
PASS: __destruct method declared in EmbeddingInterfaces.php
PASS: __destruct calls sodium_memzero on apiKey
PASS: __destruct checks if sodium_memzero exists before calling
PASS: __destruct method is inside ApiEmbeddingFunction class
PASS: __debugInfo method is inside ApiEmbeddingFunction class

All destructor source checks passed!
