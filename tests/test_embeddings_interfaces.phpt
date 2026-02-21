--TEST--
Extensions: Embedding Functions - Interface validation
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/embeddings.php';

// Test 1: Verify interfaces exist
echo "Interface validation:\n";

if (interface_exists('DenseEmbeddingFunction')) {
    echo "PASS: DenseEmbeddingFunction interface exists\n";
} else {
    echo "FAIL: DenseEmbeddingFunction interface not found\n";
    exit(1);
}

if (interface_exists('SparseEmbeddingFunction')) {
    echo "PASS: SparseEmbeddingFunction interface exists\n";
} else {
    echo "FAIL: SparseEmbeddingFunction interface not found\n";
    exit(1);
}

// Test 2: Verify OpenAIDenseEmbedding class exists and implements interface
if (class_exists('OpenAIDenseEmbedding')) {
    echo "PASS: OpenAIDenseEmbedding class exists\n";
    
    $reflection = new ReflectionClass('OpenAIDenseEmbedding');
    if ($reflection->implementsInterface('DenseEmbeddingFunction')) {
        echo "PASS: OpenAIDenseEmbedding implements DenseEmbeddingFunction\n";
    } else {
        echo "FAIL: OpenAIDenseEmbedding does not implement DenseEmbeddingFunction\n";
        exit(1);
    }
    
    if ($reflection->isSubclassOf('ApiEmbeddingFunction')) {
        echo "PASS: OpenAIDenseEmbedding extends ApiEmbeddingFunction\n";
    } else {
        echo "FAIL: OpenAIDenseEmbedding does not extend ApiEmbeddingFunction\n";
        exit(1);
    }
} else {
    echo "FAIL: OpenAIDenseEmbedding class not found\n";
    exit(1);
}

// Test 3: Verify QwenDenseEmbedding class exists and implements interface
if (class_exists('QwenDenseEmbedding')) {
    echo "PASS: QwenDenseEmbedding class exists\n";
    
    $reflection = new ReflectionClass('QwenDenseEmbedding');
    if ($reflection->implementsInterface('DenseEmbeddingFunction')) {
        echo "PASS: QwenDenseEmbedding implements DenseEmbeddingFunction\n";
    } else {
        echo "FAIL: QwenDenseEmbedding does not implement DenseEmbeddingFunction\n";
        exit(1);
    }
    
    if ($reflection->isSubclassOf('ApiEmbeddingFunction')) {
        echo "PASS: QwenDenseEmbedding extends ApiEmbeddingFunction\n";
    } else {
        echo "FAIL: QwenDenseEmbedding does not extend ApiEmbeddingFunction\n";
        exit(1);
    }
} else {
    echo "FAIL: QwenDenseEmbedding class not found\n";
    exit(1);
}

// Test 4: Verify constants
$openaiReflection = new ReflectionClass('OpenAIDenseEmbedding');
$constants = $openaiReflection->getConstants();

$expectedConstants = ['MODEL_SMALL', 'MODEL_LARGE', 'MODEL_ADA'];
foreach ($expectedConstants as $const) {
    if (isset($constants[$const])) {
        echo "PASS: OpenAIDenseEmbedding::$const = " . $constants[$const] . "\n";
    } else {
        echo "FAIL: OpenAIDenseEmbedding::$const not found\n";
        exit(1);
    }
}

$qwenReflection = new ReflectionClass('QwenDenseEmbedding');
$constants = $qwenReflection->getConstants();

$expectedConstants = ['MODEL_V4', 'MODEL_V3', 'MODEL_V2', 'MODEL_V1'];
foreach ($expectedConstants as $const) {
    if (isset($constants[$const])) {
        echo "PASS: QwenDenseEmbedding::$const = " . $constants[$const] . "\n";
    } else {
        echo "FAIL: QwenDenseEmbedding::$const not found\n";
        exit(1);
    }
}

// Test 5: Verify required methods exist
$requiredMethods = ['embed', 'embedBatch', 'getDimension'];

