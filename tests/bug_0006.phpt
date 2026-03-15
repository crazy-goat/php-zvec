--TEST--
Bug 0006: RocksDB lock error when recreating collection after failed insert (FIXED)
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/rocksdb_lock_' . uniqid();

try {
    // Create schema with required field
    $schema = new ZVecSchema('lock_test');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('vec', dimension: 4);

    // First attempt: create and fail insert
    $c1 = ZVec::create($path, $schema);
    $doc = new ZVecDoc('doc1');
    $doc->setVectorFp32('vec', [1.0, 2.0, 3.0, 4.0]);
    // Missing required 'id' field

    try {
        $c1->insert($doc);
        echo "FAIL: Should have thrown exception\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "First insert failed as expected\n";
    }

    // Close and cleanup
    $c1->close();
    exec("rm -rf " . escapeshellarg($path));

    // Second attempt: try to create at same path
    sleep(1);  // Give OS time to release locks

    $c2 = ZVec::create($path, $schema);
    echo "Recreated collection at same path\n";

    // Insert valid doc
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2);
    $doc2->setVectorFp32('vec', [5.0, 6.0, 7.0, 8.0]);
    $c2->insert($doc2);
    echo "Inserted valid doc\n";

    $c2->destroy();
    echo "Destroyed collection\n";

    echo "PASS: bug_0006 - No RocksDB lock issues\n";
    
} catch (ZVecException $e) {
    echo "FAIL: RocksDB lock error: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
First insert failed as expected
Recreated collection at same path
Inserted valid doc
Destroyed collection
PASS: bug_0006 - No RocksDB lock issues
