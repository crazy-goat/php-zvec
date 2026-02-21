--TEST--
Filter edge cases: empty filters, operators, case sensitivity
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_filter_edge_' . uniqid();

try {
    $schema = new ZVecSchema('filter_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addInt64('score', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test data
    $names = ['Alice', 'Bob', 'Charlie', 'alice', 'BOB'];
    foreach ($names as $i => $name) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setString('name', $name)
            ->setInt64('score', ($i + 1) * 10)
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }

    // Test 1: Basic filter operations
    $results = $c->queryByFilter("id = 0", topk: 10);
    if (count($results) !== 1) {
        echo "FAIL: Should find doc with id=0\n";
        exit(1);
    }
    echo "id = 0 filter OK\n";

    $results = $c->queryByFilter("id > 2", topk: 10);
    if (count($results) !== 2) {
        echo "FAIL: Should find 2 docs with id>2\n";
        exit(1);
    }
    echo "id > 2 filter OK\n";

    $results = $c->queryByFilter("id > 0 AND id < 3", topk: 10);
    if (count($results) !== 2) {
        echo "FAIL: Should find 2 docs with 0<id<3\n";
        exit(1);
    }
    echo "AND condition filter OK\n";

    $results = $c->queryByFilter("name = 'Alice'", topk: 10);
    if (count($results) !== 1) {
        echo "FAIL: Should find Alice\n";
        exit(1);
    }
    echo "String equality filter OK\n";

    // Test 2: Empty filter
    $results = $c->queryByFilter("", topk: 10);
    if (count($results) !== 5) {
        echo "FAIL: Empty filter should return all docs\n";
        exit(1);
    }
    echo "Empty filter returns all docs OK\n";

    // Test 3: Case sensitivity
    $results = $c->queryByFilter("name = 'Alice'", topk: 10);
    if (count($results) !== 1 || $results[0]->getString('name') !== 'Alice') {
        echo "FAIL: Should find Alice (case-sensitive)\n";
        exit(1);
    }
    echo "Case-sensitive Alice search OK\n";

    $results = $c->queryByFilter("name = 'alice'", topk: 10);
    if (count($results) !== 1 || $results[0]->getString('name') !== 'alice') {
        echo "FAIL: Should find alice (lowercase)\n";
        exit(1);
    }
    echo "Case-sensitive alice search OK\n";

    $results = $c->queryByFilter("name = 'BOB'", topk: 10);
    if (count($results) !== 1 || $results[0]->getString('name') !== 'BOB') {
        echo "FAIL: Should find BOB (uppercase)\n";
        exit(1);
    }
    echo "Case-sensitive BOB search OK\n";

    // Test 4: IN operator
    $doc = new ZVecDoc('doc_special');
    $doc->setInt64('id', 100)
        ->setString('name', "O'Brien")
        ->setInt64('score', 100)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    $results = $c->queryByFilter("id IN (0, 1, 2)", topk: 10);
    if (count($results) !== 3) {
        echo "FAIL: IN operator should work\n";
        exit(1);
    }
    echo "IN operator OK\n";

    // Test 5: Vector query with filter
    $results = $c->query('v', [0.0, 0.0, 0.0, 0.0], topk: 3, filter: "id < 3");
    if (count($results) !== 3) {
        echo "FAIL: Should find 3 docs with vector + scalar filter\n";
        exit(1);
    }
    echo "Vector query with scalar filter OK\n";

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "Filter operations work correctly\n";
?>
--EXPECT--
id = 0 filter OK
id > 2 filter OK
AND condition filter OK
String equality filter OK
Empty filter returns all docs OK
Case-sensitive Alice search OK
Case-sensitive alice search OK
Case-sensitive BOB search OK
IN operator OK
Vector query with scalar filter OK
Filter operations work correctly
