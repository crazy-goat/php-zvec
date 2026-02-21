# Multi-Vector Query

## Priority: MEDIUM

## Status: TODO

## Description

Python SDK allows passing multiple `VectorQuery` objects to `collection.query()`, each targeting a different vector field with its own query params. Results are combined (requires reranker for multi-vector).

## Python API

```python
from zvec import VectorQuery
from zvec.extension import RrfReRanker

results = collection.query(
    vectors=[
        VectorQuery("dense_embedding", vector=[0.1, 0.2, ...]),
        VectorQuery("sparse_embedding", vector={1: 0.5, 42: 1.2}),
    ],
    topk=10,
    filter="category = 'tech'",
    reranker=RrfReRanker(topn=10, rank_constant=60)
)
```

## VectorQuery object (Python)

```python
VectorQuery(
    field_name: str,
    vector: Union[list[float], dict[int, float]],  # dense or sparse
    query_param: Optional[QueryParam] = None,       # HnswQueryParam or IVFQueryParam
)
```

## Changes needed

### Research first
- Check C++ `Collection::Query()` — does it accept multiple VectorQuery objects?
- Earlier discovery noted "Multi-vector search exists in Python SDK docs but NOT in C++ API"
- This may be implemented purely in the Python SDK layer (client-side multi-query + rerank)

### If C++ supports it
- Add `zvec_collection_query_multi()` to C wrapper
- Add multi-vector query method to PHP

### If C++ doesn't support it (likely)
- Implement in PHP: run multiple single-vector queries, then merge/rerank results
- Implement RRF reranker in PHP
- Implement Weighted reranker in PHP

### Notes
- Rerankers (RRF, Weighted) are pure logic — no C++ dependency needed
- OpenAI/Qwen rerankers call external APIs — can implement in PHP directly