foreach ($requiredMethods as $method) {
    if ($openaiReflection->hasMethod($method)) {
        echo "PASS: OpenAIDenseEmbedding has $method method\n";
    } else {
        echo "FAIL: OpenAIDenseEmbedding missing $method method\n";
        exit(1);
    }
}

foreach ($requiredMethods as $method) {
    if ($qwenReflection->hasMethod($method)) {
        echo "PASS: QwenDenseEmbedding has $method method\n";
    } else {
        echo "FAIL: QwenDenseEmbedding missing $method method\n";
        exit(1);
    }
}

// Test 6: Verify constructor signatures
$constructor = $openaiReflection->getConstructor();
$params = $constructor->getParameters();
$paramNames = array_map(fn($p) => $p->getName(), $params);

$expectedParams = ['apiKey', 'model', 'dimensions', 'baseUrl', 'timeout', 'proxy'];
foreach ($expectedParams as $param) {
    if (in_array($param, $paramNames)) {
        echo "PASS: OpenAIDenseEmbedding constructor has \$$param parameter\n";
    } else {
        echo "FAIL: OpenAIDenseEmbedding constructor missing \$$param parameter\n";
        exit(1);
    }
}

// Test 7: Verify getModel method exists (convenience method)
if ($openaiReflection->hasMethod('getModel')) {
    echo "PASS: OpenAIDenseEmbedding has getModel method\n";
} else {
    echo "FAIL: OpenAIDenseEmbedding missing getModel method\n";
    exit(1);
}

if ($qwenReflection->hasMethod('getModel')) {
    echo "PASS: QwenDenseEmbedding has getModel method\n";
} else {
    echo "FAIL: QwenDenseEmbedding missing getModel method\n";
    exit(1);
}

echo "\nAll embedding interface tests passed!\n";
?>
--EXPECT--
Interface validation:
PASS: DenseEmbeddingFunction interface exists
PASS: SparseEmbeddingFunction interface exists
PASS: OpenAIDenseEmbedding class exists
PASS: OpenAIDenseEmbedding implements DenseEmbeddingFunction
PASS: OpenAIDenseEmbedding extends ApiEmbeddingFunction
PASS: QwenDenseEmbedding class exists
PASS: QwenDenseEmbedding implements DenseEmbeddingFunction
PASS: QwenDenseEmbedding extends ApiEmbeddingFunction
PASS: OpenAIDenseEmbedding::MODEL_SMALL = text-embedding-3-small
PASS: OpenAIDenseEmbedding::MODEL_LARGE = text-embedding-3-large
PASS: OpenAIDenseEmbedding::MODEL_ADA = text-embedding-ada-002
PASS: QwenDenseEmbedding::MODEL_V4 = text-embedding-v4
PASS: QwenDenseEmbedding::MODEL_V3 = text-embedding-v3
PASS: QwenDenseEmbedding::MODEL_V2 = text-embedding-v2
PASS: QwenDenseEmbedding::MODEL_V1 = text-embedding-v1
PASS: OpenAIDenseEmbedding has embed method
PASS: OpenAIDenseEmbedding has embedBatch method
PASS: OpenAIDenseEmbedding has getDimension method
PASS: QwenDenseEmbedding has embed method
PASS: QwenDenseEmbedding has embedBatch method
PASS: QwenDenseEmbedding has getDimension method
PASS: OpenAIDenseEmbedding constructor has $apiKey parameter
PASS: OpenAIDenseEmbedding constructor has $model parameter
PASS: OpenAIDenseEmbedding constructor has $dimensions parameter
PASS: OpenAIDenseEmbedding constructor has $baseUrl parameter
PASS: OpenAIDenseEmbedding constructor has $timeout parameter
PASS: OpenAIDenseEmbedding constructor has $proxy parameter
PASS: OpenAIDenseEmbedding has getModel method
PASS: QwenDenseEmbedding has getModel method

All embedding interface tests passed!
