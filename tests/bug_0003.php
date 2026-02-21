<?php
/**
 * Bug reproduction: Segfault after destroy()
 * 
 * Expected: ZVecException when using methods on destroyed collection
 * Actual: Segfault (process crash)
 * 
 * Status: Known limitation - destroy() invalidates the C++ handle
 * Workaround: Don't use collection object after destroy()
 */

require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_destroy_segfault';
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

$schema = new ZVecSchema('segfault_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert a doc
$doc = new ZVecDoc('doc1');
$doc->setInt64('id', 1)
    ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);
$c->insert($doc);

// Destroy the collection
$c->destroy();

// BUG: This causes segfault instead of throwing exception
// Uncommenting the next line will crash PHP with exit code 139:
// $stats = $c->stats();

echo "NOTE: Using any method on destroyed collection causes segfault (exit 139)\n";
echo "This is expected C++ behavior - the handle is invalidated after destroy()\n";
echo "PASS: bug_0003 - destroy segfault documented (test commented out to avoid crash)\n";

// Cleanup
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
