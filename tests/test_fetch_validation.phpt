--TEST--
fetch() validation: named-arg pks: rejected without PHP warning (#205), mixing scalar PK with outputFields array rejected with hint, named args cannot bypass validation, string-keyed arrays reindexed
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ZVec.php';

$warnings = [];
set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
    if ($errno === E_USER_DEPRECATED && str_contains($message, ' is deprecated, use createIndex()')) {
        return true;
    }
    $warnings[] = "[$errno] $message";
    return true;
});

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fetch_validation_' . uniqid();
try {
    $schema = new ZVecSchema('fetch_validation');
    $schema->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    for ($i = 1; $i <= 3; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)->setString('name', "User$i")->setVectorFp32('v', [1.0 * $i, 0.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    $c->flush();

    $case = static function (string $label, callable $fn) use (&$warnings): void {
        $warnings = [];
        try {
            $fn();
            echo "$label: NO EXCEPTION\n";
        } catch (ZVecException $e) {
            if (str_contains($e->getMessage(), 'Named argument pks')) {
                echo "$label: ZVecException (named-arg hint)\n";
            } elseif (str_contains($e->getMessage(), 'Mixing scalar PKs')) {
                echo "$label: ZVecException (mixing hint)\n";
            } elseif (str_contains($e->getMessage(), 'Unknown named argument')) {
                echo "$label: ZVecException (unknown named arg)\n";
            } else {
                echo "$label: ZVecException (other: {$e->getMessage()})\n";
            }
        }
        echo $warnings === [] ? "$label: no PHP warning\n" : "$label: UNEXPECTED WARNING: " . implode(' | ', $warnings) . "\n";
    };

    // #205: named-arg pks: must throw a clear ZVecException, not warn
    $case('fetch(pks: [doc1])', fn() => $c->fetch(pks: ['doc1']));
    // hoisted guard: pks: cannot bypass validation next to a positional array
    $case('fetch([doc1], pks: [x])', fn() => $c->fetch(['doc1'], pks: ['nonexistent']));
    $case('fetch(pks: [doc1], outputFields: [name])', fn() => $c->fetch(pks: ['doc1'], outputFields: ['name']));

    // #206 (DX part): scalar PK followed by an outputFields array
    $case('fetch(doc1, [name])', fn() => $c->fetch('doc1', ['name']));

    // unknown named argument in variadic form
    $case('fetch(doc1, foo: bar)', fn() => $c->fetch('doc1', foo: 'bar'));

    // outputFields must still be validated as an array of non-empty strings
    $case('fetch([doc1], name)', fn() => $c->fetch(['doc1'], 'name'));

    // valid forms
    if (count($c->fetch('doc1')) !== 1) {
        throw new RuntimeException('variadic form should work');
    }
    if (count($c->fetch(['doc1'], ['name'])) !== 1) {
        throw new RuntimeException('array + outputFields form should work');
    }
    echo "valid forms OK\n";

    // string-keyed arrays are reindexed (previously: native segfault);
    // result order is not guaranteed by the engine, so compare as sets
    $fetched = $c->fetch(['a' => 'doc1', 'b' => 'doc2']);
    $pks = array_map(fn($d) => $d->getPk(), $fetched);
    sort($pks);
    if (count($fetched) !== 2 || $pks !== ['doc1', 'doc2']) {
        throw new RuntimeException('string-keyed PK array should fetch doc1 and doc2');
    }
    echo "string-keyed PK array OK\n";

    $fetched = $c->fetch(['doc1', 'doc2'], ['a' => 'name', 'b' => 'id']);
    $byPk = [];
    foreach ($fetched as $d) {
        $byPk[$d->getPk()] = $d;
    }
    if (count($fetched) !== 2 || ($byPk['doc1'] ?? null)?->getString('name') !== 'User1' || ($byPk['doc1'] ?? null)?->getInt64('id') !== 1) {
        throw new RuntimeException('string-keyed outputFields should be applied');
    }
    echo "string-keyed outputFields OK\n";

    // named outputFields argument (scalar and array PK forms)
    $fetched = $c->fetch('doc1', outputFields: ['name']);
    if (count($fetched) !== 1 || $fetched[0]->getString('name') !== 'User1' || $fetched[0]->getInt64('id') !== null) {
        throw new RuntimeException('scalar PK + named outputFields should return only name');
    }
    $fetched = $c->fetch(['doc1'], outputFields: ['name']);
    if (count($fetched) !== 1 || $fetched[0]->getString('name') !== 'User1' || $fetched[0]->getInt64('id') !== null) {
        throw new RuntimeException('array PK + named outputFields should return only name');
    }
    echo "named outputFields OK\n";
} finally {
    if (isset($c)) {
        $c->destroy();
    }
    exec('rm -rf ' . escapeshellarg($path));
}
?>
--EXPECT--
fetch(pks: [doc1]): ZVecException (named-arg hint)
fetch(pks: [doc1]): no PHP warning
fetch([doc1], pks: [x]): ZVecException (named-arg hint)
fetch([doc1], pks: [x]): no PHP warning
fetch(pks: [doc1], outputFields: [name]): ZVecException (named-arg hint)
fetch(pks: [doc1], outputFields: [name]): no PHP warning
fetch(doc1, [name]): ZVecException (mixing hint)
fetch(doc1, [name]): no PHP warning
fetch(doc1, foo: bar): ZVecException (unknown named arg)
fetch(doc1, foo: bar): no PHP warning
fetch([doc1], name): ZVecException (other: outputFields must be an array of non-empty strings)
fetch([doc1], name): no PHP warning
valid forms OK
string-keyed PK array OK
string-keyed outputFields OK
named outputFields OK
