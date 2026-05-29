--TEST--
SMELL-006: Extract toCStringArray/freeCStringArray helpers — all 9 call sites work correctly
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/c_string_array_helpers_' . uniqid();

try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addArrayString('tags', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert test docs
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)->setString('name', 'Alice')->setArrayString('tags', ['php', 'ffi'])
        ->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $c->insert($doc1);

    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)->setString('name', 'Bob')->setArrayString('tags', ['zvec', 'vector'])
        ->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0]);
    $c->insert($doc2);

    $doc3 = new ZVecDoc('doc3');
    $doc3->setInt64('id', 3)->setString('name', 'Charlie')->setArrayString('tags', ['db', 'search'])
        ->setVectorFp32('embedding', [0.0, 0.0, 1.0, 0.0]);
    $c->insert($doc3);

    $c->optimize();
    echo "Setup OK\n";

    // ===== 1. delete() — uses toCStringArray =====
    $c->delete('doc3');
    echo "delete() OK\n";

    // ===== 2. fetch() — uses toCStringArray + NEW try-finally =====
    $fetched = $c->fetch('doc1', 'doc2');
    assert(count($fetched) === 2, 'fetch should return 2 docs');
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    assert(in_array('doc1', $pks), 'fetch should contain doc1');
    assert(in_array('doc2', $pks), 'fetch should contain doc2');
    echo "fetch() OK\n";

    // ===== 3. query() with outputFields — uses toCStringArray =====
    $queryVec = new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]);
    $queryVec->setTopk(10)
        ->setOutputFields(['name', 'tags']);
    $results = $c->query($queryVec);
    assert(count($results) >= 2, 'query with outputFields should return results');
    echo "query() with outputFields OK\n";

    // ===== 4. queryByFilter() with outputFields — uses toCStringArray =====
    $filtered = $c->queryByFilter('name = "Alice"', topk: 10, outputFields: ['name', 'id']);
    assert(count($filtered) >= 1, 'queryByFilter with outputFields should return results');
    echo "queryByFilter() with outputFields OK\n";

    // ===== 5. setArrayString() — uses toCStringArray + NEW try-finally =====
    $doc4 = new ZVecDoc('doc4');
    $doc4->setInt64('id', 4)->setString('name', 'Diana')
        ->setArrayString('tags', ['a', 'b', 'c', 'd', 'e'])
        ->setVectorFp32('embedding', [0.5, 0.5, 0.0, 0.0]);
    $c->insert($doc4);

    $fetched4 = $c->fetch('doc4');
    assert(count($fetched4) === 1, 'doc4 should exist');
    $tags = $fetched4[0]->getArrayString('tags');
    assert($tags === ['a', 'b', 'c', 'd', 'e'], 'setArrayString should preserve all values');
    echo "setArrayString() OK\n";

    // ===== 6. setOutputFields on ZVecVectorQuery — uses toCStringArray + NEW try-finally =====
    $vq = new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]);
    $vq->setTopk(5)
        ->setOutputFields(['name']);
    $vqResults = $c->query($vq);
    assert(count($vqResults) >= 1, 'vector query with setOutputFields should work');
    echo "ZVecVectorQuery::setOutputFields() OK\n";

    // ===== 7. setOutputFields on ZVecGroupByVectorQuery — uses toCStringArray + NEW try-finally =====
    $gbq = new ZVecGroupByVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0], 'name', 3, 2);
    $gbq->setOutputFields(['name', 'tags']);
    $gbResults = $c->groupByVectorQuery($gbq);
    assert(count($gbResults) >= 1, 'group by vector query should return groups');
    echo "ZVecGroupByVectorQuery::setOutputFields() OK\n";

    // ===== 8. Special characters =====
    $doc5 = new ZVecDoc('doc5-special');
    $doc5->setInt64('id', 5)
        ->setString('name', "Line1\nLine2\tTab")
        ->setArrayString('tags', ["with space", "with\"quote", ""])
        ->setVectorFp32('embedding', [0.3, 0.3, 0.3, 0.1]);
    $c->insert($doc5);

    $fetched5 = $c->fetch('doc5-special');
    assert(count($fetched5) === 1, 'special chars doc should exist');
    assert($fetched5[0]->getString('name') === "Line1\nLine2\tTab", 'special chars in string field');
    $specialTags = $fetched5[0]->getArrayString('tags');
    assert($specialTags === ["with space", "with\"quote", ""], 'special chars in array string field');
    echo "Special characters OK\n";

    $c->close();

    echo "\nPASS: All C string array helper tests pass\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Setup OK
delete() OK
fetch() OK
query() with outputFields OK
queryByFilter() with outputFields OK
setArrayString() OK
ZVecVectorQuery::setOutputFields() OK
ZVecGroupByVectorQuery::setOutputFields() OK
Special characters OK

PASS: All C string array helper tests pass
