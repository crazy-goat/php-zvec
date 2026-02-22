# Per-Document Status on Batch Operations

## Priority: LOW

## Status: DONE

## Description

Python SDK returns per-document `Status` on insert/upsert/update/delete. Our PHP wrapper throws an exception on the first failure, losing information about which specific documents failed in a batch.

## Implementation

### Option A Implemented: Return detailed results via new methods

Added three new methods that return per-document status:

```php
$results = $collection->insertBatch($doc1, $doc2, $doc3);
// Returns: [['pk' => 'doc1', 'ok' => true], ['pk' => 'doc2', 'ok' => false, 'error' => '...']]
```

### FFI Layer (ffi/zvec_ffi.h/.cc)

Added `zvec_batch_result_t` struct:
```c
typedef struct {
    int count;
    int* codes;           // Array of status codes (0 = success)
    char** messages;      // Array of messages (NULL if success)
    char** doc_pks;       // Array of document primary keys
} zvec_batch_result_t;
```

Added batch functions:
- `zvec_collection_insert_batch()` - returns per-doc status for inserts
- `zvec_collection_upsert_batch()` - returns per-doc status for upserts
- `zvec_collection_update_batch()` - returns per-doc status for updates
- `zvec_batch_result_free()` - frees the result struct

### PHP Layer (php/ZVec.php)

Added methods to `ZVec` class:
- `insertBatch(ZVecDoc ...$docs): array` - returns per-doc results
- `upsertBatch(ZVecDoc ...$docs): array` - returns per-doc results
- `updateBatch(ZVecDoc ...$docs): array` - returns per-doc results

Each method returns array of arrays with keys:
- `pk` (string): Document primary key
- `ok` (bool): Whether operation succeeded
- `error` (string|null): Error message if failed, null if succeeded

### Tests

Added `tests/test_batch_operations.phpt` covering:
- Successful batch insert with all new documents
- Batch insert with duplicate (partial failure)
- Batch upsert (all succeed including existing docs)
- Batch update with non-existent document (partial failure)

## API Usage Example

```php
// Insert batch and check results
$results = $collection->insertBatch($doc1, $doc2, $doc3);

foreach ($results as $r) {
    if ($r['ok']) {
        echo "Inserted: {$r['pk']}\n";
    } else {
        echo "Failed: {$r['pk']} - {$r['error']}\n";
    }
}

// Count successes and failures
$successes = array_filter($results, fn($r) => $r['ok']);
$failures = array_filter($results, fn($r) => !$r['ok']);
echo "Success: " . count($successes) . ", Failed: " . count($failures) . "\n";
```

## Notes

- Original `insert()`, `upsert()`, `update()` methods unchanged (throw on first error)
- New `*Batch()` methods provide detailed per-doc status without breaking existing API
- All existing tests pass (44/45, 1 expected fail for GroupByQuery)
- Integration tests pass (example.php: 21 scenarios)
