# Extended HNSW/IVF Query Parameters

## Priority: MEDIUM

## Status: TODO

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

## C++ API

Check `zvec/src/include/zvec/db/query_params.h`:
- `HnswQueryParams` constructor — does it accept radius, is_linear, is_using_refiner?
- `IVFQueryParams` constructor — same

## Changes needed

### ffi/zvec_ffi.h
- Extend `query_param_type` handling or add new fields to `zvec_collection_query_ex`

### ffi/zvec_ffi.cc
- Pass additional params to `HnswQueryParams` / `IVFQueryParams` constructors

### php/ZVec.php
- Add optional params: `$radius`, `$isLinear`, `$isUsingRefiner` to `query()` method

### Notes
- `radius` enables range-based search (return all results within distance)
- `is_linear` bypasses index for exact results (debugging/small datasets)
- `is_using_refiner` improves accuracy when quantization is used
