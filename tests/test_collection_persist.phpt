--TEST--
Collection persistence: close/reopen without flush, explicit flush
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_collection_persist_' . uniqid();

$schema = new ZVecSchema('persist_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert docs
for ($i = 1; $i <= 100; $i++) {
    $doc = new ZVecDoc("doc_$i");
    $doc->setInt64('id', $i)
        ->setString('name', "Name_$i")
        ->setVectorFp32('embedding', [0.01 * $i, 0.02 * $i, 0.03 * $i, 0.04 * $i]);
    $c->insert($doc);
}
echo "Inserted 100 docs\n";

// Close without explicit flush
$c->close();
echo "Closed without flush\n";

// Reopen and verify data persisted
$c2 = ZVec::open($path, readOnly: false);
$fetched = $c2->fetch('doc_50');
assert(count($fetched) === 1, 'Should fetch doc_50');
assert($fetched[0]->getInt64('id') === 50, 'doc_50 should have id=50');
assert($fetched[0]->getString('name') === 'Name_50', 'doc_50 should have correct name');
echo "Data persisted after close/reopen OK\n";

// Insert more docs and flush this time
$doc = new ZVecDoc('doc_101');
$doc->setInt64('id', 101)
    ->setString('name', 'Name_101')
    ->setVectorFp32('embedding', [1.0, 1.0, 1.0, 1.0]);
$c2->insert($doc);

$c2->flush();
echo "Inserted doc_101 and flushed\n";

$c2->close();

// Reopen again and verify
$c3 = ZVec::open($path, readOnly: true);
$fetched = $c3->fetch('doc_101');
assert(count($fetched) === 1, 'Should fetch doc_101 after flush');
assert($fetched[0]->getInt64('id') === 101, 'doc_101 should have id=101');
echo "Flushed data persisted OK\n";

$c3->close();
exec("rm -rf " . escapeshellarg($path));

echo "PASS: Data persistence works\n";
?>
--EXPECT--
Inserted 100 docs
Closed without flush
Data persisted after close/reopen OK
Inserted doc_101 and flushed
Flushed data persisted OK
PASS: Data persistence works
