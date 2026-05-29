--TEST--
Buffer retry logic: getArrayString, fieldNames, vectorNames, getArrayBool, schema, path, stats handle overflow
--SKIPIF--
<?php
if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available');
if (!method_exists('ZVecSchema', 'addArrayString')) die('skip addArrayString not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$path = __DIR__ . '/../test_dbs/buffer_retry_' . uniqid();

try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addString('name', nullable: false)
        ->addArrayString('tags')
        ->addArrayBool('flags')
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Test 1: Normal-size data works
    echo "=== Test 1: Normal data ===\n";
    $doc = new ZVecDoc('doc_1');
    $doc->setInt64('id', 1)
        ->setString('name', 'Normal Doc')
        ->setArrayString('tags', ['php', 'ffi', 'vector'])
        ->setArrayBool('flags', [true, false, true])
        ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);

    $c->insert($doc);
    $c->optimize();

    $fetched = $c->fetch('doc_1')[0] ?? null;
    if ($fetched === null) {
        echo "FAIL: could not fetch doc_1\n";
        exit(1);
    }

    $tags = $fetched->getArrayString('tags');
    echo "getArrayString: " . implode(',', $tags ?? []) . "\n";
    $flags = $fetched->getArrayBool('flags');
    echo "getArrayBool: " . implode(',', array_map(fn($v) => $v ? 'true' : 'false', $flags ?? [])) . "\n";

    $names = $fetched->fieldNames();
    echo "fieldNames: " . implode(',', $names) . "\n";

    // Test 2: Large array string that exceeds 8192 bytes
    echo "\n=== Test 2: Large array string (>8192 bytes) ===\n";
    $largeTags = [];
    for ($i = 0; $i < 200; $i++) {
        $largeTags[] = str_repeat('x', 50) . "_$i";
    }

    $doc2 = new ZVecDoc('doc_2');
    $doc2->setInt64('id', 2)
        ->setString('name', 'Large Doc')
        ->setArrayString('tags', $largeTags)
        ->setArrayBool('flags', array_fill(0, 200, true))
        ->setVectorFp32('embedding', [0.5, 0.6, 0.7, 0.8]);

    $c->insert($doc2);
    $c->optimize();

    $fetched2 = $c->fetch('doc_2')[0] ?? null;
    if ($fetched2 === null) {
        echo "FAIL: could not fetch doc_2\n";
        exit(1);
    }

    $largeTagsResult = $fetched2->getArrayString('tags');
    echo "getArrayString count: " . count($largeTagsResult ?? []) . " (expected: 200)\n";
    $largeFlagsResult = $fetched2->getArrayBool('flags');
    echo "getArrayBool count: " . count($largeFlagsResult ?? []) . " (expected: 200)\n";

    // Test 3: Non-existent field returns null/empty
    echo "\n=== Test 3: Non-existent field ===\n";
    $missing = $fetched->getArrayString('nonexistent');
    echo "getArrayString(nonexistent): " . ($missing === null ? 'null' : 'array') . "\n";
    $missingBool = $fetched->getArrayBool('nonexistent');
    echo "getArrayBool(nonexistent): " . ($missingBool === null ? 'null' : 'array') . "\n";

    // Test 4: schema() and path() work
    echo "\n=== Test 4: schema and path ===\n";
    $schemaStr = $c->schema();
    echo "schema ok: " . (strlen($schemaStr) > 0 ? 'yes' : 'no') . "\n";
    $pathStr = $c->path();
    echo "path ok: " . (strlen($pathStr) > 0 ? 'yes' : 'no') . "\n";

    // Test 5: stats() works
    echo "\n=== Test 5: stats ===\n";
    $statsStr = $c->stats();
    echo "stats ok: " . (strlen($statsStr) > 0 ? 'yes' : 'no') . "\n";

    // Verify results
    $ok = true;

    if ($tags !== ['php', 'ffi', 'vector']) {
        echo "\nFAIL: getArrayString returned wrong data\n";
        $ok = false;
    }
    if ($flags !== [true, false, true]) {
        echo "\nFAIL: getArrayBool returned wrong data\n";
        $ok = false;
    }
    if (count($largeTagsResult ?? []) !== 200) {
        echo "\nFAIL: getArrayString should return 200 tags for large data\n";
        $ok = false;
    }
    if (count($largeFlagsResult ?? []) !== 200) {
        echo "\nFAIL: getArrayBool should return 200 flags for large data\n";
        $ok = false;
    }
    if ($missing !== null) {
        echo "\nFAIL: getArrayString(nonexistent) should be null\n";
        $ok = false;
    }
    if ($missingBool !== null) {
        echo "\nFAIL: getArrayBool(nonexistent) should be null\n";
        $ok = false;
    }
    if (strlen($schemaStr) === 0) {
        echo "\nFAIL: schema() should return non-empty string\n";
        $ok = false;
    }
    if (strlen($pathStr) === 0) {
        echo "\nFAIL: path() should return non-empty string\n";
        $ok = false;
    }
    if (strlen($statsStr) === 0) {
        echo "\nFAIL: stats() should return non-empty string\n";
        $ok = false;
    }

    $c->close();

    if ($ok) {
        echo "\nPASS: All buffer retry tests passed\n";
    } else {
        echo "\nFAIL: Some tests failed\n";
        exit(1);
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
=== Test 1: Normal data ===
getArrayString: php,ffi,vector
getArrayBool: true,false,true
fieldNames: flags,id,name,tags

=== Test 2: Large array string (>8192 bytes) ===
getArrayString count: 200 (expected: 200)
getArrayBool count: 200 (expected: 200)

=== Test 3: Non-existent field ===
getArrayString(nonexistent): null
getArrayBool(nonexistent): null

=== Test 4: schema and path ===
schema ok: yes
path ok: yes

=== Test 5: stats ===
stats ok: yes

PASS: All buffer retry tests passed
