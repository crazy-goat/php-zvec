# AGENTS.md — zvec-php

PHP FFI bindings for [Alibaba's zvec](https://github.com/alibaba/zvec) vector database.

## Project Structure

```
zvec-php/
├── php/ZVec.php          # Main library (ZVec, ZVecSchema, ZVecDoc, ZVecException)
├── php/example.php       # Integration test / usage examples (21 scenarios)
├── ffi/                  # C++ FFI bridge (zvec_ffi.h, zvec_ffi.cc, CMakeLists.txt)
├── tests/                # Bug reproduction scripts (plain PHP, no framework)
├── todo/                 # Feature planning documents (17 items)
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

### Run a single bug reproduction test

```bash
php tests/bug_0001.php
php tests/bug_0002.php
```

### Run all tests

```bash
php php/example.php && php tests/bug_0001.php && php tests/bug_0002.php
```

There is no PHPUnit, no composer, no formal test runner. Tests are standalone PHP
scripts that output `PASS:` / `FAIL:` and `exit(1)` on failure.

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

- Test files are standalone PHP scripts in `tests/` or `php/example.php`.
- Each test creates its own temp directory and cleans up with `exec("rm -rf ...")`.
- Output format: `PASS: <test_id> - <description>` or `FAIL: <test_id> - <description>`.
- Exit with code 1 on any failure.
- **Test naming:**
  - `tests/bug_NNNN.php` (zero-padded 4-digit number) - bug reproduction scripts
  - `tests/test_*.php` - feature/functionality tests (e.g., `test_alter_column.php`)
- Bug reproductions go in `tests/bug_NNNN.php`.
- New feature tests go in `tests/test_*.php`.

### Platform Notes

- Currently macOS-only (builds `.dylib`, links CoreFoundation/Security).
- The FFI shared library must be at the path expected by `ZVec.php`
  (currently `__DIR__ . '/../ffi/build/libzvec_ffi.dylib'`).

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
