--TEST--
Multi-Vector Query: queryMulti() with RRF reranker
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
require_once __DIR__ . '/../php/ZVecRrfReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/test_multivector_' . uniqid();
try {
    // Create collection with two vector fields (semantic and keyword)
    $schema = new ZVecSchema('test_collection');
    $schema->addString('title', withInvertIndex: true)
           ->addVectorFp32('semantic_embedding', 4, ZVecSchema::METRIC_IP)
           ->addVectorFp32('keyword_embedding', 4, ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    // Insert test documents
    $docs = [
        (new ZVecDoc('doc1'))
            ->setString('title', 'PHP Vector Database')
            ->setVectorFp32('semantic_embedding', [0.9, 0.8, 0.7, 0.6])
            ->setVectorFp32('keyword_embedding', [0.3, 0.2, 0.1, 0.1]),

        (new ZVecDoc('doc2'))
            ->setString('title', 'Machine Learning Guide')
            ->setVectorFp32('semantic_embedding', [0.7, 0.9, 0.6, 0.8])
            ->setVectorFp32('keyword_embedding', [0.8, 0.7, 0.6, 0.5]),

        (new ZVecDoc('doc3'))
            ->setString('title', 'Introduction to PHP')
            ->setVectorFp32('semantic_embedding', [0.5, 0.4, 0.3, 0.2])
            ->setVectorFp32('keyword_embedding', [0.9, 0.8, 0.7, 0.6]),

        (new ZVecDoc('doc4'))
            ->setString('title', 'Vector Search Tutorial')
            ->setVectorFp32('semantic_embedding', [0.8, 0.7, 0.9, 0.6])
            ->setVectorFp32('keyword_embedding', [0.4, 0.3, 0.2, 0.1]),
    ];

    $collection->insert(...$docs);
    $collection->optimize();

    // Create vector queries for both fields
    $semanticQuery = new ZVecVectorQuery('semantic_embedding', [0.8, 0.8, 0.7, 0.7]);
    $keywordQuery = new ZVecVectorQuery('keyword_embedding', [0.8, 0.7, 0.6, 0.5]);

    // Multi-vector query with RRF reranker
    $reranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $results = $collection->queryMulti(
        vectorQueries: [$semanticQuery, $keywordQuery],
        reranker: $reranker,
        topk: 3
    );

    // Verify results
    assert(count($results) === 3, "Should return exactly 3 results");
    assert($results[0] instanceof ZVecRerankedDoc, "Result should be ZVecRerankedDoc");

    // Check that all documents are from our test set
    $pks = array_map(fn($r) => $r->getPk(), $results);
    foreach ($pks as $pk) {
        assert(in_array($pk, ['doc1', 'doc2', 'doc3', 'doc4']), "Unknown PK: $pk");
    }

    echo "queryMulti works: " . count($results) . " results\n";
    echo "First result: {$results[0]->getPk()} score=" . round($results[0]->combinedScore, 4) . "\n";

    // Test with filter
    $filteredResults = $collection->queryMulti(
        vectorQueries: [$semanticQuery, $keywordQuery],
        reranker: $reranker,
        topk: 3,
        filter: "title LIKE '%PHP%'"
    );

    // All filtered results should contain 'PHP' in title
    foreach ($filteredResults as $result) {
        $title = $result->doc->getString('title');
        assert(strpos($title, 'PHP') !== false, "Filtered result should contain 'PHP': $title");
    }

    echo "queryMulti with filter works: " . count($filteredResults) . " results\n";

    // Test empty queries error
    try {
        $collection->queryMulti([], $reranker);
        assert(false, "Should throw exception for empty queries");
    } catch (ZVecException $e) {
        echo "Empty queries error: " . $e->getMessage() . "\n";
    }

    $collection->close();
    echo "PASS\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
queryMulti works: 3 results
First result: doc2 score=0.0323
queryMulti with filter works: 2 results
Empty queries error: At least one vector query is required
PASS
