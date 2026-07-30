--TEST--
Deprecated create*Index() methods emit E_USER_DEPRECATED warnings
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/deprecated_index_warnings_' . uniqid();
$expectedWarnings = [
    'createHnswIndex' => 'createHnswIndex() is deprecated, use createIndex() with ZVecIndexParams::forHnsw() instead',
    'createHnswRabitqIndex' => 'createHnswRabitqIndex() is deprecated, use createIndex() with ZVecIndexParams::forHnswRabitq() instead',
    'createFlatIndex' => 'createFlatIndex() is deprecated, use createIndex() with ZVecIndexParams::forFlat() instead',
    'createIvfIndex' => 'createIvfIndex() is deprecated, use createIndex() with ZVecIndexParams::forIvf() instead',
];

try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    foreach ($expectedWarnings as $method => $expectedMessage) {
        $warning = null;
        $delegated = false;
        set_error_handler(function (int $errno, string $message) use (&$warning, $expectedMessage): bool {
            if ($errno === E_USER_DEPRECATED && $message === $expectedMessage) {
                $warning = $message;
                return true;
            }
            return false;
        });

        try {
            $collection->$method('missing_vector_field');
        } catch (ZVecException) {
            $delegated = true;
        } finally {
            restore_error_handler();
        }

        $passed = $warning === $expectedMessage && $delegated;
        echo $method . ': ' . ($passed ? 'PASS' : 'FAIL') . "\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
createHnswIndex: PASS
createHnswRabitqIndex: PASS
createFlatIndex: PASS
createIvfIndex: PASS
