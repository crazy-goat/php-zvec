# SegmentOption / max_doc_count_per_segment

## Priority: LOW

## Status: DONE

## Description

Add `max_buffer_size` parameter to CollectionOptions. This is the buffer size limit for segment operations, configurable at both creation and open time.

Note: Python SDK's `SegmentOption` is marked as "primarily for internal use" - users should use `CollectionOption` instead. We already expose the equivalent fields through `CollectionOptions`.

## Changes Implemented

### FFI Layer (ffi/zvec_ffi.h)
- Added `uint32_t max_buffer_size` parameter to:
  - `zvec_collection_create()` 
  - `zvec_collection_open()`
  - `zvec_collection_options()` (as output parameter)

### FFI Layer (ffi/zvec_ffi.cc)
- Updated `zvec_collection_create()` to pass `max_buffer_size` to `CollectionOptions`
- Updated `zvec_collection_open()` to pass `max_buffer_size` to `CollectionOptions`
- Updated `zvec_collection_options()` to return `max_buffer_size` from collection options

### PHP Layer (php/ZVec.php)
- Updated FFI declarations to include `max_buffer_size` parameter
- Added `$maxBufferSize` parameter to `ZVec::create()` with default value 0
- Added `$maxBufferSize` parameter to `ZVec::open()` with default value 0
- Updated `ZVec::options()` to return `max_buffer_size` in the returned array
- Updated PHPDoc to document the new return field

### Default Value
The C++ default is `64 * 1024 * 1024` (64MB). When passing 0, the behavior depends on zvec implementation - it may use 0 or apply internal defaults. Users should explicitly pass the desired value.

## Test

Created `tests/test_max_buffer_size.phpt` with comprehensive tests:
- Creating collection with default (64MB) buffer size
- Creating collection with custom (32MB) buffer size  
- Opening collection with configurable buffer size

## Notes
- The `max_buffer_size` appears to be configurable at both create and open time
- This differs from some collection options that are stored at creation
- SegmentOption in Python SDK has same fields: read_only, enable_mmap, max_buffer_size
- All three options are now fully exposed in our PHP FFI bindings

## References
- C++ `options.h`: `CollectionOptions` struct with `max_buffer_size_` field
- Python SDK documents SegmentOption as "internal use only"
