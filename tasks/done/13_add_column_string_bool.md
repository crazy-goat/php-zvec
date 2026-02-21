# Add Column: STRING and BOOL types

## Priority: MEDIUM

## Status: DONE

## Description

Add support for STRING and BOOL column types via `addColumnString()` and `addColumnBool()` methods.

## Research Results

**C++ API Limitation**: The zvec C++ library's `Collection::AddColumn()` method currently only supports numeric types (INT32, INT64, UINT32, UINT64, FLOAT, DOUBLE). When attempting to add STRING or BOOL columns, it returns:
```
Only support basic numeric data type [int32, int64, uint32, uint64, float, double]
```

This is a known limitation mentioned in zvec documentation:
> "Currently, only numerical scalar fields can be added via add_column(). Support for string and boolean types is coming soon."

## Implementation

Despite C++ API limitations, the PHP FFI bindings have been implemented for future compatibility:

### FFI Layer (`ffi/zvec_ffi.h`)
- Added: `zvec_status_t zvec_collection_add_column_string(...)`
- Already existed: `zvec_collection_add_column_bool(...)`

### FFI Layer (`ffi/zvec_ffi.cc`)
- Implemented: `zvec_collection_add_column_string()` using `DataType::STRING`
- Already existed: `zvec_collection_add_column_bool()` using `DataType::BOOL`

### PHP Layer (`php/ZVec.php`)
- Added FFI declaration for `zvec_collection_add_column_string()`
- Added method: `addColumnString(string $name, bool $nullable = true, string $defaultExpr = '')`
- Already existed: `addColumnBool()` (added in task #04)

## Current Behavior

When called on a collection, both methods will:
1. Successfully compile and link (FFI layer ready)
2. Throw `ZVecException` at runtime with message from C++ API
3. Error message: `"Only support basic numeric data type [...]"`

## Future Work

When zvec C++ library adds support for STRING/BOOL in AddColumn:
1. No changes needed in PHP FFI layer
2. Methods will automatically start working
3. Add test: `tests/test_add_column_string_bool.phpt`

## Notes

- BOOL was already implemented in task #04 (Extra Data Types)
- STRING implementation follows the same pattern as other numeric types
- Both methods are ready for when C++ API supports them
- No breaking changes - just clear error messages until upstream support
