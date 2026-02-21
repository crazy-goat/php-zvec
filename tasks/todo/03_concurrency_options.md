# Concurrency Options

## Priority: MEDIUM

## Status: TODO

## Description

Add concurrency parameters to optimize, createIndex, addColumn, and alterColumn operations.

## Python API

```python
from zvec import OptimizeOption, IndexOption, AddColumnOption, AlterColumnOption

collection.optimize(option=OptimizeOption(concurrency=4))
collection.create_index("field", param, option=IndexOption(concurrency=4))
collection.add_column(field_schema, expression="0", option=AddColumnOption(concurrency=2))
collection.alter_column("old", "new", option=AlterColumnOption(concurrency=2))
```

## C++ API

Check `zvec/src/include/zvec/db/options.h` for:
- `OptimizeOptions` (or similar)
- `IndexOptions`
- `AddColumnOptions`
- `AlterColumnOptions`

Check `collection.h` method signatures to see if they accept options.

## Changes needed

### ffi/zvec_ffi.h
- Extend `zvec_collection_optimize` with `uint32_t concurrency` param (or add `_ex` variant)
- Extend `zvec_collection_create_*_index` with concurrency
- Extend `zvec_collection_add_column_*` with concurrency
- Extend `zvec_collection_rename_column` with concurrency

### ffi/zvec_ffi.cc
- Pass options structs to C++ API calls

### php/ZVec.php
- Add optional `int $concurrency = 0` params to affected methods

### Notes
- 0 = auto-detect (system default)
- Need to verify C++ API actually accepts these options (Python SDK might wrap them differently)
