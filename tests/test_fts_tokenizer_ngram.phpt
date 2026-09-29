--TEST--
FTS tokenizer "ngram": default bigrams, ngram_min/ngram_max, token_chars, and errors
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';
// LOG_FATAL: the rejected cases below are *expected* to fail, and upstream logs
// each rejection at ERROR on stderr, which would otherwise land in the test output.
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_FATAL);

/**
 * @return string sorted PKs; result order is not part of the contract
 */
function ftsPks(ZVec $c, string $query, string $op = ZVec::FTS_OPERATOR_OR): string
{
    $q = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', $query, '', $op);
    $pks = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q));
    sort($pks);
    return $pks === [] ? '(none)' : implode(',', $pks);
}

/**
 * @param array<string, string> $docs
 * @return array{0: ZVec, 1: string} collection and its path
 */
function makeCollection(string $name, array $docs, string $extraParams = '', string $tokenizer = 'ngram'): array
{
    $path = __DIR__ . '/../test_dbs/' . $name . '_' . uniqid();
    $schema = new ZVecSchema($name);
    $schema->addString('body')
        ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);
    $c->createIndex('body', ZVecIndexParams::forFts(
        tokenizer: $tokenizer,
        filters: ['lowercase'],
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

// AND throughout: with OR, "base" is split into ba/as/se and n2 also matches
// through "as", which would hide what the ngram tokenizer actually does.
$and = ZVec::FTS_OPERATOR_AND;

[$c, $p] = makeCollection('fts_ngram_test', ['n1' => 'database', 'n2' => 'datastore', 'n3' => 'hello']);
try {
    echo 'base: ' . ftsPks($c, 'base', $and) . "\n";
    echo 'tab: ' . ftsPks($c, 'tab', $and) . "\n";
    echo 'data: ' . ftsPks($c, 'data', $and) . "\n";
    echo 'xyz: ' . ftsPks($c, 'xyz', $and) . "\n";

    // The tokenizer config must be persisted, not just cached in memory.
    $c->close();
    $c = ZVec::open($p);
    echo 'base after reopen: ' . ftsPks($c, 'base', $and) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

[$c, $p] = makeCollection('fts_ngram_range', ['n1' => 'database', 'n2' => 'datastore'], '{"ngram_min":2,"ngram_max":3}');
try {
    echo '2-3 base: ' . ftsPks($c, 'base', $and) . "\n";
    echo '2-3 sto: ' . ftsPks($c, 'sto', $and) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// token_chars restricts what counts as a token character: with ["letter"] the
// digits in "ab12cd" become separators, so the document tokenizes to "ab","cd".
[$c, $p] = makeCollection('fts_ngram_chars', ['n1' => 'ab12cd', 'n2' => 'xy'], '{"token_chars":["letter"]}');
try {
    echo 'chars ab: ' . ftsPks($c, 'ab', $and) . "\n";
    echo 'chars cd: ' . ftsPks($c, 'cd', $and) . "\n";
    echo 'chars b1: ' . ftsPks($c, 'b1', $and) . "\n";
    echo 'chars 12: ' . ftsPks($c, '12', $and) . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

$errors = [
    'min>max' => '{"ngram_min":3,"ngram_max":2}',
    'range>1' => '{"ngram_min":1,"ngram_max":3}',
    'min=0' => '{"ngram_min":0}',
    'bad char' => '{"token_chars":["emoji"]}',
];
foreach ($errors as $label => $extra) {
    $path = __DIR__ . '/../test_dbs/fts_ngram_err_' . uniqid();
    try {
        $schema = new ZVecSchema('fts_ngram_err');
        $schema->addString('body')
            ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $c = ZVec::create($path, $schema);
        try {
            $c->createIndex('body', ZVecIndexParams::forFts(
                tokenizer: 'ngram',
                filters: ['lowercase'],
                extraParams: $extra,
            ));
            echo "$label: NOT REJECTED\n";
        } catch (ZVecException $e) {
            echo "$label: rejected\n";
        }
    } finally {
        exec('rm -rf ' . escapeshellarg($path));
    }
}
?>
--EXPECT--
base: n1
tab: n1
data: n1,n2
xyz: (none)
base after reopen: n1
2-3 base: n1
2-3 sto: n2
chars ab: n1
chars cd: n1
chars b1: (none)
chars 12: (none)
min>max: rejected
range>1: rejected
min=0: rejected
bad char: rejected
