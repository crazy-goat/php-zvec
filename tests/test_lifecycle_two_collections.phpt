--TEST--
Lifecycle: two collections open simultaneously — both readable and writable
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path1 = __DIR__ . '/../test_dbs/lifecycle_two_coll_a_' . uniqid();
$path2 = __DIR__ . '/../test_dbs/lifecycle_two_coll_b_' . uniqid();

try {
    $schema = new ZVecSchema('test_two_coll');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c1 = ZVec::create($path1, $schema);
    $c2 = ZVec::create($path2, $schema);

    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1);
    $doc1->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c1->insert($doc1);

    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2);
    $doc2->setVectorFp32('v', [0.5, 0.6, 0.7, 0.8]);
    $c2->insert($doc2);

    $c1->optimize();
    $c2->optimize();

    $results1 = $c1->query('v', [0.1, 0.2, 0.3, 0.4], topk: 5);
    $results2 = $c2->query('v', [0.5, 0.6, 0.7, 0.8], topk: 5);

    echo "PASS: two collections open simultaneously (c1=" . count($results1) . ", c2=" . count($results2) . ")\n";

    $c1->close();
    $c2->close();
} finally {
    exec("rm -rf " . escapeshellarg($path1));
    exec("rm -rf " . escapeshellarg($path2));
}
?>
--EXPECTF--
PASS: two collections open simultaneously (c1=%d, c2=%d)
