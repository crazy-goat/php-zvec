# VECTOR_FP16 and VECTOR_FP64 Support

## Priority: LOW

## Status: TODO

## Difficulty: 4/5 ⭐⭐⭐⭐

## Description

Add support for FP16 (half-precision) and FP64 (double-precision) vector types.
These types exist in zvec C++ library but require special handling in FFI layer.

## Current Status

Attempted implementation failed with build errors:

```
error: static assertion failed: 'is_valid_type_v<std::vector<unsigned short>>': Unsupported type
```

## Root Causes

### VECTOR_FP16 (DataType = 22)
- zvec uses custom `ailego::Float16` type internally
- FFI tried `std::vector<uint16_t>` which is not compatible
- Need to convert uint16_t array to `std::vector<Float16>` before calling Doc::set()
- Doc::get_field<std::vector<Float16>>() returns `zvec::ailego::Float16` objects
- Need conversion back to uint16_t for FFI

### VECTOR_FP64 (DataType = 24)
- `std::vector<double>` is NOT in Doc's variant type list
- Doc only supports: float, Float16, int8_t vectors
- Requires adding double vector support to zvec core (unlikely)
- OR implement conversion float64 -> float32 (lossy)

## Implementation Options

### Option A: Full Native Support (Hard)
1. Include `zvec/ailego/utility/float_helper.h` in FFI
2. Implement uint16_t[] <-> Float16 conversion functions
3. For FP64: petition upstream to add to Doc variant, or reject

### Option B: Conversion Layer (Medium)
1. FP16: accept uint16_t[] from PHP, convert to Float16, store as FP16
2. FP64: accept double[] from PHP, convert to float[], store as FP32
3. Document precision loss for FP64

### Option C: Reject and Document (Easy)
1. Do not implement
2. Document that only FP32 and INT8 vectors are supported
3. Users should convert their data before insertion

## API Design (if implemented)

```php
// Schema
$schema->addVectorFp16('embedding_half', dimension: 512, metricType: ZVecSchema::METRIC_IP);
$schema->addVectorFp64('embedding_double', dimension: 512, metricType: ZVecSchema::METRIC_IP);

// Document
$doc->setVectorFp16('embedding_half', [0x3C00, 0x4000, ...]); // uint16 values
$doc->setVectorFp64('embedding_double', [1.234567890123, ...]); // double values

// Retrieval
$halfVec = $doc->getVectorFp16('embedding_half'); // returns int[] (uint16 values)
$doubleVec = $doc->getVectorFp64('embedding_double'); // returns float[] (converted from float32)
```

## Notes

- FP16 is mainly for storage efficiency (2 bytes vs 4 bytes per dimension)
- FP64 is rarely needed for vector search (FP32 is usually sufficient)
- Query performance should be similar (zvec handles conversion internally)
- Consider if this complexity is worth it for PHP use cases

## References

- C++ type.h: `DataType::VECTOR_FP16 = 22`, `DataType::VECTOR_FP64 = 24`
- C++ Float16: `zvec/src/include/zvec/ailego/utility/float_helper.h`
- Doc variant types: `zvec/src/include/zvec/db/doc.h` (line ~50)
