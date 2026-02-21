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

## Workflow Guidelines

### Release Command (`/release` or "wydaj wersję")

When user requests a release, follow this procedure:

1. **Ask for version type** if not specified:
   - **Major** (breaking changes): 1.x → 2.0.0
   - **Minor** (new features): 1.2.x → 1.3.0
   - Default to minor if user is unsure

2. **Calculate new version** based on current git tags:
   ```bash
   git describe --tags --abbrev=0  # get current version
   ```

3. **Update CHANGELOG.md** (create if doesn't exist):
   - Add new section with version and date
   - List changes since last tag (from git log or ask user)

4. **Create git commit**:
   ```bash
   git add CHANGELOG.md
   git commit -m "chore: release vX.Y.Z"
   ```

5. **Create git tag**:
   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   ```

6. **NEVER do `git push`** - user must push manually

### Git Commits

- **Never commit without explicit user request**. Always ask before creating commits.
- When asked to commit, follow the standard git workflow described in system instructions.
- Keep commit messages concise and descriptive, following conventional commits format:
  - `feat:` for new features
  - `fix:` for bug fixes  
  - `docs:` for documentation
  - `refactor:` for code refactoring
  - `test:` for test changes
