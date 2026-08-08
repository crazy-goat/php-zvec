# Workflow: Issue → Feature Branch → Implementation → Code Review → PR → CI → Merge

This document describes the complete workflow for handling issues in the
[crazy-goat/php-zvec](https://github.com/crazy-goat/php-zvec) repository
using `gh`, `git` and **Pi subagents**.

The main agent acts as an **orchestrator**: it delegates issue triage,
implementation and code review to subagents, then synthesizes the results,
while the parent session keeps control of branching, committing, pushing
and merging.

---

## Subagent Selection

| Stage | Universal agent (default) | More specialized (task difficulty) |
|-------|---------------------------|------------------------------------|
| Issue triage | `explore` or `delegate` | — |
| API research | `researcher` | `explore` / `context-builder` (repo-local) |
| Planning (optional) | `planner` | `oracle` (challenge the direction) |
| Implementation | `coder` | `coder-high`, `worker` (harder/riskier) |
| Code review | `review` | `review-critical`, `reviewer` (risky/large) |

**Rule of thumb:** always start with the universal agents (`explore`, `coder`,
`review`). Pick a more specialized agent only when the task difficulty
justifies it — e.g. `coder-high` for multi-file or risky changes, `worker`
when following an approved plan, `review-critical` for security- or
data-sensitive changes.

Each subagent receives a **focused task** with: the exact goal, the scope
(files/branch), constraints and the expected output shape. Subagents must
**not commit or push** — that stays in the parent session.

`coder` and `review` subagents also maintain the project knowledge base in
`docs/helpers/` (FAQ, common mistakes, decisions) and collect out-of-scope
findings as follow-up issue candidates — see sections 4, 5 and the
"Knowledge Base" section below.

---

## 1. Browse Open Issues (subagent)

Delegate issue triage to a subagent (`explore` or `delegate`):

```text
Task: "List ALL open issues — `--limit 30` is only `gh`'s default value,
there may be more. Use `gh issue list --state open --limit 100 --json
number,title,labels` (or paginate with `--page N`) until every open issue
has been seen. Inspect the most promising ones with
`gh issue view <NUMBER> --json title,body,labels,state`.
Recommend the top 3 most impactful issues, each with a one-line
justification. Do not modify anything."

Criteria:
- Issues labeled `enhancement`, `bug`, `good-first-issue`
- Issues about stability, memory leaks, data correctness
- Issues blocking other tasks
- Issues most relevant to users (documentation, API coverage)
```

The parent picks one issue from the recommendation and proceeds.

---

## 2. Create a Fresh Feature Branch

```bash
# Make sure you're on main with the latest changes
git checkout main
git pull origin main

# Create a feature branch
git checkout -b feat/issue-<NUMBER>-<short-description>
```

**Branch naming convention:**
- `feat/issue-<NUMBER>-<kebab-case>` — new feature
- `fix/issue-<NUMBER>-<kebab-case>` — bug fix
- `docs/issue-<NUMBER>-<kebab-case>` — documentation
- `test/issue-<NUMBER>-<kebab-case>` — test migration or additions

---

## 3. Research Before Implementation (subagent)

Before coding, delegate API verification to a subagent (`researcher`, or
`explore` for repository-local checks):

```text
Task: "Verify the API for issue #<NUMBER> against reference implementations:
1. zvec documentation (https://zvec.org/en/docs/)
2. Node.js API (https://zvec.org/api-reference/nodejs/) — exact enum values,
   parameter names, defaults
3. Python SDK in `zvec/python/zvec/` — actual implementation details
4. C++ headers in `zvec/src/include/zvec/db/` — what the C++ layer supports
Return a concise summary: enum values, signatures, defaults, and any
intentional deviation the PHP binding must make. Do not modify files."
```

---

## 4. Implement the Change (subagent)

Delegate implementation to a subagent. The universal agent is **`coder`**; for
harder, multi-file or riskier changes use **`coder-high`** (or `worker` when
following an approved plan).

```text
Task: "Implement issue #<NUMBER> on branch <branch>. Requirements: <summary>.
Follow AGENTS.md conventions: PSR-4 structure, PHP 8.1+ with full type
declarations, `checkStatus()` after every FFI call, free C strings, `.phpt`
test with try-finally cleanup and uniqid() temp directory. Run the tests:
`php run-tests.php -n tests/`. Do NOT commit or push."
```

**Follow-up candidates:** during implementation the `coder` subagent collects
findings worth fixing later but out of scope for the current issue (tech
debt, suspected bugs, API gaps). At the end it reports them to the user and
asks whether to open them as GitHub issues — **it never creates issues
without explicit user approval**:

```text
Final step: "List follow-up candidates found during implementation (out of
scope, tech debt, potential bugs). For each: short title, one-line
description, suggested label (e.g. `enhancement`, `bug`). Ask the user
whether to create them with `gh issue create` and create only those the
user approves."
```

**Verify every candidate first:** before reporting, each finding must be
checked for technical truth (with file:line evidence) and for existing
GitHub issues — see "Follow-up Candidate Verification" below. Drop findings
that are FALSE or already tracked.

**Knowledge base:** after implementation the `coder` subagent appends
non-obvious findings to `docs/helpers/` (common mistakes, decisions, API
details worth remembering) — see "Knowledge Base (docs/helpers/)" below.

After the subagent finishes, the parent commits and pushes:

```bash
git add -A
git commit -m "feat: implement <short description> (closes #<NUMBER>)"
git push origin feat/issue-<NUMBER>-<description>
```

**Commit message convention:**
- Type: `feat`, `fix`, `docs`, `refactor`, `ci`, `test`, `chore`
- Scope: optional, e.g. `(ffi)`, `(php)`, `(build)`, `(docs)`
- Reference to issue: `(closes #<NUMBER>)` or `(refs #<NUMBER>)`

---

## 5. Code Review via Subagent

After implementation, run a code review using a subagent (separate agent with
its own context). The universal agent is **`review`**; for security-sensitive,
large or high-risk changes use **`review-critical`** instead. For very large
changes you may split the review into parallel lanes (one subagent per
concern: correctness, memory, tests) and aggregate the results.

The review subagent checks:

- Alignment with project structure (PSR-4, FFI patterns, class naming)
- Type correctness and signatures (PHP 8.1+, full type declarations)
- Error handling (FFI status checks, exception propagation)
- Memory management (C string freeing, handle ownership)
- Coding style (see `AGENTS.md` conventions)
- Test coverage (`.phpt` test required for every feature)
- API compatibility with Node.js/Python SDKs

```text
Task: "Code review the uncommitted/committed changes for issue #<NUMBER>
(files: <list of files> or `git diff origin/main...HEAD`).
Check: type correctness, error handling, memory leaks, missing tests,
outdated documentation. List all issues to fix, ordered by severity.
Do NOT modify files."
```

**Follow-up candidates:** findings that are out of scope for the current
issue (style debt, potential improvements, minor bugs) are reported back as
follow-up issue candidates — at the end the subagent asks the user whether
to create them with `gh issue create` and creates only those the user
approves (same rule as for the `coder` subagent, section 4). Each candidate
is verified before reporting (technical truth + duplicate check) — see
"Follow-up Candidate Verification" below.

### Follow-up Candidate Verification

Before any follow-up candidate is reported to the user, the subagent
verifies it:

1. **Technical truth** — check the actual source code; cite file:line
evidence. Verdicts: `CONFIRMED` / `PARTIALLY TRUE` / `FALSE`. Drop FALSE
findings.
2. **Duplicate check on GitHub** — list EVERY issue, open and closed:
   there may be more than 30 (`--limit 30` is only `gh`'s default):
   ```bash
   gh issue list --state all --limit 100 --json number,title,state
   # if exactly 100 are returned, paginate until a page returns fewer:
   gh issue list --state all --limit 100 --page 2 --json number,title,state
   ```
   plus keyword search: `gh search issues "<keywords>"`. If an existing
   issue already covers the finding → verdict `ALREADY TRACKED` with the
   issue number; do not propose a new issue (unless the overlap is only
   tangential — then say so explicitly and reference the existing issue).
3. **Report** — one line per finding: verdict, one-line evidence (file:line),
   suggested label.

Example report line:

```text
CONFIRMED (not tracked): src/ZVecVectorQuery.php:136,162 —
setVamanaParams()/setHnswRabitqParams() create HnswQueryParams →
queryVector() rejected by engine (query.cc:173) — label: bug, priority:high
```

**Knowledge base:** the `review` subagent also appends recurring mistakes and
notable decisions to `docs/helpers/` (see "Knowledge Base (docs/helpers/)"
below).

---

## 6. Fix Issues Found in Code Review

For each problem found, either fix it directly in the parent or delegate the
fixes back to the `coder` subagent:

```text
Task: "Apply the fixes from code review: <list of issues>. Follow AGENTS.md.
Run `php run-tests.php -n tests/` after fixing. Do NOT commit or push."
```

Then commit and push:

```bash
git add -A
git commit -m "fix: <description of fix>"
git push origin feat/issue-<NUMBER>-<description>
```

**All issues must be fixed – even the least significant ones.**

---

## 7. Repeat Code Review

After fixing, invoke the review subagent (`review` / `review-critical`)
again on the updated diff.

Repeat steps 5→6 until the subagent reports no issues.

> **Acceptance criteria:** The subagent responds: "Code looks good, no issues
> to fix."

---

## 8. Build and Test Locally

Before opening a PR, verify that the project builds and all tests pass:

### If C++ changes were made (FFI layer):

```bash
# Build zvec C++ library (skips if already built for this version)
./build_zvec_lib.sh v0.6.0

# Build FFI shared library
./build_ffi.sh
```

### If only PHP changes were made:

The FFI shared library must already exist. If not, run `./build_zvec.sh`.

### Run all tests:

```bash
# Run all .phpt tests (always with -n: a legacy pre-installed zvec PHP extension
# shadows the FFI classes and breaks the suite — see issue #188)
php run-tests.php -n tests/

# Run specific test file
php run-tests.php -n tests/test_<feature>.phpt

# Run with verbose output
php run-tests.php -n -v tests/
```

> **Warning:** `run-tests.php` unlinks legacy tracked `tests/<name>.php` files
> that share a basename with a `.phpt` file (issue #187). Back them up first
> or restore with `git checkout -- tests/` after a run. Conversely the same
> restore reverts any local edits to those files — keep fixes re-applied.

> **Note:** If you see database errors, clean up stale test directories:
> ```bash
> ls test_dbs/
> rm -rf test_dbs/*/
> ```

### Verify test databases cleaned up:

```bash
ls test_dbs/
# Should be empty (except .gitignore)
```

**Only open the PR when all tests pass locally.**

---

## 9. Update CHANGELOG.md

```bash
# Edit CHANGELOG.md:
# - Add entry under [Unreleased] section
# - Follow Keep a Changelog format (https://keepachangelog.com/en/1.1.0/)
# - Use appropriate section: Added, Changed, Fixed, Removed, Deprecated
# - Include issue number, e.g. (#123)
```

This can also be delegated to a `coder` subagent along with a final
`review` pass over the docs change.

---

## 10. Create a Pull Request

```bash
# Create a PR from the feature branch to main
gh pr create \
  --title "feat: <short description> (closes #<NUMBER>)" \
  --body "## Description

Closes #<NUMBER>

## Changes

- <list of changes>

## Testing

- [ ] Builds locally (FFI, PHP)
- [ ] All .phpt tests pass
- [ ] No test database leftovers

## Code Review

- [ ] Passed subagent code review
- [ ] All review comments addressed" \
  --base main \
  --assignee @me
```

> **Note:** If you don't use `gh`, create the PR manually via GitHub UI.

---

## 11. Wait for CI

```bash
# Check PR status
gh pr view --json statusCheckRollup

# Wait for all checks to finish
gh pr checks --watch
```

CI workflow (`.github/workflows/build.yml`) runs:

1. **setup-zvec** — builds the zvec C++ library from source or downloads pre-built
2. **build-ext** — builds the PHP extension (`php-ext/`) and runs tests
3. **build-ffi** — builds the FFI shared library (`ffi/`) and runs PHP FFI tests

> **Note:** The CI workflow triggers on pull requests to `main`, but only for
> builds from the same repository (not forks), due to the `if` condition:
> `github.event.pull_request.head.repo.full_name == github.repository`

---

## 12. Handle CI Failures

If CI fails:

```bash
# 1. See which checks failed
gh pr checks

# 2. View logs
gh run view --log --job <job-name>
```

Then:

3. Delegate investigation of the failure to an `explore` subagent (give it
   the failing job name and log excerpt)
4. Fix the issues — delegate to `coder` (or `coder-high` if complex)
5. Run code review via subagent again (repeat steps 5-7)
6. Run tests locally

```bash
php run-tests.php -n tests/

# 7. Commit the fixes
git add -A
git commit -m "fix: <description of CI fix>"
git push origin feat/issue-<NUMBER>-<description>

# 8. Wait for CI to re-run
gh pr checks --watch
```

**Repeat until all CI checks pass.**

---

## 13. Merge PR and Close Issue

```bash
# Merge PR (squash merge recommended for clean history)
gh pr merge --squash --delete-branch

# Close the issue (automatic if commit contains "closes #<NUMBER>")
# Alternatively:
gh issue close <NUMBER>
```

---

## 14. Switch Back to main

```bash
git checkout main
git pull origin main
```

Done. Ready to start the next cycle from step 1.

## Knowledge Base (docs/helpers/)

`coder` and `review` subagents maintain a persistent knowledge base in
`docs/helpers/` so that lessons learned carry over to future tasks:

- `docs/helpers/faq.md` — frequently asked questions, recurring pitfalls
  (FFI memory leaks, `test_dbs/` cleanup, the `-n` flag) and their solutions
- `docs/helpers/decisions.md` — important decisions with rationale
  (API compatibility with Node.js/Python SDKs, naming, deviations)
- `docs/helpers/README.md` — structure and rules for the knowledge base

Subagents **read** the knowledge base before starting a task and **append**
short entries after finishing (one topic, the problem, the
solution/decision, optionally an issue/commit reference). In doubt, ask the
user before adding a new entry.

---

## Quick Reference – Full Cycle

```text
# 1. Pick an issue (subagent: explore/delegate)
#    Task: "gh issue list --state open --limit 100 (or paginate — 30 is just
#           the default limit), recommend top 3"

# 2. Feature branch
git checkout main && git pull origin main
git checkout -b feat/issue-<NUMBER>-<description>

# 3. Research API (subagent: researcher)
#    Node.js / Python SDK / C++ headers — https://zvec.org/api-reference/nodejs/

# 4. Implementation (subagent: coder, or coder-high for hard tasks)
#    Task: "Implement #<NUMBER>, follow AGENTS.md, add .phpt test,
#           run php run-tests.php -n tests/, do NOT commit"
git add -A && git commit -m "feat: implement <desc> (closes #<NUMBER>)"
git push origin feat/issue-<NUMBER>-<description>

# 5. Code Review (subagent: review, or review-critical for risky changes)
#    Task: "Review git diff origin/main...HEAD, list issues, do NOT modify"
#    ... fix issues (coder) ... repeat until clean
#    coder/review: collect follow-up candidates → verify (truth + gh
#                  duplicate check: --state all --limit 100, PAGINATE)
#                  → ask user → gh issue create
#    coder/review: append learnings to docs/helpers/ (faq.md, decisions.md)

# 6. Build and test locally
./build_zvec_lib.sh v0.6.0
./build_ffi.sh
php run-tests.php -n tests/

# 7. Update CHANGELOG.md

# 8. PR
gh pr create --title "feat: <desc> (closes #<NUMBER>)" --body "..." --base main

# 9. CI
gh pr checks --watch
# ... if failures → investigate (explore), fix (coder), review (review),
#     push → wait for CI (repeat)

# 10. Merge
gh pr merge --squash --delete-branch
gh issue close <NUMBER>

# 11. Switch back to main
git checkout main && git pull origin main
```

---

## Notes

- **gh** must be configured and authenticated (`gh auth status`).
- This project has **no linting/static analysis pipeline** (no php-cs-fixer,
  phpstan, psalm). Follow conventions manually — see `AGENTS.md`.
- Subagents do the heavy lifting (triage, research, coding, review); the
  parent orchestrates, synthesizes and keeps authority over commits, pushes
  and merges. Subagents must be told explicitly to **not commit or push**.
- Follow-up issues: `coder`/`review` subagents collect out-of-scope
  findings, verify each one (technical truth + duplicate check against ALL
  issues — `--limit 30` is just `gh`'s default, there may be more, so use
  `--limit 100` and paginate) and only create GitHub issues after explicit
  user approval.
- Knowledge base: FAQ, common mistakes and decisions live in
  `docs/helpers/` (`faq.md`, `decisions.md`) — subagents read it before
  starting and update it after each task.
- Subagents run locally and have read/write/edit/bash access. Give them clear
  instructions: goal, scope, constraints, expected output.
- The CI builds zvec from source only once per workflow run and caches it
  as an artifact for downstream jobs (ext + ffi).
- Pre-built zvec artifacts are stored in GitHub Releases under the
  `zvec-build-v0.6.0` release tag and downloaded by CI to avoid rebuilding
  from source on every PR.
- Test databases are created in `test_dbs/` (git-ignored). Always clean up
  after test runs: `rm -rf test_dbs/*/`
- Keep feature branches short-lived. If a rebase is needed:
  ```bash
  git fetch origin main
  git rebase origin/main
  git push --force-with-lease origin feat/issue-<NUMBER>-<description>
  ```
- For release workflow (tagging, CHANGELOG, version bump), see the
  "Release Workflow" section in `AGENTS.md`. Never push tags — user
  pushes manually.
