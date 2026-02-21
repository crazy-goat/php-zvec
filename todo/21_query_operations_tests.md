# Query Operations Tests

## Priority: MEDIUM

## Status: TODO

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

## Notes

- Score ordering depends on metric type:
  - IP, COSINE: higher is better (descending)
  - L2: lower is better (ascending)
- Filter syntax: SQL-like WHERE clause
- Query params are optional, defaults should work
