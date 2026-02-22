# Reranker Parameter in query()

## Priority: LOW

## Status: DONE

## Description

Python SDK's `query()` accepts a `reranker` parameter for post-processing search results. This is used for:
1. Multi-vector fusion (RRF, Weighted) 
2. Semantic reranking (Cross-encoder, API-based)
3. Two-stage retrieval (recall top-100, rerank to top-10)

## Python API

```python
# Two-stage retrieval with semantic reranker
result = collection.query(
    vectors=VectorQuery("embedding", vector=[0.1, ...]),
    topk=100,
    reranker=DefaultLocalReRanker(
        query="machine learning",
        rerank_field="content",
        topn=10
    ),
)

# Multi-vector with RRF fusion
result = collection.query(
    vectors=[
        VectorQuery("dense", vector=[0.1, ...]),
        VectorQuery("sparse", vector={1: 0.5, ...}),
    ],
    topk=50,
    reranker=RrfReRanker(topn=10),
)
```

## Implementation

### php/ZVecReRanker.php (NEW)
- Created `ZVecReRanker` interface with `rerank(array $docs): array` method
- Defines contract for all reranker implementations

### php/ZVecRrfReRanker.php
- Updated to implement `ZVecReRanker` interface

### php/ZVecWeightedReRanker.php
- Updated to implement `ZVecReRanker` interface

### php/ZVec.php
- Added `$reranker` parameter to `query()` method (type: `?ZVecReRanker`)
- When reranker is provided, query fetches `max(topk * 2, 100)` results for two-stage retrieval
- Results are passed to reranker and returned as `ZVecRerankedDoc[]`
- Updated PHPDoc return type: `ZVecDoc[]|ZVecRerankedDoc[]`

### tests/test_reranker_in_query.phpt (NEW)
- Comprehensive test covering:
  - Query without reranker (baseline)
  - Query with RRF reranker (two-stage retrieval)
  - Query with Weighted reranker
  - Query with ZVecVectorQuery and reranker

## Usage

```php
// Two-stage retrieval with RRF reranker
$rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
$results = $collection->query(
    'embedding',
    [1.0, 0.0, 0.0, 0.0],
    topk: 3,
    reranker: $rrfReranker
);
// Returns: ZVecRerankedDoc[]

// With Weighted reranker
$weightedReranker = new ZVecWeightedReRanker(
    topn: 3,
    metricType: ZVecSchema::METRIC_L2,
    weights: ['embedding' => 1.0]
);
$results = $collection->query(
    'embedding',
    [1.0, 0.0, 0.0, 0.0],
    topk: 3,
    reranker: $weightedReranker
);
```

## Notes
- Multi-vector fusion (passing multiple vectors) is still in task 05
- API-based rerankers (OpenAI, Qwen) are in task 10
- All 50 tests pass including new test

