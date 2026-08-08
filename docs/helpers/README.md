# Knowledge Base (docs/helpers/)

This directory is the project's persistent knowledge base, maintained by
`coder` and `review` subagents (see `docs/workflow.md`).

## Files

- `faq.md` — frequently asked questions, recurring pitfalls and their
  solutions (FFI memory leaks, `test_dbs/` cleanup, the `-n` flag, ...)
- `decisions.md` — important decisions with rationale (API compatibility
  with Node.js/Python SDKs, naming, intentional deviations)

## Rules

- **Read before starting** a task — the knowledge carries over between tasks.
- **Append after finishing** — one topic, the problem, the
  solution/decision, optionally an issue/commit reference.
- Entries must be **short and actionable**. No essays.
- In doubt whether an entry is worth keeping, ask the user.
- New files may be added for a new topic area, but keep the structure flat:
  one FAQ file, one decisions file, or a topic file only when a category
  grows large (e.g. `ffi-memory.md`).

## Entry template (faq.md)

```markdown
### Topic title

**Problem:** one sentence — what breaks / what people trip on.

**Solution:** one or two sentences — the correct approach.

**Reference:** optional (issue/commit/task number).
```

## Entry template (decisions.md)

```markdown
### Decision title

**Decision:** what was decided (API shape, naming, deviation).

**Rationale:** why — reference implementation, constraint, past bug.

**Reference:** optional (issue/commit/task number).
```
