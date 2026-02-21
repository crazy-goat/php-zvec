<?php

require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_collection_open';
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

$schema = new ZVecSchema('open_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

// Create and close
$c = ZVec::create($path, $schema);
$c->close();
echo "  Created and closed collection\n";

// Reopen in read-write mode
$c2 = ZVec::open($path, readOnly: false);
assert($c2->options()['read_only'] === false, 'Should be read-write');
echo "  Reopened in read-write mode OK\n";

// Insert a doc to verify write works
$doc = new ZVecDoc('doc1');
$doc->setInt64('id', 1)->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);
$c2->insert($doc);
echo "  Insert works after reopen\n";

$c2->close();

// Reopen in read-only mode
$c3 = ZVec::open($path, readOnly: true);
assert($c3->options()['read_only'] === true, 'Should be read-only');
echo "  Reopened in read-only mode OK\n";

// Try to write to read-only (should fail)
try {
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)->setVectorFp32('embedding', [0.4, 0.3, 0.2, 0.1]);
    $c3->insert($doc2);
    echo "FAIL: test_collection_open - Should not allow writes to read-only collection\n";
    exit(1);
} catch (ZVecException $e) {
    echo "  Read-only correctly blocks writes\n";
}

$c3->close();

// Try to open non-existent collection (should fail)
$nonExistentPath = __DIR__ . '/../non_existent_collection_12345';
try {
    $c4 = ZVec::open($nonExistentPath, readOnly: false);
    echo "FAIL: test_collection_open - Should fail for non-existent path\n";
    exit(1);
} catch (ZVecException $e) {
    echo "  Non-existent path correctly rejected\n";
}

exec("rm -rf " . escapeshellarg($path));

echo "PASS: test_collection_open - collection open/close works\n";
