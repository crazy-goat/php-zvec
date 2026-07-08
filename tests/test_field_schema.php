<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fieldschema_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id')
        ->addString('name', nullable: true)
        ->addFloat('score')
        ->addDouble('rating')
        ->addBool('active')
        ->addInt32('count')
        ->addUint32('ucount')
        ->addUint64('ubig')
        ->addVectorFp32('vec', dimension: 128, metricType: ZVecSchema::METRIC_COSINE)
        ->addVectorFp16('vecf16', dimension: 16, metricType: ZVecSchema::METRIC_L2)
        ->addVectorInt8('veci8', dimension: 32, metricType: ZVecSchema::METRIC_IP)
        ->addSparseVectorFp32('sparse', metricType: ZVecSchema::METRIC_IP)
        ->addArrayInt32('arr_i32')
        ->addArrayString('arr_str');
    $c = ZVec::create($path, $schema);

    // Test 1: Basic scalar field introspection
    $idFs = $c->getFieldSchema('id');
    echo "1 id type: " . $idFs->getDataType() . "\n";
    echo "2 id nullable: " . ($idFs->isNullable() ? '1' : '0') . "\n";
    echo "3 id isVector: " . ($idFs->isVectorField() ? '1' : '0') . "\n";
    echo "4 id isDense: " . ($idFs->isDenseVector() ? '1' : '0') . "\n";
    echo "5 id isSparse: " . ($idFs->isSparseVector() ? '1' : '0') . "\n";
    echo "6 id isArray: " . ($idFs->isArrayType() ? '1' : '0') . "\n";
    echo "7 id dim: " . $idFs->getDimension() . "\n";
    echo "8 id name: " . $idFs->getName() . "\n";
    echo "9 id elemDt: " . $idFs->getElementDataType() . "\n";

    // Test 2: Nullable string
    $nameFs = $c->getFieldSchema('name');
    echo "10 name nullable: " . ($nameFs->isNullable() ? '1' : '0') . "\n";
    echo "11 name type: " . $nameFs->getDataType() . "\n";
    echo "12 name isVector: " . ($nameFs->isVectorField() ? '1' : '0') . "\n";

    // Test 3: Vector fields
    $vecFs = $c->getFieldSchema('vec');
    echo "13 vec dim: " . $vecFs->getDimension() . "\n";
    echo "14 vec type: " . $vecFs->getDataType() . "\n";
    echo "15 vec isDense: " . ($vecFs->isDenseVector() ? '1' : '0') . "\n";
    echo "16 vec isVector: " . ($vecFs->isVectorField() ? '1' : '0') . "\n";
    echo "17 vec isSparse: " . ($vecFs->isSparseVector() ? '1' : '0') . "\n";

    // Test 4: FP16 vector
    $vf16 = $c->getFieldSchema('vecf16');
    echo "18 vecf16 type: " . $vf16->getDataType() . "\n";
    echo "19 vecf16 dim: " . $vf16->getDimension() . "\n";

    // Test 5: INT8 vector
    $vi8 = $c->getFieldSchema('veci8');
    echo "20 veci8 type: " . $vi8->getDataType() . "\n";
    echo "21 veci8 dim: " . $vi8->getDimension() . "\n";

    // Test 6: Sparse vector
    $spFs = $c->getFieldSchema('sparse');
    echo "22 sparse type: " . $spFs->getDataType() . "\n";
    echo "23 sparse isSparse: " . ($spFs->isSparseVector() ? '1' : '0') . "\n";
    echo "24 sparse isVector: " . ($spFs->isVectorField() ? '1' : '0') . "\n";
    echo "25 sparse dim: " . $spFs->getDimension() . "\n";

    // Test 7: Array field
    $arrFs = $c->getFieldSchema('arr_i32');
    echo "26 arr_i32 type: " . $arrFs->getDataType() . "\n";
    echo "27 arr_i32 isArray: " . ($arrFs->isArrayType() ? '1' : '0') . "\n";
    echo "28 arr_i32 elemDt: " . $arrFs->getElementDataType() . "\n";
    echo "29 arr_i32 elemSize: " . $arrFs->getElementDataSize() . "\n";

    // Test 8: Index info (initially none)
    echo "30 id hasIndex: " . ($idFs->hasIndex() ? '1' : '0') . "\n";
    echo "31 id hasInvert: " . ($idFs->hasInvertIndex() ? '1' : '0') . "\n";
    echo "32 id idxType: " . $idFs->getIndexType() . "\n";

    // Test 9: Create HNSW index and check
    $c->createIndex('vec', ZVecIndexParams::forHnsw(ZVecSchema::METRIC_COSINE));
    $vecFs2 = $c->getFieldSchema('vec');
    echo "33 vec hasIndex: " . ($vecFs2->hasIndex() ? '1' : '0') . "\n";
    echo "34 vec idxType: " . $vecFs2->getIndexType() . "\n";

    // Test 10: Create invert index on string
    $c->createIndex('name', ZVecIndexParams::forInvert());
    $nameFs2 = $c->getFieldSchema('name');
    echo "35 name hasInvert: " . ($nameFs2->hasInvertIndex() ? '1' : '0') . "\n";
    echo "36 name hasIndex: " . ($nameFs2->hasIndex() ? '1' : '0') . "\n";

    // Test 11: Non-existent field
    try {
        $c->getFieldSchema('nonexistent');
        echo "UNEXPECTED: Should have thrown\n";
    } catch (ZVecException $e) {
        echo "37 nonexistent: " . $e->getCode() . "\n";
    }

    // Test 12: Closed collection error
    $c->close();
    try {
        $c->getFieldSchema('id');
        echo "UNEXPECTED: Should have thrown\n";
    } catch (ZVecException $e) {
        echo "38 closed: caught\n";
    }

    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
