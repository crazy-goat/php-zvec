# IVF Index Creation

## Priority: HIGH

## Status: TODO

## Description

Add IVF (Inverted File Index) creation support. Currently we have HNSW and Flat — IVF is the only missing index type.

## Python API

```python
from zvec import IVFIndexParam, MetricType, QuantizeType

collection.create_index("embedding", IVFIndexParam(
    metric_type=MetricType.IP,
    n_list=100,        # number of clusters (0 = auto)
    n_iters=10,        # k-means iterations
    use_soar=False,    # SOAR optimization
    quantize_type=QuantizeType.UNDEFINED
))
```

## C++ API

Check `zvec/src/include/zvec/db/index_params.h` for `IVFIndexParams` class.

## Changes needed

### ffi/zvec_ffi.h
- Add `zvec_collection_create_ivf_index(coll, field_name, metric_type, n_list, n_iters, use_soar)`

### ffi/zvec_ffi.cc
- Implement using `IVFIndexParams` (check constructor params in index_params.h)

### php/ZVec.php
- Add `createIvfIndex(string $fieldName, int $metricType, int $nList = 0, int $nIters = 10, bool $useSoar = false)`
- Add FFI cdef entry

### php/example.php
- Add test: create IVF index, query, switch back to HNSW
