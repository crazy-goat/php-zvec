<?php

declare(strict_types=1);

/**
 * Basic Embedding Functions Example
 * 
 * This example demonstrates how to use the embedding functions
 * to convert text into vector representations using external APIs.
 */

require_once __DIR__ . '/../php/embeddings.php';

echo "=== Basic Embedding Functions Example ===\n\n";

// ============================================================
// 1. OpenAI Embedding
// ============================================================
echo "1. OpenAI Dense Embedding\n";
echo "--------------------------\n";

// Note: Replace 'your-api-key' with actual OpenAI API key
// For this example, we'll use mock embedding to avoid API calls

class ExampleMockEmbedding extends ApiEmbeddingFunction implements DenseEmbeddingFunction
{
    private int $dimension;

    public function __construct(int $dimension = 1536)
    {
        $this->dimension = $dimension;
    }

    protected function getDefaultBaseUrl(): string { return ''; }
    protected function getHeaders(): array { return []; }
    public function getDimension(): int { return $this->dimension; }

    public function embed(string $input): array
    {
        // Generate deterministic mock vector
        $vector = [];
        $hash = crc32($input);
        
        for ($i = 0; $i < $this->dimension; $i++) {
            $vector[] = (float) (sin($hash + $i * 0.1) * 0.5);
        }
        
        // Normalize
        $norm = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector)));
        if ($norm > 0) {
            $vector = array_map(fn($x) => $x / $norm, $vector);
        }
        
        return $vector;
    }

    public function embedBatch(array $inputs): array
    {
        return array_map(fn($input) => $this->embed($input), $inputs);
    }
}

// Create embedder with 128 dimensions for demo
$embedder = new ExampleMockEmbedding(128);
echo "Created embedder with dimension: " . $embedder->getDimension() . "\n";

// Single text embedding
$text = "The quick brown fox jumps over the lazy dog";
$vector = $embedder->embed($text);
echo "Text: '$text'\n";
echo "Vector dimension: " . count($vector) . "\n";
echo "First 5 values: [" . implode(', ', array_slice($vector, 0, 5)) . "]\n\n";

// ============================================================
// 2. Batch Embedding
// ============================================================
echo "2. Batch Embedding\n";
echo "------------------\n";

$texts = [
    "PHP is a popular programming language",
    "Vector databases store high-dimensional data",
    "Machine learning models generate embeddings",
];

$vectors = $embedder->embedBatch($texts);
echo "Embedded " . count($vectors) . " texts\n";
foreach ($texts as $i => $text) {
    echo ($i + 1) . ". '$text' -> vector[0] = " . round($vectors[$i][0], 4) . "\n";
}
echo "\n";

// ============================================================
// 3. Similarity Comparison (Cosine)
// ============================================================
echo "3. Similarity Comparison\n";
echo "------------------------\n";

function cosineSimilarity(array $a, array $b): float
{
    $dot = 0;
    $normA = 0;
    $normB = 0;
    
    for ($i = 0; $i < count($a); $i++) {
        $dot += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    
    return $dot / (sqrt($normA) * sqrt($normB));
}

$text1 = "King";
$text2 = "Queen";
$text3 = "Apple";

$vec1 = $embedder->embed($text1);
$vec2 = $embedder->embed($text2);
$vec3 = $embedder->embed($text3);

$sim12 = cosineSimilarity($vec1, $vec2);
$sim13 = cosineSimilarity($vec1, $vec3);

echo "Similarity '$text1' <-> '$text2': " . round($sim12, 4) . "\n";
echo "Similarity '$text1' <-> '$text3': " . round($sim13, 4) . "\n";
echo "Note: In real embeddings, King/Queen would be more similar than King/Apple\n\n";

// ============================================================
// 4. Using Real OpenAI API (commented out)
// ============================================================
echo "4. Real API Usage (requires API key)\n";
echo "-------------------------------------\n";
echo "To use real OpenAI API:\n\n";
echo "\$embedder = new OpenAIDenseEmbedding(\n";
echo "    apiKey: 'sk-your-openai-api-key',\n";
echo "    model: OpenAIDenseEmbedding::MODEL_SMALL,  // 1536 dims\n";
echo "    // model: OpenAIDenseEmbedding::MODEL_LARGE,  // 3072 dims\n";
echo "    // dimensions: 512,  // Optional: reduce dimensions for v3 models\n";
echo ");\n\n";
echo "\$vector = \$embedder->embed('Your text here');\n\n";

// ============================================================
// 5. Using DashScope/Qwen API (commented out)
// ============================================================
echo "5. DashScope/Qwen API Usage\n";
echo "---------------------------\n";
echo "To use DashScope API:\n\n";
echo "\$embedder = new QwenDenseEmbedding(\n";
echo "    apiKey: 'your-dashscope-api-key',\n";
echo "    model: QwenDenseEmbedding::MODEL_V4,  // 1792 dims\n";
echo "    // model: QwenDenseEmbedding::MODEL_V3,  // 1024 dims\n";
echo ");\n\n";
echo "\$vector = \$embedder->embed('Your text here');\n\n";

echo "=== Example Complete ===\n";
