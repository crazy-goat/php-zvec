# Task: Separate Test Database Directory

## Priority: LOW

## Status: DONE

## Difficulty: 1/5 ⭐

## Problem

When tests fail (or are interrupted), they leave behind test directories in the project root:
- `test_column_drop_699a1ddd583f5/`
- `test_column_rename_699a1dddd7c13/`
- etc.

These directories contain RocksDB data files and clutter the repository.

## Solution

### Created: `test_dbs/` Directory

Katalog `test_dbs/` jest w repozytorium, ale jego zawartość jest ignorowana:

```
zvec-php/
├── test_dbs/           # Committed to repo (empty directory)
│   └── .gitignore      # Ignores everything except itself
├── tests/
├── php/
└── ...
```

### test_dbs/.gitignore
```
*
!.gitignore
```

### Update Test Pattern

Change from:
```php
$path = __DIR__ . '/../test_column_drop_' . uniqid();
```

To:
```php
$path = __DIR__ . '/../test_dbs/column_drop_' . uniqid();
```

## Implementation

### Files Created:
- ✅ `test_dbs/.gitignore` - ignores all files in the directory

### Files to Update (future):
- `tests/test_*.phpt` - ~25 files to update path pattern
- `AGENTS.md` - update test conventions documentation

### Example Test Update:
```php
// OLD
$path = __DIR__ . '/../test_column_drop_' . uniqid();

// NEW  
$path = __DIR__ . '/../test_dbs/column_drop_' . uniqid();
```

## Benefits

1. **Clean repository root** - no more scattered test directories
2. **Easy cleanup** - can safely `rm -rf test_dbs/*`
3. **Git cleanliness** - directory structure preserved, only data ignored
4. **Consistency** - all tests use same pattern

## Notes

- Directory `test_dbs/` is committed to repo but stays empty
- All database files inside are ignored by git
- Tests still need try-finally for cleanup, but failures won't clutter repo root
