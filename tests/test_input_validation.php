<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$passed = 0;
$failed = 0;

function testValidation(string $name, callable $fn, string $expectedMessage): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "FAIL: $name - no exception thrown\n";
        $failed++;
    } catch (ZVecException $e) {
        if (str_contains($e->getMessage(), $expectedMessage)) {
            echo "PASS: $name\n";
            $passed++;
        } else {
            echo "FAIL: $name - expected message containing '$expectedMessage', got: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

// ZVecSchema validation
testValidation(
    'ZVecSchema empty name',
    fn() => new ZVecSchema(''),
    'Schema name must not be empty'
);

// ZVec::create() validation
testValidation(
    'ZVec::create() empty path',
    fn() => ZVec::create('', new ZVecSchema('test')),
    'Path must not be empty'
);

// ZVec::open() validation
testValidation(
    'ZVec::open() empty path',
    fn() => ZVec::open(''),
    'Path must not be empty'
);

// Vector schema dimension validation
$schema = new ZVecSchema('test_schema');
testValidation(
    'addVectorFp32() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorFp32('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorFp32() dimension negative',
    fn() => (new ZVecSchema('s'))->addVectorFp32('v', -1),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorFp64() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorFp64('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorInt8() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorInt8('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorFp16() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorFp16('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorInt4() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorInt4('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorInt16() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorInt16('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorBinary32() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorBinary32('v', 0),
    'Dimension must be a positive integer'
);

testValidation(
    'addVectorBinary64() dimension 0',
    fn() => (new ZVecSchema('s'))->addVectorBinary64('v', 0),
    'Dimension must be a positive integer'
);

// ZVecVectorQuery validation
testValidation(
    'ZVecVectorQuery empty field name',
    fn() => new ZVecVectorQuery('', [0.1, 0.2, 0.3]),
    'Field name must not be empty'
);

// ZVecGroupByVectorQuery validation
testValidation(
    'ZVecGroupByVectorQuery empty field name',
    fn() => new ZVecGroupByVectorQuery('', [0.1, 0.2, 0.3], 'category'),
    'Field name must not be empty'
);

testValidation(
    'ZVecGroupByVectorQuery empty groupByField',
    fn() => new ZVecGroupByVectorQuery('v', [0.1, 0.2, 0.3], ''),
    'Group by field must not be empty'
);

testValidation(
    'ZVecGroupByVectorQuery groupCount=0',
    fn() => new ZVecGroupByVectorQuery('v', [0.1, 0.2, 0.3], 'category', groupCount: 0),
    'groupCount must be a positive integer'
);

testValidation(
    'ZVecGroupByVectorQuery groupTopk=0',
    fn() => new ZVecGroupByVectorQuery('v', [0.1, 0.2, 0.3], 'category', groupTopk: 0),
    'groupTopk must be a positive integer'
);

// ZVecIndexParams validation
testValidation(
    'ZVecIndexParams::forHnsw() m=0',
    fn() => ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, m: 0),
    'm must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forHnsw() efConstruction=0',
    fn() => ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, efConstruction: 0),
    'efConstruction must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forHnsw() m negative',
    fn() => ZVecIndexParams::forHnsw(ZVecSchema::METRIC_IP, m: -1),
    'm must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forHnswRabitq() m=0',
    fn() => ZVecIndexParams::forHnswRabitq(ZVecSchema::METRIC_IP, m: 0),
    'm must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forIvf() nList=0',
    fn() => ZVecIndexParams::forIvf(ZVecSchema::METRIC_IP, nList: 0),
    'nList must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forIvf() nIters=0',
    fn() => ZVecIndexParams::forIvf(ZVecSchema::METRIC_IP, nIters: 0),
    'nIters must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forVamana() maxDegree=0',
    fn() => ZVecIndexParams::forVamana(ZVecSchema::METRIC_IP, maxDegree: 0),
    'maxDegree must be a positive integer'
);

testValidation(
    'ZVecIndexParams::forVamana() searchListSize=0',
    fn() => ZVecIndexParams::forVamana(ZVecSchema::METRIC_IP, searchListSize: 0),
    'searchListSize must be a positive integer'
);

// Collection method validation (requires valid collection)
$path = __DIR__ . '/../test_dbs/input_validation_' . uniqid();
$schema = new ZVecSchema('validation_test');
$schema->addInt64('id')
    ->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP);

try {
    $c = ZVec::create($path, $schema);

    // Query validation
    testValidation(
        'query() topk=0',
        fn() => $c->query('vec', [0.1, 0.2, 0.3, 0.4], topk: 0),
        'topk must be a positive integer'
    );

    testValidation(
        'query() topk negative',
        fn() => $c->query('vec', [0.1, 0.2, 0.3, 0.4], topk: -5),
        'topk must be a positive integer'
    );

    testValidation(
        'query() empty field name',
        fn() => $c->query('', [0.1, 0.2, 0.3, 0.4]),
        'Field name must not be empty'
    );

    // queryFp64 validation
    testValidation(
        'queryFp64() topk=0',
        fn() => $c->queryFp64('vec', [0.1, 0.2, 0.3, 0.4], topk: 0),
        'topk must be a positive integer'
    );

    testValidation(
        'queryFp64() empty field name',
        fn() => $c->queryFp64('', [0.1, 0.2, 0.3, 0.4]),
        'Field name must not be empty'
    );

    // queryByFilter validation
    testValidation(
        'queryByFilter() topk=0',
        fn() => $c->queryByFilter('id > 0', topk: 0),
        'topk must be a positive integer'
    );

    // delete validation
    testValidation(
        'delete() no pks',
        fn() => $c->delete(),
        'At least one PK is required'
    );

    // fetch validation
    testValidation(
        'fetch() no pks',
        fn() => $c->fetch(),
        'At least one PK is required'
    );

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "\nResults: $passed passed, $failed failed\n";
if ($failed > 0) {
    exit(1);
}
?>
