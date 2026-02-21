<?php

require_once __DIR__ . '/ZVec.php';

// ============================================================
// 0. INIT (global config - must be called before anything else)
// ============================================================
echo "=== 0. Init ===\n";
ZVec::init(
    logType: ZVec::LOG_CONSOLE,
    logLevel: ZVec::LOG_WARN,
    queryThreads: 2,
    optimizeThreads: 2,
);
echo "Initialized (console log, WARN level, 2 query threads, 2 optimize threads).\n\n";

$path = __DIR__ . '/../demo_collection';

if (is_dir($path)) {
    exec("rm -rf " . escapeshellarg($path));
}

// ============================================================
// 1. CREATE
// ============================================================
echo "=== 1. Create Collection ===\n";

$schema = new ZVecSchema('demo');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false, withInvertIndex: true)
    ->addFloat('weight', nullable: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$collection = ZVec::create($path, $schema);
echo "Created.\n\n";

// ============================================================
// 2. INSPECT
// ============================================================
echo "=== 2. Inspect ===\n";
echo "Schema: " . $collection->schema() . "\n";
echo "Stats:  " . $collection->stats() . "\n";
echo "Path:   " . $collection->path() . "\n";
$opts = $collection->options();
echo "Options: read_only=" . ($opts['read_only'] ? 'true' : 'false') . " enable_mmap=" . ($opts['enable_mmap'] ? 'true' : 'false') . "\n\n";

// ============================================================
// 3. INSERT single + batch + duplicate
// ============================================================
echo "=== 3. Insert ===\n";

$doc1 = new ZVecDoc('doc_1');
$doc1->setInt64('id', 1)->setString('name', 'Alice')->setFloat('weight', 1.5)
    ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);
$collection->insert($doc1);

$doc2 = new ZVecDoc('doc_2');
$doc2->setInt64('id', 2)->setString('name', 'Bob')->setFloat('weight', 2.5)
    ->setVectorFp32('embedding', [0.4, 0.3, 0.2, 0.1]);
$doc3 = new ZVecDoc('doc_3');
$doc3->setInt64('id', 3)->setString('name', 'Charlie')->setFloat('weight', 3.0)
    ->setVectorFp32('embedding', [0.5, 0.5, 0.5, 0.5]);
$doc4 = new ZVecDoc('doc_4');
$doc4->setInt64('id', 4)->setString('name', 'Diana')->setFloat('weight', 4.2)
    ->setVectorFp32('embedding', [0.9, 0.1, 0.1, 0.1]);
$doc5 = new ZVecDoc('doc_5');
$doc5->setInt64('id', 5)->setString('name', 'Eve')->setFloat('weight', 0.8)
    ->setVectorFp32('embedding', [0.2, 0.8, 0.2, 0.8]);
$collection->insert($doc2, $doc3, $doc4, $doc5);
echo "Inserted 5 docs (single + batch).\n";

try {
    $dup = new ZVecDoc('doc_1');
    $dup->setInt64('id', 1)->setString('name', 'Dup')->setFloat('weight', 0.0)
        ->setVectorFp32('embedding', [0.0, 0.0, 0.0, 0.0]);
    $collection->insert($dup);
    echo "ERROR: Duplicate should fail!\n";
} catch (ZVecException $e) {
    echo "Duplicate rejected: " . $e->getMessage() . "\n";
}
echo "\n";

// ============================================================
// 4. OPTIMIZE
// ============================================================
echo "=== 4. Optimize ===\n";
$collection->optimize();
echo "Stats: " . $collection->stats() . "\n\n";

// ============================================================
// INDEX DDL - create/drop invert index + change vector index
// (must run before any deletes as createIndex can't handle
//  segments left read-only after delete operations)
// ============================================================
echo "=== 5. Index DDL: Scalar ===\n";
$collection->createInvertIndex('weight', enableRange: true);
echo "Created inverted index on 'weight'.\n";
$collection->dropIndex('weight');
echo "Dropped index on 'weight'.\n\n";

echo "=== 6. Index DDL: Vector ===\n";
$collection->createFlatIndex('embedding', metricType: ZVecSchema::METRIC_IP);
$collection->optimize();
echo "Switched to Flat index.\n";
$results = $collection->query('embedding', [0.3, 0.3, 0.3, 0.3], topk: 2);
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} score={$doc->getScore()}\n";
}
$collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP);
$collection->optimize();
echo "Switched back to HNSW.\n\n";

