--TEST--
FTS filter "stemmer": Snowball stemming, stemmer_lang, and rejected filter names
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
        tokenizer: 'standard',
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
function ftsPks(ZVec $c, string $query): string
{
    $q = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', $query);
    $pks = array_map(static fn(ZVecDoc $d): string => $d->getPk(), $c->queryVector($q));
    sort($pks);
    return $pks === [] ? '(none)' : implode(',', $pks);
}

$docs = [
    'd1' => 'the quick brown fox jumps',
    'd2' => 'foxes are clever animals',
    'd3' => 'running runners run daily',
    'd4' => 'completely unrelated text',
];

// Without stemming each form is indexed literally, so "fox" and "foxes" are
// different terms. This is the baseline the stemmer below is measured against.
[$c, $p] = makeCollection('fts_stem_base', $docs, ['lowercase']);
try {
    echo 'base fox: ' . ftsPks($c, 'fox') . "\n";
    echo 'base foxes: ' . ftsPks($c, 'foxes') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// Snowball english is the default language.
[$c, $p] = makeCollection('fts_stem_en', $docs, ['lowercase', 'stemmer']);
try {
    echo 'fox: ' . ftsPks($c, 'fox') . "\n";
    echo 'foxes: ' . ftsPks($c, 'foxes') . "\n";
    echo 'run: ' . ftsPks($c, 'run') . "\n";
    echo 'running: ' . ftsPks($c, 'running') . "\n";

    $c->close();
    $c = ZVec::open($p);
    echo 'fox after reopen: ' . ftsPks($c, 'fox') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

[$c, $p] = makeCollection('fts_stem_porter', $docs, ['lowercase', 'stemmer'], '{"stemmer_lang":"porter"}');
try {
    echo 'porter fox: ' . ftsPks($c, 'fox') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// A different Snowball language, with German umlauts in the documents.
$german = ['d1' => 'Häuser und Katzen', 'd2' => 'Haus'];
[$c, $p] = makeCollection('fts_stem_de', $german, ['lowercase', 'stemmer'], '{"stemmer_lang":"german"}');
try {
    echo 'german haus: ' . ftsPks($c, 'haus') . "\n";
    echo 'german katze: ' . ftsPks($c, 'katze') . "\n";
} finally {
    exec('rm -rf ' . escapeshellarg($p));
}

// The two bad examples from the old forFts() docblock, which showed values that
// upstream rejects. Kept here so the docblock fix is backed by a test.
$errors = [
    'unknown lang' => [['lowercase', 'stemmer'], '{"stemmer_lang":"klingon"}'],
    'stemmer_en' => [['lowercase', 'stemmer_en'], ''],
    'not json' => [['lowercase'], 'stemmer_lang=en'],
    'bad tokenizer' => [['lowercase'], ''],
];
foreach ($errors as $label => [$filters, $extra]) {
    $path = __DIR__ . '/../test_dbs/fts_stem_err_' . uniqid();
    try {
        $schema = new ZVecSchema('fts_stem_err');
        $schema->addString('body')
            ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $c = ZVec::create($path, $schema);
        try {
            $c->createIndex('body', ZVecIndexParams::forFts(
                tokenizer: $label === 'bad tokenizer' ? 'bogus' : 'standard',
                filters: $filters,
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
base fox: d1
base foxes: d2
fox: d1,d2
foxes: d1,d2
run: d3
running: d3
fox after reopen: d1,d2
porter fox: d1,d2
german haus: d1,d2
german katze: d1
unknown lang: rejected
stemmer_en: rejected
not json: rejected
bad tokenizer: rejected
