--TEST--
Reranker RRF: empty query results input returns empty array
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_rrf_empty_' . uniqid();

try {
    $schema = new ZVecSchema('test_rrf_empty');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    $reranker = new ZVecRrfReRanker(topn: 10, rankConstant: 60);

    // Edge case: empty associative array (no fields)
    $emptyResults = $reranker->rerank([]);
    echo count($emptyResults) === 0 ? "PASS: empty main array returns 0 results\n" : "FAIL\n";

    // Edge case: field with empty array
    $emptyFieldResults = $reranker->rerank(['v' => []]);
    echo count($emptyFieldResults) === 0 ? "PASS: field with empty doc array returns 0 results\n" : "FAIL\n";

    // Edge case: field with non-array value
    $nonArrayResults = $reranker->rerank(['v' => null]);
    echo count($nonArrayResults) === 0 ? "PASS: field with null value returns 0 results\n" : "FAIL\n";

    // Edge case: non-ZVecDoc objects in array
    $nonDocResults = $reranker->rerank(['v' => ['not_a_doc']]);
    echo count($nonDocResults) === 0 ? "PASS: non-ZVecDoc elements are filtered out\n" : "FAIL\n";

    $collection->close();
    echo "All RRF empty input tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: empty main array returns 0 results
PASS: field with empty doc array returns 0 results
PASS: field with null value returns 0 results
PASS: non-ZVecDoc elements are filtered out
All RRF empty input tests passed