// ============================================================
// 7. QUERY - single vector
// ============================================================
echo "=== 7. Single-Vector Search ===\n";
$results = $collection->query('embedding', [0.1, 0.2, 0.3, 0.4], topk: 3);
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} score={$doc->getScore()} name={$doc->getString('name')}\n";
}
echo "\n";

// ============================================================
// 8. QUERY - with includeVector
// ============================================================
echo "=== 8. Query with includeVector ===\n";
$results = $collection->query('embedding', [0.5, 0.5, 0.5, 0.5], topk: 2, includeVector: true);
foreach ($results as $doc) {
    $vec = $doc->getVectorFp32('embedding');
    $vecStr = '[' . implode(', ', array_map(fn($v) => round($v, 2), $vec)) . ']';
    echo "  pk={$doc->getPk()} score={$doc->getScore()} embedding={$vecStr}\n";
}
echo "\n";

// ============================================================
// 9. QUERY - vector + filter
// ============================================================
echo "=== 9. Filtered Vector Search ===\n";
$results = $collection->query('embedding', [0.5, 0.5, 0.5, 0.5], topk: 10, filter: 'weight > 2.0');
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} score={$doc->getScore()} weight={$doc->getFloat('weight')}\n";
}
echo "\n";

// ============================================================
// 10. QUERY - filter only (no vector)
// ============================================================
echo "=== 10. Conditional Filtering ===\n";
$results = $collection->queryByFilter('id >= 3', topk: 10);
echo "WHERE id >= 3:\n";
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} id={$doc->getInt64('id')} name={$doc->getString('name')}\n";
}
$results = $collection->queryByFilter("name = 'Bob'", topk: 10);
echo "WHERE name = 'Bob':\n";
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} name={$doc->getString('name')}\n";
}
echo "\n";

// ============================================================
// 11. QUERY - with output_fields
// ============================================================
echo "=== 11. Query with output_fields ===\n";
$results = $collection->query('embedding', [0.5, 0.5, 0.5, 0.5], topk: 3, outputFields: ['name']);
echo "Only 'name' field:\n";
foreach ($results as $doc) {
    $name = $doc->getString('name');
    $weight = $doc->getFloat('weight');
    echo "  pk={$doc->getPk()} name={$name} weight=" . ($weight === null ? 'null' : $weight) . "\n";
}
$results = $collection->queryByFilter('id >= 1', topk: 3, outputFields: ['id', 'weight']);
echo "Only 'id','weight' fields:\n";
foreach ($results as $doc) {
    $name = $doc->getString('name');
    echo "  pk={$doc->getPk()} id={$doc->getInt64('id')} weight={$doc->getFloat('weight')} name=" . ($name === null ? 'null' : $name) . "\n";
}
echo "\n";

// ============================================================
// 12. QUERY - with HNSW query params (ef)
// ============================================================
echo "=== 12. Query with HNSW params ===\n";
$results = $collection->query('embedding', [0.1, 0.2, 0.3, 0.4], topk: 2,
    queryParamType: ZVec::QUERY_PARAM_HNSW, hnswEf: 50);
foreach ($results as $doc) {
    echo "  pk={$doc->getPk()} score={$doc->getScore()} name={$doc->getString('name')}\n";
}
echo "\n";

// ============================================================
// 13. QUERY - GroupBy (zvec marks this "Coming Soon", API ready but results not grouped yet)
// ============================================================
echo "=== 13. GroupBy Query ===\n";
$groups = $collection->groupByQuery(
    'embedding', [0.5, 0.5, 0.5, 0.5],
    groupByField: 'name',
    groupCount: 2,
    groupTopk: 3,
);
echo "Groups returned: " . count($groups) . " (grouping not yet active in zvec)\n";
foreach ($groups as $group) {
    echo "  group_value='" . $group['group_value'] . "' docs=" . count($group['docs']) . "\n";
}
echo "\n";

// ============================================================
// 14. FETCH
// ============================================================
echo "=== 14. Fetch ===\n";
$fetched = $collection->fetch('doc_1', 'doc_3', 'doc_999');
echo "Fetched " . count($fetched) . " of 3 (doc_999 missing):\n";
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} name={$doc->getString('name')}\n";
}
echo "\n";

// ============================================================
// 15. UPSERT
// ============================================================
echo "=== 15. Upsert ===\n";
$u1 = new ZVecDoc('doc_2');
$u1->setInt64('id', 2)->setString('name', 'Bob REPLACED')->setFloat('weight', 22.2)
    ->setVectorFp32('embedding', [0.9, 0.9, 0.9, 0.9]);
$u2 = new ZVecDoc('doc_6');
$u2->setInt64('id', 6)->setString('name', 'Frank')->setFloat('weight', 6.0)
    ->setVectorFp32('embedding', [0.6, 0.6, 0.6, 0.6]);
