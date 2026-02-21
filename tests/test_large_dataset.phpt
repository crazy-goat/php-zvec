--TEST--
Large dataset: insert 1500+ docs, performance checks
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_large_' . uniqid();

try {
    $schema = new ZVecSchema('large_test');
    $schema->setMaxDocCountPerSegment(10000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 128, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert 1500 documents
    $startTime = microtime(true);
    for ($i = 1; $i <= 1500; $i++) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setString('name', "name_$i");
        
        $vector = [];
        for ($j = 0; $j < 128; $j++) {
            $vector[] = (float)rand() / (float)getrandmax();
        }
        $doc->setVectorFp32('embedding', $vector);
        $c->insert($doc);
    }
    $insertTime = microtime(true) - $startTime;
    echo "Inserted 1500 documents OK\n";

    $stats = $c->stats();
    if (strpos($stats, 'doc_count:1500') === false) {
        echo "FAIL: Should have 1500 documents\n";
        exit(1);
    }
    echo "Verified doc_count=1500 OK\n";

    // Query performance check
    $queryVector = [];
    for ($j = 0; $j < 128; $j++) {
        $queryVector[] = (float)rand() / (float)getrandmax();
    }

    $startTime = microtime(true);
    $results = $c->query('embedding', $queryVector, topk: 10);
    $queryTime = microtime(true) - $startTime;

    echo "Query returned " . count($results) . " results OK\n";
    
    if (count($results) !== 10 || $queryTime >= 5.0) {
        echo "FAIL: Query should return 10 results in under 5 seconds\n";
        exit(1);
    }
    echo "Query performance OK (under 5s threshold)\n";

    // Multiple queries performance
    $startTime = microtime(true);
    $totalResults = 0;
    for ($q = 0; $q < 10; $q++) {
        $qv = [];
        for ($j = 0; $j < 128; $j++) {
            $qv[] = (float)rand() / (float)getrandmax();
        }
        $res = $c->query('embedding', $qv, topk: 5);
        $totalResults += count($res);
    }
    $multiQueryTime = microtime(true) - $startTime;
    
    echo "10 queries completed OK\n";
    echo "Total results returned: $totalResults\n";
    
    if ($multiQueryTime >= 10.0) {
        echo "FAIL: 10 queries should complete in less than 10 seconds\n";
        exit(1);
    }
    echo "Multiple query performance OK\n";

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "Large dataset operations work correctly\n";
?>
--EXPECT--
Inserted 1500 documents OK
Verified doc_count=1500 OK
Query returned 10 results OK
Query performance OK (under 5s threshold)
10 queries completed OK
Total results returned: 50
Multiple query performance OK
Large dataset operations work correctly
