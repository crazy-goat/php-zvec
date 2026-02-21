# Migrate legacy PHP tests to .phpt format

## Priority: LOW

## Status: TODO

## Difficulty: 1/5 ⭐

## Description

Migrate the remaining legacy PHP test files (`.php` format) to the standard `.phpt` format.

## Current State

### Legacy tests (`.php` format) - 8 files:
- `test_alter_column.php` - alterColumn DDL operations
- `test_collection_create.php` - collection creation
- `test_collection_destroy.php` - collection destruction  
- `test_collection_open.php` - collection opening
- `test_collection_optimize.php` - optimize operation
- `test_collection_persist.php` - persistence/close-reopen
- `test_doc_introspection.php` - doc introspection methods

### Modern tests (`.phpt` format) - 6 files:
- `test_closed_collection_protection.phpt` ✅ - closed flag protection
- `test_concurrent_ops.phpt` - concurrent operations
- `test_error_handling.phpt` - error handling scenarios
- `test_filter_edge_cases.phpt` - filter query edge cases
- `test_large_dataset.phpt` - large dataset performance
- `test_schema_edge_cases.phpt` - schema edge cases

## Migration Plan

Convert each `.php` test to `.phpt` format following the template:

```php
--TEST--
Test name: brief description
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_name_' . uniqid();
try {
    // Test code here
    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS
```

## Why Migrate?

1. **Consistency** - All tests in single format
2. **Standardization** - `.phpt` is PHP's official test format
3. **Integration** - Works with `php run-tests.php` runner
4. **Portability** - Can run with different PHP builds
5. **Cleanup** - Old format scripts exit on failure, `.phpt` reports properly

## Notes

- Low priority - current tests work fine
- Can be done incrementally (one test at a time)
- Keep existing tests until migration complete
- Related to task #24 (phpt test format) which is about migration in general
- These 8 tests cover basic operations that are also tested in `example.php`
