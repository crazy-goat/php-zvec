--TEST--
SEC-008: API key loading from environment variables — source analysis
--FILE--
<?php
$source = file_get_contents(__DIR__ . '/../src/embeddings/EmbeddingInterfaces.php');

// Test 1: Constructor uses env var fallback
if (str_contains($source, "getenv('OPENAI_API_KEY')") && str_contains($source, "getenv('DASHSCOPE_API_KEY')")) {
    echo "PASS: Constructor uses OPENAI_API_KEY and DASHSCOPE_API_KEY env vars as fallback\n";
} else {
    echo "FAIL: Env var fallback not found in constructor\n";
    exit(1);
}

// Test 2: apiKey parameter is nullable
if (str_contains($source, '?string $apiKey = null')) {
    echo "PASS: apiKey constructor parameter is nullable with default null\n";
} else {
    echo "FAIL: apiKey parameter not nullable\n";
    exit(1);
}

// Test 3: Null coalescing chain for env vars
$constructStart = strpos($source, 'public function __construct(');
$constructEnd = strpos($source, 'abstract protected function getDefaultBaseUrl()');
if ($constructStart !== false && $constructEnd !== false) {
    $constructBody = substr($source, $constructStart, $constructEnd - $constructStart);
    $hasCoalescing = preg_match('/\$this->apiKey\s*=\s*\$apiKey\s*\?\?/', $constructBody);
    if ($hasCoalescing) {
        echo "PASS: Constructor uses null coalescing for apiKey with env var fallback\n";
    } else {
        echo "FAIL: Null coalescing pattern not found in constructor\n";
        exit(1);
    }
} else {
    echo "FAIL: Could not locate constructor\n";
    exit(1);
}

// Test 4: OpenAIDenseEmbedding constructor also nullable
$openaiSource = file_get_contents(__DIR__ . '/../src/embeddings/OpenAIDenseEmbedding.php');
if (str_contains($openaiSource, '?string $apiKey = null')) {
    echo "PASS: OpenAIDenseEmbedding constructor has nullable apiKey\n";
} else {
    echo "FAIL: OpenAIDenseEmbedding constructor apiKey not nullable\n";
    exit(1);
}

// Test 5: QwenDenseEmbedding constructor also nullable
$qwenSource = file_get_contents(__DIR__ . '/../src/embeddings/QwenDenseEmbedding.php');
if (str_contains($qwenSource, '?string $apiKey = null')) {
    echo "PASS: QwenDenseEmbedding constructor has nullable apiKey\n";
} else {
    echo "FAIL: QwenDenseEmbedding constructor apiKey not nullable\n";
    exit(1);
}

echo "\nAll API key env var source checks passed!\n";
?>
--EXPECT--
PASS: Constructor uses OPENAI_API_KEY and DASHSCOPE_API_KEY env vars as fallback
PASS: apiKey constructor parameter is nullable with default null
PASS: Constructor uses null coalescing for apiKey with env var fallback
PASS: OpenAIDenseEmbedding constructor has nullable apiKey
PASS: QwenDenseEmbedding constructor has nullable apiKey

All API key env var source checks passed!
