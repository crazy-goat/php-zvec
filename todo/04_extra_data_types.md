# Additional Scalar Data Types

## Priority: MEDIUM

## Status: TODO

## Description

Add support for data types available in Python SDK but missing in PHP wrapper.

## Missing scalar types

| Type | Python enum | Notes |
|------|------------|-------|
| `BOOL` | `DataType.BOOL` | |
| `INT32` | `DataType.INT32` | |
| `UINT32` | `DataType.UINT32` | |
| `UINT64` | `DataType.UINT64` | |

## Missing vector types

| Type | Python enum | Notes |
|------|------------|-------|
| `VECTOR_FP16` | `DataType.VECTOR_FP16` | Half-precision vectors |
| `VECTOR_FP64` | `DataType.VECTOR_FP64` | Double-precision vectors |
| `VECTOR_INT8` | `DataType.VECTOR_INT8` | Quantized int8 vectors |
| `SPARSE_VECTOR_FP16` | `DataType.SPARSE_VECTOR_FP16` | |

## Missing array types

| Type | Python enum | Notes |
|------|------------|-------|
| `ARRAY_STRING` | `DataType.ARRAY_STRING` | |
| `ARRAY_INT32` | `DataType.ARRAY_INT32` | |
| `ARRAY_INT64` | `DataType.ARRAY_INT64` | |
| `ARRAY_FLOAT` | `DataType.ARRAY_FLOAT` | |
| `ARRAY_DOUBLE` | `DataType.ARRAY_DOUBLE` | |
| `ARRAY_BOOL` | `DataType.ARRAY_BOOL` | |
| `ARRAY_UINT32` | `DataType.ARRAY_UINT32` | |
| `ARRAY_UINT64` | `DataType.ARRAY_UINT64` | |

## Changes needed

### ffi/zvec_ffi.h
- Add `zvec_schema_add_field_bool`, `_int32`, `_uint32`, `_uint64`
- Add `zvec_schema_add_field_vector_fp16`, `_fp64`, `_int8`
- Add `zvec_doc_set_*/get_*` for new types
- Array types: need to investigate C++ API for set/get arrays

### ffi/zvec_ffi.cc
- Map to `DataType::BOOL`, `DataType::INT32`, etc. from `zvec/db/type.h`
- Implement set/get for each type

### php/ZVec.php (ZVecSchema)
- Add `addBool()`, `addInt32()`, `addUint32()`, `addUint64()`
- Add `addVectorFp16()`, `addVectorFp64()`, `addVectorInt8()`

### php/ZVec.php (ZVecDoc)
- Add `setBool/getBool`, `setInt32/getInt32`, `setUint32/getUint32`, `setUint64/getUint64`

### Notes
- Check `zvec/src/include/zvec/db/type.h` for actual enum values
- Array types may require more complex FFI handling (passing arrays of values)
- Vector FP16/INT8 may need special handling for data conversion
