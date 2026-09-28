# AGENTS.md — zvec-php

PHP FFI bindings for [Alibaba's zvec](https://github.com/alibaba/zvec) vector database.

## Project Structure

```
zvec-php/
├── src/ZVec.php              # Barrel file (requires all classes) + ZVec class
├── src/ZVecException.php     # Custom exception class
├── src/ZVecCollectionOptions.php  # Collection open/create options
├── src/ZVecCollectionStats.php    # Collection statistics
├── src/ZVecFieldSchema.php        # Schema introspection for a single field
├── src/ZVecIndexParams.php        # Index creation parameters (HNSW, Flat, IVF, etc.)
├── src/ZVecVectorQuery.php        # Vector query builder
├── src/ZVecGroupByVectorQuery.php # Group-by vector query builder
├── src/ZVecSchema.php             # Schema definition (field types, metrics, vectors)
├── src/ZVecDoc.php                # Document handle (getters/setters, serialization)
├── src/ZVecReRanker.php      # Base re-ranker class
├── src/ZVecRerankedDoc.php   # Reranked document class
├── src/ZVecRrfReRanker.php   # RRF re-ranker
├── src/ZVecWeightedReRanker.php # Weighted re-ranker
├── src/embeddings/           # Embedding function interfaces and implementations
├── examples/                 # Usage examples
├── ffi/                      # C++ FFI bridge (zvec_ffi.h, zvec_ffi.cc, CMakeLists.txt)
├── src/Installer.php         # Composer CLI installer for FFI shared library
├── bin/zvec-install          # CLI tool entry point for FFI library download
├── composer.json             # Composer package definition
├── tests/                    # Test files (.phpt format)
├── test_dbs/                 # Test database directory (content ignored by git)
├── tasks/todo/               # Feature planning documents
├── build_zvec_lib.sh         # Builds only the zvec C++ library (with version caching)
├── build_ffi.sh              # Builds only the FFI shared library (requires zvec built)
├── build_zvec.sh             # Orchestrator: builds zvec C++ lib + FFI shared library
├── zvec/                     # Downloaded official zvec SDK (not committed)
└── cmake-3.28.3-*/           # Vendored CMake (not committed)
```

## Build Commands

### Step 1: Fetch the zvec C++ library (only if not already present for this version)

```bash
./build_zvec_lib.sh [version]
```

Default version is `v0.7.0`.

**Since v0.7.0 we do not compile zvec from source.** The script downloads the
official prebuilt SDK published by upstream:

```
https://github.com/alibaba/zvec/releases/download/<version>/zvec-sdk-<os>-<arch>.tar.gz
```

It is extracted into `zvec/` (`zvec/src/include`, `zvec/build/lib`,
`zvec/data`) and stamped in `zvec/build/.zvec_version`. If the stamp already
matches the requested version the download is skipped. This avoids building Arrow
and RocksDB, which dominated the build time.

To force a source build instead:

```bash
ZVEC_NO_SDK=1 ./build_zvec_lib.sh v0.7.0
```

> **Note:** `zvec/` is a plain directory of SDK artifacts, not a git submodule.
> Do not run `git submodule update` — that path no longer exists.

For CI with a project-built prebuilt tarball:
```bash
./build_zvec_lib.sh v0.7.0 "https://url-to-prebuilt.tar.gz"
```

### Step 2: Build the FFI shared library (requires zvec already built)

```bash
./build_ffi.sh
```

### Build both in one command (orchestrator)

```bash
./build_zvec.sh [version]
```

This calls `build_zvec_lib.sh` then `build_ffi.sh`.

### Build PHP extension (requires zvec already built)

```bash
./php-ext/build_ext.sh
```

### Run the integration test suite

```bash
php run-tests.php -n -d extension=ffi.so tests/
```

> **Note:** The goal is *FFI enabled, legacy `zvec` extension disabled*.
> Getting only one of the two produces a silently useless run:
>
> - Without `-n`, a machine with the legacy `zvec` PHP extension (v0.4.10,
>   `php-ext/`) loaded from `php.ini` shadows the FFI classes: `src/ZVec.php`
>   bails out early (`if (extension_loaded('zvec')) return;`) and FFI-only
>   classes like `ZVecIndexParams` never load (#188).
> - With `-n` alone, FFI is **also** lost wherever it comes from a conf.d ini
>   rather than being compiled in. Every test then reports SKIP with
>   `reason: FFI extension not available` — on a machine where FFI is
>   perfectly available. A run with ~170 skips is a broken run, not a pass.
>
> `-d extension=ffi.so` re-enables FFI after `-n` has stripped the ini, so both
> goals hold. If your PHP has FFI compiled in, drop the `-d` flag and use
> `php run-tests.php -n tests/`. Sanity check before trusting a full run:
> `php run-tests.php -n -d extension=ffi.so tests/ | grep -c SKIP` must be 0.

### Run .phpt tests (standard PHP test format)

```bash
# Run all phpt tests
php run-tests.php -n -d extension=ffi.so tests/

# Run single phpt test
php run-tests.php -n -d extension=ffi.so tests/test_error_handling.phpt

# Run with verbose output
php run-tests.php -n -d extension=ffi.so -v tests/
```

The `run-tests.php` script is bundled with this project (from php-src).
It parses `.phpt` files and executes the PHP code within `--FILE--` sections.

### Run legacy PHP test scripts

Legacy scripts need the same flags as the suite, so they exercise the FFI
bindings rather than a pre-installed `zvec` extension (#188):

```bash
# Run a single test
php -n -d extension=ffi.so tests/test_error_handling.php

# Run all tests (old format)
for f in tests/*.php; do php -n -d extension=ffi.so "$f"; done
```

> **Warning:** `run-tests.php` derives the executable path from the `.phpt`
> basename in the same directory and **unlinks it unconditionally** — running
> the suite deletes legacy tracked `tests/<name>.php` files that share a
> basename with a `.phpt` file (#187). Back them up first, or restore with
> `git checkout -- tests/` after the run.

### Run all tests (both formats)

```bash
# Build first if needed
./build_zvec.sh

# Run all tests (phpt suite)
php run-tests.php -n -d extension=ffi.so tests/

# Run legacy scripts (restore deleted tests/*.php first, see warning above)
for f in tests/*.php; do php -n -d extension=ffi.so "$f"; done
```

## Testing Requirements

After every feature implementation, **ALL tests must pass** before the task is considered complete:

### Pre-commit Test Checklist

Before marking any task as DONE:

1. **Build the FFI library** (if C++ changes):
   ```bash
   # If zvec version changed (e.g. new v0.7.0):
   ./build_zvec_lib.sh v0.7.0

   # Rebuild FFI wrapper (always if ffi/*.cc or ffi/*.h changed):
   ./build_ffi.sh

   # Or use the orchestrator for both:
   ./build_zvec.sh
   ```

2. **Run all .phpt tests**:
   ```bash
   php run-tests.php -n -d extension=ffi.so tests/
   ```
   Must report `Tests skipped: 0`. A large skip count means FFI was not loaded.

3. **Run all tests**:
   ```bash
   php run-tests.php -n -d extension=ffi.so tests/
   for f in tests/*.php; do php -n -d extension=ffi.so "$f"; done   # legacy scripts (restore deleted files first, see #187)
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
require_once __DIR__ . '/../src/ZVec.php';
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

- Composer PSR-4 autoloading is configured for `CrazyGoat\ZVec\` namespace in `src/`.
- Global classes (`ZVec`, `ZVecSchema`, `ZVecDoc`, `ZVecException`) are loaded via
  Composer classmap autoloading — no `require_once` needed when using Composer.
- No `use` import statements for global classes — reference them by their unqualified names.

### File Organization

- Each library class has its own file under `src/` (e.g., `src/ZVecException.php`,
  `src/ZVecSchema.php`, `src/ZVecDoc.php`).
- `src/ZVec.php` is a barrel file that requires all individual class files, providing
  backward compatibility for `require_once __DIR__ . '/../src/ZVec.php';`.
- Test/example files use `require_once __DIR__ . '/../src/ZVec.php';`.

### Naming Conventions

| Element            | Convention        | Example                             |
|--------------------|-------------------|-------------------------------------|
| Classes            | PascalCase        | `ZVec`, `ZVecSchema`, `ZVecDoc`     |
| Methods            | camelCase         | `createHnswIndex`, `addColumnInt64` |
| Constants          | UPPER_SNAKE_CASE  | `METRIC_IP`, `LOG_CONSOLE`, `TYPE_FLOAT` |
| Parameters         | camelCase         | `$fieldName`, `$queryVector`        |
| Private properties | camelCase (no `_` prefix) | `$handle`, `$closed`       |

#### Schema Field Method Conventions

`ZVecSchema` uses four naming patterns for field-adding methods:

| Pattern | Examples | Description |
|---------|----------|-------------|
| `add<Type>()` | `addInt64()`, `addString()`, `addBool()`, `addBinary()` | Scalar and primitive types — no prefix |
| `addArray<Type>()` | `addArrayInt32()`, `addArrayString()`, `addArrayBool()` | Array types — `Array` infix |
| `addVector<Type>()` | `addVectorFp32()`, `addVectorBinary64()` | Dense vector types — `Vector` prefix |
| `addSparseVector<Type>()` | `addSparseVectorFp32()`, `addSparseVectorFp16()` | Sparse vector types — `SparseVector` prefix |

The deprecated `addField*()` methods (`addFieldBinary()`, `addFieldArrayString()`, etc.) remain as backward-compatible aliases that emit `E_USER_DEPRECATED` warnings.

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
- **Getter/setter with fluent setters**: Data classes (`ZVecRerankedDoc`,
  `ZVecRrfReRanker`, `ZVecWeightedReRanker`) use `private` properties with
  public getters and setters. Setters return `self` for fluent chaining.
- **RAII**: `__destruct()` calls `close()` / resource-free methods.
- **Ownership tracking**: Use `$ownsHandle` boolean to decide if destructor frees.

### FFI-Specific Rules

- C declarations live in `ffi/zvec_ffi_php.h`, which `ZVec::ffi()` strips and
  passes to `FFI::cdef()`. That file must mirror `ffi/zvec_ffi.h` — a function
  added to only one of them throws `FFI\Exception: undefined C function`.
- Manually allocate and free C string arrays with `FFI::new()` / `FFI::free()`.
- Always free C strings returned by the FFI layer to avoid memory leaks.
- Use `FFI::string()` to convert C strings to PHP strings before freeing.

### Never inline a zvec singleton in the FFI adapter

**Never call `GlobalConfig::Instance()` (or any other
`zvec::ailego::Singleton<T>::Instance()`) directly from `ffi/zvec_ffi.cc`.**
Use `global_config_ptr()` instead, which resolves the real `Instance()` inside
libzvec with `dlsym`.

`Singleton<T>::Instance()` is an inline template holding a function-local
static. Inlining it in the adapter makes the compiler emit the adapter's *own*
`GNU_UNIQUE` definition of `::obj` plus its *own* guard variable. The dynamic
linker still binds libzvec's internal calls to the same object, but each module
then runs the constructor under its own guard — so the singleton is constructed
twice and destroyed twice at exit. The second destruction releases an
already-freed `shared_ptr` control block:

```
free(): chunks in smallbin corrupted
Aborted (core dumped)     # exit 134
```

valgrind pinpoints it as an invalid read in `_Sp_counted_base::_M_release()`
under `~GlobalConfig()` inside `libzvec_ffi.so`. The symptom appears at process
exit, not at the call site, so it looks unrelated to whatever code you were
working on. Regression test:
`tests/bug_0055_globalconfig_singleton_double_destroy.phpt`.

Rule of thumb: if a `zvec::` C++ symbol would be *inlined* into the adapter and
is also used inside libzvec, resolve it at runtime instead of calling it
directly.

### alterColumn() Limitations

The `alterColumn()` method supports changing column data type (scalar numeric only) and renaming:

```php
// Rename only
$collection->alterColumn('old_name', newName: 'new_name');

// Change type only (INT64 -> FLOAT)
$collection->alterColumn('value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
```

**Important limitations:**
- `nullable` must be explicitly specified when `newDataType` is provided — otherwise throws `ZVecException`
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

require_once __DIR__ . '/../src/ZVec.php';
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
- Run via `php run-tests.php -n -d extension=ffi.so tests/`: `-n` avoids a legacy pre-installed `zvec` extension shadowing the FFI classes, `-d extension=ffi.so` keeps FFI available when it comes from a conf.d ini — see "Run the integration test suite"
- Each test creates unique temp directory with `uniqid()` and cleans up with `try-finally`
- **Test naming:**
  - `tests/bug_NNNN.php` (zero-padded 4-digit number) - bug reproduction scripts  
  - `tests/test_*.phpt` - feature/functionality tests (e.g., `test_alter_column.phpt`)

**A `.phpt` and a hand-written `.php` may share a base name.** Both are valid
tests. `run-tests.php` extracts each `.phpt` body to
`tests/<name>.php.tmp-extract` (never `tests/<name>.php`) precisely so the
hand-written file cannot be clobbered or deleted — see issue #187. Do not
"clean up" `tests/*.php.tmp-extract` with `git add -A`; they are gitignored.
Beware of `git add -A` in general here: the directory holds both tracked tests
and runner output.

**Legacy format (being migrated):**
- Old tests use standalone PHP scripts with `PASS:/FAIL:` output
- Each test creates its own temp directory and cleans up with `exec("rm -rf ...")`
- Exit with code 1 on any failure
- Will be migrated to `.phpt` format (task #24)

### Platform Notes

- Pre-built FFI library available for Linux x86_64 (glibc).
- macOS and musl Linux builds not yet available as pre-built artifacts.
- The FFI shared library is resolved from two locations (in order):
  1. `__DIR__ . '/../lib/libzvec_ffi.so'` — Composer-installed (via `vendor/bin/zvec-install`)
  2. `__DIR__ . '/../ffi/build/libzvec_ffi.so'` — locally built (via `./build_zvec.sh`)
- `zvec/` holds the downloaded official SDK (not a git submodule). If it is
  missing, run `./build_zvec_lib.sh` to fetch it.

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

### Memory Leak Regression Tests

The project includes regression tests to detect FFI memory leaks:

**Test files:**
- `tests/test_memory_collection_lifecycle.phpt` — 50x create/open/close/destroy cycle
- `tests/test_memory_deserialize_buffer.phpt` — 100x serialize/deserialize cycle
- `tests/test_memory_query_output_fields.phpt` — 100x query with/without output fields
- `tests/test_memory_init_error_path.phpt` — 20x init error/recovery cycles
- `tests/test_memory_delete_cstrings.phpt` — 30x insert/delete/fetch cycles

**Methodology:**
- PHP heap monitoring: `memory_get_usage()` delta between start and end
- Native memory monitoring: VmRSS from `/proc/self/status` (Linux only)
- Threshold: 500KB maximum growth per test scenario
- Each test uses `try-finally` with `uniqid()` temp directory cleanup

**Adding new memory tests:**
1. Create `tests/test_memory_<scenario>.phpt`
2. Record `memory_get_usage()` before the loop
3. Run the scenario N times (typically 20-100 iterations)
4. Record `memory_get_usage()` after the loop
5. Assert delta is within 500KB threshold
6. Use `try-finally` for cleanup

**VmRSS monitoring (optional, Linux only):**
```php
function getVmRSS(): int {
    $status = @file_get_contents('/proc/self/status');
    if ($status === false) return 0;
    if (preg_match('/^VmRSS:\s+(\d+)\s+kB$/m', $status, $m)) {
        return (int)$m[1];
    }
    return 0;
}
```

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
- The global collections registry is thread-safe (std::shared_mutex + std::unordered_map)

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

### PHP Layer (src/ZVec.php)
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

### After Implementation Checklist

When a task is completed:

1. **Update task file status**: Change `## Status: TODO` to `## Status: DONE` in the task file
2. **Move to done folder**: Move the task file from `tasks/todo/` to `tasks/done/`
3. **Update implementation section**: Document what was actually implemented
4. **Verify all tests pass** (see Testing Requirements section above)

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
