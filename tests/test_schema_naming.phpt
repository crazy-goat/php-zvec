--TEST--
Schema naming: new unprefixed methods produce identical schema; deprecated addField*() delegates with warning
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/schema_naming_' . uniqid();
try {
    // Test 1: New unprefixed methods produce identical schema (binary field)
    $schema1 = new ZVecSchema('test');
    $schema1->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema1->addBinary('bin');

    $schema2 = new ZVecSchema('test');
    $schema2->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    set_error_handler(function () { return true; }); // suppress deprecation warning
    $schema2->addFieldBinary('bin');
    restore_error_handler();

    $coll1 = ZVec::create($path . '_1', $schema1);
    $fs1 = $coll1->getFieldSchema('bin');
    $coll1->destroy();

    $coll2 = ZVec::create($path . '_2', $schema2);
    $fs2 = $coll2->getFieldSchema('bin');
    $coll2->destroy();

    echo "1 binary type match: " . ($fs1->getDataType() === $fs2->getDataType() ? '1' : '0') . "\n";
    echo "2 binary nullable match: " . ($fs1->isNullable() === $fs2->isNullable() ? '1' : '0') . "\n";

    // Test 2: Array fields
    $schema3 = new ZVecSchema('test');
    $schema3->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema3->addArrayInt32('i32');
    $schema3->addArrayInt64('i64');
    $schema3->addArrayUint32('u32');
    $schema3->addArrayUint64('u64');
    $schema3->addArrayFloat('f32');
    $schema3->addArrayDouble('f64');
    $schema3->addArrayString('strs');
    $schema3->addArrayBool('bools');

    $schema4 = new ZVecSchema('test');
    $schema4->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    set_error_handler(function () { return true; }); // suppress deprecation warnings
    $schema4->addFieldArrayInt32('i32');
    $schema4->addFieldArrayInt64('i64');
    $schema4->addFieldArrayUint32('u32');
    $schema4->addFieldArrayUint64('u64');
    $schema4->addFieldArrayFloat('f32');
    $schema4->addFieldArrayDouble('f64');
    $schema4->addFieldArrayString('strs');
    $schema4->addFieldArrayBool('bools');
    restore_error_handler();

    $coll3 = ZVec::create($path . '_3', $schema3);
    $coll4 = ZVec::create($path . '_4', $schema4);

    $types = ['i32', 'i64', 'u32', 'u64', 'f32', 'f64', 'strs', 'bools'];
    $allMatch = true;
    foreach ($types as $t) {
        $fs3 = $coll3->getFieldSchema($t);
        $fs4 = $coll4->getFieldSchema($t);
        if ($fs3->getDataType() !== $fs4->getDataType() || $fs3->isNullable() !== $fs4->isNullable()) {
            $allMatch = false;
            echo "MISMATCH on $t\n";
        }
    }
    echo "3 array types all match: " . ($allMatch ? '1' : '0') . "\n";
    $coll3->destroy();
    $coll4->destroy();

    // Test 3-4: All 9 deprecated addField*() methods emit E_USER_DEPRECATED
    $deprecatedMethods = [
        ['addFieldBinary', 'bin'],
        ['addFieldArrayString', 'strs'],
        ['addFieldArrayBool', 'flags'],
        ['addFieldArrayInt32', 'i32'],
        ['addFieldArrayInt64', 'i64'],
        ['addFieldArrayUint32', 'u32'],
        ['addFieldArrayUint64', 'u64'],
        ['addFieldArrayFloat', 'f32'],
        ['addFieldArrayDouble', 'f64'],
    ];
    $allDeprecated = true;
    foreach ($deprecatedMethods as $i => [$method, $fieldName]) {
        $caught = false;
        set_error_handler(function ($errno, $errstr) use ($method, &$caught) {
            if ($errno === E_USER_DEPRECATED && str_contains($errstr, $method)) {
                $caught = true;
            }
            return true;
        });
        $s = new ZVecSchema('test');
        $s->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $s->$method($fieldName);
        restore_error_handler();
        if (!$caught) {
            $allDeprecated = false;
            echo "MISSING DEPRECATION: $method\n";
        }
    }
    echo "4 all deprecated warnings: " . ($allDeprecated ? '1' : '0') . "\n";
    echo "5 deprecated count: " . count($deprecatedMethods) . "\n";

    // Test 5 (renumbered): Deprecated methods still produce valid schema (round-trip via deprecated API)
    $schema7 = new ZVecSchema('test');
    $schema7->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    set_error_handler(function () { return true; });
    $schema7->addFieldBinary('bin');
    $schema7->addFieldArrayString('tags');
    $schema7->addFieldArrayBool('flags');
    $schema7->addFieldArrayInt32('counts');
    $schema7->addFieldArrayInt64('big_ids');
    $schema7->addFieldArrayUint32('uids');
    $schema7->addFieldArrayUint64('u64s');
    $schema7->addFieldArrayFloat('scores');
    $schema7->addFieldArrayDouble('vals');
    restore_error_handler();
    $coll7 = ZVec::create($path . '_7', $schema7);
    $doc = new ZVecDoc('d1');
    $doc->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
    $doc->setBinary('bin', "\x00\x01");
    $doc->setArrayString('tags', ['a', 'b']);
    $doc->setArrayBool('flags', [true, false]);
    $doc->setArrayInt32('counts', [1, 2, 3]);
    $doc->setArrayInt64('big_ids', [1000, 2000]);
    $doc->setArrayUint32('uids', [10, 20]);
    $doc->setArrayUint64('u64s', [100, 200]);
    $doc->setArrayFloat('scores', [1.5, 2.5]);
    $doc->setArrayDouble('vals', [3.14, 2.72]);
    $coll7->insert($doc);
    $fetched = $coll7->fetch('d1');
    $d = $fetched[0];
    echo "6 deprecated round-trip bin: " . bin2hex($d->getBinary('bin')) . "\n";
    echo "7 deprecated round-trip tags: " . implode(',', $d->getArrayString('tags')) . "\n";
    echo "8 deprecated round-trip flags: " . implode(',', array_map(fn($v) => $v ? '1' : '0', $d->getArrayBool('flags'))) . "\n";
    echo "9 deprecated round-trip counts: " . implode(',', $d->getArrayInt32('counts')) . "\n";
    echo "10 deprecated round-trip big_ids: " . implode(',', $d->getArrayInt64('big_ids')) . "\n";
    echo "11 deprecated round-trip uids: " . implode(',', $d->getArrayUint32('uids')) . "\n";
    echo "12 deprecated round-trip u64s: " . implode(',', $d->getArrayUint64('u64s')) . "\n";
    echo "13 deprecated round-trip scores: " . implode(',', array_map(fn($v) => round($v, 1), $d->getArrayFloat('scores'))) . "\n";
    echo "14 deprecated round-trip vals: " . implode(',', array_map(fn($v) => round($v, 1), $d->getArrayDouble('vals'))) . "\n";
    $coll7->destroy();

    // Test 6 (renumbered): New methods used in a full workflow
    $schema8 = new ZVecSchema('test');
    $schema8->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema8->addBinary('bin');
    $schema8->addArrayString('tags');
    $coll8 = ZVec::create($path . '_8', $schema8);
    $doc8 = new ZVecDoc('d1');
    $doc8->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]);
    $doc8->setBinary('bin', "data");
    $doc8->setArrayString('tags', ['x', 'y']);
    $coll8->insert($doc8);
    $f = $coll8->fetch('d1')[0];
    echo "15 new API bin: " . $f->getBinary('bin') . "\n";
    echo "16 new API tags: " . implode(',', $f->getArrayString('tags')) . "\n";
    $coll8->destroy();

    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path) . '*');
}
?>
--EXPECT--
1 binary type match: 1
2 binary nullable match: 1
3 array types all match: 1
4 all deprecated warnings: 1
5 deprecated count: 9
6 deprecated round-trip bin: 0001
7 deprecated round-trip tags: a,b
8 deprecated round-trip flags: 1,0
9 deprecated round-trip counts: 1,2,3
10 deprecated round-trip big_ids: 1000,2000
11 deprecated round-trip uids: 10,20
12 deprecated round-trip u64s: 100,200
13 deprecated round-trip scores: 1.5,2.5
14 deprecated round-trip vals: 3.1,2.7
15 new API bin: data
16 new API tags: x,y
PASS
