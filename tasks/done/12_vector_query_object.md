# VectorQuery as First-Class Object

## Priority: MEDIUM

## Status: DONE

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

## Implemented PHP API

```php
$vq = new ZVecVectorQuery('embedding', [0.1, 0.2, 0.3]);
$vq->setHnswParams(ef: 300);

$results = $collection->query($vq, topk: 10, filter: "category = 'tech'");

// Or using positional arguments
$results = $collection->query($vq, [], 10, false, "category = 'tech'");

// groupByQuery also supports VectorQuery
$groups = $collection->groupByQuery($vq, [], 'category', 2, 3);
```

## Implementation

### php/ZVec.php
- Added `ZVecVectorQuery` class with properties:
  - `fieldName` - vector field name
  - `vector` - dense vector data (float array)
  - `docId` - for query by document ID (future feature)
  - `queryParamType`, `hnswEf`, `ivfNprobe`, `radius`, `isLinear`, `isUsingRefiner` - query parameters
- Methods:
  - `__construct(string $fieldName, array $vector)` - create vector query
  - `fromId(string $fieldName, string $docId)` - create query by document ID
  - `setHnswParams(int $ef)` - set HNSW query parameters
  - `setIvfParams(int $nprobe)` - set IVF query parameters
  - `setFlatParams()` - mark as flat query
  - `setRadius(float $radius)` - set search radius
  - `setLinear(bool $linear)` - enable linear search
  - `setUsingRefiner(bool $refiner)` - enable refiner
  - All setter methods return `$this` for fluent interface

### Modified Methods
- `ZVec::query()` - now accepts `string|ZVecVectorQuery $fieldOrQuery` as first parameter
- `ZVec::groupByQuery()` - now accepts `string|ZVecVectorQuery $fieldOrQuery` as first parameter
- Backward compatibility maintained - existing code using positional arguments still works
- When `ZVecVectorQuery` is passed, its properties override the corresponding parameters

### Tests
- Created `tests/test_vector_query_object.phpt` with comprehensive tests covering:
  - Old API backward compatibility (positional arguments)
  - New API with VectorQuery object
  - HNSW params via VectorQuery
  - Radius-based search
  - Document ID query placeholder (throws exception)
  - groupByQuery with VectorQuery
  - Fluent interface

### Notes
- This is a PHP-side refactor - no FFI changes required
- Prerequisites for multi-vector query (task 05) - VectorQuery objects can be collected in arrays
- Future: add `ZVecHnswQueryParam` and `ZVecIvfQueryParam` classes for more structured param handling
- Task 11 (query by ID) can now use the `ZVecVectorQuery::fromId()` factory method
