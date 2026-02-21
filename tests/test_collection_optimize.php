<?php

require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_collection_optimize';
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

$schema = new ZVecSchema('optimize_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert enough docs to create multiple segments
for ($i = 1; $i <= 1500; $i++) {
    $doc = new ZVecDoc("doc_$i");
    $doc->setInt64('id', $i)
        ->setVectorFp32('embedding', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
    $c->insert($doc);
}

$statsBefore = $c->stats();
echo "  Stats before optimize: " . substr($statsBefore, 0, 100) . "...\n";

// Optimize
$c->optimize();
echo "  Optimize completed\n";

$statsAfter = $c->stats();
echo "  Stats after optimize: " . substr($statsAfter, 0, 100) . "...\n";

// Test optimize on read-only (should fail)
$c->close();
$c2 = ZVec::open($path, readOnly: true);

try {
    $c2->optimize();
    echo "FAIL: test_collection_optimize - Should not allow optimize on read-only\n";
    exit(1);
} catch (ZVecException $e) {
    echo "  Optimize on read-only correctly rejected\n";
}

$c2->close();
exec("rm -rf " . escapeshellarg($path));

echo "PASS: test_collection_optimize - segment optimization works\n";
