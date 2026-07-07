--TEST--
SEC-008: API key masking — __debugInfo() source analysis
--FILE--
<?php
$source = file_get_contents(__DIR__ . '/../src/embeddings/EmbeddingInterfaces.php');

// __debugInfo method exists
if (!str_contains($source, 'function __debugInfo()')) { echo "FAIL: __debugInfo method not found\n"; exit(1); }
echo "PASS: __debugInfo method declared in EmbeddingInterfaces.php\n";

// API key masking pattern
if (!str_contains($source, "'***' . substr(\$this->apiKey, -4)")) { echo "FAIL: API key mask pattern not found\n"; exit(1); }
echo "PASS: API key mask pattern found (*** + last 4 chars)\n";

// apiKey key in return array
if (!str_contains($source, "'apiKey'")) { echo "FAIL: apiKey key not in __debugInfo return array\n"; exit(1); }
echo "PASS: apiKey key present in __debugInfo return array\n";

// Raw apiKey NOT exposed directly
$debugInfoPos = strpos($source, 'function __debugInfo()');
$braceCount = 0;
$debugBody = '';
for ($i = $debugInfoPos; $i < strlen($source); $i++) {
    $debugBody .= $source[$i];
    if ($source[$i] === '{') $braceCount++;
    if ($source[$i] === '}') $braceCount--;
    if ($braceCount === 0 && $i > $debugInfoPos) break;
}
if (str_contains($debugBody, "'apiKey' => \$this->apiKey")) { echo "FAIL: Raw apiKey exposed in __debugInfo\n"; exit(1); }
echo "PASS: Raw apiKey not exposed directly in __debugInfo\n";

// __clone method prevents cloned-instance buffer corruption
if (!str_contains($source, 'function __clone()')) { echo "FAIL: __clone method not found\n"; exit(1); }
echo "PASS: __clone method declared to prevent cloning\n";

// Destructor with sodium_memzero
if (!str_contains($source, 'sodium_memzero($this->apiKey)')) { echo "FAIL: sodium_memzero call not found\n"; exit(1); }
echo "PASS: Destructor calls sodium_memzero on apiKey\n";
if (!str_contains($source, "function_exists('sodium_memzero')")) { echo "FAIL: function_exists guard not found\n"; exit(1); }
echo "PASS: Destructor checks sodium_memzero exists before calling\n";

// Nullable apiKey with env var fallback
if (!str_contains($source, '?string $apiKey = null')) { echo "FAIL: Nullable apiKey not found\n"; exit(1); }
if (!str_contains($source, "getenv('OPENAI_API_KEY')")) { echo "FAIL: OPENAI_API_KEY env var fallback not found\n"; exit(1); }
if (!str_contains($source, "getenv('DASHSCOPE_API_KEY')")) { echo "FAIL: DASHSCOPE_API_KEY env var fallback not found\n"; exit(1); }
echo "PASS: Constructor has nullable apiKey with env var fallback\n";

// Verify OpenAIDenseEmbedding constructor is also nullable
$openaiSource = file_get_contents(__DIR__ . '/../src/embeddings/OpenAIDenseEmbedding.php');
if (!str_contains($openaiSource, '?string $apiKey = null')) { echo "FAIL: OpenAIDenseEmbedding apiKey not nullable\n"; exit(1); }
echo "PASS: OpenAIDenseEmbedding constructor has nullable apiKey\n";

// Verify QwenDenseEmbedding constructor is also nullable
$qwenSource = file_get_contents(__DIR__ . '/../src/embeddings/QwenDenseEmbedding.php');
if (!str_contains($qwenSource, '?string $apiKey = null')) { echo "FAIL: QwenDenseEmbedding apiKey not nullable\n"; exit(1); }
echo "PASS: QwenDenseEmbedding constructor has nullable apiKey\n";

echo "\nAll API key security source checks passed!\n";
?>
--EXPECT--
PASS: __debugInfo method declared in EmbeddingInterfaces.php
PASS: API key mask pattern found (*** + last 4 chars)
PASS: apiKey key present in __debugInfo return array
PASS: Raw apiKey not exposed directly in __debugInfo
PASS: __clone method declared to prevent cloning
PASS: Destructor calls sodium_memzero on apiKey
PASS: Destructor checks sodium_memzero exists before calling
PASS: Constructor has nullable apiKey with env var fallback
PASS: OpenAIDenseEmbedding constructor has nullable apiKey
PASS: QwenDenseEmbedding constructor has nullable apiKey

All API key security source checks passed!
