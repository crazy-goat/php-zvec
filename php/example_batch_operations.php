<?php

/**
 * Example: Per-Document Batch Operations
 * 
 * Demonstrates insertBatch(), upsertBatch(), and updateBatch() methods
 * which return per-document status instead of throwing on first error.
 */

require_once __DIR__ . '/ZVec.php';

// Initialize
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../demo_collection_batch';
if (is_dir($path)) {
    exec("rm -rf " . escapeshellarg($path));
}

// Create collection
$schema = new ZVecSchema('batch_demo');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$collection = ZVec::create($path, $schema);
$collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP);
$collection->optimize();

echo "=== Batch Operations Demo ===\n\n";

// ==========================================
// 1. Insert Batch - All new documents
// ==========================================
echo "1. Insert batch (all new documents):\n";
$docs = [
    (new ZVecDoc('doc1'))
        ->setInt64('id', 1)
        ->setString('name', 'Alice')
        ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]),
    (new ZVecDoc('doc2'))
        ->setInt64('id', 2)
        ->setString('name', 'Bob')
        ->setVectorFp32('embedding', [0.4, 0.3, 0.2, 0.1]),
    (new ZVecDoc('doc3'))
        ->setInt64('id', 3)
        ->setString('name', 'Charlie')
        ->setVectorFp32('embedding', [0.5, 0.5, 0.5, 0.5]),
];

$results = $collection->insertBatch(...$docs);

foreach ($results as $r) {
    $status = $r['ok'] ? '✓' : '✗';
    echo "  {$status} {$r['pk']}\n";
}

$successes = count(array_filter($results, fn($r) => $r['ok']));
echo "  Result: {$successes}/" . count($results) . " succeeded\n\n";

// ==========================================
// 2. Insert Batch - With duplicate (partial failure)
// ==========================================
echo "2. Insert batch with duplicate:\n";
$docs = [
    (new ZVecDoc('doc1'))  // Duplicate - should fail
        ->setInt64('id', 1)
        ->setString('name', 'Alice Duplicate')
        ->setVectorFp32('embedding', [0.9, 0.9, 0.9, 0.9]),
    (new ZVecDoc('doc4'))  // New - should succeed
        ->setInt64('id', 4)
        ->setString('name', 'Diana')
        ->setVectorFp32('embedding', [0.7, 0.7, 0.7, 0.7]),
];

$results = $collection->insertBatch(...$docs);

foreach ($results as $r) {
    $status = $r['ok'] ? '✓ OK' : '✗ FAILED';
    $error = $r['error'] ? " - {$r['error']}" : '';
    echo "  {$r['pk']}: {$status}{$error}\n";
}

$successes = count(array_filter($results, fn($r) => $r['ok']));
$failures = count(array_filter($results, fn($r) => !$r['ok']));
echo "  Summary: {$successes} succeeded, {$failures} failed\n\n";

// ==========================================
// 3. Upsert Batch - All succeed
// ==========================================
echo "3. Upsert batch (new and existing):\n";
$docs = [
    (new ZVecDoc('doc1'))  // Existing - updated
        ->setInt64('id', 1)
        ->setString('name', 'Alice UPDATED')
        ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]),
    (new ZVecDoc('doc5'))  // New
        ->setInt64('id', 5)
        ->setString('name', 'Eve')
        ->setVectorFp32('embedding', [0.2, 0.8, 0.2, 0.8]),
];

$results = $collection->upsertBatch(...$docs);

foreach ($results as $r) {
    $status = $r['ok'] ? '✓' : '✗';
    echo "  {$status} {$r['pk']}\n";
}
echo "  All upserts succeed (both new and existing docs)\n\n";

// ==========================================
// 4. Update Batch - Partial failure
// ==========================================
echo "4. Update batch (partial failure):\n";
$docs = [
    (new ZVecDoc('doc2'))  // Exists - will update
        ->setString('name', 'Bob UPDATED'),
    (new ZVecDoc('doc_nonexistent'))  // Doesn't exist - will fail
        ->setString('name', 'NonExistent'),
];

$results = $collection->updateBatch(...$docs);

foreach ($results as $r) {
    $status = $r['ok'] ? '✓ OK' : '✗ FAILED';
    $error = $r['error'] ? " - error" : '';
    echo "  {$r['pk']}: {$status}{$error}\n";
}

$successes = count(array_filter($results, fn($r) => $r['ok']));
$failures = count(array_filter($results, fn($r) => !$r['ok']));
echo "  Summary: {$successes} succeeded, {$failures} failed\n\n";

// ==========================================
// 5. Process results programmatically
// ==========================================
echo "5. Processing batch results programmatically:\n";

$docs = [
    (new ZVecDoc('doc3'))->setString('name', 'Charlie V2'),
    (new ZVecDoc('doc6'))->setString('name', 'Frank'),
    (new ZVecDoc('doc7'))->setString('name', 'Grace'),
    (new ZVecDoc('doc_nonexistent2'))->setString('name', 'NoOne'),
];

$results = $collection->updateBatch(...$docs);

// Separate successful and failed
$successful = [];
$failed = [];
foreach ($results as $r) {
    if ($r['ok']) {
        $successful[] = $r['pk'];
    } else {
        $failed[$r['pk']] = $r['error'] ?? 'Unknown error';
    }
}

echo "  Successful updates: " . implode(', ', $successful) . "\n";
echo "  Failed updates:\n";
foreach ($failed as $pk => $error) {
    echo "    - {$pk}: {$error}\n";
}

// ==========================================
// Cleanup
// ==========================================
$collection->close();
$collection = ZVec::open($path);
$collection->destroy();

echo "\nDone! Collection destroyed.\n";
