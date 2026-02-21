# Migrate legacy PHP tests to .phpt format

## Priority: LOW

## Status: DONE

## Difficulty: 1/5 ⭐

## Description

Migrate the remaining legacy PHP test files (`.php` format) to the standard `.phpt` format.

## Current State

### Legacy tests (`.php` format) - 0 files:
All legacy tests have been migrated ✅

### Modern tests (`.phpt` format) - 13 files:
- `test_alter_column.phpt` ✅ - alterColumn DDL operations
- `test_closed_collection_protection.phpt` ✅ - closed flag protection
- `test_collection_create.phpt` ✅ - collection creation
- `test_collection_destroy.phpt` ✅ - collection destruction  
- `test_collection_open.phpt` ✅ - collection opening
- `test_collection_optimize.phpt` ✅ - optimize operation
- `test_collection_persist.phpt` ✅ - persistence/close-reopen
- `test_concurrent_ops.phpt` ✅ - concurrent operations
- `test_doc_introspection.phpt` ✅ - doc introspection methods
- `test_error_handling.phpt` ✅ - error handling scenarios
- `test_filter_edge_cases.phpt` ✅ - filter query edge cases
- `test_large_dataset.phpt` ✅ - large dataset performance
- `test_schema_edge_cases.phpt` ✅ - schema edge cases

## Migration Summary

Successfully migrated all 7 legacy `.php` test files to `.phpt` format:

| Old File | New File | Status |
|----------|----------|--------|
| `test_alter_column.php` | `test_alter_column.phpt` | ✅ |
| `test_collection_create.php` | `test_collection_create.phpt` | ✅ |
| `test_collection_destroy.php` | `test_collection_destroy.phpt` | ✅ |
| `test_collection_open.php` | `test_collection_open.phpt` | ✅ |
| `test_collection_optimize.php` | `test_collection_optimize.phpt` | ✅ |
| `test_collection_persist.php` | `test_collection_persist.phpt` | ✅ |
| `test_doc_introspection.php` | `test_doc_introspection.phpt` | ✅ |

All tests follow the standard `.phpt` format:
- `--TEST--` - test name and brief description
- `--SKIPIF--` - skip if FFI not available
- `--FILE--` - actual test code
- `--EXPECT--` - expected output

Key improvements:
- Unique temp directories using `uniqid()` to avoid conflicts
- Cleanup with `exec("rm -rf ...")` in try-finally blocks
- Consistent error handling and PASS/FAIL reporting
- Integration with `php run-tests.php` runner

## Test Results

```bash
$ php run-tests.php tests/
Number of tests :    13                13
Tests skipped   :     0 (  0.0%) --------
Tests warned    :     0 (  0.0%) (  0.0%)
Tests failed    :     0 (  0.0%) (  0.0%)
Tests passed    :    13 (100.0%) (100.0%)
```

## Benefits Achieved

1. ✅ **Consistency** - All tests now in single format
2. ✅ **Standardization** - Using PHP's official `.phpt` test format
3. ✅ **Integration** - Works seamlessly with `php run-tests.php` runner
4. ✅ **Portability** - Can run with different PHP builds
5. ✅ **Cleanup** - Old `.php` files removed, no duplication

## Notes

- Related to task #24 (phpt test format) which defined the migration approach
- Task #27 specifically covered these 8 legacy tests from basic operations
- All tests pass and example.php integration tests also pass
