--TEST--
Bug 0049: ZVecGroupByVectorQuery inherited methods call wrong FFI functions (UB)
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: Setters with group_by equivalents work without UB
$q = new ZVecGroupByVectorQuery('embedding', [1.0, 2.0, 3.0, 4.0], 'category');

$q->setFilter('price > 10');
echo "setFilter OK\n";

$q->setRadius(5.0);
echo "setRadius OK\n";

$q->setLinear(true);
echo "setLinear OK\n";

$q->setUsingRefiner(true);
echo "setUsingRefiner OK\n";

$q->setIncludeVector(true);
echo "setIncludeVector OK\n";

$q->setOutputFields(['name', 'price']);
echo "setOutputFields OK\n";

// Test 2: Unsupported setters throw ZVecException
try {
    $q->setTopk(20);
    echo "FAIL: setTopk should throw\n";
} catch (ZVecException $e) {
    echo "setTopk throws: OK\n";
}

try {
    $q->setHnswParams(200);
    echo "FAIL: setHnswParams should throw\n";
} catch (ZVecException $e) {
    echo "setHnswParams throws: OK\n";
}

try {
    $q->setHnswRabitqParams(200);
    echo "FAIL: setHnswRabitqParams should throw\n";
} catch (ZVecException $e) {
    echo "setHnswRabitqParams throws: OK\n";
}

try {
    $q->setIvfParams(10);
    echo "FAIL: setIvfParams should throw\n";
} catch (ZVecException $e) {
    echo "setIvfParams throws: OK\n";
}

try {
    $q->setFlatParams();
    echo "FAIL: setFlatParams should throw\n";
} catch (ZVecException $e) {
    echo "setFlatParams throws: OK\n";
}

try {
    $q->setVamanaParams(100);
    echo "FAIL: setVamanaParams should throw\n";
} catch (ZVecException $e) {
    echo "setVamanaParams throws: OK\n";
}

// Test 3: Regular ZVecVectorQuery setters still work correctly
$vq = new ZVecVectorQuery('embedding', [1.0, 2.0, 3.0, 4.0]);
$vq->setTopk(20);
$vq->setHnswParams(200);
$vq->setFilter('price > 10');
echo "ZVecVectorQuery setters unaffected: OK\n";

// Test 4: Group-specific setters still work
$q->setGroupByField('category');
echo "setGroupByField OK\n";
$q->setGroupCount(3);
echo "setGroupCount OK\n";
$q->setGroupTopk(5);
echo "setGroupTopk OK\n";

echo "PASS: Bug 0049 - All setters correctly handled\n";
?>
--EXPECT--
setFilter OK
setRadius OK
setLinear OK
setUsingRefiner OK
setIncludeVector OK
setOutputFields OK
setTopk throws: OK
setHnswParams throws: OK
setHnswRabitqParams throws: OK
setIvfParams throws: OK
setFlatParams throws: OK
setVamanaParams throws: OK
ZVecVectorQuery setters unaffected: OK
setGroupByField OK
setGroupCount OK
setGroupTopk OK
PASS: Bug 0049 - All setters correctly handled
