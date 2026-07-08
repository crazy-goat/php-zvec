<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0010_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addArrayInt32('i32');
    $schema->addArrayInt64('i64');
    $schema->addArrayUint32('u32');
    $schema->addArrayUint64('u64');
    $schema->addArrayFloat('f32');
    $schema->addArrayDouble('f64');
    $schema->addArrayString('strs');
    $schema->addArrayBool('bools');
    $coll = ZVec::create($path, $schema);

    // Test 1: Doc with empty arrays
    $doc = new ZVecDoc('d1');
    $doc->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
    $doc->setArrayInt32('i32', []);
    $doc->setArrayInt64('i64', []);
    $doc->setArrayUint32('u32', []);
    $doc->setArrayUint64('u64', []);
    $doc->setArrayFloat('f32', []);
    $doc->setArrayDouble('f64', []);
    $doc->setArrayString('strs', []);
    $doc->setArrayBool('bools', []);
    $coll->insert($doc);

    // Test 2: Doc with non-empty arrays
    $doc2 = new ZVecDoc('d2');
    $doc2->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0]);
    $doc2->setArrayInt32('i32', [1, 2, 3]);
    $doc2->setArrayInt64('i64', [100, 200]);
    $doc2->setArrayUint32('u32', [10, 20]);
    $doc2->setArrayUint64('u64', [1000]);
    $doc2->setArrayFloat('f32', [1.5, 2.5]);
    $doc2->setArrayDouble('f64', [3.14]);
    $doc2->setArrayString('strs', ['hello', 'world']);
    $doc2->setArrayBool('bools', [true, false]);
    $coll->insert($doc2);

    $coll->optimize();

    // Fetch d1 (empty arrays)
    $fetched = $coll->fetch('d1');
    $d = $fetched[0];

    $pass = true;

    // Test: empty arrays should return [], not null
    $tests = [
        ['i32', $d->getArrayInt32('i32'), []],
        ['i64', $d->getArrayInt64('i64'), []],
        ['u32', $d->getArrayUint32('u32'), []],
        ['u64', $d->getArrayUint64('u64'), []],
        ['f32', $d->getArrayFloat('f32'), []],
        ['f64', $d->getArrayDouble('f64'), []],
        ['strs', $d->getArrayString('strs'), []],
        ['bools', $d->getArrayBool('bools'), []],
    ];

    foreach ($tests as [$name, $actual, $expected]) {
        if ($actual !== $expected) {
            echo "FAIL: $name empty array - expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
            $pass = false;
        }
    }

    // Test: non-existent field should still return null
    $nullTests = [
        ['nonexistent_int32', $d->getArrayInt32('nonexistent_int32')],
        ['nonexistent_int64', $d->getArrayInt64('nonexistent_int64')],
        ['nonexistent_uint32', $d->getArrayUint32('nonexistent_uint32')],
        ['nonexistent_uint64', $d->getArrayUint64('nonexistent_uint64')],
        ['nonexistent_float', $d->getArrayFloat('nonexistent_float')],
        ['nonexistent_double', $d->getArrayDouble('nonexistent_double')],
        ['nonexistent_string', $d->getArrayString('nonexistent_string')],
        ['nonexistent_bool', $d->getArrayBool('nonexistent_bool')],
    ];

    foreach ($nullTests as [$name, $actual]) {
        if ($actual !== null) {
            echo "FAIL: $name should be null, got " . var_export($actual, true) . "\n";
            $pass = false;
        }
    }

    // Fetch d2 (non-empty arrays)
    $fetched2 = $coll->fetch('d2');
    $d2 = $fetched2[0];

    $nonEmptyTests = [
        ['i32', $d2->getArrayInt32('i32'), [1, 2, 3]],
        ['i64', $d2->getArrayInt64('i64'), [100, 200]],
        ['u32', $d2->getArrayUint32('u32'), [10, 20]],
        ['u64', $d2->getArrayUint64('u64'), [1000]],
        ['f32', $d2->getArrayFloat('f32'), [1.5, 2.5]],
        ['f64', $d2->getArrayDouble('f64'), [3.14]],
        ['strs', $d2->getArrayString('strs'), ['hello', 'world']],
        ['bools', $d2->getArrayBool('bools'), [true, false]],
    ];

    foreach ($nonEmptyTests as [$name, $actual, $expected]) {
        if ($actual !== $expected) {
            echo "FAIL: $name non-empty - expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
            $pass = false;
        }
    }

    if ($pass) {
        echo "PASS: bug_0010 - getArray*() returns [] for empty arrays and null for missing fields\n";
    } else {
        exit(1);
    }
} catch (ZVecException $e) {
    echo "FAIL: bug_0010 - " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (isset($coll)) { try { $coll->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
