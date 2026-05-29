--TEST--
WeightedReRanker: empty weights must throw ZVecException
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: empty weights array should throw
try {
    new ZVecWeightedReRanker([]);
    echo "FAIL: no exception for empty weights\n";
} catch (ZVecException $e) {
    echo "PASS: empty weights throws ZVecException\n";
    echo "  message: " . $e->getMessage() . "\n";
}

// Test 2: non-empty weights should work
try {
    $r = new ZVecWeightedReRanker(['field' => 1.0]);
    echo "PASS: non-empty weights accepted\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// Test 3: weights parameter is required (no default)
$ref = new ReflectionMethod(ZVecWeightedReRanker::class, '__construct');
$params = $ref->getParameters();
$weightsParam = null;
foreach ($params as $p) {
    if ($p->getName() === 'weights') {
        $weightsParam = $p;
        break;
    }
}
if ($weightsParam !== null && !$weightsParam->isDefaultValueAvailable()) {
    echo "PASS: weights parameter has no default value (required)\n";
} else {
    echo "FAIL: weights parameter should be required\n";
}

// Test 4: rerank() with valid weights produces correct results
$path = __DIR__ . '/../test_dbs/weighted_validate_' . uniqid();
try {
    $schema = new ZVecSchema('test_validate');
    $schema->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    $docs = [
        (new ZVecDoc('d1'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('d2'))->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    $reranker = new ZVecWeightedReRanker(
        weights: ['embedding' => 1.0],
        topn: 10,
        metricType: ZVecSchema::METRIC_IP
    );

    // Create a mock query result
    $results = $collection->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 2);
    $reranked = $reranker->rerank(['embedding' => $results]);

    echo "PASS: rerank() returns " . count($reranked) . " results\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: empty weights throws ZVecException
  message: ZVecWeightedReRanker requires at least one field weight
PASS: non-empty weights accepted
PASS: weights parameter has no default value (required)
PASS: rerank() returns 2 results
