# Improve error handling and cleanup safety

## Priority: MEDIUM

## Status: TODO

## Difficulty: 2/5 ⭐⭐

## Description

Improve error handling in PHP wrapper to safely cleanup after failed operations without segfaults or RocksDB lock errors.

## Problem

When operations fail (e.g., missing required field, invalid data type), the collection is left in an inconsistent state:

```php
$c = ZVec::create($path, $schema);
$doc = new ZVecDoc('doc1');
$doc->setVectorFp32('v', [...]);  // Missing required 'id' field
$c->insert($doc);  // Throws ZVecException

// Collection is now in bad state
$c->close();  // May fail or leave locks
exec("rm -rf $path");  // RocksDB errors about locks/files
```

Current issues observed:
1. `IO error: lock hold by current process` when recreating collection at same path
2. `Failed to flush cf[id$TERMS] of RocksDB` during cleanup
3. Segfault when calling operations after failed insert

## Solution

1. **Better cleanup in error scenarios:**
   - Track if collection is in "dirty" state after failed operation
   - Provide `forceClose()` method that ignores errors
   - Cleanup temp directories safely

2. **Resource tracking:**
   - Add `private bool $dirty = false` flag
   - Set dirty=true before risky operations
   - Clear dirty=false on success
   - Force cleanup if dirty=true in destructor

3. **Safe temp directory handling:**
   - Use unique temp dirs per test (already done in tests)
   - Don't reuse paths after failures
   - Document that failed collections should use `destroy()` not `close()`

## Implementation

### Option A: Dirty flag approach

```php
class ZVec {
    private bool $dirty = false;
    
    public function insert(ZVecDoc $doc): void {
        $this->dirty = true;
        try {
            // ... FFI insert ...
            $this->dirty = false;  // Success
        } catch (ZVecException $e) {
            // Keep dirty=true, rethrow
            throw $e;
        }
    }
    
    public function close(): void {
        if ($this->dirty) {
            // Log warning or force cleanup
            error_log("Warning: Closing dirty collection");
        }
        // ... existing close ...
    }
}
```

### Option B: Destructor safety

```php
public function __destruct() {
    if (!$this->closed && $this->ownsHandle) {
        try {
            $this->close();
        } catch (Throwable $e) {
            // Ignore errors during destruction
        }
    }
}
```

## Tests

Add to `tests/test_error_handling.php`:
- Verify cleanup after failed insert doesn't segfault
- Verify multiple failed operations can be cleaned up
- Test that unique temp dirs prevent lock issues

## Notes

- Root cause is in C++ layer (RocksDB lock handling)
- PHP can only mitigate, not fully fix
- Current workaround: use unique temp directories
- Related to task #25 (closed flag) - both improve robustness
- See error logs in `tests/test_error_handling.php` for examples
