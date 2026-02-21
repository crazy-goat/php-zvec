# Test Framework: Migrate to .phpt format

## Priority: MEDIUM

## Status: TODO

## Difficulty: 3/5 ⭐

## Description

Currently tests use custom `PASS:/FAIL:` format with `exit(1)`. PHP has native `.phpt` format which is the standard way to write tests for PHP extensions and FFI bindings.

**Current approach:**
- `test_collection_create.php` outputs `PASS: test_name - description` or `FAIL:`
- Manual assertion checking with `assert()`
- Exit code checking

**Desired approach:**
- Use `.phpt` files with `--TEST--`, `--FILE--`, `--EXPECT--` sections
- Run via `php run-tests.php` (bundled with PHP)
- Better integration with PHP testing ecosystem

## Implementation

### New Test Format

All tests should migrate to `.phpt` format:

```phpt
--TEST--
Collection creation with schema
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_collection_create';
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

$schema = new ZVecSchema('create_test');
$schema->addInt64('id', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);
assert(strpos($c->schema(), 'create_test') !== false);
echo "OK\n";
$c->close();
exec("rm -rf " . escapeshellarg($path));
?>
--EXPECT--
OK
```

### Files to Migrate

1. **Collection tests** (task #18):
   - `test_collection_create.php` → `test_collection_create.phpt`
   - `test_collection_open.php` → `test_collection_open.phpt`
   - `test_collection_destroy.php` → `test_collection_destroy.phpt`
   - `test_collection_optimize.php` → `test_collection_optimize.phpt`
   - `test_collection_persist.php` → `test_collection_persist.phpt`

2. **Bug reproductions**:
   - `bug_0001.php` → `bug_0001.phpt`
   - `bug_0002.php` → `bug_0002.phpt`
   - `bug_0003.php` → `bug_0003.phpt`
   - `bug_0004.php` → `bug_0004.phpt`

3. **Existing tests**:
   - `test_alter_column.php` → `test_alter_column.phpt`
   - `test_doc_introspection.php` → `test_doc_introspection.phpt`

### PHP Commands

```bash
# Run all phpt tests
php run-tests.php tests/

# Run single test
php run-tests.php tests/test_collection_create.phpt

# Run with verbose output
php run-tests.php -v tests/
```

### Benefits

- **Standard PHP testing** - `.phpt` is the official PHP test format
- **No custom format** - No need to remember `PASS:/FAIL:` convention
- **Better error reporting** - `run-tests.php` shows diffs on failure
- **CI/CD ready** - Most PHP CI setups understand `.phpt` natively
- **Categorization** - Use `--SKIPIF--`, `--XFAIL--` sections for known issues

### Notes

- `php run-tests.php` is bundled with PHP source, but can also be downloaded
- Can write wrapper script if `run-tests.php` not available
- Keep temp directory cleanup pattern with `exec("rm -rf ...")`
- Use `--SKIPIF--` for tests that require built FFI library
