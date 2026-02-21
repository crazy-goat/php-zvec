# SegmentOption / max_doc_count_per_segment

## Priority: LOW

## Status: TODO

## Description

Python SDK has `SegmentOption` with `read_only`, `enable_mmap`, and `max_buffer_size`. We expose `max_doc_count_per_segment` on schema, but there may be additional segment-level options we're missing.

## Python API

```python
opt = SegmentOption()
print(opt.enable_mmap)       # True
print(opt.read_only)         # False
print(opt.max_buffer_size)   # internal
```

## Changes needed

### Research first
- Check if SegmentOption is exposed in C++ API or only internal
- Probably internal/advanced — LOW priority

### Notes
- We already set `max_doc_count_per_segment` on schema which is the main user-facing setting
- SegmentOption appears to be primarily internal
