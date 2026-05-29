--TEST--
Doc getter pointer lifetime: verify FFI::string() copies prevent data corruption
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/ptr_lifetime_' . uniqid();
try {
    $schema = new ZVecSchema('test_ptr');
    $schema->addString('name');
    $schema->addVectorFp32('vec', dimension: 3, metricType: ZVecSchema::METRIC_IP);

    $coll = ZVec::create($path, schema: $schema);

    // Insert two documents
    $doc1 = new ZVecDoc('pk_alpha');
    $doc1->setString('name', 'Alice');
    $doc1->setVectorFp32('vec', [1.0, 0.0, 0.0]);

    $doc2 = new ZVecDoc('pk_beta');
    $doc2->setString('name', 'Bob');
    $doc2->setVectorFp32('vec', [0.0, 1.0, 0.0]);

    $coll->insert($doc1, $doc2);

    // Fetch both docs and create a map by PK
    $fetched = $coll->fetch('pk_alpha', 'pk_beta');
    $docMap = [];
    foreach ($fetched as $doc) {
        $docMap[$doc->getPk()] = $doc;
    }

    // Get PK of doc1, then get string from doc2
    $pk1 = $docMap['pk_alpha']->getPk();
    $name2 = $docMap['pk_beta']->getString('name');

    // Verify both values are correct (no cross-contamination)
    if ($pk1 !== 'pk_alpha') {
        echo "FAIL: PK of doc1 expected 'pk_alpha', got '{$pk1}'\n";
    } else {
        echo "PK of doc1 preserved correctly\n";
    }

    if ($name2 !== 'Bob') {
        echo "FAIL: name of doc2 expected 'Bob', got '{$name2}'\n";
    } else {
        echo "Name of doc2 preserved correctly\n";
    }

    // Get vector from doc1 after getting string from doc2
    $vec1 = $docMap['pk_alpha']->getVectorFp32('vec');
    if ($vec1 === null || count($vec1) !== 3 || abs($vec1[0] - 1.0) > 0.001) {
        echo "FAIL: vector of doc1 corrupted\n";
    } else {
        echo "Vector of doc1 preserved correctly\n";
    }

    $coll->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PK of doc1 preserved correctly
Name of doc2 preserved correctly
Vector of doc1 preserved correctly
