# AGENTS.md — zvec-php

PHP FFI bindings for [Alibaba's zvec](https://github.com/alibaba/zvec) vector database.

## Project Structure

```
zvec-php/
├── php/ZVec.php          # Main library (ZVec, ZVecSchema, ZVecDoc, ZVecException)
├── php/example.php       # Integration test / usage examples (21 scenarios)
├── ffi/                  # C++ FFI bridge (zvec_ffi.h, zvec_ffi.cc, CMakeLists.txt)
├── tests/                # Bug reproduction scripts (plain PHP, no framework)
├── test_dbs/             # Test database directory (content ignored by git)
├── tasks/todo/           # Feature planning documents
├── build_zvec.sh         # Builds zvec C++ lib + FFI shared library
├── zvec/                 # Git-cloned upstream zvec C++ library (not committed)
└── cmake-3.28.3-*/       # Vendored CMake (not committed)
```

## Build Commands

### Build the native FFI library (required before running PHP code)

```bash
./build_zvec.sh
```

This clones zvec if needed, downloads CMake 3.28 locally, builds the C++ library,
then builds the FFI wrapper (`ffi/build/libzvec_ffi.dylib`). macOS only currently.

### Run the integration test suite

```bash
php php/example.php
```

### Run .phpt tests (standard PHP test format)

```bash
# Run all phpt tests
php run-tests.php tests/

# Run single phpt test
php run-tests.php tests/test_error_handling.phpt

# Run with verbose output
php run-tests.php -v tests/
```

The `run-tests.php` script is bundled with this project (from php-src).
It parses `.phpt` files and executes the PHP code within `--FILE--` sections.

### Run legacy PHP test scripts

```bash
# Run a single test
php tests/test_error_handling.php

# Run all tests (old format)
for f in tests/*.php; do php "$f"; done
```

### Run all tests (both formats)

```bash
# Build first if needed
./build_zvec.sh

# Run all tests
php run-tests.php tests/ && php php/example.php
```

## Testing Requirements

After every feature implementation, **ALL tests must pass** before the task is considered complete:

### Pre-commit Test Checklist

Before marking any task as DONE:

1. **Build the FFI library** (if C++ changes):
   ```bash
   ./build_zvec.sh
   ```

2. **Run all .phpt tests**:
   ```bash
   php run-tests.php tests/
   ```

3. **Run integration tests**:
   ```bash
   php php/example.php
   ```

4. **Verify test databases cleaned up:**
   ```bash
   ls test_dbs/
   # Should be empty (except .gitignore)
   ```

### Test Requirements for New Features

Every new feature MUST include:

1. **Unit test(s)** in `tests/test_<feature>.phpt` format
2. **Cleanup with `try-finally`** to prevent temp directory leaks
3. **Unique temp directory names** using `uniqid()` to avoid conflicts
4. **No segfaults or crashes** on error conditions

### Example Test Template

```php
--TEST--
Feature name: brief description
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/feature_' . uniqid();
try {
    // Test code here
    echo "Feature works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Feature works
```

## No Lint/Static Analysis/CI

There is currently no php-cs-fixer, phpcs, phpstan, psalm, editorconfig, or CI
pipeline configured. Follow the conventions below manually.

## Code Style Guidelines

### PHP Version

PHP 8.1+ required. Always use `declare(strict_types=1);` at the top of every file.

### Namespaces & Imports

- No namespaces are used — all classes live in the global namespace.
- No `use` import statements — reference all types by their global names.
- Keep this convention until a composer autoloader is introduced.

### File Organization

- All library classes (`ZVec`, `ZVecSchema`, `ZVecDoc`, `ZVecException`) live in
  a single file: `php/ZVec.php`.
- Test/example files use `require_once __DIR__ . '/../php/ZVec.php';`.

### Naming Conventions

| Element            | Convention        | Example                             |
|--------------------|-------------------|-------------------------------------|
| Classes            | PascalCase        | `ZVec`, `ZVecSchema`, `ZVecDoc`     |
| Methods            | camelCase         | `createHnswIndex`, `addColumnInt64` |
| Constants          | UPPER_SNAKE_CASE  | `METRIC_IP`, `LOG_CONSOLE`, `TYPE_FLOAT` |
| Parameters         | camelCase         | `$fieldName`, `$queryVector`        |
| Private properties | camelCase (no `_` prefix) | `$handle`, `$closed`       |

### Type System

- Use full type declarations on all properties, parameters, and return types.
- Use union types where needed: `FFI\CData|string $handleOrPk`.
- Use nullable types: `?string $filter = null`.
- Use PHPDoc `@param` / `@return` only when PHP's type system is insufficient
  (e.g., array generics): `@param float[] $vector`, `@return ZVecDoc[]`.
- Do NOT add redundant PHPDoc that merely restates the type signature.

### Error Handling

- Custom exception: `ZVecException extends RuntimeException`.
- All FFI calls must be followed by a status check via `self::checkStatus()`.
- The status code from the C library is passed as the exception code:
  `throw new ZVecException(FFI::string($status->message), $status->code)`.
- Let exceptions propagate — do not catch and suppress errors within the library.
- In test scripts, use try/catch for expected exceptions; use `assert()` or
  boolean flags for other assertions.

### Design Patterns

- **Singleton FFI**: The `FFI` instance is lazy-loaded via `private static ?FFI $ffi`.
  Access through `self::ffi()`.
- **Static factories**: `ZVec` uses `create()` and `open()` static methods,
  constructor is private.
- **Fluent / builder**: `ZVecSchema` and `ZVecDoc` methods return `$this` (`self`).
- **RAII**: `__destruct()` calls `close()` / resource-free methods.
- **Ownership tracking**: Use `$ownsHandle` boolean to decide if destructor frees.

### FFI-Specific Rules

- Inline C declarations in `FFI::cdef()` — do not load the `.h` file at runtime.
- Manually allocate and free C string arrays with `FFI::new()` / `FFI::free()`.
- Always free C strings returned by the FFI layer to avoid memory leaks.
- Use `FFI::string()` to convert C strings to PHP strings before freeing.

### alterColumn() Limitations

The `alterColumn()` method supports changing column data type (scalar numeric only) and renaming:

```php
// Rename only
$collection->alterColumn('old_name', newName: 'new_name');

// Change type only (INT64 -> FLOAT)
$collection->alterColumn('value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
```

**Important limitations:**
- Cannot rename AND change type in one call — requires two separate calls
- Cannot change nullable: true → false (only false → true or keep same)
- Only scalar numeric types: INT32, INT64, UINT32, UINT64, FLOAT, DOUBLE
- Data type constants: `TYPE_INT32=4`, `TYPE_INT64=5`, `TYPE_UINT32=6`, `TYPE_UINT64=7`, `TYPE_FLOAT=8`, `TYPE_DOUBLE=9`

### Bug Reproduction Tests

**When to write a bug test:**
- Any issue discovered in `ffi/` (C++ wrapper) must have a bug test
- Any issue discovered in `php/` (PHP bindings) must have a bug test
- Unexpected behavior, segfaults, or API inconsistencies

**Bug test naming:**
- Use sequential numbering: `bug_0001.php`, `bug_0002.php`, etc.
- Zero-padded 4 digits
- Place in `tests/` directory

**Bug test format:**
```php
<?php
/**
 * Bug reproduction: [Brief description]
 * 
 * Expected: [What should happen]
 * Actual: [What actually happens]
 * 
 * Status: [Known limitation / Fixed / In progress]
 * Location: [Which file/component is affected]
 */

require_once __DIR__ . '/../php/ZVec.php';
// ... test code ...
// If bug causes crash, comment out and document
```

**Examples:**
- `bug_0003.php` - segfault after `destroy()`
- `bug_0004.php` - `max_doc_count_per_segment` minimum threshold

### Comments

- Do NOT add inline comments unless explaining something non-obvious.
- Do NOT add class-level or method-level doc comments unless they provide
  information beyond what the type signature conveys.
- PHPDoc blocks are only for array generics and complex return types.

### Formatting

- 4-space indentation (no tabs).
- Opening braces on the same line as the declaration.
- Use named arguments for clarity in calls with many parameters:
  `logType: ZVec::LOG_CONSOLE`.
- Use arrow functions for short lambdas: `fn($v) => round($v, 2)`.

### Test Conventions

**Current format (.phpt):**
- Test files use `.phpt` format in `tests/` directory
- Each test uses `--TEST--`, `--SKIPIF--`, `--FILE--`, `--EXPECT--` sections
- Run via `php run-tests.php tests/`
- Each test creates unique temp directory with `uniqid()` and cleans up with `try-finally`
- **Test naming:**
  - `tests/bug_NNNN.php` (zero-padded 4-digit number) - bug reproduction scripts  
  - `tests/test_*.phpt` - feature/functionality tests (e.g., `test_alter_column.phpt`)

**Legacy format (being migrated):**
- Old tests use standalone PHP scripts with `PASS:/FAIL:` output
- Each test creates its own temp directory and cleans up with `exec("rm -rf ...")`
- Exit with code 1 on any failure
- Will be migrated to `.phpt` format (task #24)

### Platform Notes

- Currently macOS-only (builds `.dylib`, links CoreFoundation/Security).
- The FFI shared library must be at the path expected by `ZVec.php`
  (currently `__DIR__ . '/../ffi/build/libzvec_ffi.dylib'`).
- `zvec/` directory is a git submodule - run `git submodule update --init` if missing.

### Memory Management

**FFI Memory Leaks:**
- Always free C strings returned by FFI: `FFI::free($ptr)`
- Convert to PHP string before freeing: `$str = FFI::string($ptr)`
- Never store `FFI\CData` objects in long-lived variables
- Schema/collection handles are freed in destructors (`$ownsHandle` flag)

**Collection Lifecycle:**
- `close()` - closes handle but keeps data on disk (can reopen)
- `destroy()` - removes entire directory (cannot reopen, object invalid)
- `__destruct()` calls `close()` automatically if not already closed
- After `destroy()`, any method call causes **segfault** (handle invalidated)

### Debug & Logging

```php
ZVec::init(
    logType: ZVec::LOG_CONSOLE,    // or LOG_FILE, LOG_NONE
    logLevel: ZVec::LOG_DEBUG,     // DEBUG, INFO, WARN, ERROR
    logDir: '/tmp/zvec_logs',      // for LOG_FILE mode
    queryThreads: 4,
    optimizeThreads: 2,
);
```

### Common Pitfalls

**Index Completeness:**
- After inserting docs, `index_completeness:0` until `optimize()` called
- Query works without optimize but slower (brute force scan)
- Always call `optimize()` before performance testing

**Destroy vs Close:**
```php
$c->close();     // Safe, can reopen later
$c->destroy();   // Data deleted forever, $c is now invalid!
```

**Thread Safety:**
- One `ZVec::init()` per process (call before any operations)
- Multiple collections can be open simultaneously
- Each collection handle is NOT thread-safe (use one handle per thread)

**Temp Directory Pattern:**
```php
$path = __DIR__ . '/../test_dbs/test_name_' . uniqid();  // Unique per test in test_dbs/
// ... test code ...
exec("rm -rf " . escapeshellarg($path));   // Always cleanup
```

**Note:** The `test_dbs/` directory is committed to repo but its contents are ignored via `.gitignore`. This prevents cluttering the project root when tests fail.

## API Consistency

When implementing new features, maintain consistency with the official zvec SDKs:

### Reference Implementations

1. **Node.js API** (https://zvec.org/api-reference/nodejs/)
   - Best reference for TypeScript/JavaScript API patterns
   - Shows exact enum values, parameter names, default values
   - Example: `ZVecQuantizeType = { UNDEFINED: 0, FP16: 1, INT8: 2, INT4: 3 }`

2. **Python SDK** (`zvec/python/zvec/`)
   - Check `model/param/` for parameter classes (HnswIndexParam, etc.)
   - Check `typing/` for enum definitions
   - Check `tests/` for usage examples and edge cases

3. **C++ API** (`zvec/src/include/zvec/db/`)
   - Verify what the C++ layer actually supports
   - Check constructors in `index_params.h`, `options.h`
   - Confirm enum values in `type.h`

### Keeping PHP API Compatible

- Use identical enum values (e.g., `QUANTIZE_INT8 = 2` matches Node.js/Python)
- Use similar method names (camelCase in PHP vs snake_case in Python)
- Maintain same default values when applicable
- Document any intentional deviations

## Task Planning & Documentation

### Todo Directory Structure

The `todo/` directory contains numbered task files (e.g., `01_ivf_index_creation.md`, `02_quantize_type.md`). Each task file should follow this format:

```markdown
# Task Title

## Priority: HIGH | MEDIUM | LOW

## Status: TODO | DONE

## Difficulty: N/5 ⭐ (1-5 scale)

## Description
Brief explanation of what needs to be done.

## Implementation

### FFI Layer (ffi/zvec_ffi.h/.cc)
- List changes needed in C++ wrapper

### PHP Layer (php/ZVec.php)
- List PHP changes
- Constants to add
- Method signatures

### Tests
- Test cases to add

## Notes
Any important limitations or dependencies.
```

### Before Implementation Checklist

Before starting a new feature:

1. **Check zvec documentation**: https://zvec.org/en/docs/
2. **Check Node.js API**: https://zvec.org/api-reference/nodejs/ for reference implementation
3. **Check Python SDK** in `zvec/python/zvec/` for actual implementation details
4. **Look at C++ headers** in `zvec/src/include/zvec/db/` to verify what's supported

### Example: Researching QuantizeType

When implementing quantize type support:
- Node.js API shows: `ZVecQuantizeType = { UNDEFINED: 0, FP16: 1, INT8: 2, INT4: 3 }`
- Python SDK shows: `QuantizeType.UNDEFINED`, `.FP16`, `.INT8`, `.INT4`
- C++ headers show: `QuantizeType` enum in `index_params.h`
- This confirms: values 0-3, supported on all index types (HNSW, Flat, IVF)

### Test Task Format

Tasks for test migration should specify:
- Which scenarios from `example.php` to migrate
- Group tests by documentation category (Collections, Data Operations, etc.)
- Dependencies on other tasks
- Estimated difficulty (tests are usually 1-2⭐)

## Release Workflow

### Semantic Versioning

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

- **Patch release**: `1.2.3` → `1.2.4` - Bug fixes, small changes
- **Minor release**: `1.2.3` → `1.3.0` - New features, backwards compatible
- **Major release**: `1.2.3` → `2.0.0` - Breaking changes

### Release Command (`/release`)

When user requests a release:

1. **Ask for version type** if not specified:
   - "Patch/small" = patch (0.0.1 increment)
   - "Minor/large" = minor (0.1.0 increment)
   - Breaking changes = major (1.0.0 increment)

2. **Calculate new version** based on current git tags:
   ```bash
   git describe --tags --abbrev=0  # get current version
   ```

3. **Update CHANGELOG.md**:
   - Add new section with version and date
   - List all changes since last tag
   - Categorize: Added, Changed, Deprecated, Removed, Fixed, Security

4. **Write descriptive commit message**:
   - Commit message should describe WHAT changed, not just version bump
   - Good: `feat: add quantize type support for HNSW and Flat indexes`
   - Bad: `chore: release v0.3.0` (this is just the tag message)
   - For releases with multiple changes, use a summary commit or list major changes

5. **Create git commit**:
   ```bash
   git add CHANGELOG.md [and any other files]
   git commit -m "feat: add quantize type support for HNSW and Flat indexes"
   # or for multiple features:
   git commit -m "feat: add quantize type and test planning tasks
   
   - Add quantize_type parameter to createHnswIndex and createFlatIndex
   - Add QUANTIZE_* constants (FP16, INT8, INT4)
   - Create test migration planning tasks (#18-23)"
   ```

6. **Create git tag**:
   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   ```

7. **NEVER do `git push`** - user must push manually

### Example Release Flow

```bash
# User: "patch release"
# Current: v0.2.0
# New: v0.2.1

git describe --tags --abbrev=0  # v0.2.0

# Update CHANGELOG.md with changes since v0.2.0
# Commit with descriptive message
git add CHANGELOG.md
git commit -m "fix: resolve issue with delete store recovery"

# Create tag
git tag -a v0.2.1 -m "Release v0.2.1"

# Done - user pushes manually
```
