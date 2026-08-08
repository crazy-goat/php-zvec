# Decisions — Important Choices & Rationale

Knowledge base maintained by `coder`/`review` subagents. Read before
starting a task, append after finishing. See `README.md` for rules.

---

### Enum values must match Node.js / Python SDKs

**Decision:** PHP constants use identical values to the official SDKs
(e.g. `QUANTIZE_INT8 = 2`, `METRIC_IP`, `TYPE_FLOAT = 8`).

**Rationale:** keeps the PHP API compatible across languages; the Node.js
API, Python SDK and C++ headers are the reference implementations.

---

### PHP 8.1+ with full type declarations

**Decision:** all properties, parameters and return types are fully typed;
union types (`FFI\CData|string`) and PHPDoc only where PHP's type system
is insufficient (array generics).

**Rationale:** safety and self-documenting code; no redundant PHPDoc.

---

### FFI declarations inline in `FFI::cdef()`

**Decision:** inline C declarations in `FFI::cdef()` — never load the `.h`
file at runtime.

**Rationale:** no runtime dependency on header paths.

---

### Global classes via Composer classmap

**Decision:** global classes (`ZVec`, `ZVecSchema`, `ZVecDoc`, ...) are
loaded via Composer classmap autoloading; `src/ZVec.php` is a barrel file
for backward compatibility with `require_once`.

---

### Alter column: scalar numeric types only, nullable never turns off

**Decision:** `alterColumn()` supports renaming and type change for scalar
numeric types only; `nullable: true → false` is not supported; rename and
type change cannot be combined in one call.

**Rationale:** limitation of the underlying C++ layer — verify in
`zvec/src/include/zvec/db/` before extending.
