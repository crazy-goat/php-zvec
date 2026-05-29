--TEST--
Column ops: alterColumn nullable false→true and nullable true→false limitation
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/alter_nullable_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addInt64('value', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setInt64('value', 42)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    // Test 1: Change nullable true→false on already-nullable column — should fail
    try {
        $c->alterColumn('value', nullable: false);
        echo "UNEXPECTED: nullable true→false succeeded\n";
    } catch (ZVecException $e) {
        echo "Correctly rejected nullable true→false: " . $e->getMessage() . "\n";
    }

    // Test 2: Change nullable false→true on non-nullable column — may fail at C++ level
    try {
        $c->alterColumn('id', nullable: true);
        echo "Changed nullable false→true on 'id' OK\n";

        // Insert doc with null id — verify nullable works
        $doc2 = new ZVecDoc('doc2');
        $doc2->setFieldNull('id')
            ->setInt64('value', 99)
            ->setVectorFp32('v', [0.2, 0.3, 0.4, 0.5]);
        $c->insert($doc2);
        $c->optimize();
        echo "Inserted doc with null id OK\n";
    } catch (ZVecException $e) {
        echo "nullable false→true rejected at C++ level: " . $e->getMessage() . "\n";
    }

    // Test 3: Verify original column is still readable
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    assert($fetched[0]->getInt64('value') === 42, 'Expected value=42 unchanged');
    echo "Original column unchanged OK\n";

    $c->close();
    echo "PASS: alterColumn nullable operations verified\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Correctly rejected nullable true→false: %s
nullable false→true rejected at C++ level: %s
Original column unchanged OK
PASS: alterColumn nullable operations verified
