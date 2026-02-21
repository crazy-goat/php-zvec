# Reranker Parameter in query()

## Priority: LOW

## Status: TODO

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

## Changes needed

### php/ZVec.php
- Add `$reranker` parameter to `query()` method
- Define `ZVecReRanker` interface with `rerank(array $docs): array`
- The actual reranker implementations are in tasks 09 (RRF, Weighted) and 10 (API-based)

### Notes
- Depends on task 09 (reranker implementations)
- Depends on task 05 (multi-vector query) for fusion rerankers
- Semantic rerankers work with single-vector too (two-stage retrieval)
- This is the PHP-side integration point
