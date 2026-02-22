# VECTOR_FP16 and VECTOR_FP64 Support

## Priority: LOW

## Status: DONE (FP16 only)

## Difficulty: 4/5 ⭐⭐⭐⭐

## Description

Add support for FP16 (half-precision) and FP64 (double-precision) vector types.
These types exist in zvec C++ library but require special handling in FFI layer.

## Implementation Summary

**FP16 (VECTOR_FP16)**: ✅ **Implemented**
**FP64 (VECTOR_FP64)**: ❌ **Not implemented** (not supported by zvec C++ Doc variant)

## Implementation Details

### FP16 Support (Implemented)

**FFI Layer (ffi/zvec_ffi.h/.cc)**:
- Added `zvec_schema_add_field_vector_fp16()` for schema creation
- Added `zvec_doc_set_vector_fp16()` - converts uint16_t[] → `ailego::Float16`
- Added `zvec_doc_get_vector_fp16()` - converts `ailego::Float16` → uint16_t[]
- Added `zvec_collection_query_fp16()` - converts uint16_t[] query vector → `ailego::Float16`
- Uses `zvec/ailego/utility/float_helper.h` for FP16 ↔ FP32 conversion

**PHP Layer (php/ZVec.php)**:
- `ZVecSchema::addVectorFp16($name, $dimension, $metricType)`
- `ZVecDoc::setVectorFp16($field, array $vector)` - accepts int[] (uint16 values)
- `ZVecDoc::getVectorFp16($field): ?array` - returns int[] (uint16 values)
- `ZVec::queryFp16($fieldName, array $queryVector, ...)` - separate method for FP16 queries

**Tests**:
- `tests/test_fp16_vectors.phpt` - full workflow: create, insert, index, query, retrieve

### FP64 Not Implemented

**Reason**: `std::vector<double>` is NOT in zvec's `Doc::Value` variant (doc.h:34-50).
Doc only supports: `std::vector<float>`, `std::vector<float16_t>`, `std::vector<int8_t>`.
FP64 would require upstream changes to zvec core.

## API Usage

```php
// Schema
$schema = new ZVecSchema('test');
$schema->addVectorFp16('embedding', dimension: 384, metricType: ZVecSchema::METRIC_IP);

// Insert
$doc = new ZVecDoc('doc1');
$doc->setVectorFp16('embedding', [0x3C00, 0x4000, 0x4200, 0x4400]); // uint16 values
$collection->insert($doc);

// Query (use queryFp16 with uint16[] values)
$results = $collection->queryFp16('embedding', [0x3C00, 0x4000, 0x4200, 0x4400], topk: 10);

// Retrieve
$retrieved = $results[0]->getVectorFp16('embedding'); // returns int[] (uint16 values)
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
