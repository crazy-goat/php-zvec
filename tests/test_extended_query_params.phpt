--TEST--
Extended HNSW/IVF Query Parameters: isLinear
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_params_' . uniqid();
try {
    // Create schema with vector field
    $schema = (new ZVecSchema('test'))
        ->addVectorFp32('embedding', 128, ZVecSchema::METRIC_IP)
        ->addString('category');

    $collection = ZVec::create($path, $schema);

    // Insert some test documents
    for ($i = 0; $i < 100; $i++) {
        $vec = array_fill(0, 128, 0.0);
        $vec[0] = $i / 100.0;
        $vec[1] = 1.0 - ($i / 100.0);
        
        $doc = (new ZVecDoc("doc_$i"))
            ->setVectorFp32('embedding', $vec)
            ->setString('category', $i % 2 === 0 ? 'even' : 'odd');
        $collection->insert($doc);
    }
    $collection->optimize();

    // Create HNSW index
    $collection->createHnswIndex('embedding', ZVecSchema::METRIC_IP, 16, 200);

    $queryVec = array_fill(0, 128, 0.0);
    $queryVec[0] = 0.5;
    $queryVec[1] = 0.5;

    // Test 1: Basic query with default params
    $results = $collection->query(
        fieldName: 'embedding',
        queryVector: $queryVec,
        topk: 10,
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 100
    );
    echo "Test 1 - Basic query: " . count($results) . " results\n";

    // Test 2: Query with isLinear=true (force brute-force)
    $results = $collection->query(
        fieldName: 'embedding',
        queryVector: $queryVec,
        topk: 10,
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 100,
        isLinear: true
    );
    echo "Test 2 - Linear search: " . count($results) . " results\n";

    // Test 3: IVF with isLinear
    $collection2Path = __DIR__ . '/../test_dbs/query_params_ivf_' . uniqid();
    $schema2 = (new ZVecSchema('test2'))
        ->addVectorFp32('embedding', 128, ZVecSchema::METRIC_IP);
    $collection2 = ZVec::create($collection2Path, $schema2);

    for ($i = 0; $i < 200; $i++) {
        $vec = array_fill(0, 128, 0.0);
        $vec[0] = sin($i * 0.1);
        $vec[1] = cos($i * 0.1);
        $doc = (new ZVecDoc("doc_$i"))->setVectorFp32('embedding', $vec);
        $collection2->insert($doc);
    }
    $collection2->optimize();
    $collection2->createIvfIndex('embedding', ZVecSchema::METRIC_IP, 16, 10, false);

    $results = $collection2->query(
        fieldName: 'embedding',
        queryVector: $queryVec,
        topk: 10,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 4,
        isLinear: true
    );
    echo "Test 3 - IVF linear search: " . count($results) . " results\n";

    $collection2->close();
    exec("rm -rf " . escapeshellarg($collection2Path));

    // Test 4: Query with different EF value
    $results = $collection->query(
        fieldName: 'embedding',
        queryVector: $queryVec,
        topk: 10,
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 50
    );
    echo "Test 4 - HNSW with ef=50: " . count($results) . " results\n";

    // Test 5: Test groupByQuery with extended params
    $results = $collection->groupByQuery(
        fieldName: 'embedding',
        queryVector: $queryVec,
        groupByField: 'category',
        groupCount: 2,
        groupTopk: 5,
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 100,
        isLinear: true
    );
    echo "Test 5 - GroupBy with linear: " . count($results) . " groups\n";

    echo "All tests passed!\n";

    $collection->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Test 1 - Basic query: 10 results
Test 2 - Linear search: 10 results
Test 3 - IVF linear search: 10 results
Test 4 - HNSW with ef=50: 10 results
Test 5 - GroupBy with linear: 1 groups
All tests passed!
