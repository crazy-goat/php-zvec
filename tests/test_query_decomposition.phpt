--TEST--
query() decomposition: resolveQueryParams, executeQuery, executeQueryFp64 helpers
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_decomposition_' . uniqid();
try {
    // Setup: create collection with FP32 vector field
    $schema = new ZVecSchema('query_decomp');
    $schema->addVectorFp32('vec', dimension: 4, metricType: ZVecSchema::METRIC_IP)
           ->addString('category', nullable: false, withInvertIndex: true)
           ->addString('title', nullable: false);

    $coll = ZVec::create($path, $schema);

    // Insert test documents
    $docs = [
        (new ZVecDoc('d1'))->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0])->setString('category', 'A')->setString('title', 'Alpha'),
        (new ZVecDoc('d2'))->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0])->setString('category', 'B')->setString('title', 'Beta'),
        (new ZVecDoc('d3'))->setVectorFp32('vec', [0.0, 0.0, 1.0, 0.0])->setString('category', 'A')->setString('title', 'Gamma'),
        (new ZVecDoc('d4'))->setVectorFp32('vec', [0.0, 0.0, 0.0, 1.0])->setString('category', 'B')->setString('title', 'Delta'),
    ];
    $coll->insert(...$docs);
    $coll->optimize();

    // Test 1: query() with string fieldName works (uses executeQuery helper)
    $results = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 2);
    assert(count($results) >= 1, 'query() with string fieldName must return results');
    assert($results[0]->getPk() !== null, 'Results must have PK');
    echo "1. query() with string fieldName works\n";

    // Test 2: query() with ZVecVectorQuery object works (uses resolveQueryParams helper)
    $vq = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $vq->setTopk(2);
    $results2 = $coll->query($vq);
    assert(count($results2) >= 1, 'query() with ZVecVectorQuery must return results');
    echo "2. query() with ZVecVectorQuery object works\n";

    // Test 3: query() with outputFields works (tests output fields path in executeQuery)
    $results3 = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 2, outputFields: ['category', 'title']);
    assert(count($results3) >= 1, 'query() with outputFields must return results');
    echo "3. query() with outputFields works\n";

    // Test 4: query() with filter works (tests filter path in executeQuery)
    $results4 = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 4, filter: 'category = "A"');
    assert(count($results4) >= 1, 'query() with filter must return results');
    foreach ($results4 as $doc) {
        assert($doc->getString('category') === 'A', 'Filtered results must match filter');
    }
    echo "4. query() with filter works\n";

    // Test 5: query() with queryParamType works (tests extended query path in executeQuery)
    $results5 = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 2, queryParamType: ZVec::QUERY_PARAM_HNSW, hnswEf: 200);
    assert(count($results5) >= 1, 'query() with queryParamType must return results');
    echo "5. query() with queryParamType works\n";

    // Test 6: query() with includeVector works
    $results6 = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 1, includeVector: true);
    assert(count($results6) >= 1, 'query() with includeVector must return results');
    $vec = $results6[0]->getVectorFp32('vec');
    assert($vec !== null, 'Results with includeVector must have vector data');
    assert(count($vec) === 4, 'Vector must have correct dimension');
    echo "6. query() with includeVector works\n";

    // Test 7: queryWithReranker() uses resolveQueryParams + executeQuery helpers
    $vqRerank = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $vqRerank->setTopk(2);
    $reranker = new ZVecRrfReRanker(topn: 2, rankConstant: 60);
    $results7 = $coll->queryWithReranker($vqRerank, reranker: $reranker);
    assert(count($results7) >= 1, 'queryWithReranker() must return results');
    echo "7. queryWithReranker() with ZVecVectorQuery works\n";

    // Test 8: queryWithReranker() with string fieldName
    $results8 = $coll->queryWithReranker('vec', [1.0, 0.0, 0.0, 0.0], topk: 2, reranker: $reranker);
    assert(count($results8) >= 1, 'queryWithReranker() with string fieldName must return results');
    echo "8. queryWithReranker() with string fieldName works\n";

    // Test 9: queryWithReranker() with outputFields
    $results9 = $coll->queryWithReranker('vec', [1.0, 0.0, 0.0, 0.0], topk: 2, outputFields: ['category'], reranker: $reranker);
    assert(count($results9) >= 1, 'queryWithReranker() with outputFields must return results');
    echo "9. queryWithReranker() with outputFields works\n";

    // Test 10: Validation works through resolveQueryParams
    $validationPassed = false;
    try {
        $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 0);
    } catch (ZVecException $e) {
        $validationPassed = str_contains($e->getMessage(), 'topk must be a positive integer');
    }
    assert($validationPassed, 'resolveQueryParams must validate topk > 0');
    echo "10. resolveQueryParams validates topk\n";

    // Test 11: Validation works for empty fieldName
    $validationPassed2 = false;
    try {
        $coll->query('', [1.0, 0.0, 0.0, 0.0]);
    } catch (ZVecException $e) {
        $validationPassed2 = str_contains($e->getMessage(), 'Field name must not be empty');
    }
    assert($validationPassed2, 'resolveQueryParams must validate fieldName');
    echo "11. resolveQueryParams validates fieldName\n";

    // Test 12: ZVecVectorQuery with docId throws
    $vqDocId = new ZVecVectorQuery('vec', [1.0, 0.0, 0.0, 0.0]);
    $vqDocId->docId = 'some_doc';
    $docIdThrown = false;
    try {
        $coll->query($vqDocId);
    } catch (ZVecException $e) {
        $docIdThrown = str_contains($e->getMessage(), 'docId not yet implemented');
    }
    assert($docIdThrown, 'resolveQueryParams must throw for docId');
    echo "12. resolveQueryParams throws for docId\n";

    // Test 13: query() with combined outputFields and filter
    $results13 = $coll->query('vec', [1.0, 0.0, 0.0, 0.0], topk: 2, outputFields: ['title'], filter: 'category = "A"');
    assert(count($results13) >= 1, 'query() with combined outputFields and filter must return results');
    echo "13. query() with combined outputFields and filter works\n";

    // Test 14: query() with all params (outputFields + filter + queryParamType)
    $results14 = $coll->query(
        'vec', [1.0, 0.0, 0.0, 0.0],
        topk: 2, includeVector: true, filter: 'category = "A"',
        outputFields: ['title'], queryParamType: ZVec::QUERY_PARAM_HNSW, hnswEf: 200
    );
    assert(count($results14) >= 1, 'query() with all params must return results');
    echo "14. query() with all params works\n";

    $coll->close();
    echo "\nAll tests passed!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
1. query() with string fieldName works
2. query() with ZVecVectorQuery object works
3. query() with outputFields works
4. query() with filter works
5. query() with queryParamType works
6. query() with includeVector works
7. queryWithReranker() with ZVecVectorQuery works
8. queryWithReranker() with string fieldName works
9. queryWithReranker() with outputFields works
10. resolveQueryParams validates topk
11. resolveQueryParams validates fieldName
12. resolveQueryParams throws for docId
13. query() with combined outputFields and filter works
14. query() with all params works

All tests passed!
