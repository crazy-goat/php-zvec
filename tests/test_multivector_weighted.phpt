--TEST--
Multi-Vector Query: queryMulti() with Weighted reranker
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
require_once __DIR__ . '/../php/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/test_multivector_weighted_' . uniqid();
try {
    // Create collection with two vector fields
    $schema = new ZVecSchema('test_collection');
    $schema->addString('title', withInvertIndex: true)
           ->addVectorFp32('semantic_embedding', 4, ZVecSchema::METRIC_IP)
           ->addVectorFp32('keyword_embedding', 4, ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    // Insert test documents with different semantic vs keyword characteristics
    $docs = [
        (new ZVecDoc('doc1'))
            ->setString('title', 'PHP Database Tutorial')
            ->setVectorFp32('semantic_embedding', [0.95, 0.85, 0.75, 0.65])  // High semantic match
            ->setVectorFp32('keyword_embedding', [0.4, 0.3, 0.2, 0.1]),       // Low keyword match

        (new ZVecDoc('doc2'))
            ->setString('title', 'PHP Basics')
            ->setVectorFp32('semantic_embedding', [0.3, 0.2, 0.1, 0.1])       // Low semantic match
            ->setVectorFp32('keyword_embedding', [0.95, 0.85, 0.75, 0.65]),  // High keyword match

        (new ZVecDoc('doc3'))
            ->setString('title', 'Advanced PHP')
            ->setVectorFp32('semantic_embedding', [0.85, 0.75, 0.65, 0.55])    // Good semantic
            ->setVectorFp32('keyword_embedding', [0.85, 0.75, 0.65, 0.55]),  // Good keyword

        (new ZVecDoc('doc4'))
            ->setString('title', 'Random Topic')
            ->setVectorFp32('semantic_embedding', [0.1, 0.1, 0.1, 0.1])
            ->setVectorFp32('keyword_embedding', [0.1, 0.1, 0.1, 0.1]),
    ];

    $collection->insert(...$docs);
    $collection->optimize();

    // Query vectors
    $semanticQuery = new ZVecVectorQuery('semantic_embedding', [0.9, 0.8, 0.7, 0.6]);
    $keywordQuery = new ZVecVectorQuery('keyword_embedding', [0.9, 0.8, 0.7, 0.6]);

    // Test with weights favoring semantic (0.7) over keyword (0.3)
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['semantic_embedding' => 0.7, 'keyword_embedding' => 0.3]
    );

    $results = $collection->queryMulti(
        vectorQueries: [$semanticQuery, $keywordQuery],
        reranker: $weightedReranker,
        topk: 3
    );

    // With semantic weight 0.7, doc1 should rank higher than doc2
    $pks = array_map(fn($r) => $r->getPk(), $results);
    $doc1Pos = array_search('doc1', $pks);
    $doc2Pos = array_search('doc2', $pks);
    assert($doc1Pos < $doc2Pos, "doc1 should rank higher than doc2 with semantic weight 0.7");

    echo "Weighted query works: semantic_weight=0.7, doc1 ranks higher than doc2\n";

    // Test with reversed weights favoring keyword
    $weightedReranker2 = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['semantic_embedding' => 0.3, 'keyword_embedding' => 0.7]
    );

    $results2 = $collection->queryMulti(
        vectorQueries: [$semanticQuery, $keywordQuery],
        reranker: $weightedReranker2,
        topk: 3
    );

    // With keyword weight 0.7, doc2 should rank higher than doc1
    $pks2 = array_map(fn($r) => $r->getPk(), $results2);
    $doc1Pos2 = array_search('doc1', $pks2);
    $doc2Pos2 = array_search('doc2', $pks2);
    assert($doc2Pos2 < $doc1Pos2, "doc2 should rank higher than doc1 with keyword weight 0.7");

    echo "Weighted query works: keyword_weight=0.7, doc2 ranks higher than doc1\n";

    // Test outputFields
    $resultsWithFields = $collection->queryMulti(
        vectorQueries: [$semanticQuery, $keywordQuery],
        reranker: $weightedReranker,
        topk: 2,
        outputFields: ['title']
    );

    // Verify we got title field
    foreach ($resultsWithFields as $result) {
        $title = $result->doc->getString('title');
        assert(!empty($title), "Title should not be empty");
    }

    echo "queryMulti with outputFields works\n";

    $collection->close();
    echo "PASS\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Weighted query works: semantic_weight=0.7, doc1 ranks higher than doc2
Weighted query works: keyword_weight=0.7, doc2 ranks higher than doc1
queryMulti with outputFields works
PASS
