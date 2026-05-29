--TEST--
Extracted parseQueryResult() and parseGroupResult() helpers
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/parse_query_result_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    $docs = [
        ['doc1', 1, [1.0, 0.0, 0.0, 0.0], 'tech'],
        ['doc2', 2, [0.9, 0.1, 0.0, 0.0], 'tech'],
        ['doc3', 3, [0.5, 0.5, 0.0, 0.0], 'finance'],
        ['doc4', 4, [0.0, 1.0, 0.0, 0.0], 'finance'],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d[0]);
        $doc->setInt64('id', $d[1])
            ->setVectorFp32('embedding', $d[2])
            ->setString('category', $d[3]);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: query() uses parseQueryResult()
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 2);
    assert(count($results) === 2, 'query() should return 2 results');
    assert($results[0]->getPk() === 'doc1', 'query() first result should be doc1');
    echo "query() uses parseQueryResult() OK\n";

    // Test 2: queryByFilter() uses parseQueryResult()
    $results = $c->queryByFilter(filter: "category = 'tech'", topk: 10);
    assert(count($results) === 2, 'queryByFilter() should return 2 tech docs');
    echo "queryByFilter() uses parseQueryResult() OK\n";

    // Test 3: fetch() uses parseQueryResult()
    $results = $c->fetch('doc1');
    assert(count($results) === 1, 'fetch() should return 1 doc');
    assert($results[0]->getPk() === 'doc1', 'fetch() should return doc1');
    echo "fetch() uses parseQueryResult() OK\n";

    // Test 4: groupByQuery() uses parseGroupResult()
    $results = $c->groupByQuery(
        fieldName: 'embedding',
        queryVector: [1.0, 0.0, 0.0, 0.0],
        groupByField: 'category',
        groupCount: 2,
        groupTopk: 2
    );
    assert(count($results) >= 1, 'groupByQuery() should return at least 1 group');
    echo "groupByQuery() uses parseGroupResult() OK\n";

    $c->close();
    echo "PASS: All query methods work with extracted helpers\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
query() uses parseQueryResult() OK
queryByFilter() uses parseQueryResult() OK
fetch() uses parseQueryResult() OK
groupByQuery() uses parseGroupResult() OK
PASS: All query methods work with extracted helpers
