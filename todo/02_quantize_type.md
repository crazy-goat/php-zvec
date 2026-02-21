# QuantizeType Support on Index Creation

## Priority: HIGH

## Status: TODO

## Description

Add `quantize_type` parameter to HNSW, Flat, and IVF index creation. Python SDK supports FP16, INT8, INT4 quantization for vector compression.

## Python API

```python
from zvec import HnswIndexParam, QuantizeType

collection.create_index("embedding", HnswIndexParam(
    metric_type=MetricType.IP,
    m=50,
    ef_construction=500,
    quantize_type=QuantizeType.INT8  # <-- this is missing
))
```

## QuantizeType enum values

- `UNDEFINED` (0) — no quantization (current default)
- `FP16` (1)
- `INT8` (2)
- `INT4` (3)

## Changes needed

### ffi/zvec_ffi.h
- Add `quantize_type` param to `zvec_collection_create_hnsw_index`
- Add `quantize_type` param to `zvec_collection_create_flat_index`
- Add `quantize_type` param to `zvec_collection_create_ivf_index` (if task 01 done)

### ffi/zvec_ffi.cc
- Check `HnswIndexParams`, `FlatIndexParams` constructors for quantize_type support
- Map uint32_t to `QuantizeType` enum

### php/ZVec.php
- Add `$quantizeType` param to `createHnswIndex()`, `createFlatIndex()`, `createIvfIndex()`
- Add constants: `QUANTIZE_UNDEFINED`, `QUANTIZE_FP16`, `QUANTIZE_INT8`, `QUANTIZE_INT4`

### php/example.php
- Test creating index with quantization
