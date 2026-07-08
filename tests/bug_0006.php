<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0006_' . uniqid();

try {
    // Create schema and collection
    $schema = new ZVecSchema('test_bug6');
    $schema->addInt64('id');
    $schema->addString('name');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert some test docs
    for ($i = 0; $i < 5; $i++) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i);
        $doc->setString('name', "name_$i");
        $doc->setVectorFp32('vec', [1.0, 2.0, 3.0, 4.0]);
        $c->insert($doc);
    }
    echo "Insert OK\n";

    // =========================================================
    // Test 1: query() with outputFields and bad field name
    // =========================================================
    for ($i = 0; $i < 50; $i++) {
        try {
            $c->query('nonexistent_field', [1.0, 2.0, 3.0, 4.0],
                topk: 5, outputFields: ['id', 'name']);
            echo "FAIL: query() expected exception (iter $i)\n";
            exit(1);
        } catch (ZVecException $e) {
            // Expected — no leak
        }
    }
    echo "Test 1: query() with bad field + outputFields OK (50 iterations)\n";

    // =========================================================
    // Test 2: query() with outputFields in normal path
    // =========================================================
    $docs = $c->query('vec', [1.0, 2.0, 3.0, 4.0],
        topk: 5, outputFields: ['id', 'name']);
    if (count($docs) === 5) {
        echo "Test 2: query() outputFields OK (" . count($docs) . " docs)\n";
    } else {
        echo "FAIL: Expected 5 docs, got " . count($docs) . "\n";
        exit(1);
    }

    // =========================================================
    // Test 3: queryFp64() with outputFields and bad field name
    // =========================================================
    for ($i = 0; $i < 50; $i++) {
        try {
            $c->queryFp64('nonexistent_field', [1.0, 2.0, 3.0, 4.0],
                topk: 5, outputFields: ['id', 'name']);
            echo "FAIL: queryFp64() expected exception (iter $i)\n";
            exit(1);
        } catch (ZVecException $e) {
            // Expected — no leak
        }
    }
    echo "Test 3: queryFp64() with bad field + outputFields OK (50 iterations)\n";

    // =========================================================
    // Test 4: queryByFilter() with outputFields and bad filter
    // =========================================================
    for ($i = 0; $i < 50; $i++) {
        try {
            $c->queryByFilter('nonexistent_field > 0', topk: 5, outputFields: ['id']);
            echo "FAIL: queryByFilter() expected exception (iter $i)\n";
            exit(1);
        } catch (ZVecException $e) {
            // Expected — no leak
        }
    }
    echo "Test 4: queryByFilter() with bad filter + outputFields OK (50 iterations)\n";

    // =========================================================
    // Test 5: groupByQuery() with outputFields and bad field name
    // =========================================================
    for ($i = 0; $i < 50; $i++) {
        try {
            $c->groupByQuery('nonexistent_field', [1.0, 2.0, 3.0, 4.0],
                groupByField: 'name', groupCount: 2, groupTopk: 3,
                outputFields: ['id', 'name']);
            echo "FAIL: groupByQuery() expected exception (iter $i)\n";
            exit(1);
        } catch (ZVecException $e) {
            // Expected — no leak
        }
    }
    echo "Test 5: groupByQuery() with bad field + outputFields OK (50 iterations)\n";

    // =========================================================
    // Test 6: groupByQuery() with outputFields in normal path
    // =========================================================
    $groups = $c->groupByQuery('vec', [1.0, 2.0, 3.0, 4.0],
        groupByField: 'name', groupCount: 2, groupTopk: 3,
        outputFields: ['id']);
    if (count($groups) > 0) {
        echo "Test 6: groupByQuery() outputFields OK (" . count($groups) . " groups)\n";
    } else {
        echo "FAIL: Expected groups but got empty\n";
        exit(1);
    }

    // =========================================================
    // Test 7: queryByFilter() with outputFields in normal path
    // =========================================================
    $docs = $c->queryByFilter('id >= 0', topk: 5, outputFields: ['id', 'name']);
    if (count($docs) === 5) {
        echo "Test 7: queryByFilter() outputFields OK (" . count($docs) . " docs)\n";
    } else {
        echo "FAIL: Expected 5 docs, got " . count($docs) . "\n";
        exit(1);
    }

    // Close
    $c->close();
    echo "Close OK\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "All tests passed!\n";
?>
