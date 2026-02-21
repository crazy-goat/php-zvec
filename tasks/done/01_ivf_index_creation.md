# IVF Index Creation

## Priority: HIGH

## Status: DONE

## Difficulty: 2/5 ⭐⭐

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

## Implementation

### ffi/zvec_ffi.h
- Added `zvec_collection_create_ivf_index(coll, field_name, metric_type, n_list, n_iters, use_soar, quantize_type)`

### ffi/zvec_ffi.cc
- Implemented using `IVFIndexParams(metric_type, n_list, n_iters, use_soar, quantize_type)`

### php/ZVec.php
- Added FFI cdef declaration for `zvec_collection_create_ivf_index`
- Added `createIvfIndex(string $fieldName, int $metricType = ZVecSchema::METRIC_IP, int $nList = 1024, int $nIters = 10, bool $useSoar = false, int $quantizeType = 0)` method

### tests/test_ivf_index.phpt
- Comprehensive test covering: IVF creation, querying with IVF param, switching to HNSW and back, IVF with SOAR, IVF with quantization

## Tests

✅ All tests pass (39 tests, 38 passed, 1 expected fail)
✅ Integration test passes (php/example.php)

## API Usage

```php
// Create IVF index
$c->createIvfIndex(
    'embedding',
    metricType: ZVecSchema::METRIC_IP,
    nList: 10,           // Number of clusters (default: 1024)
    nIters: 5,           // K-means iterations (default: 10)
    useSoar: false,      // SOAR optimization (default: false)
    quantizeType: ZVec::QUANTIZE_INT8  // Optional quantization
);

// Query with IVF
$results = $c->query(
    'embedding', [0.1, 0.2, 0.3, 0.4],
    topk: 10,
    queryParamType: ZVec::QUERY_PARAM_IVF,
    ivfNprobe: 3  // Number of clusters to search
);
```
