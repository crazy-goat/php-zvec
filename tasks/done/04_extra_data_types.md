# Additional Scalar Data Types

## Priority: MEDIUM

## Status: DONE

## Difficulty: 3/5 ⭐

## Description

Add support for data types available in Python SDK but missing in PHP wrapper.

## Implementation Summary

### Added scalar types:
- ✅ BOOL (3) - schema only, not supported for column DDL
- ✅ INT32 (4) - full support
- ✅ UINT32 (6) - full support  
- ✅ UINT64 (7) - full support

### Added vector types:
- ✅ VECTOR_INT8 (26) - INT8 vectors for quantized storage

### Not implemented (see task #28):
- ❌ VECTOR_FP16 (22) - requires zvec's custom Float16 type, see `tasks/todo/28_fp16_fp64_vectors.md`
- ❌ VECTOR_FP64 (24) - double-precision vectors (not in Doc variant), see `tasks/todo/28_fp16_fp64_vectors.md`
- ❌ FP16/FP64 sparse vectors

### Not implemented (complex array handling):
- ❌ Array types (ARRAY_STRING, ARRAY_INT32, etc.)

## Files Modified

### FFI Layer (ffi/)
- `zvec_ffi.h` - Added declarations for new schema/doc functions
- `zvec_ffi.cc` - Implemented:
  - `zvec_schema_add_field_bool/int32/uint32/uint64`
  - `zvec_schema_add_field_vector_int8`
  - `zvec_doc_set_bool/int32/uint32/uint64`
  - `zvec_doc_set_vector_int8`
  - `zvec_doc_get_bool/int32/uint32/uint64`
  - `zvec_doc_get_vector_int8`
  - `zvec_collection_add_column_int32/uint32/uint64`

### PHP Layer (php/ZVec.php)
- Added FFI declarations for all new functions
- Added data type constant: `TYPE_BOOL = 3`
- ZVecSchema methods:
  - `addBool()`, `addInt32()`, `addUint32()`, `addUint64()`
  - `addVectorInt8()`
- ZVecDoc methods:
  - `setBool()`, `setInt32()`, `setUint32()`, `setUint64()`
  - `setVectorInt8()`
  - `getBool()`, `getInt32()`, `getUint32()`, `getUint64()`
  - `getVectorInt8()`
- ZVec column DDL methods:
  - `addColumnInt32()`, `addColumnUint32()`, `addColumnUint64()`

### Tests
- `tests/test_extra_data_types.phpt` - Tests all new types

## Notes
- BOOL columns can be defined in schema but cannot be added via column DDL
- FP16/FP64 vector types require zvec's custom Float16 type which is not directly compatible with FFI
- All new scalar types (INT32, UINT32, UINT64) support full CRUD operations
