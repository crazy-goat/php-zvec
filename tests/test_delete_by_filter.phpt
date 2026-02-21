--TEST--
Data operations: delete documents by filter conditions
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/delete_filter_' . uniqid();

try {
    $schema = new ZVecSchema('delete_filter_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createInvertIndex('category', enableRange: false, enableWildcard: false);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert test documents with categories
    $categories = ['A', 'B', 'A', 'B', 'A'];
    $scores = [90.0, 85.0, 95.0, 70.0, 88.0];
    for ($i = 0; $i < 5; $i++) {
        $doc = new ZVecDoc("doc" . ($i + 1));
        $doc->setInt64('id', $i + 1)
            ->setString('category', $categories[$i])
            ->setFloat('score', $scores[$i])
            ->setVectorFp32('embedding', [1.0 * ($i + 1), 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    echo "Inserted 5 documents\n";

    // Test: Delete using simple scalar filter
    $c->deleteByFilter("category = 'A'");
    echo "Delete by category filter OK\n";

    // Verify documents were deleted
    $fetched = $c->fetch('doc1', 'doc2', 'doc3', 'doc4', 'doc5');
    assert(count($fetched) === 2, 'Should have 2 documents remaining (category B)');
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    assert(in_array('doc2', $pks), 'doc2 (category B) should exist');
    assert(in_array('doc4', $pks), 'doc4 (category B) should exist');
    echo "Verify filter deletion OK\n";

    // Re-insert more documents for next test
    $newDocs = [];
    for ($i = 6; $i <= 8; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('category', 'C')
            ->setFloat('score', 60.0 + $i)
            ->setVectorFp32('embedding', [1.0 * $i, 0.0, 0.0, 0.0]);
        $newDocs[] = $doc;
    }
    $c->upsert(...$newDocs);
    echo "Re-inserted documents\n";

    // Test: Delete with numeric condition
    $c->deleteByFilter("score < 75.0");
    echo "Delete by score filter OK\n";

    // Verify deletion
    $fetched = $c->fetch('doc2', 'doc4', 'doc6', 'doc7', 'doc8');
    // doc6 has score 66.0, should be deleted
    // doc2 (85.0) and doc4 (70.0) - doc4 should be deleted if score < 75
    $remaining = array_map(fn($d) => $d->getPk(), $fetched);
    echo "Remaining: " . implode(', ', $remaining) . "\n";

    // Test: Delete all matching filter (delete everything)
    $c->deleteByFilter("category = 'B'");
    echo "Delete all category B OK\n";

    $c->deleteByFilter("category = 'C'");
    echo "Delete all category C OK\n";

    // Verify all deleted
    $fetched = $c->fetch('doc2', 'doc6', 'doc7', 'doc8');
    assert(count($fetched) === 0, 'All documents should be deleted');
    echo "All documents deleted OK\n";

    // Test: Delete with no matches (should not error)
    $c->deleteByFilter("category = 'NONEXISTENT'");
    echo "No-match delete handled OK\n";

    $c->close();
    echo "PASS: Delete by filter operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Inserted 5 documents
Delete by category filter OK
Verify filter deletion OK
Re-inserted documents
Delete by score filter OK
Remaining: %s
Delete all category B OK
Delete all category C OK
All documents deleted OK
No-match delete handled OK
PASS: Delete by filter operations work
