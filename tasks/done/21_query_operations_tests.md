# Query Operations Tests

## Priority: MEDIUM

## Status: DONE

## Difficulty: 2/5 ⭐⭐

## Description

Test vector search and query operations.

Part of: Test Suite Migration (split from task #18)

Based on: https://zvec.org/en/docs/data-operations/query/

---

## Test: test_query_basic.php

### Coverage
- Single vector search
- Query with topk parameter
- Query with includeVector=true
- Query with includeVector=false
- Verify score ordering (descending for IP/COSINE)

---

## Test: test_query_filtered.php

### Coverage
- Vector search + scalar filter
- Filter only (no vector, via queryByFilter)
- Complex filter conditions
- Filter matching 0 results

---

## Test: test_query_output_fields.php

### Coverage
- Query with outputFields parameter
- Query returning all fields (default)
- Query returning specific fields only
- Verify missing fields are null

---

## Test: test_query_hnsw_params.php

### Coverage
- Query with hnswEf parameter (ef value)
- Compare recall with different ef values
- Query with queryParamType parameter

---

## Test: test_groupby_query.php

### Coverage
- GroupBy query with groupByField
- Test groupCount and groupTopk params
- Note: zvec marks this "Coming Soon", results not grouped yet
- Verify API works even if grouping not active

---

## Implementation

All test files created and passing:

### test_query_basic.phpt
- Single vector search with default and custom topk
- Query with includeVector=true/false
- Score ordering verification (descending for IP, ascending for L2)

### test_query_filtered.phpt
- Vector search with simple and compound filters
- Filter only (queryByFilter) with various conditions
- Complex filters with AND/OR, numeric comparisons, IN operator
- Zero result scenarios

### test_query_output_fields.phpt
- Query returning all fields (default)
- Query with specific outputFields (field selection)
- outputFields with queryByFilter
- Missing fields return null correctly

### test_query_hnsw_params.phpt
- Query with different hnswEf values (50, 200, 400)
- Recall comparison between ef settings
- QUERY_PARAM_NONE and QUERY_PARAM_HNSW
- Score ordering verification

### test_groupby_query.phpt
- API verification for future GroupBy feature
- Manual grouping demonstration (client-side)
- Documents zvec "Coming Soon" status

### Notes

All tests:
- Use unique temp directories with `uniqid()`
- Clean up with try-finally
- Test both success and edge cases
- Pass with 100% success rate
