# Workflow: Issue → Feature Branch → Implementation → Code Review → PR → CI → Merge

This document describes the complete workflow for handling issues in the
[crazy-goat/php-zvec](https://github.com/crazy-goat/php-zvec) repository
using `gh` and `git`.

---

## 1. Browse Open Issues

```bash
# List open issues (title, number, labels)
gh issue list --state open --limit 30

# View a specific issue (description, labels, state)
gh issue view <NUMBER> --json title,body,labels,state
```

**Criteria for selecting the most impactful issue:**
- Issues labeled `enhancement`, `bug`, `good-first-issue`
- Issues about stability, memory leaks, data correctness
- Issues blocking other tasks
- Issues most relevant to users (documentation, API coverage)

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

## 3. Research Before Implementation

Before coding, verify the API against reference implementations:

1. **Check zvec documentation**: https://zvec.org/en/docs/
2. **Check Node.js API**: https://zvec.org/api-reference/nodejs/ for reference
3. **Check Python SDK** in `zvec/python/zvec/` for actual implementation details
4. **Check C++ headers** in `zvec/src/include/zvec/db/` to verify what's supported

---

## 4. Implement the Change

```bash
# Edit files, then commit and push
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
its own context). The subagent checks:

- Alignment with project structure (PSR-4, FFI patterns, class naming)
- Type correctness and signatures (PHP 8.1+, full type declarations)
- Error handling (FFI status checks, exception propagation)
- Memory management (C string freeing, handle ownership)
- Coding style (see `AGENTS.md` conventions)
- Test coverage (`.phpt` test required for every feature)
- API compatibility with Node.js/Python SDKs

```bash
# The subagent receives a task like:
# "Code review the changes in files: <list of files>.
#  Check: type correctness, error handling, memory leaks,
#  missing tests, outdated documentation.
#  List all issues to fix."
```

---

## 6. Fix Issues Found in Code Review

```bash
# For each problem found:
# 1. Apply the fix
# 2. Commit with a descriptive message
git add -A
git commit -m "fix: <description of fix>"
git push origin feat/issue-<NUMBER>-<description>
```

**All issues must be fixed – even the least significant ones.**

---

## 7. Repeat Code Review

After fixing, invoke the subagent for another code review.

Repeat steps 5→6 until the subagent reports no issues.

> **Acceptance criteria:** The subagent responds: "Code looks good, no issues
> to fix."

---

## 8. Build and Test Locally

Before opening a PR, verify that the project builds and all tests pass:

### If C++ changes were made (FFI layer):

```bash
# Build zvec C++ library (skips if already built for this version)
./build_zvec_lib.sh v0.4.0

# Build FFI shared library
./build_ffi.sh
```

### If only PHP changes were made:

The FFI shared library must already exist. If not, run `./build_zvec.sh`.

### Run all tests:

```bash
# Run all .phpt tests
php run-tests.php tests/

# Run specific test file
php run-tests.php tests/test_<feature>.phpt

# Run with verbose output
php run-tests.php -v tests/
```

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

# 3. Fix the issues locally
# 4. Run code review via subagent again (repeat steps 5-7)
# 5. Run tests locally
php run-tests.php tests/

# 6. Commit the fixes
git add -A
git commit -m "fix: <description of CI fix>"
git push origin feat/issue-<NUMBER>-<description>

# 7. Wait for CI to re-run
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

---

## Quick Reference – Full Cycle

```bash
# 1. Pick an issue
gh issue list --state open --limit 30
gh issue view <NUMBER>

# 2. Feature branch
git checkout main && git pull origin main
git checkout -b feat/issue-<NUMBER>-<description>

# 3. Research API (Node.js, Python, C++)
#    https://zvec.org/api-reference/nodejs/

# 4. Implementation
# ... coding ...
git add -A && git commit -m "feat: implement <desc> (closes #<NUMBER>)"
git push origin feat/issue-<NUMBER>-<description>

# 5. Code Review (subagent)
# ... fix issues ... (repeat until clean)

# 6. Build and test locally
./build_zvec_lib.sh v0.4.0
./build_ffi.sh
php run-tests.php tests/

# 7. Update CHANGELOG.md

# 8. PR
gh pr create --title "feat: <desc> (closes #<NUMBER>)" --body "..." --base main

# 9. CI
gh pr checks --watch
# ... if failures → fix, code review, push → wait for CI (repeat)

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
- The CI builds zvec from source only once per workflow run and caches it
  as an artifact for downstream jobs (ext + ffi).
- Pre-built zvec artifacts are stored in GitHub Releases under the
  `zvec-build-v0.4.0` release tag and downloaded by CI to avoid rebuilding
  from source on every PR.
- Test databases are created in `test_dbs/` (git-ignored). Always clean up
  after test runs: `rm -rf test_dbs/*/`
- Keep feature branches short-lived. If a rebase is needed:
  ```bash
  git fetch origin main
  git rebase origin/main
  git push --force-with-lease origin feat/issue-<NUMBER>-<description>
  ```
- Code review via subagent runs locally – the subagent has access to
  read/write/edit/bash tools. Give it clear instructions on what to check.
- For release workflow (tagging, CHANGELOG, version bump), see the
  "Release Workflow" section in `AGENTS.md`. Never push tags — user
  pushes manually.
