# VectorQuery by Document ID

## Priority: MEDIUM

## Status: DONE

## Description

Python SDK's `VectorQuery` supports querying by document ID instead of providing an explicit vector. This lets you "find similar documents" by referencing an existing document's embedding.

## Python API

```python
import zvec

# Query by vector (what we support now)
VectorQuery(field_name="embedding", vector=[0.1, 0.2, ...])

# Query by document ID (IMPLEMENTED)
VectorQuery(field_name="embedding", id="doc_42")
```

You must provide exactly one of `vector` or `id`. Specifying both raises an error.

## Implementation

### Research
C++ `VectorQuery` struct in `doc.h` does NOT have an `id` field. Implemented as fetch(id) → get vector → query(vector) pattern in PHP layer.

### PHP Layer (php/ZVec.php)
Added `queryById()` method that:
1. Fetches the document by ID using existing `fetch()` method
2. Extracts the vector from the specified field using `getVectorFp32()`
3. Calls `query()` with the extracted vector and all other parameters

```php
public function queryById(
    string $fieldName,
    string $docId,
    int $topk = 10,
    bool $includeVector = false,
    ?string $filter = null,
    ?array $outputFields = null,
    int $queryParamType = self::QUERY_PARAM_NONE,
    int $hnswEf = 200,
    int $ivfNprobe = 10,
    float $radius = 0.0,
    bool $isLinear = false,
    bool $isUsingRefiner = false
): array
```

### Tests
- Added `tests/test_query_by_id.phpt` with 6 test cases
- All 48 existing tests still pass

### Notes
- FFI layer not modified - implementation uses existing fetch() and query() methods
- Throws ZVecException if document not found or field doesn't exist
- Full support for all query parameters (filter, outputFields, HNSW/IVF params, etc.)
