# VECTOR_FP64 Support (Blocked - Upstream Issue)

## Priority: LOW

## Status: BLOCKED

## Difficulty: 5/5 ⭐⭐⭐⭐⭐

## Description

Add support for FP64 (double-precision) vector types. This feature is blocked because zvec C++ library does not support double-precision vectors in its Doc type.

## Blocking Issue

**Root cause**: `std::vector<double>` is NOT in zvec's `Doc::Value` variant type list.

From `zvec/src/include/zvec/db/doc.h` (lines 34-50):
```cpp
using Value = std::variant<
    std::monostate,  // 0 - represents null value
    bool, int32_t, uint32_t, int64_t, uint64_t, float, double,  // 1~7
    std::string,                                                // 8
    std::vector<bool>,                                          // 9
    std::vector<int8_t>,                                        // 10
    std::vector<int16_t>,                                       // 11
    std::vector<int32_t>,                                       // 12
    std::vector<int64_t>,                                       // 13
    std::vector<uint32_t>,                                      // 14
    std::vector<uint64_t>,                                      // 15
    std::vector<float16_t>,                                     // 16 ✅ FP16 vector
    std::vector<float>,                                         // 17 ✅ FP32 vector
    std::vector<double>,                                        // 18 ⚠️ NOT vector - scalar double
    std::vector<std::string>,                                   // 19
    std::pair<std::vector<uint32_t>, std::vector<float>>,       // 20
    std::pair<std::vector<uint32_t>, std::vector<float16_t>>>;  // 21
```

Note: Line 18 is `std::vector<double>` (array of scalar doubles), NOT a double-precision vector type.

## Implementation Options

### Option A: Wait for Upstream (Recommended)
1. Create issue in zvec repository requesting FP64 vector support
2. Wait for upstream to add `DataType::VECTOR_FP64` and corresponding variant type
3. Implement FFI bindings once upstream support is available

### Option B: Workaround with FP32 (Not Recommended)
1. Accept double[] from PHP
2. Convert to float[] and store as FP32 (precision loss)
3. Document precision loss clearly
4. **Drawback**: Misleading API - users expect FP64 precision but get FP32

### Option C: Reject (Current Status)
1. Do not implement
2. Document that FP64 vectors are not supported
3. Recommend users use FP32 for vector search (sufficient for most use cases)

## API Design (if upstream support becomes available)

```php
// Schema
$schema->addVectorFp64('embedding_double', dimension: 512, metricType: ZVecSchema::METRIC_IP);

// Document
$doc->setVectorFp64('embedding_double', [1.234567890123, ...]); // double values

// Query
$results = $collection->queryFp64('embedding_double', [1.234567890123, ...], topk: 10);

// Retrieval
$doubleVec = $doc->getVectorFp64('embedding_double'); // returns float[] (double precision)
```

## FFI Implementation (if upstream support becomes available)

### FFI Layer (ffi/zvec_ffi.h/.cc)
```cpp
// Schema
void zvec_schema_add_field_vector_fp64(zvec_schema_t schema, const char* name, 
                                       uint32_t dimension, uint32_t metric_type);

// Doc set/get
void zvec_doc_set_vector_fp64(zvec_doc_t doc, const char* field, 
                              const double* data, uint32_t dim);
int zvec_doc_get_vector_fp64(zvec_doc_t doc, const char* field, 
                             const double** out, uint32_t* dim);

// Query
zvec_status_t zvec_collection_query_fp64(zvec_collection_t coll, const char* field_name,
                                         const double* query_vector, uint32_t dim,
                                         int topk, int include_vector,
                                         const char* filter,
                                         zvec_query_result_t* result);
```

### PHP Layer (php/ZVec.php)
```php
// ZVecSchema
public function addVectorFp64(string $name, int $dimension, int $metricType = self::METRIC_IP): self;

// ZVecDoc
public function setVectorFp64(string $field, array $vector): self;
public function getVectorFp64(string $field): ?array;

// ZVec
public function queryFp64(string $fieldName, array $queryVector, int $topk = 10, 
                         bool $includeVector = false, ?string $filter = null): array;
```

## Notes

- FP64 is rarely needed for vector search - FP32 provides sufficient precision for similarity search
- Storage cost: 8 bytes per dimension vs 4 bytes (FP32) or 2 bytes (FP16)
- Most embedding models output FP32 or lower precision
- Consider if 64-bit precision is truly necessary for your use case

## Next Steps

1. Check zvec roadmap for FP64 support plans
2. If needed, create upstream issue: https://github.com/alibaba/zvec/issues
3. Monitor zvec releases for FP64 vector support
4. Implement PHP bindings once upstream support lands

## References

- zvec Doc variant: `zvec/src/include/zvec/db/doc.h` (lines 34-50)
- DataType enum: `zvec/src/include/zvec/db/type.h` (line 24: `VECTOR_FP64 = 24`)
- FP16 implementation: `tasks/done/28_fp16_fp64_vectors.md`
