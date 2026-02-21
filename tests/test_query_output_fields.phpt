--TEST--
Query operations: outputFields parameter for field selection
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_output_' . uniqid();

try {
    $schema = new ZVecSchema('output_fields_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('title', nullable: true, withInvertIndex: true)
        ->addString('description', nullable: true)
        ->addFloat('score', nullable: true)
        ->addDouble('rating', nullable: true)
        ->addBool('published', nullable: true, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert a document with all fields
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setString('title', 'Test Document')
        ->setString('description', 'This is a detailed description')
        ->setFloat('score', 95.5)
        ->setDouble('rating', 4.8)
        ->setBool('published', true)
        ->setVectorFp32('embedding', [1.0, 0.5, 0.3, 0.2]);
    $c->insert($doc);

    $c->optimize();
    echo "Inserted and optimized\n";

    // Test 1: Query returning all fields (default - no outputFields specified)
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 1, includeVector: false);
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getInt64('id') === 1, 'id should be present');
    assert($results[0]->getString('title') === 'Test Document', 'title should be present');
    assert($results[0]->getString('description') === 'This is a detailed description', 'description should be present');
    assert($results[0]->getFloat('score') === 95.5, 'score should be present');
    assert($results[0]->getDouble('rating') === 4.8, 'rating should be present');
    assert($results[0]->getBool('published') === true, 'published should be present');
    echo "Query returning all fields OK\n";

    // Test 2: Query with specific outputFields - single field
    $results = $c->query(
        'embedding', 
        [1.0, 0.0, 0.0, 0.0], 
        topk: 1, 
        includeVector: false,
        outputFields: ['id', 'title']
    );
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getInt64('id') === 1, 'id should be present');
    assert($results[0]->getString('title') === 'Test Document', 'title should be present');
    // Other fields should be null (not retrieved)
    assert($results[0]->getString('description') === null, 'description should be null (not requested)');
    assert($results[0]->getFloat('score') === null, 'score should be null (not requested)');
    assert($results[0]->getDouble('rating') === null, 'rating should be null (not requested)');
    assert($results[0]->getBool('published') === null, 'published should be null (not requested)');
    echo "Query with specific outputFields (2 fields) OK\n";

    // Test 3: Query with outputFields - all scalar fields, no vector
    $results = $c->query(
        'embedding', 
        [1.0, 0.0, 0.0, 0.0], 
        topk: 1, 
        includeVector: false,
        outputFields: ['id', 'title', 'description', 'score', 'rating', 'published']
    );
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getInt64('id') === 1, 'id should be present');
    assert($results[0]->getString('title') === 'Test Document', 'title should be present');
    assert($results[0]->getString('description') === 'This is a detailed description', 'description should be present');
    assert($results[0]->getFloat('score') === 95.5, 'score should be present');
    assert($results[0]->getDouble('rating') === 4.8, 'rating should be present');
    assert($results[0]->getBool('published') === true, 'published should be present');
    echo "Query with all scalar outputFields OK\n";

    // Test 4: Query with outputFields including vector
    // Note: outputFields for vector fields may not be supported by the backend
    // Using includeVector=true instead to get vector data
    $results = $c->query(
        'embedding', 
        [1.0, 0.0, 0.0, 0.0], 
        topk: 1, 
        includeVector: true  // Use includeVector to get vector
    );
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getInt64('id') === 1, 'id should be present');
    // When includeVector=true, vector should be retrievable
    $vector = $results[0]->getVectorFp32('embedding');
    assert($vector !== null, 'Vector should be present with includeVector=true');
    assert(count($vector) === 4, 'Vector should have 4 dimensions');
    echo "Query with includeVector=true for vector OK\n";

    // Test 5: Query with outputFields - only ID
    $results = $c->query(
        'embedding', 
        [1.0, 0.0, 0.0, 0.0], 
        topk: 1, 
        includeVector: false,
        outputFields: ['id']
    );
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getInt64('id') === 1, 'id should be present');
    assert($results[0]->getString('title') === null, 'title should be null');
    assert($results[0]->getPk() === 'doc1', 'Primary key should always be accessible');
    echo "Query with only ID in outputFields OK\n";

    // Test 6: queryByFilter with outputFields
    $results = $c->queryByFilter("id = 1", topk: 1, outputFields: ['title', 'score']);
    assert(count($results) === 1, 'Should return 1 result');
    assert($results[0]->getString('title') === 'Test Document', 'title should be present');
    assert($results[0]->getFloat('score') === 95.5, 'score should be present');
    assert($results[0]->getInt64('id') === null, 'id should be null (not in outputFields)');
    assert($results[0]->getString('description') === null, 'description should be null');
    echo "queryByFilter with outputFields OK\n";

    // Test 7: Verify missing fields are null, not error
    $results = $c->query(
        'embedding', 
        [1.0, 0.0, 0.0, 0.0], 
        topk: 1, 
        includeVector: false,
        outputFields: ['id']  // Only id
    );
    
    // Accessing non-requested fields should return null without error
    $description = $results[0]->getString('description');
    $score = $results[0]->getFloat('score');
    $rating = $results[0]->getDouble('rating');
    $published = $results[0]->getBool('published');
    
    assert($description === null, 'Unrequested string field should be null');
    assert($score === null, 'Unrequested float field should be null');
    assert($rating === null, 'Unrequested double field should be null');
    assert($published === null, 'Unrequested bool field should be null');
    echo "Missing fields return null correctly OK\n";

    // Note: Empty outputFields array is not supported (would create 0-length FFI array)
    // Use outputFields: ['pk'] or rely on default behavior instead

    $c->close();
    echo "PASS: outputFields parameter works correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted and optimized
Query returning all fields OK
Query with specific outputFields (2 fields) OK
Query with all scalar outputFields OK
Query with includeVector=true for vector OK
Query with only ID in outputFields OK
queryByFilter with outputFields OK
Missing fields return null correctly OK
PASS: outputFields parameter works correctly
