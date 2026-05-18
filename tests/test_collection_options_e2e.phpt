--TEST--
CollectionOptions E2E: createWith → insert → close → openWith read-only → query → verify read-only rejection
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/col_opts_e2e_' . uniqid();
try {
    $schema = new ZVecSchema('e2e_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    // Create with explicit options
    $opts = new ZVecCollectionOptions(readOnly: false, enableMmap: true, maxBufferSize: 128 * 1024 * 1024);
    $c = ZVec::createWith($path, $schema, $opts);

    // Verify options via getOptions
    $ro = $c->getOptions();
    assert($ro->getReadOnly() === false, 'Should be read-write');
    assert($ro->getEnableMmap() === true, 'Mmap should be enabled');
    assert($ro->getMaxBufferSize() === 128 * 1024 * 1024, 'Buffer size should be 128MB');
    echo "Options verified\n";

    // Insert documents
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc1);

    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)->setVectorFp32('embedding', [0.5, 0.6, 0.7, 0.8]);
    $c->insert($doc2);

    $c->flush();
    echo "Inserted 2 docs\n";

    $c->close();

    // Reopen read-only
    $opts2 = new ZVecCollectionOptions(readOnly: true, enableMmap: true);
    $c2 = ZVec::openWith($path, $opts2);
    $ro2 = $c2->getOptions();
    assert($ro2->getReadOnly() === true, 'Reopened should be read-only');
    echo "Reopened read-only\n";

    // Query should work
    $results = $c2->query('embedding', [0.1, 0.2, 0.3, 0.4], topk: 10);
    assert(count($results) > 0, 'Query should return results');
    echo "Query returned " . count($results) . " results\n";

    // Insert should fail on read-only
    $doc3 = new ZVecDoc('doc3');
    $doc3->setInt64('id', 3)->setVectorFp32('embedding', [0.9, 0.8, 0.7, 0.6]);
    try {
        $c2->insert($doc3);
        echo "FAIL: Should not allow writes to read-only\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Read-only correctly blocks insert\n";
    }

    $c2->close();

    // Verify data persists by reopening read-write
    $opts3 = new ZVecCollectionOptions(readOnly: false, enableMmap: true);
    $c3 = ZVec::openWith($path, $opts3);
    $results2 = $c3->query('embedding', [0.1, 0.2, 0.3, 0.4], topk: 10);
    assert(count($results2) > 0, 'Data should persist');
    echo "Data persists after reopen\n";

    $c3->destroy();

    // Test invalid path with openWith
    $invalidPath = '/nonexistent/path';
    try {
        $badOpts = new ZVecCollectionOptions();
        ZVec::openWith($invalidPath, $badOpts);
        echo "FAIL: Should fail for invalid path\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Invalid path correctly rejected\n";
    }

    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Options verified
Inserted 2 docs
Reopened read-only
Query returned 2 results
Read-only correctly blocks insert
Data persists after reopen
Invalid path correctly rejected
PASS
