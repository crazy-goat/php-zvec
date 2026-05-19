--TEST--
SMELL-004: Extracted writeDocs/writeDocsBatch helpers — all 6 operations work correctly
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/write_helpers_' . uniqid();

try {
    // ===== Create collection =====
    $schema = new ZVecSchema('write_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // ===== 1. insert() =====
    $docInsert = new ZVecDoc('insert_doc');
    $docInsert->setInt64('id', 1)
        ->setString('name', 'Inserted')
        ->setFloat('score', 10.0)
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($docInsert);
    echo "insert() OK\n";

    // ===== 2. upsert() — new doc =====
    $docUpsertNew = new ZVecDoc('upsert_new');
    $docUpsertNew->setInt64('id', 2)
        ->setString('name', 'Upsert New')
        ->setFloat('score', 20.0)
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    $c->upsert($docUpsertNew);
    echo "upsert() new doc OK\n";

    // ===== 3. upsert() — existing doc (update) =====
    $docUpsertExisting = new ZVecDoc('insert_doc');
    $docUpsertExisting->setInt64('id', 1)
        ->setFloat('score', 15.0)
        ->setString('name', 'Inserted Updated')
        ->setVectorFp32('embedding', [0.9, 0.1, 0.0, 0.0]);
    $c->upsert($docUpsertExisting);
    echo "upsert() existing doc OK\n";

    // ===== 4. update() =====
    $docUpdate = new ZVecDoc('insert_doc');
    $docUpdate->setInt64('id', 1)
        ->setFloat('score', 99.0);
    $c->update($docUpdate);
    echo "update() OK\n";

    // ===== Verify single-doc operations =====
    $fetched = $c->fetch('insert_doc', 'upsert_new');
    $docsByPk = [];
    foreach ($fetched as $d) {
        $docsByPk[$d->getPk()] = $d;
    }
    assert(isset($docsByPk['insert_doc']), 'insert_doc should exist');
    assert($docsByPk['insert_doc']->getFloat('score') === 99.0, 'insert_doc score should be 99.0 (updated)');
    assert($docsByPk['insert_doc']->getString('name') === 'Inserted Updated', 'insert_doc name should be "Inserted Updated" (upserted)');
    assert(isset($docsByPk['upsert_new']), 'upsert_new should exist');
    assert($docsByPk['upsert_new']->getFloat('score') === 20.0, 'upsert_new score should be 20.0');
    echo "Single-doc verify OK\n";

    // ===== 5. insertBatch() =====
    $b1 = new ZVecDoc('batch1');
    $b1->setInt64('id', 10)->setString('name', 'Batch1')->setFloat('score', 100.0)
        ->setVectorFp32('embedding', [0.1, 0.0, 0.0, 0.0]);
    $b2 = new ZVecDoc('batch2');
    $b2->setInt64('id', 11)->setString('name', 'Batch2')->setFloat('score', 200.0)
        ->setVectorFp32('embedding', [0.0, 0.1, 0.0, 0.0]);
    $results = $c->insertBatch($b1, $b2);
    assert(count($results) === 2, 'insertBatch should return 2 results');
    assert($results[0]['ok'] === true, 'batch1 insert should succeed');
    assert($results[1]['ok'] === true, 'batch2 insert should succeed');
    echo "insertBatch() OK\n";

    // ===== 6. upsertBatch() =====
    $ub1 = new ZVecDoc('batch1');
    $ub1->setInt64('id', 10)->setFloat('score', 150.0)->setVectorFp32('embedding', [0.1, 0.0, 0.0, 0.0]);
    $ub2 = new ZVecDoc('batch3');
    $ub2->setInt64('id', 12)->setString('name', 'Batch3')->setFloat('score', 300.0)
        ->setVectorFp32('embedding', [0.0, 0.0, 0.1, 0.0]);
    $results = $c->upsertBatch($ub1, $ub2);
    assert(count($results) === 2, 'upsertBatch should return 2 results');
    assert($results[0]['pk'] === 'batch1', 'first result pk should be batch1');
    assert($results[1]['pk'] === 'batch3', 'second result pk should be batch3');
    echo "upsertBatch() OK\n";

    // ===== 7. updateBatch() =====
    $up1 = new ZVecDoc('batch1');
    $up1->setInt64('id', 10)->setFloat('score', 175.0);
    $up2 = new ZVecDoc('batch2');
    $up2->setInt64('id', 11)->setFloat('score', 250.0);
    $results = $c->updateBatch($up1, $up2);
    assert(count($results) === 2, 'updateBatch should return 2 results');
    assert($results[0]['ok'] === true, 'batch1 update should succeed');
    assert($results[1]['ok'] === true, 'batch2 update should succeed');
    echo "updateBatch() OK\n";

    // ===== Verify batch operations =====
    $fetched = $c->fetch('batch1', 'batch2', 'batch3');
    $docs = [];
    foreach ($fetched as $d) {
        $docs[$d->getPk()] = $d;
    }
    assert($docs['batch1']->getFloat('score') === 175.0, 'batch1 score should be 175.0 (updated)');
    assert($docs['batch2']->getFloat('score') === 250.0, 'batch2 score should be 250.0 (updated)');
    assert($docs['batch3']->getFloat('score') === 300.0, 'batch3 score should be 300.0 (upserted)');
    echo "Batch verify OK\n";

    // ===== 8. insertBatch with duplicate (error path) =====
    $dup = new ZVecDoc('batch1');
    $dup->setInt64('id', 10)->setFloat('score', 999.0)
        ->setVectorFp32('embedding', [0.5, 0.5, 0.5, 0.5]);
    $results = $c->insertBatch($dup);
    assert($results[0]['ok'] === false, 'duplicate insert should fail');
    assert($results[0]['error'] !== null, 'duplicate should have error message');
    echo "insertBatch() duplicate error OK\n";

    // ===== 9. updateBatch with non-existent doc (error path) =====
    $nonExistent = new ZVecDoc('does_not_exist');
    $nonExistent->setInt64('id', 999)->setFloat('score', 0.0);
    $results = $c->updateBatch($nonExistent);
    assert($results[0]['ok'] === false, 'update non-existent should fail');
    echo "updateBatch() non-existent error OK\n";

    $c->close();

    echo "\nPASS: All write helpers pass\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
insert() OK
upsert() new doc OK
upsert() existing doc OK
update() OK
Single-doc verify OK
insertBatch() OK
upsertBatch() OK
updateBatch() OK
Batch verify OK
insertBatch() duplicate error OK
updateBatch() non-existent error OK

PASS: All write helpers pass
