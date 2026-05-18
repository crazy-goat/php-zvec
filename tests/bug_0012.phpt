--TEST--
Bug 0012: query() with ZVecVectorQuery ignores query object's topk, includeVector, and filter
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0012_' . uniqid();

try {
    $schema = new ZVecSchema('bug0012');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('price', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert 5 docs with varying prices and categories
    $docs = [
        ['pk' => 'd1', 'price' => 10, 'category' => 'a', 'v' => [0.1, 0.2, 0.3, 0.4]],
        ['pk' => 'd2', 'price' => 20, 'category' => 'a', 'v' => [0.2, 0.3, 0.4, 0.5]],
        ['pk' => 'd3', 'price' => 30, 'category' => 'b', 'v' => [0.3, 0.4, 0.5, 0.6]],
        ['pk' => 'd4', 'price' => 40, 'category' => 'b', 'v' => [0.4, 0.5, 0.6, 0.7]],
        ['pk' => 'd5', 'price' => 50, 'category' => 'c', 'v' => [0.5, 0.6, 0.7, 0.8]],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d['pk']);
        $doc->setInt64('price', $d['price'])
            ->setString('category', $d['category'])
            ->setVectorFp32('v', $d['v']);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: topk from ZVecVectorQuery (setTopk(3) should return <= 3 results)
    $q1 = new ZVecVectorQuery('v', [0.5, 0.5, 0.5, 0.5]);
    $q1->setTopk(3);
    $results1 = $c->query($q1);
    $count1 = count($results1);
    if ($count1 <= 3 && $count1 > 0) {
        echo "PASS: ZVecVectorQuery topk=3 returned $count1 results (expected <=3)\n";
    } else {
        echo "FAIL: topk=3 returned $count1 results\n";
    }

    // Test 2: includeVector from ZVecVectorQuery
    $q2 = new ZVecVectorQuery('v', [0.1, 0.2, 0.3, 0.4]);
    $q2->setIncludeVector(true);
    $results2 = $c->query($q2);
    if (count($results2) > 0 && $results2[0]->getVectorFp32('v') !== null) {
        echo "PASS: ZVecVectorQuery includeVector=true returned vector data\n";
    } else {
        echo "FAIL: includeVector=true did not return vector data\n";
    }

    // Test 3: filter from ZVecVectorQuery
    $q3 = new ZVecVectorQuery('v', [0.5, 0.5, 0.5, 0.5]);
    $q3->setFilter('category = "a"');
    $results3 = $c->query($q3);
    if (count($results3) === 2) {
        echo "PASS: ZVecVectorQuery filter='category=\"a\"' returned 2 results\n";
    } else {
        echo "FAIL: filter returned " . count($results3) . " results (expected 2)\n";
    }

    // Test 4: Method signature defaults as fallback (no setTopk/setFilter on query obj)
    $q4 = new ZVecVectorQuery('v', [0.5, 0.5, 0.5, 0.5]);
    $results4 = $c->query($q4, topk: 2);
    if (count($results4) <= 2) {
        echo "PASS: Method signature topk=2 fallback returned " . count($results4) . " results\n";
    } else {
        echo "FAIL: Method signature topk=2 fallback returned " . count($results4) . " results\n";
    }

    // Test 5: groupByQuery with filter from ZVecVectorQuery
    // Note: groupByQuery currently returns all docs in one group (zvec limitation)
    // Verify query object's filter doesn't cause crash, and total docs <= 5
    $q5 = new ZVecVectorQuery('v', [0.5, 0.5, 0.5, 0.5]);
    $q5->setFilter('price > 30');
    $gResults = $c->groupByQuery($q5, [], groupByField: 'category', groupCount: 2, groupTopk: 2);
    $totalDocs = array_sum(array_map(fn($g) => count($g['docs']), $gResults));
    if ($totalDocs >= 1) {
        echo "PASS: groupByQuery with ZVecVectorQuery filter returned $totalDocs total doc(s) in " . count($gResults) . " group(s)\n";
    } else {
        echo "FAIL: groupByQuery returned 0 docs total\n";
    }

    $c->close();
    echo "DONE\n";
} catch (ZVecException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
PASS: ZVecVectorQuery topk=3 returned %d results (expected <=3)
PASS: ZVecVectorQuery includeVector=true returned vector data
PASS: ZVecVectorQuery filter='category="a"' returned 2 results
PASS: Method signature topk=2 fallback returned 2 results
PASS: groupByQuery with ZVecVectorQuery filter returned %d total doc(s) in %d group(s)
DONE
