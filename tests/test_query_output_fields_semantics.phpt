--TEST--
Query output_fields optional semantics (zvec v0.6.0): nullopt = all fields, empty array = all fields
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
/**
 * Regression test for the v0.6.0 output_fields_ semantics change (#172).
 *
 * Upstream v0.6.0 made SearchQuery::output_fields_ a std::optional:
 *   - nullopt        -> SELECT * (all fields)
 *   - empty vector   -> select NO fields
 *   - non-empty      -> select listed fields
 *
 * Our binding deliberately never materializes an empty vector:
 *   - outputFields omitted (null) -> all fields
 *   - outputFields: []            -> all fields (NOT upstream's "no fields";
 *                                    passing an explicit empty selection is
 *                                    not supported by the PHP API)
 */
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/of_semantics_' . uniqid();
try {
    $schema = new ZVecSchema('of_semantics');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: false, withInvertIndex: true)
        ->addString('title', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    foreach ([['d1', 1, 'a', 'First'], ['d2', 2, 'a', 'Second'], ['d3', 3, 'b', 'Third']] as [$pk, $id, $cat, $title]) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', $id)
            ->setString('category', $cat)
            ->setString('title', $title)
            ->setVectorFp32('v', [0.1 * $id, 0.2, 0.3, 0.4]);
        $c->insert($doc);
    }
    $c->optimize();

    // 1. query() without outputFields must return ALL fields (would return
    //    zero fields if the FFI layer wrongly set an empty vector)
    $r = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 1);
    assert($r[0]->getInt64('id') !== null, 'id must be present without outputFields');
    assert($r[0]->getString('category') !== null, 'category must be present without outputFields');
    assert($r[0]->getString('title') !== null, 'title must be present without outputFields');
    echo "query without outputFields returns all fields\n";

    // 2. queryByFilter() without outputFields must return ALL fields
    $r = $c->queryByFilter('id = 2', topk: 1);
    assert($r[0]->getInt64('id') === 2, 'id must be present');
    assert($r[0]->getString('title') === 'Second', 'title must be present');
    echo "queryByFilter without outputFields returns all fields\n";

    // 3. groupByQuery() without outputFields must return ALL fields
    $groups = $c->groupByQuery('v', [0.1, 0.2, 0.3, 0.4], groupByField: 'category', groupCount: 2, groupTopk: 2);
    $doc = $groups[0]['docs'][0];
    assert($doc->getInt64('id') !== null, 'group doc id must be present');
    assert($doc->getString('title') !== null, 'group doc title must be present');
    echo "groupByQuery without outputFields returns all fields\n";

    // 4. Explicit empty array is treated as "no selection" (nullopt), i.e.
    //    ALL fields — NOT upstream's empty-vector "no fields" semantics
    $r = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 1, outputFields: []);
    assert($r[0]->getInt64('id') !== null, 'empty outputFields array must behave like null (all fields)');
    echo "empty outputFields array returns all fields (documented deviation)\n";

    // 5. Sanity: explicit non-empty selection still restricts fields
    $r = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 1, outputFields: ['id']);
    assert($r[0]->getInt64('id') !== null, 'selected field must be present');
    assert($r[0]->getString('title') === null, 'unselected field must be null');
    echo "explicit outputFields selection still restricts fields\n";

    $c->close();
    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
query without outputFields returns all fields
queryByFilter without outputFields returns all fields
groupByQuery without outputFields returns all fields
empty outputFields array returns all fields (documented deviation)
explicit outputFields selection still restricts fields
PASS
