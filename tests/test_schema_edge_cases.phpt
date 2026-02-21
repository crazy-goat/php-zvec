--TEST--
Schema edge cases: empty collections, unicode, long field names
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: Empty collection operations
$path1 = __DIR__ . '/../test_dbs/schema_edge_empty_' . uniqid();

try {
    $schema1 = new ZVecSchema('empty_test');
    $schema1->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c1 = ZVec::create($path1, $schema1);

    $results = $c1->query('v', [0.1, 0.2, 0.3, 0.4], topk: 5);
    if (count($results) !== 0) {
        echo "FAIL: Query on empty collection should return 0 results\n";
        exit(1);
    }
    echo "Query on empty collection returns 0 results OK\n";

    $stats = $c1->stats();
    if (strpos($stats, 'doc_count:0') === false) {
        echo "FAIL: Empty collection should have doc_count:0\n";
        exit(1);
    }
    echo "Stats on empty collection OK\n";

    $c1->close();
} finally {
    exec("rm -rf " . escapeshellarg($path1));
}

// Test 2: Collection with primarily scalar fields
$path2 = __DIR__ . '/../test_dbs/schema_edge_scalar_' . uniqid();

try {
    $schema2 = new ZVecSchema('scalar_test');
    $schema2->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c2 = ZVec::create($path2, $schema2);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setString('name', 'test_name')
        ->setFloat('score', 3.14)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c2->insert($doc);

    $results = $c2->queryByFilter('id = 1', topk: 10);
    if (count($results) !== 1 || $results[0]->getString('name') !== 'test_name') {
        echo "FAIL: Should find 1 document by filter\n";
        exit(1);
    }
    echo "Primarily scalar fields collection operations OK\n";

    $c2->close();
} finally {
    exec("rm -rf " . escapeshellarg($path2));
}

// Test 3: Collection with primarily vector fields
$path3 = __DIR__ . '/../test_dbs/schema_edge_vectors_' . uniqid();

try {
    $schema3 = new ZVecSchema('vectors_test');
    $schema3->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v1', dimension: 4, metricType: ZVecSchema::METRIC_IP)
        ->addVectorFp32('v2', dimension: 8, metricType: ZVecSchema::METRIC_L2);

    $c3 = ZVec::create($path3, $schema3);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setVectorFp32('v1', [0.1, 0.2, 0.3, 0.4])
        ->setVectorFp32('v2', [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8]);
    $c3->insert($doc);

    $results = $c3->query('v1', [0.1, 0.2, 0.3, 0.4], topk: 5);
    if (count($results) !== 1) {
        echo "FAIL: Should find 1 document\n";
        exit(1);
    }
    echo "Primarily vector fields collection operations OK\n";

    $c3->close();
} finally {
    exec("rm -rf " . escapeshellarg($path3));
}

// Test 4: Long field names
$path4 = __DIR__ . '/../test_dbs/schema_edge_long_' . uniqid();

try {
    $longFieldName = 'long_field_name_20_123';
    $schema4 = new ZVecSchema('long_names_test');
    $schema4->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addInt64($longFieldName, nullable: true, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c4 = ZVec::create($path4, $schema4);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setInt64($longFieldName, 999)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c4->insert($doc);

    $fetched = $c4->fetch('doc1');
    if (count($fetched) !== 1 || $fetched[0]->getInt64($longFieldName) !== 999) {
        echo "FAIL: Long field name should work\n";
        exit(1);
    }
    echo "Long field name operations OK\n";

    $c4->close();
} finally {
    exec("rm -rf " . escapeshellarg($path4));
}

// Test 5: Unicode in field values
$path5 = __DIR__ . '/../test_dbs/schema_edge_unicode_' . uniqid();

try {
    $schema5 = new ZVecSchema('unicode_test');
    $schema5->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c5 = ZVec::create($path5, $schema5);

    $unicodeNames = [
        'Japanese: こんにちは',
        'Chinese: 你好世界',
        'Arabic: مرحبا',
        'Emoji: 🎉🚀💯',
        'Polish: Zażółć gęślą jaźń'
    ];

    foreach ($unicodeNames as $i => $name) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setString('name', $name)
            ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
        $c5->insert($doc);
    }

    $results = $c5->queryByFilter("id < 5", topk: 10);
    if (count($results) !== 5) {
        echo "FAIL: Should find 5 documents\n";
        exit(1);
    }

    foreach ($results as $doc) {
        $id = $doc->getInt64('id');
        $name = $doc->getString('name');
        if ($name !== $unicodeNames[$id]) {
            echo "FAIL: Unicode name should match for id=$id\n";
            exit(1);
        }
    }
    echo "Unicode field values preserved OK\n";

    $c5->close();
} finally {
    exec("rm -rf " . escapeshellarg($path5));
}

echo "All edge case scenarios work\n";
?>
--EXPECT--
Query on empty collection returns 0 results OK
Stats on empty collection OK
Primarily scalar fields collection operations OK
Primarily vector fields collection operations OK
Long field name operations OK
Unicode field values preserved OK
All edge case scenarios work
