--TEST--
IVF-RaBitQ: constants, forIvfRabitq() validation, setIvfRabitqParams()
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

echo 'INDEX_TYPE_IVF_RABITQ=' . ZVec::INDEX_TYPE_IVF_RABITQ . "\n";
echo 'QUERY_PARAM_IVF_RABITQ=' . ZVec::QUERY_PARAM_IVF_RABITQ . "\n";

$params = ZVecIndexParams::forIvfRabitq(ZVecSchema::METRIC_L2);
echo 'params ok: ' . ($params instanceof ZVecIndexParams ? 'yes' : 'no') . "\n";

// Upstream only logs an error for out-of-range values at build time, so these
// must be rejected up front.
$invalid = [
    'nlist=0' => static fn() => ZVecIndexParams::forIvfRabitq(ZVecSchema::METRIC_L2, nList: 0),
    'totalBits=0' => static fn() => ZVecIndexParams::forIvfRabitq(ZVecSchema::METRIC_L2, totalBits: 0),
    'totalBits=10' => static fn() => ZVecIndexParams::forIvfRabitq(ZVecSchema::METRIC_L2, totalBits: 10),
    'sampleCount=-1' => static fn() => ZVecIndexParams::forIvfRabitq(ZVecSchema::METRIC_L2, sampleCount: -1),
];
foreach ($invalid as $label => $make) {
    try {
        $make();
        echo "$label: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo "$label: rejected\n";
    }
}

$query = (new ZVecVectorQuery('v', array_fill(0, 64, 0.1)))->setIvfRabitqParams(nprobe: 16);
echo "paramType={$query->queryParamType} nprobe={$query->ivfNprobe}\n";

try {
    (new ZVecVectorQuery('v', array_fill(0, 64, 0.1)))->setIvfRabitqParams(0);
    echo "nprobe=0: NOT REJECTED\n";
} catch (ZVecException $e) {
    echo "nprobe=0: rejected\n";
}

try {
    (new ZVecGroupByVectorQuery('v', array_fill(0, 64, 0.1), 'g'))->setIvfRabitqParams();
    echo "group-by: NOT REJECTED\n";
} catch (ZVecException $e) {
    echo "group-by: rejected\n";
}
?>
--EXPECT--
INDEX_TYPE_IVF_RABITQ=7
QUERY_PARAM_IVF_RABITQ=7
params ok: yes
nlist=0: rejected
totalBits=0: rejected
totalBits=10: rejected
sampleCount=-1: rejected
paramType=7 nprobe=16
nprobe=0: rejected
group-by: rejected
