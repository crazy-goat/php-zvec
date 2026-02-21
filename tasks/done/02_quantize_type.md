# QuantizeType Support on Index Creation

## Priority: HIGH

## Status: ✅ DONE

## Description

Add `quantize_type` parameter to HNSW, Flat, and IVF index creation. Python SDK supports FP16, INT8, INT4 quantization for vector compression.

## Python API

```python
from zvec import HnswIndexParam, QuantizeType

collection.create_index("embedding", HnswIndexParam(
    metric_type=MetricType.IP,
    m=50,
    ef_construction=500,
    quantize_type=QuantizeType.INT8
))
```

## QuantizeType enum values

- `UNDEFINED` (0) — no quantization (default)
- `FP16` (1)
- `INT8` (2)
- `INT4` (3)

## Implementation

### ffi/zvec_ffi.h
- ✅ Added `uint32_t quantize_type` param to `zvec_collection_create_hnsw_index()`
- ✅ Added `uint32_t quantize_type` param to `zvec_collection_create_flat_index()`

### ffi/zvec_ffi.cc
- ✅ Added `to_quantize_type()` mapping function to convert uint32_t to `QuantizeType` enum
- ✅ Updated `zvec_collection_create_hnsw_index()` to pass quantize_type to `HnswIndexParams` constructor
- ✅ Updated `zvec_collection_create_flat_index()` to pass quantize_type to `FlatIndexParams` constructor

### php/ZVec.php
- ✅ Added constants:
  - `QUANTIZE_UNDEFINED = 0`
  - `QUANTIZE_FP16 = 1`
  - `QUANTIZE_INT8 = 2`
  - `QUANTIZE_INT4 = 3`
- ✅ Added `$quantizeType = 0` parameter to `createHnswIndex()` method
- ✅ Added `$quantizeType = 0` parameter to `createFlatIndex()` method
- ✅ Updated FFI cdef declarations to include quantize_type parameter

### php/example.php
- ✅ Added scenario 6b: "Quantized Index (INT8)" test case

## Usage Example

```php
// Create HNSW index with INT8 quantization (4x smaller index)
$collection->createHnswIndex(
    fieldName: 'embedding',
    metricType: ZVecSchema::METRIC_IP,
    m: 50,
    efConstruction: 500,
    quantizeType: ZVec::QUANTIZE_INT8
);

// Create Flat index with FP16 quantization (2x smaller)
$collection->createFlatIndex(
    fieldName: 'embedding',
    metricType: ZVecSchema::METRIC_IP,
    quantizeType: ZVec::QUANTIZE_FP16
);
```

## Notes

- Default value is `QUANTIZE_UNDEFINED` (0) = no quantization
- IVF index support pending (task 01)
- Quantization trades off some accuracy for significant storage savings:
  - FP16: 2x smaller
  - INT8: 4x smaller  
  - INT4: 8x smaller
