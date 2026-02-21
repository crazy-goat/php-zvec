<?php

declare(strict_types=1);

/**
 * Embeddings with ZVec Collection Example
 * 
 * This example demonstrates how to use embedding functions
 * to generate vectors and store them in a ZVec collection for similarity search.
 */

require_once __DIR__ . '/../php/ZVec.php';
require_once __DIR__ . '/../php/embeddings.php';

echo "=== Embeddings with ZVec Collection Example ===\n\n";

// ============================================================
// 0. INIT
// ============================================================
ZVec::init(
    logType: ZVec::LOG_CONSOLE,
    logLevel: ZVec::LOG_WARN,
);

// ============================================================
// 1. Create Mock Embedding Function
// ============================================================
class DemoEmbedding extends ApiEmbeddingFunction implements DenseEmbeddingFunction
{
    private int $dimension;

    public function __construct(int $dimension = 128)
    {
        $this->dimension = $dimension;
    }

    protected function getDefaultBaseUrl(): string { return ''; }
    protected function getHeaders(): array { return []; }
    public function getDimension(): int { return $this->dimension; }

    public function embed(string $input): array
    {
        // Simple mock: create vector from text hash
        $vector = [];
        $hash = crc32(strtolower($input));
        
        // Generate semi-random but deterministic vector
        mt_srand($hash);
        for ($i = 0; $i < $this->dimension; $i++) {
            $vector[] = (float) (mt_rand(-1000, 1000) / 1000);
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

$embedder = new DemoEmbedding(128);
echo "Created embedder with " . $embedder->getDimension() . " dimensions\n\n";

// ============================================================
// 2. Create Collection
// ============================================================
$path = __DIR__ . '/../demo_embeddings_collection';

// Clean up if exists
if (is_dir($path)) {
    exec("rm -rf " . escapeshellarg($path));
}

$schema = new ZVecSchema('docs');
// Use METRIC_IP (Inner Product) which is commonly used for normalized vectors
$schema->setMaxDocCountPerSegment(1000)
    ->addString('title', nullable: false, withInvertIndex: true)
    ->addString('content', nullable: false, withInvertIndex: true)
    ->addString('category', nullable: true, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: $embedder->getDimension(), metricType: ZVecSchema::METRIC_IP);

$collection = ZVec::create($path, $schema);
echo "Created collection at: $path\n";
echo "Schema: " . $collection->schema() . "\n\n";

// ============================================================
// 3. Sample Documents
// ============================================================
$documents = [
    [
        'id' => 'doc_1',
        'title' => 'Introduction to PHP',
        'content' => 'PHP is a server-side scripting language designed for web development.',
        'category' => 'programming',
    ],
    [
        'id' => 'doc_2',
        'title' => 'Machine Learning Basics',
        'content' => 'Machine learning is a subset of artificial intelligence.',
        'category' => 'ai',
    ],
    [
        'id' => 'doc_3',
        'title' => 'Vector Databases Explained',
        'content' => 'Vector databases store and query high-dimensional vectors efficiently.',
        'category' => 'databases',
    ],
    [
        'id' => 'doc_4',
        'title' => 'PHP Best Practices',
        'content' => 'Writing clean and maintainable PHP code requires following standards.',
        'category' => 'programming',
    ],
    [
        'id' => 'doc_5',
        'title' => 'Deep Learning Fundamentals',
        'content' => 'Deep learning uses neural networks with multiple layers.',
        'category' => 'ai',
    ],
];

// ============================================================
// 4. Generate Embeddings and Insert Documents
// ============================================================
echo "4. Inserting documents with embeddings...\n";

foreach ($documents as $doc) {
    // Combine title and content for embedding
    $textToEmbed = $doc['title'] . ". " . $doc['content'];
    $embedding = $embedder->embed($textToEmbed);
    
    $zdoc = new ZVecDoc($doc['id']);
    $zdoc->setString('title', $doc['title'])
        ->setString('content', $doc['content'])
        ->setString('category', $doc['category'])
        ->setVectorFp32('embedding', $embedding);
    
    $collection->insert($zdoc);
    echo "  Inserted: {$doc['title']} (embedding dim: " . count($embedding) . ")\n";
}

echo "\nCollection stats: " . $collection->stats() . "\n\n";

// ============================================================
// 5. Similarity Search
// ============================================================
echo "5. Similarity Search\n";
echo "-------------------\n";
echo "Note: Query without explicit index uses brute force search\n\n";

// Search for similar documents
$queryText = "How to write PHP code";
$queryVector = $embedder->embed($queryText);

echo "Query: '$queryText'\n";
echo "Searching for similar documents...\n\n";

$results = $collection->query(
    'embedding',
    $queryVector,
    3
);

echo "Top 3 similar documents:\n";
foreach ($results as $i => $doc) {
    $pk = $doc->getPk();
    $score = $doc->getScore();
    echo ($i + 1) . ". Document: $pk (score: " . round($score, 4) . ")\n";
}
echo "\n";

// ============================================================
// 6. Category Filter + Vector Search
// ============================================================
echo "6. Filtered Search (AI category only)\n";
echo "--------------------------------------\n";

$queryText2 = "Neural networks and deep learning";
$queryVector2 = $embedder->embed($queryText2);

echo "Query: '$queryText2'\n";
echo "Filter: category = 'ai'\n\n";

$results = $collection->query(
    'embedding',
    $queryVector2,
    2,
    false,
    "category = 'ai'"
);

echo "Top 2 AI documents:\n";
foreach ($results as $i => $doc) {
    $pk = $doc->getPk();
    $score = $doc->getScore();
    echo ($i + 1) . ". Document: $pk (score: " . round($score, 4) . ")\n";
}
echo "\n";

// ============================================================
// 7. Using Real API (example code)
// ============================================================
echo "7. Using Real OpenAI API\n";
echo "------------------------\n";
echo "To use real embeddings with OpenAI:\n\n";
echo "\$embedder = new OpenAIDenseEmbedding(\n";
echo "    apiKey: 'sk-your-openai-api-key',\n";
echo "    model: OpenAIDenseEmbedding::MODEL_SMALL\n";
echo ");\n\n";
echo "// Generate embedding\n";
echo "\$vector = \$embedder->embed('Your text here');\n\n";
echo "// Use with ZVec\n";
echo "\$doc = new ZVecDoc('id');\n";
echo "\$doc->setVectorFp32('embedding', \$vector);\n";
echo "\$collection->insert(\$doc);\n\n";

// ============================================================
// Cleanup
// ============================================================
$collection->close();
exec("rm -rf " . escapeshellarg($path));
echo "Cleaned up demo collection\n";
echo "\n=== Example Complete ===\n";