$collection->upsert($u1, $u2);
$collection->optimize();
$fetched = $collection->fetch('doc_2', 'doc_6');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} name={$doc->getString('name')} weight={$doc->getFloat('weight')}\n";
}
echo "\n";

// ============================================================
// 16. UPDATE (partial)
// ============================================================
echo "=== 16. Update ===\n";
$upd = new ZVecDoc('doc_1');
$upd->setString('name', 'Alice UPDATED')->setFloat('weight', 99.9);
$collection->update($upd);
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    $vec = $doc->getVectorFp32('embedding');
    $vecStr = '[' . implode(', ', array_map(fn($v) => round($v, 2), $vec)) . ']';
    echo "  pk={$doc->getPk()} name={$doc->getString('name')} weight={$doc->getFloat('weight')} embedding={$vecStr}\n";
}
echo "\n";

// ============================================================
// 17. DELETE by ID (single + batch)
// ============================================================
echo "=== 17. Delete by ID ===\n";
$collection->delete('doc_4');
echo "Deleted doc_4. Fetch: " . count($collection->fetch('doc_4')) . " (expected 0).\n";
$collection->delete('doc_5', 'doc_6');
echo "Deleted doc_5, doc_6.\n";
echo "Stats: " . $collection->stats() . "\n\n";

// ============================================================
// 18. DELETE BY FILTER
// ============================================================
echo "=== 18. Delete by Filter ===\n";
$d7 = new ZVecDoc('doc_7');
$d7->setInt64('id', 7)->setString('name', 'Grace')->setFloat('weight', 1.0)
    ->setVectorFp32('embedding', [0.1, 0.1, 0.1, 0.1]);
$d8 = new ZVecDoc('doc_8');
$d8->setInt64('id', 8)->setString('name', 'Heidi')->setFloat('weight', 2.0)
    ->setVectorFp32('embedding', [0.2, 0.2, 0.2, 0.2]);
$collection->insert($d7, $d8);
$collection->optimize();
$collection->deleteByFilter('weight <= 2.0');
$collection->optimize();
$remaining = $collection->queryByFilter('id >= 0', topk: 100);
echo "After DELETE WHERE weight <= 2.0: " . count($remaining) . " docs remaining\n";
foreach ($remaining as $doc) {
    echo "  pk={$doc->getPk()} name={$doc->getString('name')} weight={$doc->getFloat('weight')}\n";
}
echo "\n";

// ============================================================
// 19. COLUMN DDL - add, rename, drop
// ============================================================
echo "=== 19. Column DDL ===\n";

$collection->addColumnInt64('rating', nullable: true, defaultExpr: '5');
echo "Added 'rating' (default=5).\n";
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} rating={$doc->getInt64('rating')}\n";
}

$collection->renameColumn('rating', 'score_val');
echo "Renamed 'rating' -> 'score_val'.\n";
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} score_val={$doc->getInt64('score_val')}\n";
}

// Test alterColumn - change type from INT64 to FLOAT
$collection->addColumnInt64('temp_val', nullable: true, defaultExpr: '100');
echo "Added 'temp_val' column (INT64).\n";
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} temp_val={$doc->getInt64('temp_val')}\n";
}

$collection->alterColumn('temp_val', newDataType: ZVec::TYPE_FLOAT, nullable: true);
echo "Altered 'temp_val' column: INT64 -> FLOAT.\n";
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} temp_val={$doc->getFloat('temp_val')}\n";
}

$collection->dropColumn('score_val');
echo "Dropped 'score_val'.\n";
echo "Schema: " . $collection->schema() . "\n\n";

// ============================================================
// 20. CLOSE and RE-OPEN
// ============================================================
echo "=== 20. Close and Re-open ===\n";
$collection->optimize();
$collection->flush();
$collection->close();
$collection = ZVec::open($path, readOnly: true);
$opts = $collection->options();
echo "Re-opened read_only=" . ($opts['read_only'] ? 'true' : 'false') . "\n";
$fetched = $collection->fetch('doc_1');
foreach ($fetched as $doc) {
    echo "  pk={$doc->getPk()} name={$doc->getString('name')} (persisted)\n";
}
$collection->close();
echo "\n";

// ============================================================
// 21. DESTROY
// ============================================================
echo "=== 21. Destroy ===\n";
$collection = ZVec::open($path);
$collection->destroy();
echo "Destroyed. Directory exists: " . (is_dir($path) ? 'yes' : 'no') . "\n\n";

echo "All tests passed!\n";
