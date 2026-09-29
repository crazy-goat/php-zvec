--TEST--
FTS tokenizer "jieba": bundled dictionary, cut modes, user dictionary, and errors
--SKIPIF--
<?php
if (!extension_loaded('ffi')) die('skip FFI extension not available');

$found = false;
foreach (['/../lib/zvec_data/jieba_dict', '/../ffi/build/zvec_data/jieba_dict'] as $d) {
    if (is_file(__DIR__ . $d . '/jieba.dict.utf8')) { $found = true; break; }
}
if (!$found) die('skip bundled jieba dictionary not found');
if (getenv('ZVEC_JIEBA_DICT_DIR') !== false) die('skip ZVEC_JIEBA_DICT_DIR overrides the bundled dictionary');
?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
// The jieba dictionary is found automatically at init(); no jieba_dict_dir needed.
// LOG_FATAL because the rejected case below is expected to fail and upstream logs
// it at ERROR on stderr.
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

/**
 * @param array<string, string> $docs
 * @return array{0: ZVec, 1: string} collection and its path
 */
function makeCollection(string $name, array $docs, array $filters, string $extraParams = ''): array
{
    $path = __DIR__ . '/../test_dbs/' . $name . '_' . uniqid();
    $schema = new ZVecSchema($name);
    $schema->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);
    $c->createIndex('body', ZVecIndexParams::forFts(
        tokenizer: 'jieba',
        filters: $filters,
        extraParams: $extraParams,
    ));
    foreach ($docs as $pk => $body) {
        $c->insert(
            (new ZVecDoc($pk))->setString('body', $body)->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])
        );
    }
    $c->flush();
    return [$c, $path];
}

/** @return string sorted PKs; result order is not part of the contract */
function ftsPks(ZVec $c, string $query, ?string $matchString = null): string
{
    $q = (new ZVecVectorQuery('body', []))->setTopk(10)
        ->setFts('body', $query, $matchString ?? '');
    $pks = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q));
    sort($pks);
    return $pks === [] ? '(none)' : implode(',', $pks);
}

$docs = [
    'j1' => '我来到北京清华大学',
    'j2' => '他来到了网易杭研大厦',
    'j3' => '小明硕士毕业于中国科学院计算所',
];

// Default cut_mode is "search", which emits both short and long terms.
[$c, $p] = makeCollection('fts_jieba_default', $docs, []);
try {
    echo '北京: ' . ftsPks($c, '北京') . "\n";
    echo '清华: ' . ftsPks($c, '清华') . "\n";
    echo '杭研: ' . ftsPks($c, '杭研') . "\n";
    echo '中国科学院: ' . ftsPks($c, '中国科学院') . "\n";
    echo '上海: ' . ftsPks($c, '上海') . "\n";
    // A phrase query goes through matchString rather than queryString.
    echo 'phrase 北京大学: ' . ftsPks($c, '', '北京大学') . "\n";

    $c->close();
    $c = ZVec::open($p);
    echo '北京 after reopen: ' . ftsPks($c, '北京') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// The cut mode really changes the tokens: "mix" favours precise single words and
// drops the compounds that "search" and "full" both emit.
$modes = ['search', 'full', 'mix', 'hmm'];
$terms = ['北京', '清华', '清华大学', '科学院', '大学'];
foreach ($modes as $mode) {
    [$c, $p] = makeCollection('fts_jieba_' . $mode, $docs, [], json_encode(['cut_mode' => $mode]));
    try {
        foreach ($terms as $term) {
            echo "$mode $term: " . ftsPks($c, $term) . "\n";
        }
    } finally {
        exec('rm -rf ' . escapeshellarg($p));
    }
}

// Mixed Chinese/ASCII text, where lowercase still applies to the ASCII part.
[$c, $p] = makeCollection('fts_jieba_mixed', ['j1' => 'Hello World 你好世界'], ['lowercase']);
try {
    echo 'mixed hello: ' . ftsPks($c, 'hello') . "\n";
    echo 'mixed 世界: ' . ftsPks($c, '世界') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// A user dictionary adds a term. In "mix" mode, the added whole term then
// matches and the original sub-words stop matching, which is what makes the
// effect observable.
$dictFile = __DIR__ . '/../test_dbs/jieba_user_' . uniqid() . '.dict';
file_put_contents($dictFile, "蓝鲸矢量库 1000 n\n");
[$c, $p] = makeCollection('fts_jieba_user', ['u1' => '蓝鲸矢量库很快'], [], json_encode(['cut_mode' => 'mix']));
try {
    echo 'user 矢量: ' . ftsPks($c, '矢量') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}
[$c, $p] = makeCollection('fts_jieba_user2', ['u1' => '蓝鲸矢量库很快'], [], json_encode(
    ['cut_mode' => 'mix', 'user_dict_path' => $dictFile],
    JSON_UNESCAPED_SLASHES,
));
try {
    echo 'dict 矢量: ' . ftsPks($c, '矢量') . "\n";
    echo 'dict 蓝鲸矢量库: ' . ftsPks($c, '蓝鲸矢量库') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
    unlink($dictFile);
}

$path = __DIR__ . '/../test_dbs/fts_jieba_err_' . uniqid();
try {
    $schema = new ZVecSchema('fts_jieba_err');
    $schema->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);
    try {
        $c->createIndex('body', ZVecIndexParams::forFts(
            tokenizer: 'jieba',
            filters: [],
            extraParams: '{"cut_mode":"bogus"}',
        ));
        echo "bad cut_mode: NOT REJECTED\n";
    } catch (ZVecException $e) {
        echo 'bad cut_mode: ' . (str_contains($e->getMessage(), "unknown cut_mode 'bogus'") ? 'rejected' : $e->getMessage()) . "\n";
    }
} finally {
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
北京: j1
清华: j1
杭研: j2
中国科学院: j3
上海: (none)
phrase 北京大学: j1
北京 after reopen: j1
search 北京: j1
search 清华: j1
search 清华大学: j1
search 科学院: j3
search 大学: j1
full 北京: j1
full 清华: j1
full 清华大学: j1
full 科学院: j3
full 大学: j1
mix 北京: j1
mix 清华: (none)
mix 清华大学: j1
mix 科学院: (none)
mix 大学: (none)
hmm 北京: j1
hmm 清华: (none)
hmm 清华大学: j1
hmm 科学院: j3
hmm 大学: (none)
mixed hello: j1
mixed 世界: j1
user 矢量: u1
dict 矢量: (none)
dict 蓝鲸矢量库: u1
bad cut_mode: rejected
