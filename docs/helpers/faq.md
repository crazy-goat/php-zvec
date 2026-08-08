# FAQ — Recurring Pitfalls & Solutions

Knowledge base maintained by `coder`/`review` subagents. Read before
starting a task, append after finishing. See `README.md` for rules.

---

### Run tests with `-n` (no php.ini)

**Problem:** a legacy pre-installed `zvec` PHP extension (v0.4.10) in
`php.ini` shadows the FFI classes (`src/ZVec.php` bails out early), so FFI
classes like `ZVecIndexParams` never load and tests fail en masse.

**Solution:** always run the suite as
`php run-tests.php -n tests/` — the `-n` flag disables the extension.

**Reference:** issue #188.

---

### `run-tests.php` deletes legacy `tests/*.php` files

**Problem:** `run-tests.php` derives the executable path from the `.phpt`
basename and unlinks it unconditionally — legacy tracked
`tests/<name>.php` files sharing a basename with a `.phpt` file get
deleted.

**Solution:** back them up first, or restore with
`git checkout -- tests/` after the run. Note the restore also reverts
local edits — keep fixes re-applied.

**Reference:** issue #187.

---

### Always call `optimize()` before performance testing

**Problem:** after inserting docs, `index_completeness: 0` — queries work
but fall back to brute-force scan.

**Solution:** call `optimize()` before performance tests.

---

### Free C strings and follow status checks

**Problem:** memory leaks in FFI bindings.

**Solution:** always convert with `FFI::string($ptr)` and `FFI::free($ptr)`;
never store `FFI\CData` in long-lived variables; check status after every
FFI call via `self::checkStatus()`.

---

### `destroy()` invalidates the handle

**Problem:** after `destroy()` any method call segfaults.

**Solution:** only `close()` if the collection may be reopened; never use
an object after `destroy()`.

---

### queryVector() enforces query-param/index-type match

**Problem:** `queryVector()` rejects a query when the params' index type
(`HnswQueryParams`, `VamanaQueryParams`, ...) differs from the field's index
type (validation in `zvec/src/db/index/common/query.cc`).
`setVamanaParams()`/`setHnswRabitqParams()` used to call
`zvec_vector_query_set_hnsw_ef` (HnswQueryParams), so queryVector() on
Vamana/RaBitQ indexes always failed with INVALID_ARGUMENT.

**Solution:** dedicated FFI setters create the matching params class:
`zvec_vector_query_set_vamana_ef_search` → `VamanaQueryParams`,
`zvec_vector_query_set_hnsw_rabitq_ef` → `HnswRabitqQueryParams`.
The legacy `query()` path was never affected (it rebuilds params per
`queryParamType`).

**Reference:** issue #193.
