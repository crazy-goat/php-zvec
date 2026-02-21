--TEST--
Extensions: Embedding Functions - Integration with ZVec
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/embeddings.php';

/**
 * Mock embedding function for testing (simulates API without making HTTP calls)
 */
class MockDenseEmbedding extends ApiEmbeddingFunction implements DenseEmbeddingFunction
{
    private int $dimension;

    public function __construct(int $dimension = 1536)
    {
        // Don't call parent constructor - we don't need API key for mock
        $this->dimension = $dimension;
    }

    protected function getDefaultBaseUrl(): string
    {
        return '';
    }

    protected function getHeaders(): array
    {
        return [];
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function embed(string $input): array
    {
        // Generate deterministic mock vector based on input string
        $vector = [];
        $hash = crc32($input);
        
        for ($i = 0; $i < $this->dimension; $i++) {
            // Generate pseudo-random value between -1 and 1
            $vector[] = (float) (sin($hash + $i * 0.1) * 0.5);
        }
        
        // Normalize the vector
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

echo "Mock embedding function test:\n";

// Test mock embedding function
$embedder = new MockDenseEmbedding(128);

// Test single embed
$vector = $embedder->embed('Hello world');
if (count($vector) === 128) {
    echo "PASS: Single embed returns correct dimension (128)\n";
} else {
    echo "FAIL: Expected 128 dimensions, got " . count($vector) . "\n";
    exit(1);
}

// Test vector is normalized (length ≈ 1)
$length = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector)));
if (abs($length - 1.0) < 0.01) {
    echo "PASS: Vector is normalized (length ≈ 1.0)\n";
} else {
    echo "FAIL: Vector length is $length, expected ≈ 1.0\n";
    exit(1);
}

// Test same input produces same vector
$vector2 = $embedder->embed('Hello world');
if ($vector === $vector2) {
    echo "PASS: Same input produces same vector (deterministic)\n";
} else {
    echo "FAIL: Same input produced different vectors\n";
    exit(1);
}

// Test different input produces different vector
$vector3 = $embedder->embed('Different text');
if ($vector !== $vector3) {
    echo "PASS: Different input produces different vector\n";
} else {
    echo "FAIL: Different input produced same vector\n";
    exit(1);
}

// Test batch embed
$texts = ['Text 1', 'Text 2', 'Text 3'];
$batch = $embedder->embedBatch($texts);
if (count($batch) === 3) {
    echo "PASS: Batch embed returns correct count (3)\n";
} else {
    echo "FAIL: Expected 3 vectors, got " . count($batch) . "\n";
    exit(1);
}

// Test empty batch
$empty = $embedder->embedBatch([]);
if (count($empty) === 0) {
    echo "PASS: Empty batch returns empty array\n";
} else {
    echo "FAIL: Empty batch should return empty array\n";
    exit(1);
}

// Test getDimension method
if ($embedder->getDimension() === 128) {
    echo "PASS: getDimension returns correct value (128)\n";
} else {
    echo "FAIL: getDimension returned wrong value\n";
    exit(1);
}

echo "\nAll mock embedding tests passed!\n";
?>
--EXPECT--
Mock embedding function test:
PASS: Single embed returns correct dimension (128)
PASS: Vector is normalized (length ≈ 1.0)
PASS: Same input produces same vector (deterministic)
PASS: Different input produces different vector
PASS: Batch embed returns correct count (3)
PASS: Empty batch returns empty array
PASS: getDimension returns correct value (128)

All mock embedding tests passed!
