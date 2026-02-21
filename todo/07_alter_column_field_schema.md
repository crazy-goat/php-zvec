# Alter Column with Field Schema

## Priority: LOW

## Status: TODO

## Description

Python SDK's `alter_column()` supports not just renaming but also changing the column's data type (for scalar numeric columns only).

## Python API

```python
# Rename only
collection.alter_column(old_name="id", new_name="doc_id")

# Modify schema only (change type)
new_schema = FieldSchema(name="doc_id", data_type=DataType.INT64)
collection.alter_column("id", field_schema=new_schema)
```

## Current PHP implementation

Only supports rename:
```php
$collection->renameColumn('old_name', 'new_name');
```

## Changes needed

### Research first
- Check C++ `Collection::AlterColumn()` method signature in `collection.h`
- Does it accept a FieldSchema for type change, or just name?

### ffi/zvec_ffi.h
- Add `zvec_collection_alter_column_ex()` with field_schema params (data_type, nullable)

### ffi/zvec_ffi.cc
- Implement using C++ AlterColumn with new FieldSchema

### php/ZVec.php
- Extend `renameColumn()` or add `alterColumn()` with optional type change

### Notes
- Only supports scalar numeric columns: DOUBLE, FLOAT, INT32, INT64, UINT32, UINT64
- May trigger data migration or index rebuild
- Limitation documented in Python API
