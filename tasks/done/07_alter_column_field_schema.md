# Alter Column with Field Schema

## Priority: LOW

## Status: ✅ DONE

## Implementation

Extended `alterColumn()` to support both renaming and changing data type (for scalar numeric columns).

### FFI Layer (ffi/zvec_ffi.h/.cc)
- Added `zvec_collection_alter_column()` function accepting:
  - `column_name` - column to alter
  - `new_name` - optional rename (NULL or empty for no change)
  - `data_type` - new type (4=INT32, 5=INT64, 6=UINT32, 7=UINT64, 8=FLOAT, 9=DOUBLE)
  - `nullable` - nullable flag for the new schema

### PHP Layer (php/ZVec.php)
- Added `ZVec::alterColumn(string $columnName, ?string $newName = null, ?int $newDataType = null, ?bool $nullable = null): void`
- Added type constants: `TYPE_INT32=4`, `TYPE_INT64=5`, `TYPE_UINT32=6`, `TYPE_UINT64=7`, `TYPE_FLOAT=8`, `TYPE_DOUBLE=9`

### Test
- `tests/test_alter_column.php` - Full test coverage including:
  - Rename column using alterColumn()
  - Change data type (INT64 → FLOAT)
  - Change type + rename in two steps (FLOAT → DOUBLE, then rename)
  - Schema verification
- `php/example.php` - Additional demo in scenario #19

## Notes
- Only supports scalar numeric columns: DOUBLE, FLOAT, INT32, INT64, UINT32, UINT64
- Data type change may trigger data migration or index rebuild
- Original Python API limitation: only scalar numeric types supported
