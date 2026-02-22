# Fix Segfault When Using QUERY_PARAM_FLAT on HNSW Index

## Priority: HIGH

## Status: DONE

## Difficulty: 3/5 ⭐⭐⭐

## Description

Querying a collection with `queryParamType: ZVec::QUERY_PARAM_FLAT` on an index that was created as HNSW causes a segfault (SIGSEGV / Termsig=11). This happens because the code tries to use Flat query parameters on an HNSW index without proper validation.

## Root Cause

The FFI layer doesn't validate if the `query_param_type` matches the actual index type. When `QUERY_PARAM_FLAT` (3) is passed but the index is HNSW, the C++ code likely tries to cast or access data structures incorrectly.

## Solution

Added validation in C++ FFI layer (`ffi/zvec_ffi.cc`):

1. Created `validate_query_param_type()` function that checks if the requested query_param_type matches the actual index type for the field
2. Added validation calls in `zvec_collection_query_ex()` and `zvec_collection_group_by_query()` before executing the query
3. Returns `StatusCode::INVALID_ARGUMENT` error with descriptive message when mismatch detected

## Changes Made

### ffi/zvec_ffi.cc
- Added `validate_query_param_type()` helper function
- Validates field exists and has matching index type
- Maps query_param_type (1=HNSW, 2=IVF, 3=FLAT) to IndexType enum
- Returns error status on mismatch instead of proceeding to query

### php/ZVec.php
- Removed PHP-side validation (no longer needed, handled in C++)
- Constants QUERY_PARAM_* remain unchanged

### tests/bug_0029_query_param_validation.phpt
- Added test case verifying exception is thrown instead of segfault
- Tests both `query()` and `groupByQuery()` methods

## Bug Reproduction

```php
$collection->createHnswIndex('embedding', ZVecSchema::METRIC_IP, 16, 200);

// This now throws ZVecException instead of segfault:
$results = $collection->query(
    fieldName: 'embedding',
    queryVector: $queryVec,
    topk: 10,
    queryParamType: ZVec::QUERY_PARAM_FLAT  // Invalid - index is HNSW
);
```

## Acceptance Criteria

- [x] Query with mismatched query_param_type throws ZVecException instead of segfault
- [x] Test added to verify fix works (tests/bug_0029_query_param_validation.phpt)
- [x] All existing tests still pass (43/43 PASS, 1 XFAIL)

## Notes

- This is a safety issue - segfault crashes the entire PHP process
- Validation is now done at the FFI layer (C++) for better performance and consistency
- Error message: "Query parameter type mismatch for field 'X': index type does not match query_param_type"
