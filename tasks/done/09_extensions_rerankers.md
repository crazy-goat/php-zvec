# Extensions: Rerankers

## Priority: LOW

## Status: DONE

## Description

Python SDK provides rerankers for combining multi-vector search results. These are pure logic (no C++ dependency) and can be implemented entirely in PHP.

## Rerankers to implement

### RrfReRanker (Reciprocal Rank Fusion)
```python
RrfReRanker(topn=10, rank_constant=60)
# Score = 1 / (k + rank + 1)
```
- Combines results from multiple queries without needing scores
- Good default choice for multi-vector

### WeightedReRanker
```python
WeightedReRanker(
    topn=10,
    metric=MetricType.L2,
    weights={"dense": 0.7, "sparse": 0.3}
)
```
- Normalizes scores per metric type, then weighted sum
- Needs score normalization logic per metric (L2, IP, COSINE)

## Implementation

### Files created

1. **php/ZVecRerankedDoc.php** - Result wrapper class containing:
   - `ZVecDoc $doc` - the original document
   - `float $combinedScore` - combined score from reranking algorithm
   - `array $sourceRanks` - rankings from each vector field query [fieldName => rank]
   - `array $sourceScores` - original scores from each query [fieldName => score]
   - Methods: `getPk()`, `getOriginalScore()`

2. **php/ZVecRrfReRanker.php** - Reciprocal Rank Fusion implementation:
   - `__construct(int $topn = 10, int $rankConstant = 60)`
   - `rerank(array $queryResults): ZVecRerankedDoc[]`
   - Formula: `Score = 1 / (rankConstant + rank)`
   - Input: `[fieldName => ZVecDoc[]]` - ordered results from each field
   - Output: Sorted array of ZVecRerankedDoc by combined score

3. **php/ZVecWeightedReRanker.php** - Weighted score combination:
   - `__construct(int $topn = 10, int $metricType = 2, array $weights = [])`
   - `rerank(array $queryResults): ZVecRerankedDoc[]`
   - Score normalization per metric:
     - L2 (distance): inverted normalization `($max - $score) / range`
     - IP, COSINE: standard normalization `($score - $min) / range`
   - Weighted sum: `Σ(weight_i × normalized_score_i)`
   - Input: `[fieldName => ZVecDoc[]]` with field-specific weights
   - Output: Sorted array of ZVecRerankedDoc by combined score

### Test added

- **tests/test_rerankers.phpt** - Comprehensive test covering:
  - RRF reranker with two vector fields
  - Weighted reranker with custom weights
  - Edge cases (empty results, single field)
  - Result structure validation

### Example added

- **php/example_rerankers.php** - Standalone demo showing:
  - Creating collection with two vector fields (semantic + keyword)
  - Querying both fields separately
  - RRF reranker usage and results interpretation
  - Weighted reranker with different field weights
  - Single field reranking edge case
  - Accessing source ranks and scores

Run with: `php php/example_rerankers.php`

### Usage example

```php
require_once 'php/ZVecRrfReRanker.php';
require_once 'php/ZVecWeightedReRanker.php';

// Query multiple vector fields
$denseResults = $collection->query('dense_embedding', $vector1, topk: 10);
$sparseResults = $collection->query('sparse_embedding', $vector2, topk: 10);

// RRF reranking
$reranker = new ZVecRrfReRanker(topn: 5, rankConstant: 60);
$results = $reranker->rerank([
    'dense_embedding' => $denseResults,
    'sparse_embedding' => $sparseResults,
]);

// Weighted reranking
$weightedReranker = new ZVecWeightedReRanker(
    topn: 5,
    metricType: ZVecSchema::METRIC_IP,
    weights: ['dense_embedding' => 0.7, 'sparse_embedding' => 0.3]
);
$results = $weightedReranker->rerank([
    'dense_embedding' => $denseResults,
    'sparse_embedding' => $sparseResults,
]);

// Access results
foreach ($results as $result) {
    echo $result->getPk() . " score: " . $result->combinedScore . "\n";
    print_r($result->sourceRanks);  // Original rankings per field
}
```

### Notes
- These are standalone PHP classes, no FFI needed
- Useful with multi-vector query (task 05 - depends on this)
- QwenReRanker / DefaultLocalReRanker are API-based — separate task
- RRF doesn't require score normalization (works on ranks only)
- Weighted requires metric-aware normalization
- All tests pass (48/48 tests including new reranker test)
