# Add Column: STRING and BOOL types

## Priority: MEDIUM

## Status: TODO

## Description

Currently `add_column` only supports numeric types (INT64, FLOAT, DOUBLE). The zvec docs say:

> Currently, only numerical scalar fields can be added via add_column(). Support for string and boolean types is coming soon.

However, we should prepare for this and also check if C++ API already supports it even if Python SDK doesn't expose it yet.

## Current PHP implementation

```php
$collection->addColumnInt64('rating', nullable: true, defaultExpr: '5');
$collection->addColumnFloat('weight', nullable: true, defaultExpr: '0');
$collection->addColumnDouble('price', nullable: true, defaultExpr: '0');
// Missing: addColumnString, addColumnBool
```

## Changes needed

### Research first
- Check C++ `Collection::AddColumn()` — does it accept STRING/BOOL FieldSchema?
- If yes, implement. If no, create stubs that throw "not supported yet"

### ffi/zvec_ffi.h
- Add `zvec_collection_add_column_string()`
- Add `zvec_collection_add_column_bool()`

### ffi/zvec_ffi.cc
- Implement with STRING/BOOL DataType

### php/ZVec.php
- Add `addColumnString()`, `addColumnBool()`
