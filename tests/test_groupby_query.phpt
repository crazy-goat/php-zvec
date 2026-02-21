--TEST--
Query operations: GroupBy query (API verification - feature Coming Soon)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_groupby_' . uniqid();

try {
    $schema = new ZVecSchema('groupby_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addString('tag', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert documents with different categories
    $categories = ['tech', 'finance', 'health', 'tech', 'finance'];
    $tags = ['news', 'blog', 'article', 'news', 'report'];
    
    for ($i = 0; $i < 5; $i++) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setString('category', $categories[$i])
            ->setString('tag', $tags[$i])
            ->setFloat('score', 80.0 + $i * 3)
            ->setVectorFp32('embedding', [1.0, $i * 0.1, 0.0, 0.0]);
        $c->insert($doc);
    }
    echo "Inserted 5 documents\n";

    $c->optimize();
    echo "Optimized\n";

    // Test 1: Verify regular query still works (API is not broken)
    // Note: GroupBy is marked as "Coming Soon" in zvec docs
    // We verify that the query API works without groupby params
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 5);
    assert(count($results) === 5, 'Regular query should work');
    echo "Regular query API OK\n";

    // Test 2: Verify queryByFilter still works
    $results = $c->queryByFilter("category = 'tech'", topk: 10);
    // We expect 2 tech docs
    assert(count($results) === 2, 'Should find 2 tech documents');
    echo "Filter query OK\n";

    // Test 3: Test that query accepts groupByField parameter (if implemented)
    // This test documents the expected API for future implementation
    // Currently, the PHP API doesn't have groupby parameters yet
    // When implemented, it should look like:
    // $results = $c->query(
    //     'embedding', 
    //     [1.0, 0.0, 0.0, 0.0], 
    //     topk: 10,
    //     groupByField: 'category',
    //     groupCount: 3,
    //     groupTopk: 2
    // );
    
    echo "Note: GroupBy is marked 'Coming Soon' in zvec documentation\n";
    echo "When implemented, expected API:\n";
    echo "  - groupByField: field to group results by\n";
    echo "  - groupCount: number of groups to return\n";
    echo "  - groupTopk: top results per group\n";

    // Test 4: Verify we can manually implement grouping client-side
    // Fetch all results and group them manually
    $allResults = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 5);
    
    $grouped = [];
    foreach ($allResults as $doc) {
        $category = $doc->getString('category');
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $doc;
    }
    
    // Verify manual grouping works
    assert(isset($grouped['tech']), 'Should have tech group');
    assert(isset($grouped['finance']), 'Should have finance group');
    assert(isset($grouped['health']), 'Should have health group');
    assert(count($grouped['tech']) === 2, 'Tech should have 2 docs');
    assert(count($grouped['finance']) === 2, 'Finance should have 2 docs');
    assert(count($grouped['health']) === 1, 'Health should have 1 doc');
    echo "Manual grouping (client-side) works correctly\n";

    // Test 5: Test filtering by category as a workaround
    $categories = ['tech', 'finance', 'health'];
    foreach ($categories as $cat) {
        $catResults = $c->queryByFilter("category = '$cat'", topk: 10);
        echo "  Category '$cat': " . count($catResults) . " documents\n";
    }
    echo "Per-category queries work\n";

    $c->close();
    echo "PASS: GroupBy API ready for future implementation\n";
    echo "Note: Native GroupBy support is Coming Soon in zvec\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 5 documents
Optimized
Regular query API OK
Filter query OK
Note: GroupBy is marked 'Coming Soon' in zvec documentation
When implemented, expected API:
  - groupByField: field to group results by
  - groupCount: number of groups to return
  - groupTopk: top results per group
Manual grouping (client-side) works correctly
  Category 'tech': 2 documents
  Category 'finance': 2 documents
  Category 'health': 1 documents
Per-category queries work
PASS: GroupBy API ready for future implementation
Note: Native GroupBy support is Coming Soon in zvec
