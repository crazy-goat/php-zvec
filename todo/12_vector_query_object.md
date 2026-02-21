# VectorQuery as First-Class Object

## Priority: MEDIUM

## Status: TODO

## Description

Python SDK uses `VectorQuery` as a structured object that bundles field_name + vector + query_params together. Our PHP API currently takes these as separate parameters to `query()`. This makes the API harder to use for multi-vector queries and doesn't match the Python SDK's design.

## Python API

```python
import zvec

result = collection.query(
    vectors=zvec.VectorQuery(
        field_name="embedding",
        vector=[0.1, 0.2, 0.3],
        param=zvec.HnswQueryParam(ef=300),
    ),
    topk=10,
    filter="category = 'tech'",
    include_vector=False,
    output_fields=["title", "url"],
)
```

## Current PHP API

```php
$results = $collection->query(
    fieldName: 'embedding',
    queryVector: [0.1, 0.2, 0.3],
    topk: 10,
    filter: "category = 'tech'",
    queryParamType: ZVec::QUERY_PARAM_HNSW,
    hnswEf: 300,
);
```

## Proposed PHP API

```php
$vq = new ZVecVectorQuery('embedding', [0.1, 0.2, 0.3]);
$vq->setHnswParams(ef: 300);

$results = $collection->query(
    vectors: $vq,  // or [$vq1, $vq2] for multi-vector
    topk: 10,
    filter: "category = 'tech'",
);
```

## Changes needed

### php/ZVec.php
- Add `ZVecVectorQuery` class
- Modify `query()` to accept `ZVecVectorQuery` or array of them
- Keep backward compatibility with current parameter-based API

### Notes
- This is mostly a PHP-side refactor
- Prerequisite for multi-vector query (task 05)
- Could add `ZVecHnswQueryParam` and `ZVecIvfQueryParam` classes too
