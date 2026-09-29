--TEST--
ZVec::init(): jiebaDictDir and ftsBruteForceByKeysRatio options, with getters
--SKIPIF--
<?php
if (!extension_loaded('ffi')) die('skip FFI extension not available');

$found = false;
foreach (['/../ffi/build/zvec_data/jieba_dict', '/../lib/zvec_data/jieba_dict'] as $d) {
    if (is_file(__DIR__ . $d . '/jieba.dict.utf8')) { $found = true; break; }
}
if (!$found) die('skip bundled jieba dictionary not found');
if (getenv('ZVEC_JIEBA_DICT_DIR') !== false) die('skip ZVEC_JIEBA_DICT_DIR overrides the bundled dictionary');
?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

// Validation must reject bad values *before* any FFI call: upstream
// GlobalConfig::initialize() flips its "initialized" flag before validating, so a
// value it rejects would still leave the library marked initialized and every
// later init() would silently do nothing.
$rejected = [];
foreach ([
    'too high' => 1.5,
    'negative' => -0.1,
    'NaN' => NAN,
] as $label => $ratio) {
    try {
        ZVec::init(ftsBruteForceByKeysRatio: $ratio);
        echo "$label: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo "$label: rejected\n";
    }
}

foreach ([
    'empty dir' => '',
    'missing dir' => '/nonexistent/zvec/jieba',
] as $label => $dir) {
    try {
        ZVec::init(jiebaDictDir: $dir);
        echo "$label: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo "$label: rejected\n";
    }
}

// A folder that exists but lacks the dictionary files would be fatal upstream:
// cppjieba calls abort() instead of returning a Status, killing the process.
$emptyDir = __DIR__ . '/../test_dbs/jieba_empty_' . uniqid();
mkdir($emptyDir, 0777, true);
try {
    ZVec::init(jiebaDictDir: $emptyDir);
    echo "incomplete dir: NOT REJECTED\n";
} catch (ZVecException $e) {
    echo "incomplete dir: rejected\n";
} finally {
    exec('rm -rf ' . escapeshellarg($emptyDir));
}

echo 'pre-init: ' . (ZVec::isInitialized() ? '1' : '0') . "\n";

// Now init for real with a *copy* of the bundled dictionary, so the test proves
// a custom folder is honoured rather than the bundled default.
$customDir = __DIR__ . '/../test_dbs/jieba_custom_' . uniqid();
mkdir($customDir, 0777, true);
$source = is_file(__DIR__ . '/../lib/zvec_data/jieba_dict/jieba.dict.utf8')
    ? __DIR__ . '/../lib/zvec_data/jieba_dict'
    : __DIR__ . '/../ffi/build/zvec_data/jieba_dict';
foreach (['jieba.dict.utf8', 'hmm_model.utf8'] as $file) {
    copy("{$source}/{$file}", "{$customDir}/{$file}");
}

$path = __DIR__ . '/../test_dbs/init_fts_' . uniqid();
try {
    ZVec::init(
        logType: ZVec::LOG_CONSOLE,
        logLevel: ZVec::LOG_WARN,
        jiebaDictDir: $customDir,
        ftsBruteForceByKeysRatio: 0.2,
    );

    // The value is stored as a C float, so compare as a string, not with ===.
    echo 'ratio: ' . sprintf('%.2f', ZVec::getFtsBruteForceByKeysRatio()) . "\n";
    echo 'dir matches: ' . (ZVec::getJiebaDictDir() === $customDir ? 'yes' : 'no') . "\n";

    // End to end: an FTS index using the jieba tokenizer without a per-field
    // jieba_dict_dir, so the global value is what actually resolves it.
    $schema = new ZVecSchema('jieba_init_test');
    $schema->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);
    $collection->createIndex('body', ZVecIndexParams::forFts(tokenizer: 'jieba', filters: []));
    $collection->insert(
        (new ZVecDoc('j1'))->setString('body', '我来到北京清华大学')->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])
    );
    $collection->insert(
        (new ZVecDoc('j2'))->setString('body', '他来到了网易杭研大厦')->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0])
    );
    $collection->flush();

    $query = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', '北京');
    $hits = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $collection->queryVector($query));
    echo 'fts hit: ' . implode(',', $hits) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($path));
    exec('rm -rf ' . escapeshellarg($customDir));
}
?>
--EXPECT--
too high: rejected
negative: rejected
NaN: rejected
empty dir: rejected
missing dir: rejected
incomplete dir: rejected
pre-init: 0
ratio: 0.20
dir matches: yes
fts hit: j1
