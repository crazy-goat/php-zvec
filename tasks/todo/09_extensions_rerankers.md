# Extensions: Rerankers

## Priority: LOW

## Status: TODO

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

## Changes needed

### php/ZVecRrfReRanker.php (new file)
- `rerank(array $queryResults): array` — $queryResults is `[fieldName => ZVecDoc[]]`
- Implements RRF formula

### php/ZVecWeightedReRanker.php (new file)
- Score normalization per metric type
- Weighted sum combination

### Notes
- These are standalone PHP classes, no FFI needed
- Useful only with multi-vector query (task 05)
- QwenReRanker / DefaultLocalReRanker are API-based — separate task
