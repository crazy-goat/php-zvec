--TEST--
GroupBy query with ZVecGroupByVectorQuery object (no UB from wrong FFI calls)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/groupby_obj_' . uniqid();

try {
    $schema = new ZVecSchema('gb_obj_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addString('category', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $docs = [
        ['pk' => 'a1', 'cat' => 'electronics', 'name' => 'Phone',  'vec' => [0.1, 0.2, 0.3, 0.4]],
        ['pk' => 'a2', 'cat' => 'electronics', 'name' => 'Laptop', 'vec' => [0.2, 0.3, 0.4, 0.5]],
        ['pk' => 'b1', 'cat' => 'books',       'name' => 'Novel',  'vec' => [0.5, 0.5, 0.5, 0.5]],
        ['pk' => 'b2', 'cat' => 'books',       'name' => 'Manual', 'vec' => [0.6, 0.6, 0.6, 0.6]],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d['pk']);
        $doc->setString('category', $d['cat'])
            ->setString('name', $d['name'])
            ->setVectorFp32('v', $d['vec']);
        $c->insert($doc);
    }
    $c->optimize();
    echo "Insert OK\n";

    // Test 1: groupByQuery() convenience method (no crash/UB)
    $groups = $c->groupByQuery('v', [0.5, 0.5, 0.5, 0.5], groupByField: 'category', groupCount: 2, groupTopk: 3);
    echo "groupByQuery returns " . count($groups) . " groups\n";

    // Test 2: groupByVectorQuery with ZVecGroupByVectorQuery object
    $q = new ZVecGroupByVectorQuery('v', [0.5, 0.5, 0.5, 0.5], 'category', groupCount: 2, groupTopk: 3);
    $q->setFilter("name != ''");
    $q->setRadius(100.0);
    $q->setIncludeVector(true);
    $q->setOutputFields(['name', 'category']);

    $groups2 = $c->groupByVectorQuery($q);
    echo "groupByVectorQuery returns " . count($groups2) . " groups\n";

    // Test 3: ZVecGroupByVectorQuery with group-specific setters
    $q2 = new ZVecGroupByVectorQuery('v', [0.5, 0.5, 0.5, 0.5], 'category');
    $q2->setGroupCount(5);
    $q2->setGroupTopk(10);
    $q2->setGroupByField('category');
    $groups3 = $c->groupByVectorQuery($q2);
    echo "Group-specific setters work: " . count($groups3) . " groups\n";

    $c->close();
    echo "PASS: GroupBy query object tests complete (no UB)\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Insert OK
groupByQuery returns %d groups
groupByVectorQuery returns %d groups
Group-specific setters work: %d groups
PASS: GroupBy query object tests complete (no UB)
