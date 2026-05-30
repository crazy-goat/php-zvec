--TEST--
Encapsulation: Private properties with getters/setters on reranker classes
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

// Test 1: ZVecRerankedDoc - properties are private, getters work
echo "Test 1: ZVecRerankedDoc encapsulation\n";
$schema = new ZVecSchema('test_encapsulation');
$schema->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/encapsulation_' . uniqid();
try {
    $collection = ZVec::create($path, $schema);
    $doc = (new ZVecDoc('doc1'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0]);
    $collection->insert($doc);
    $collection->optimize();

    $results = $collection->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 1);
    $reranked = new ZVecRerankedDoc($results[0], 0.5, ['embedding' => 1], ['embedding' => 0.9]);

    // Verify getters work
    assert($reranked->getPk() === 'doc1', "getPk() must return document PK");
    assert($reranked->getCombinedScore() === 0.5, "getCombinedScore() must return combined score");
    assert($reranked->getOriginalScore() >= 0, "getOriginalScore() must return score");
    assert($reranked->getDoc() instanceof ZVecDoc, "getDoc() must return ZVecDoc");
    assert(count($reranked->getSourceRanks()) === 1, "getSourceRanks() must return ranks array");
    assert(count($reranked->getSourceScores()) === 1, "getSourceScores() must return scores array");
    echo "  PASS: All getters work correctly\n";

    // Verify properties are private (direct access triggers error)
    $accessError = false;
    set_error_handler(function ($errno) use (&$accessError) {
        if ($errno === E_ERROR || $errno === E_WARNING) {
            $accessError = true;
            return true;
        }
        return false;
    });
    // This would trigger a fatal error in PHP 8.2+ for readonly/private
    // We test with reflection instead
    restore_error_handler();

    $reflection = new ReflectionClass($reranked);
    foreach (['doc', 'combinedScore', 'sourceRanks', 'sourceScores'] as $prop) {
        $property = $reflection->getProperty($prop);
        assert(!$property->isPublic(), "Property '$prop' must not be public");
    }
    echo "  PASS: All properties are private\n";

    // Test 2: ZVecRrfReRanker - private properties with getters/setters
    echo "\nTest 2: ZVecRrfReRanker encapsulation\n";
    $rrf = new ZVecRrfReRanker(topn: 5, rankConstant: 30);

    // Verify getters
    assert($rrf->getTopn() === 5, "getTopn() must return topn value");
    assert($rrf->getRankConstant() === 30, "getRankConstant() must return rankConstant value");
    echo "  PASS: Getters return correct values\n";

    // Verify setters work
    $rrf->setTopn(10);
    assert($rrf->getTopn() === 10, "setTopn() must update value");

    $rrf->setRankConstant(60);
    assert($rrf->getRankConstant() === 60, "setRankConstant() must update value");
    echo "  PASS: Setters update values correctly\n";

    // Verify setters return self for fluent interface
    $result = $rrf->setTopn(3);
    assert($result === $rrf, "setTopn() must return self for fluent interface");
    echo "  PASS: Fluent interface works\n";

    // Verify properties are private
    $reflection = new ReflectionClass($rrf);
    foreach (['topn', 'rankConstant'] as $prop) {
        $property = $reflection->getProperty($prop);
        assert(!$property->isPublic(), "Property '$prop' must not be public");
    }
    echo "  PASS: All properties are private\n";

    // Test 3: ZVecWeightedReRanker - private properties with getters/setters
    echo "\nTest 3: ZVecWeightedReRanker encapsulation\n";
    $weighted = new ZVecWeightedReRanker(
        weights: ['embedding' => 1.0],
        topn: 5,
        metricType: ZVecSchema::METRIC_L2
    );

    // Verify getters
    assert($weighted->getTopn() === 5, "getTopn() must return topn value");
    assert($weighted->getMetricType() === ZVecSchema::METRIC_L2, "getMetricType() must return metricType value");
    assert($weighted->getWeights() === ['embedding' => 1.0], "getWeights() must return weights array");
    echo "  PASS: Getters return correct values\n";

    // Verify setters work
    $weighted->setTopn(20);
    assert($weighted->getTopn() === 20, "setTopn() must update value");

    $weighted->setMetricType(ZVecSchema::METRIC_IP);
    assert($weighted->getMetricType() === ZVecSchema::METRIC_IP, "setMetricType() must update value");

    $weighted->setWeights(['embedding' => 0.5, 'other' => 0.5]);
    assert($weighted->getWeights() === ['embedding' => 0.5, 'other' => 0.5], "setWeights() must update value");
    echo "  PASS: Setters update values correctly\n";

    // Verify setWeights with empty array throws exception
    $exceptionThrown = false;
    try {
        $weighted->setWeights([]);
    } catch (ZVecException $e) {
        $exceptionThrown = true;
        assert(str_contains($e->getMessage(), 'requires at least one field weight'), "Exception message must be descriptive");
    }
    assert($exceptionThrown, "setWeights([]) must throw ZVecException");
    echo "  PASS: setWeights([]) throws ZVecException\n";

    // Verify fluent interface
    $result = $weighted->setTopn(3);
    assert($result === $weighted, "setTopn() must return self for fluent interface");
    echo "  PASS: Fluent interface works\n";

    // Verify properties are private
    $reflection = new ReflectionClass($weighted);
    foreach (['topn', 'metricType', 'weights'] as $prop) {
        $property = $reflection->getProperty($prop);
        assert(!$property->isPublic(), "Property '$prop' must not be public");
    }
    echo "  PASS: All properties are private\n";

    $collection->close();
    echo "\nAll encapsulation tests passed!\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Test 1: ZVecRerankedDoc encapsulation
  PASS: All getters work correctly
  PASS: All properties are private

Test 2: ZVecRrfReRanker encapsulation
  PASS: Getters return correct values
  PASS: Setters update values correctly
  PASS: Fluent interface works
  PASS: All properties are private

Test 3: ZVecWeightedReRanker encapsulation
  PASS: Getters return correct values
  PASS: Setters update values correctly
  PASS: setWeights([]) throws ZVecException
  PASS: Fluent interface works
  PASS: All properties are private

All encapsulation tests passed!
