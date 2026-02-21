# Add closed flag to prevent segfaults

## Priority: HIGH

## Status: ✅ DONE

## Implementation

Added `checkClosed()` method to `ZVec` class that throws `ZVecException` when any operation is attempted on a closed/destroyed collection.

### Changes in `php/ZVec.php`:

1. Added `checkClosed()` helper method:
   ```php
   private function checkClosed(): void
   {
       if ($this->closed) {
           throw new ZVecException("Collection is closed or destroyed");
       }
   }
   ```

2. Added `$this->checkClosed()` call at start of all public methods:
   - Data operations: `insert()`, `upsert()`, `update()`, `delete()`, `deleteByFilter()`, `fetch()`
   - Query operations: `query()`, `queryByFilter()`, `groupByQuery()`
   - Maintenance: `flush()`, `optimize()`, `destroy()`
   - Schema operations: `schema()`, `path()`, `options()`, `stats()`
   - Column DDL: `addColumn*()`, `dropColumn()`, `renameColumn()`, `alterColumn()`
   - Index operations: `createInvertIndex()`, `createHnswIndex()`, `createFlatIndex()`, `dropIndex()`

### Result

**Before fix:**
```php
$c->close();
$c->insert($doc);  // SEGFAULT (exit 139)
```

**After fix:**
```php
$c->close();
$c->insert($doc);  // ZVecException: Collection is closed or destroyed
```

### Tests

- `tests/test_closed_collection_protection.phpt` - PASS: Verifies all operations throw ZVecException instead of segfault
- `tests/test_segfault_example.php` - Demonstrates fix working  
- All existing tests PASS (6/6)

### Before vs After

**Before fix:**
```php
$c->close();
$c->insert($doc);  // SEGFAULT (exit 139)
```

**After fix:**
```php
$c->close();
$c->insert($doc);  // ZVecException: Collection is closed or destroyed
```
