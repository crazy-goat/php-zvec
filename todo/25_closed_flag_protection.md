# Add closed flag to prevent segfaults

## Priority: HIGH

## Status: TODO

## Difficulty: 2/5 ⭐⭐

## Description

Add `$closed` boolean flag to ZVec collection class to track if collection was closed/destroyed. Block all operations on closed collection with clear PHP exception instead of C++ segfault.

## Problem

Currently, calling any method on a closed/destroyed collection causes segfault (exit code 139):
```php
$c->close();
$c->insert($doc);  // SEGFAULT!
$c->query(...);     // SEGFAULT!
$c->stats();        // SEGFAULT!
```

This is documented in `tests/bug_0003.php` as known limitation.

## Solution

Add `private bool $closed = false` property to ZVec class:

1. Set `$closed = true` in `close()` method
2. Set `$closed = true` in `destroy()` method  
3. Add check at start of every public method:
   ```php
   if ($this->closed) {
       throw new ZVecException("Collection is closed");
   }
   ```

## Implementation

### PHP Layer (php/ZVec.php)

```php
class ZVec {
    private bool $closed = false;
    
    public function close(): void {
        if ($this->closed) return;
        // ... existing code ...
        $this->closed = true;
    }
    
    public function insert(ZVecDoc $doc): void {
        $this->ensureOpen();
        // ... existing code ...
    }
    
    public function query(...): array {
        $this->ensureOpen();
        // ... existing code ...
    }
    
    // ... all other public methods ...
    
    private function ensureOpen(): void {
        if ($this->closed) {
            throw new ZVecException("Collection is closed or destroyed");
        }
    }
}
```

## Tests

Update `tests/test_error_handling.php` to test closed collection operations:
- `insert()` after close - should throw ZVecException
- `query()` after close - should throw ZVecException  
- `stats()` after close - should throw ZVecException
- Double close should be safe (no-op)

## Notes

- This is PHP-layer protection only - C++ layer still segfaults
- FFI handle becomes invalid after close(), we can't prevent that
- But we CAN prevent PHP code from using it
- Reference: `tests/bug_0003.php` for segfault reproduction
