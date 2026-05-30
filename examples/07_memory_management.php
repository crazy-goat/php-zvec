<?php

declare(strict_types=1);

/**
 * Example 7: Memory Management — FFI memory cleanup patterns
 *
 * Demonstrates:
 * - VmRSS monitoring for native memory leak detection
 * - C string lifecycle (allocation via FFI, freeing via FFI::free)
 * - try-finally guards for guaranteed cleanup
 * - Collection lifecycle memory patterns
 * - Serialize/deserialize buffer management
 */

require_once __DIR__ . '/../src/ZVec.php';

echo "=== Example 7: Memory Management ===\n\n";

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

/**
 * Get current VmRSS ( Resident Set Size ) in KB from /proc/self/status.
 * This measures native (C/C++) memory usage, not PHP heap.
 */
function getVmRSS(): int {
    $status = @file_get_contents('/proc/self/status');
    if ($status === false) {
        return 0; // Not on Linux
    }
    if (preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $m)) {
        return (int)$m[1];
    }
    return 0;
}

// --- 1. Collection lifecycle memory test ---
echo "[1] Collection lifecycle memory test\n";
$path1 = __DIR__ . '/../test_dbs/example_07_a_' . uniqid();

$schema = new ZVecSchema('mem_demo');
$schema->setMaxDocCountPerSegment(100)
    ->addInt64('id', nullable: false)
    ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$vmBefore = getVmRSS();

for ($i = 0; $i < 20; $i++) {
    $c = ZVec::create($path1, $schema);
    $doc = new ZVecDoc("doc{$i}");
    $doc->setInt64('id', $i)->setVectorFp32('vec', [(float)$i, 0.0, 0.0, 0.0]);
    $c->insert($doc);
    $c->optimize();
    $c->close();
    $c = ZVec::open($path1);
    $c->destroy();
}

$vmAfter = getVmRSS();
$deltaKb = $vmAfter - $vmBefore;
echo "    VmRSS delta: {$deltaKb} KB (20x create/destroy)\n";

if ($deltaKb > 500) {
    echo "    WARNING: Possible native memory leak (+{$deltaKb} KB)\n";
} else {
    echo "    OK: No significant native memory growth\n";
}

// --- 2. Serialize/deserialize buffer test ---
echo "\n[2] Serialize/deserialize buffer test\n";

$vmBefore = getVmRSS();
$memBefore = memory_get_usage();

for ($i = 0; $i < 50; $i++) {
    $doc = new ZVecDoc('test_pk');
    $doc->setInt64('id', $i)
        ->setString('name', "item_{$i}")
        ->setVectorFp32('vec', [(float)$i, 1.0, 0.0, 0.0]);

    $data = $doc->serialize();
    $restored = ZVecDoc::deserialize($data);
    // $restored is freed when it goes out of scope
}

$vmAfter = getVmRSS();
$memAfter = memory_get_usage();
$vmDeltaKb = $vmAfter - $vmBefore;
$phpDelta = $memAfter - $memBefore;

echo "    VmRSS delta: {$vmDeltaKb} KB, PHP heap delta: {$phpDelta} bytes (50x serialize/deserialize)\n";

if ($vmDeltaKb > 200) {
    echo "    WARNING: Possible native buffer leak (+{$vmDeltaKb} KB)\n";
} else {
    echo "    OK: No native buffer memory growth\n";
}

// --- 3. C string cleanup pattern (try-finally) ---
echo "\n[3] C string cleanup pattern (try-finally)\n";
$path3 = __DIR__ . '/../test_dbs/example_07_b_' . uniqid();

$schema3 = new ZVecSchema('cstr_demo');
$schema3->setMaxDocCountPerSegment(100)
    ->addInt64('id', nullable: false)
    ->addString('name', nullable: true)
    ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path3, $schema3);

// Insert docs with string fields (each string is a C string allocation)
for ($i = 0; $i < 20; $i++) {
    $doc = new ZVecDoc("doc{$i}");
    $doc->setInt64('id', $i)
        ->setString('name', "product_name_{$i}_with_padding_to_make_it_longer")
        ->setVectorFp32('vec', [(float)$i, 0.0, 0.0, 0.0]);
    $c->insert($doc);
}

$vmBefore = getVmRSS();

// Query with outputFields — each outputField name is a C string
// allocated via toCStringArray() and freed via freeCStringArray()
for ($i = 0; $i < 30; $i++) {
    $results = $c->query(
        'vec',
        [1.0, 0.0, 0.0, 0.0],
        topk: 5,
        outputFields: ['id', 'name']
    );
}

$vmAfter = getVmRSS();
$deltaKb = $vmAfter - $vmBefore;
echo "    VmRSS delta: {$deltaKb} KB (30x query with outputFields)\n";

if ($deltaKb > 200) {
    echo "    WARNING: Possible C string leak (+{$deltaKb} KB)\n";
} else {
    echo "    OK: C strings properly freed\n";
}

$c->close();
exec("rm -rf " . escapeshellarg($path3));

// --- 4. Summary ---
echo "\n=== Memory Management Summary ===\n";
echo "Key patterns for preventing FFI memory leaks:\n";
echo "  1. Always use try-finally for C string arrays (toCStringArray/freeCStringArray)\n";
echo "  2. Free serialized buffers immediately after use (FFI::free)\n";
echo "  3. Collection destructors auto-call close() — but explicit close() is safer\n";
echo "  4. VmRSS monitoring catches native leaks that memory_get_usage() misses\n";
echo "  5. Never store FFI\\CData in long-lived PHP variables\n";

exec("rm -rf " . escapeshellarg(__DIR__ . '/../test_dbs/example_07_a_' . glob(__DIR__ . '/../test_dbs/example_07_a_*')[0]));
echo "\nDone!\n";
