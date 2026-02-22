# Extended HNSW/IVF Query Parameters

## Priority: MEDIUM

## Status: DONE

## Description

Python SDK exposes additional query parameters that we don't pass through yet.

## Missing HNSW query params

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `radius` | float | 0.0 | Search radius for range queries |
| `is_linear` | bool | false | Force brute-force linear search |
| `is_using_refiner` | bool | false | Use refiner for better accuracy |

## Missing IVF query params

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `radius` | float | 0.0 | Search radius for range queries |
| `is_linear` | bool | false | Force brute-force linear search |
| `is_using_refiner` | bool | false | Use refiner for better accuracy |

## Implementation

### Changes Made

#### ffi/zvec_ffi.h
- Extended `zvec_collection_query_ex` with new parameters:
  - `float radius` - search radius for range queries
  - `int is_linear` - force brute-force search
  - `int is_using_refiner` - use refiner for quantized indexes
- Extended `zvec_collection_group_by_query` with same parameters

#### ffi/zvec_ffi.cc
- Updated `apply_query_params()` to accept and pass new parameters
- `HnswQueryParams` now constructed with: `hnsw_ef, radius, is_linear, is_using_refiner`
- `IVFQueryParams` now sets: `nprobe, is_using_refiner` with `radius` and `is_linear` via setters
- `FlatQueryParams` now sets: `is_using_refiner` with `radius` and `is_linear` via setters
- Updated both `zvec_collection_query_ex` and `zvec_collection_group_by_query`

#### php/ZVec.php
- Updated FFI C declaration for both query functions
- Added new parameters to `query()` method:
  - `float $radius = 0.0`
  - `bool $isLinear = false`
  - `bool $isUsingRefiner = false`
- Added same parameters to `groupByQuery()` method

### Test Added

`tests/test_extended_query_params.phpt` - Tests:
1. Basic HNSW query with default params
2. HNSW query with `isLinear=true` (force brute-force)
3. IVF query with `isLinear=true`
4. HNSW query with different `ef` values
5. GroupBy query with `isLinear=true`

## Known Limitations Discovered

During implementation, discovered two bugs that need separate tasks:

1. **Task #29**: Segfault when using `QUERY_PARAM_FLAT` on HNSW index - needs validation
2. **Task #30**: RocksDB flush error during collection close - cosmetic but noisy

### Notes
- `radius` parameter exists in C++ API but causes internal errors in zvec (not exposed to PHP)
- `is_using_refiner` requires quantized index (INT8/INT4) to work properly
- `is_linear` works correctly and forces brute-force search
