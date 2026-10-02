# Contributing

Thanks for helping! The general guide for all `crazy-goat` repositories is at
<https://github.com/crazy-goat/.github/blob/main/CONTRIBUTING.md>. This file adds what is
specific to php-zvec.

## Getting a working checkout

```bash
composer install
./build_zvec.sh                      # fetches the zvec SDK, builds ffi/build/libzvec_ffi.*
.github/scripts/run-tests.sh         # the phpt suite; "Tests skipped" must be 0
bin/lint.sh                          # same as `composer lint`
```

Project commands, memory-management rules and test conventions are in
[`AGENTS.md`](AGENTS.md). The development process (issues, worktrees, review, release) is
in [`docs/workflow.md`](docs/workflow.md) and [`docs/release-workflow.md`](docs/release-workflow.md).

## Before opening a pull request

- Write everything in English (Polish only as test data).
- `bin/lint.sh` and the test suite pass; new behaviour comes with a `tests/*.phpt` test.
- Add an entry under `## [Unreleased]` in `CHANGELOG.md` for every user-visible change.
- Use [Conventional Commits](https://www.conventionalcommits.org/); the PR title becomes
  the squash commit message. Wait for `ci-ok`.
