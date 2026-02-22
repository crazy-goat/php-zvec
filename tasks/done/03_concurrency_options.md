# Concurrency Options

## Priority: MEDIUM

## Status: DONE

## Description

Add concurrency parameters to optimize, createIndex, addColumn, and alterColumn operations.

## Implementation

### FFI Layer (ffi/zvec_ffi.h/.cc)

Updated FFI functions to accept concurrency parameter:
- `zvec_collection_optimize(zvec_collection_t coll, uint32_t concurrency)`
- `zvec_collection_create_hnsw_index(..., uint32_t concurrency)`
- `zvec_collection_create_flat_index(..., uint32_t concurrency)`
- `zvec_collection_create_ivf_index(..., uint32_t concurrency)`
- `zvec_collection_add_column_*` functions with concurrency parameter
- `zvec_collection_rename_column(..., uint32_t concurrency)`
- `zvec_collection_alter_column(..., uint32_t concurrency)`

The C++ API uses:
- `OptimizeOptions` with `concurrency_` field
- `CreateIndexOptions` with `concurrency_` field  
- `AddColumnOptions` with `concurrency_` field
- `AlterColumnOptions` with `concurrency_` field

When concurrency is 0, the C++ library uses auto-detect (system default).

### PHP Layer (php/ZVec.php)

Added optional `int $concurrency = 0` parameter to:
- `optimize(int $concurrency = 0)`
- `createHnswIndex(..., int $concurrency = 0)`
- `createFlatIndex(..., int $concurrency = 0)`
- `createIvfIndex(..., int $concurrency = 0)`
- `addColumnInt64(..., int $concurrency = 0)` and other addColumn* methods
- `renameColumn(string $oldName, string $newName, int $concurrency = 0)`
- `alterColumn(..., int $concurrency = 0)`

## API Usage

```php
// Optimize with 2 threads
$collection->optimize(concurrency: 2);

// Create index with 4 threads
$collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, concurrency: 4);

// Add column with 2 threads
$collection->addColumnInt64('new_field', true, '0', concurrency: 2);

// Alter column with 2 threads
$collection->alterColumn('old_name', newName: 'new_name', concurrency: 2);

// Use default (auto-detect) by omitting parameter or passing 0
$collection->optimize(); // Uses system default
```

## Tests

Added `tests/test_concurrency_options.phpt` - tests all operations with concurrency parameter:
- optimize with concurrency=2
- createHnswIndex with concurrency=2
- createFlatIndex with concurrency=2
- addColumnInt64 with concurrency=2
- addColumnFloat with concurrency=2
- alterColumn (rename) with concurrency=2
- renameColumn with concurrency=2
- addColumn with default concurrency (0)

All 46 tests pass (45 passed, 1 expected failure for GroupBy "Coming Soon" feature).
