# Per-Document Status on Batch Operations

## Priority: LOW

## Status: TODO

## Description

Python SDK returns per-document `Status` on insert/upsert/update/delete. Our PHP wrapper throws an exception on the first failure, losing information about which specific documents failed in a batch.

## Python API

```python
statuses = collection.insert([doc1, doc2, doc3])
# Returns: [Status(OK), Status(ALREADY_EXISTS, "doc2 exists"), Status(OK)]

for status in statuses:
    if not status.ok():
        print(f"Failed: {status.message()}")
```

## Current PHP implementation

```php
// Throws ZVecException on first failure — no per-doc status
$collection->insert($doc1, $doc2, $doc3);
```

## Changes needed

### ffi/zvec_ffi.cc
- Currently we check `res.value()` statuses and return first error
- Change: return array of statuses, or collect all errors

### Option A: Return detailed results
```php
$results = $collection->insertBatch([$doc1, $doc2, $doc3]);
// Returns: [['pk' => 'doc1', 'ok' => true], ['pk' => 'doc2', 'ok' => false, 'error' => '...']]
```

### Option B: Collect all errors in exception
```php
try {
    $collection->insert($doc1, $doc2, $doc3);
} catch (ZVecBatchException $e) {
    $e->getFailedDocs(); // ['doc2' => 'already exists']
    $e->getSucceededDocs(); // ['doc1', 'doc3']
}
```

### Notes
- Breaking change if we change current behavior
- Could add separate `insertBatch()` method that returns statuses
- Or add optional parameter to control behavior
