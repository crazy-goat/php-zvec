--TEST--
Full-Text Search: forFts() index + setFts() query (issue #180)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip This test uses ZVecIndexParams which only works via FFI'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fts_' . uniqid();
try {
    echo "INDEX_TYPE_FTS=" . ZVec::INDEX_TYPE_FTS . " QUERY_PARAM_FTS=" . ZVec::QUERY_PARAM_FTS . "\n";

    // --- validation on params factory ---
    try {
        ZVecIndexParams::forFts(tokenizer: '');
        echo "UNEXPECTED: empty tokenizer accepted\n";
    } catch (ZVecException $e) {
        echo "empty tokenizer rejected\n";
    }
    try {
        ZVecIndexParams::forFts(filters: ['lowercase', '']);
        echo "UNEXPECTED: empty filter accepted\n";
    } catch (ZVecException $e) {
        echo "empty filter rejected\n";
    }

    // --- validation on query builder ---
    try {
        (new ZVecVectorQuery('body', []))->setFts('', 'term');
        echo "UNEXPECTED: empty field accepted\n";
    } catch (ZVecException $e) {
        echo "empty fts field rejected\n";
    }
    try {
        (new ZVecVectorQuery('body', []))->setFts('body', 'term', '', 'XOR');
        echo "UNEXPECTED: bad operator accepted\n";
    } catch (ZVecException $e) {
        echo "bad operator rejected\n";
    }

    // --- build a collection with a FTS-indexed STRING field ---
    $schema = new ZVecSchema('fts_test');
    $schema->addString('body');
    // FTS is not a vector index, so a collection must also carry a vector field
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path, $schema);

    $c->createIndex('body', ZVecIndexParams::forFts(
        tokenizer: 'standard',
        filters: ['lowercase'],
        extraParams: '',
    ));

    $docs = [
        ['d1', 'the quick brown fox jumps over the lazy dog', [1.0, 0.0, 0.0, 0.0]],
        ['d2', 'a fast brown rabbit runs through the field', [0.0, 1.0, 0.0, 0.0]],
        ['d3', 'completely unrelated text about databases', [0.0, 0.0, 1.0, 0.0]],
        ['d4', 'foxes are clever animals', [0.0, 0.0, 0.0, 1.0]],
    ];
    foreach ($docs as [$pk, $body, $vec]) {
        $c->insert((new ZVecDoc($pk))->setString('body', $body)->setVectorFp32('vec', $vec));
    }
    $c->flush();

    // --- OR: any term matches ---
    $q = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'fox');
    echo "paramType: " . $q->queryParamType . " (expect " . ZVec::QUERY_PARAM_FTS . ")\n";
    $pks = array_map(fn($d) => $d->getPk(), $c->queryVector($q));
    sort($pks);
    echo "fox (OR): " . implode(',', $pks) . "\n";

    // --- OR with two terms widens the result set ---
    $q2 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'fox rabbit');
    $pks2 = array_map(fn($d) => $d->getPk(), $c->queryVector($q2));
    sort($pks2);
    echo "fox rabbit (OR): " . implode(',', $pks2) . "\n";

    // --- AND narrows it: every bare term must be present ---
    $q3 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'fox rabbit', '', ZVec::FTS_OPERATOR_AND);
    $pks3 = array_map(fn($d) => $d->getPk(), $c->queryVector($q3));
    sort($pks3);
    echo "fox rabbit (AND): " . implode(',', $pks3) . " (" . count($pks3) . ")\n";

    // --- lowercase folding: uppercase input still matches ---
    $q4 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'DATABASES');
    $pks4 = array_map(fn($d) => $d->getPk(), $c->queryVector($q4));
    echo "DATABASES: " . implode(',', $pks4) . "\n";

    // --- no match yields nothing ---
    $q5 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'nonexistentterm');
    echo "nonexistent: " . count($c->queryVector($q5)) . " results\n";

    // --- matchString is the alternative to queryString, never combined ---
    $q6 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', matchString: 'clever');
    $pks6 = array_map(fn($d) => $d->getPk(), $c->queryVector($q6));
    echo "matchString clever: " . implode(',', $pks6) . "\n";

    // --- upstream requires exactly one of the two sides ---
    foreach ([['query+match', 'fox', 'clever'], ['neither', '', '']] as [$label, $qs, $ms]) {
        try {
            (new ZVecVectorQuery('body', []))->setFts('body', $qs, $ms);
            echo "UNEXPECTED: $label accepted\n";
        } catch (ZVecException $e) {
            echo "$label rejected\n";
        }
    }

    // --- operator is case-insensitive on input ---
    $q7 = (new ZVecVectorQuery('body', []))->setTopk(10)->setFts('body', 'fox rabbit', '', 'and');
    echo "operator readback: " . $q7->ftsDefaultOperator . "\n";

    $c->close();
    echo "PASS: FTS works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
INDEX_TYPE_FTS=11 QUERY_PARAM_FTS=11
empty tokenizer rejected
empty filter rejected
empty fts field rejected
bad operator rejected
paramType: 11 (expect 11)
fox (OR): d1
fox rabbit (OR): d1,d2
fox rabbit (AND):  (0)
DATABASES: d3
nonexistent: 0 results
matchString clever: d4
query+match rejected
neither rejected
operator readback: AND
PASS: FTS works
