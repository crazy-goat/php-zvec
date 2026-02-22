# Multi-Vector Query

## Priority: MEDIUM

## Status: DONE

## Description

Python SDK allows passing multiple `VectorQuery` objects to `collection.query()`, each targeting a different vector field with its own query params. Results are combined (requires reranker for multi-vector).

## Implementation

### Research Results
- C++ `Collection::Query()` only accepts a single `VectorQuery` (line 99 in collection.h)
- Multi-vector query must be implemented at client level (PHP layer)

### PHP Implementation (ZVec.php)

Added `queryMulti()` method that:
- Accepts array of `ZVecVectorQuery` objects
- Executes each query individually against collection
- Fetches `max(topk*2, 100)` candidates per field
- Passes results to reranker for merging
- Returns `ZVecRerankedDoc[]` sorted by combined score

```php
$semanticQuery = new ZVecVectorQuery('semantic_embedding', [0.8, 0.8, 0.7, 0.7]);
$keywordQuery = new ZVecVectorQuery('keyword_embedding', [0.8, 0.7, 0.6, 0.5]);

$results = $collection->queryMulti(
    vectorQueries: [$semanticQuery, $keywordQuery],
    reranker: new ZVecRrfReRanker(topn: 10),
    topk: 10,
    filter: "category = 'tech'"
);
```

### Tests
- `tests/test_multivector_query.phpt` - RRF reranker test
- `tests/test_multivector_weighted.phpt` - Weighted reranker test

Both tests verify:
- Multi-field queries work correctly
- Filter expressions are applied
- Error handling for empty queries
- Output fields selection

### Rerankers Available
- `ZVecRrfReRanker` - Reciprocal Rank Fusion (rank-based, no normalization)
- `ZVecWeightedReRanker` - Score normalization + weighted combination

## Usage Example

See `php/example_multivector_query.php` for a complete working example:

```php
$titleVq = new ZVecVectorQuery('title_embedding', [0.9, 0.8, 0.7, 0.6]);
$contentVq = new ZVecVectorQuery('content_embedding', [0.8, 0.7, 0.6, 0.5]);

$results = $collection->queryMulti(
    vectorQueries: [$titleVq, $contentVq],
    reranker: new ZVecRrfReRanker(topn: 10),
    topk: 10,
    filter: "category = 'tech'"
);

foreach ($results as $result) {
    echo $result->getPk() . ": " . $result->combinedScore . "\n";
    echo "  Ranks: title=" . $result->sourceRanks['title_embedding'];
    echo ", content=" . $result->sourceRanks['content_embedding'] . "\n";
}
```

## Notes
- No FFI changes needed (pure PHP implementation)
- Reranker is required parameter for queryMulti()
- Each vector query can have different query params (HNSW ef, IVF nprobe, etc.)
