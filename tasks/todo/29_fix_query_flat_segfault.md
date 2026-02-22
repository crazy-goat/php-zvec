# Fix Segfault When Using QUERY_PARAM_FLAT on HNSW Index

## Priority: HIGH

## Status: TODO

## Difficulty: 3/5 ⭐⭐⭐

## Description

Querying a collection with `queryParamType: ZVec::QUERY_PARAM_FLAT` on an index that was created as HNSW causes a segfault (SIGSEGV / Termsig=11). This happens because the code tries to use Flat query parameters on an HNSW index without proper validation.

## Bug Reproduction

```php
$collection->createHnswIndex('embedding', ZVecSchema::METRIC_IP, 16, 200);

// This causes segfault:
$results = $collection->query(
    fieldName: 'embedding',
    queryVector: $queryVec,
    topk: 10,
    queryParamType: ZVec::QUERY_PARAM_FLAT  // Invalid - index is HNSW
);
```

## Root Cause

The FFI layer doesn't validate if the `query_param_type` matches the actual index type. When `QUERY_PARAM_FLAT` (3) is passed but the index is HNSW, the C++ code likely tries to cast or access data structures incorrectly.

## Possible Solutions

### Option A: PHP-side validation (Easiest)
- Query the collection schema before query
- Check if field has index and what type
- Throw ZVecException if query_param_type doesn't match index type

### Option B: FFI-side validation (Better)
- Add validation in `zvec_collection_query_ex` to check index type
- Return error status if mismatch detected

### Option C: Auto-detect index type (Best UX)
- If `QUERY_PARAM_NONE` (0) is passed, auto-detect based on index type
- If specific type is passed, validate it matches

## Changes Needed

### Option A - PHP-side:
```php
private function validateQueryParamType(string $fieldName, int $queryParamType): void
{
    if ($queryParamType === self::QUERY_PARAM_NONE) {
        return;
    }
    // Parse schema JSON to get index type for field
    // Throw if mismatch
}
```

### Option B - FFI-side:
```cpp
// In zvec_collection_query_ex, before applying query params:
auto index_type = c->GetIndexType(field_name); // Need to add this API
if (query_param_type == 3 && index_type != IndexType::FLAT) {
    return make_status(Status(ErrorCode::INVALID_ARGUMENT, "..."));
}
```

## Notes

- This is a safety issue - segfault crashes the entire PHP process
- Related to task #06 (extended query params) where this was discovered
- Should also check similar issues with QUERY_PARAM_IVF on non-IVF indexes

## Acceptance Criteria

- [ ] Query with mismatched query_param_type throws ZVecException instead of segfault
- [ ] Test added to verify fix works
- [ ] All existing tests still pass
