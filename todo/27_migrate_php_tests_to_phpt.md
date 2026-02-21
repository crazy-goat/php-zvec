# Migrate legacy PHP tests to .phpt format

## Priority: LOW

## Status: DONE

## Difficulty: 1/5 ⭐

## Description

Migrate the remaining legacy PHP test files (`.php` format) to the standard `.phpt` format.

## Current State

### Legacy tests (`.php` format) - 0 files:
All legacy tests have been migrated ✅

### Modern tests (`.phpt` format) - 19 files:

**Functional tests (13 files):**
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

**Bug reproduction tests (6 files):**

**FIXED - tests pass normally:**
- `bug_0001.phpt` ✅ - Column DDL after delete (FIXED: auto flush before DDL)
- `bug_0003.phpt` ✅ - Segfault after destroy (FIXED: proper exception handling)
- `bug_0005.phpt` ✅ - Cleanup after failed insert (FIXED: resource cleanup improved)
- `bug_0006.phpt` ✅ - RocksDB lock error (FIXED: lock management fixed)

**Still broken - XFAIL:**
- `bug_0002.phpt` ⚠️ - GroupByQuery does not return proper groups (zvec "Coming Soon")

**Documentation only:**
- `bug_0004.phpt` ✅ - max_doc_count_per_segment minimum threshold (limitation, not a bug)

## Migration Summary

Successfully migrated all 6 bug reproduction `.php` files to `.phpt` format:

| Old File | New File | Status | Notes |
|----------|----------|--------|-------|
| `bug_0001.php` | `bug_0001.phpt` | ✅ Migrated, **FIXED** | Bug fixed, test passes normally |
| `bug_0002.php` | `bug_0002.phpt` | ✅ Migrated, **XFAIL** | GroupByQuery "Coming Soon" in zvec |
| `bug_0003.php` | `bug_0003.phpt` | ✅ Migrated, **FIXED** | Bug fixed, test passes normally |
| `bug_0004.php` | `bug_0004.phpt` | ✅ Migrated | Documentation only (not a bug) |
| `bug_0005_cleanup_after_failed_ops.php` | `bug_0005.phpt` | ✅ Migrated, **FIXED** | Bug fixed, test passes normally |
| `bug_0006_rocksdb_lock.php` | `bug_0006.phpt` | ✅ Migrated, **FIXED** | Bug fixed, test passes normally |

Previously migrated 7 functional `.php` test files:

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
- `--XFAIL--` - reason for expected failure (only bug_0002.phpt - known limitation)
- `--SKIPIF--` - skip if FFI not available
- `--FILE--` - actual test code
- `--EXPECT--` / `--EXPECTF--` - expected output

**XFAIL Policy:**
- Fixed bugs (bug_0001, bug_0003, bug_0005, bug_0006) have their XFAIL sections removed
- Only bug_0002.phpt still has XFAIL (zvec marks GroupByQuery as "Coming Soon")

Key improvements:
- Unique temp directories using `uniqid()` to avoid conflicts
- Cleanup with `exec("rm -rf ...")` in try-finally blocks
- Consistent error handling and PASS/FAIL reporting
- Integration with `php run-tests.php` runner

## Test Results

```bash
$ php run-tests.php tests/
Number of tests :    19                19
Tests skipped   :     0 (  0.0%) --------
Tests warned    :     0 (  0.0%) --------
Tests failed    :     0 (  0.0%) --------
Expected fail   :     1 (  5.3%) (  5.3%)  <- Only true XFAIL
Tests passed    :    18 ( 94.7%) ( 94.7%)
```

**Breakdown:**
- **18 PASS**: 13 functional tests + 4 fixed bug tests + 1 documentation test
- **1 XFAIL**: bug_0002.phpt (GroupByQuery "Coming Soon" - not yet implemented in zvec)
- **0 WARN**: No warnings - all fixed bugs have their XFAIL sections removed

## Benefits Achieved

1. ✅ **Complete Migration** - All `.php` files migrated to `.phpt` (13 functional + 6 bug tests)
2. ✅ **Standardization** - Using PHP's official `.phpt` test format
3. ✅ **XFAIL Support** - Bug tests marked with `--XFAIL--` for expected failures
4. ✅ **Integration** - Works seamlessly with `php run-tests.php` runner
5. ✅ **Documentation** - Bug tests serve as living documentation of known issues
6. ✅ **Portability** - Can run with different PHP builds
7. ✅ **Cleanup** - Old `.php` files removed, no duplication

## Bug Status Updates

### Fixed Bugs (4/6):
These bugs were documented as XFAIL but are now **FIXED** and pass:

| Bug | Description | Fixed In |
|-----|-------------|----------|
| bug_0001 | Column DDL after delete causes recovery failure | C++ wrapper - auto flush before DDL |
| bug_0003 | Segfault after destroy() when using methods | C++ wrapper - proper exception handling |
| bug_0005 | Cleanup failure after failed insert | C++ wrapper - resource cleanup improved |
| bug_0006 | RocksDB lock error when recreating collection | C++ wrapper - lock management fixed |

### Still XFAIL (1/6):
These tests document known limitations that should be fixed in the future:

| Bug | Description | Reason |
|-----|-------------|--------|
| bug_0002 | GroupByQuery does not return proper groups | zvec marks this as "Coming Soon" |

### Documentation Only (1/6):

| Bug | Description | Type |
|-----|-------------|------|
| bug_0004 | max_doc_count_per_segment minimum threshold | Documented limitation, not a bug |

## Notes

- Related to task #24 (phpt test format) which defined the migration approach
- Bug tests use `--XFAIL--` section to mark known limitations
- When a bug is fixed, remove the `--XFAIL--` line from the corresponding test
- Task #27 now covers ALL `.php` test files - both functional and bug reproduction
- All 19 tests (13 functional + 6 bug) are now in `.phpt` format
