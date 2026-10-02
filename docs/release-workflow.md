# Release workflow

One milestone = one release. Versions follow
[Semantic Versioning](https://semver.org/) and the changelog follows
[Keep a Changelog](https://keepachangelog.com/). Tags are `vX.Y.Z`.

Everything is written in **English**, including release notes.

## 1. Release gate

A release is ready when the milestone has **no open issues**:

```bash
gh api repos/{owner}/{repo}/milestones --jq '.[] | select(.title=="vX.Y.Z") | {title, open_issues, closed_issues}'
```

- Open issues that will not make it: move them to the next milestone
  (`gh issue edit <N> --milestone vX.Y.(Z+1)`).
- The default branch must have a green `ci-ok`.

## 2. Choose the version

| Change | Bump |
|---|---|
| Bug fixes only | patch (`1.2.3` → `1.2.4`) |
| New, backward compatible features | minor (`1.2.3` → `1.3.0`) |
| Breaking changes | major (`1.2.3` → `2.0.0`) |

Before `1.0.0`, breaking changes bump the minor version. The milestone title
already holds the planned version. Change the milestone title if the plan changed.

## 3. Prepare the CHANGELOG (pull request)

```bash
git switch -c chore/release-vX.Y.Z
```

In `CHANGELOG.md`:

- Rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`.
- Add a fresh empty `## [Unreleased]` above it.
- Group entries under Added, Changed, Deprecated, Removed, Fixed, Security.
- Update the compare links at the bottom, if the file has them.
- Bump the version in files that carry it. `composer.json` does not need it. The
  extension header `php-ext/php_zvec.h` (`PHP_ZVEC_VERSION`) does, but it is stale
  today (see "Known gaps" below).
- Keep the compare links at the bottom of `CHANGELOG.md` in step with the sections:
  `tests/test_docs_consistency.phpt` checks them.

Open a PR titled `chore: release vX.Y.Z`, wait for `ci-ok`, squash merge.

## 4. Tag

Tag the merge commit on the default branch with an **annotated** tag:

```bash
git switch <default-branch> && git pull --ff-only
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

## 5. GitHub Release

Pushing the tag starts `.github/workflows/release.yml` (workflow `Release PHP Extension`).
It does not call a shared workflow, because it also builds binaries:

1. `create-release` checks that the tag commit is on `main`, extracts the matching
   `## [X.Y.Z]` section from `CHANGELOG.md` with `awk` (truncated below 120000
   characters; GitHub rejects 125000), and runs
   `gh release create "$GITHUB_REF_NAME" --verify-tag --notes-file release-notes.md`.
   It fails when the section is missing or empty. Tags with a `-` (for example
   `v0.7.0-rc1`) become pre-releases.
2. `release-ffi-linux` (glibc and musl, x86_64 and aarch64), `release-ffi-macos`
   (darwin-aarch64) and `release-ext` (PHP 8.4 extension, Ubuntu 24.04 x86_64) build
   and test, then upload `libzvec_ffi-<platform>.tar.gz` and
   `zvec-php<version>-ubuntu24-x86_64.tar.gz` with `gh release upload --clobber`.
3. `checksums` downloads every `*.tar.gz` asset and uploads `checksums.sha256`.

Only the FFI adapter is shipped; `zvec-install` fetches `libzvec` from the upstream
SDK release (SHA-256 pinned in `src/Installer.php`), so a new `ZVEC_VERSION` needs its
checksum added first (see `AGENTS.md`).

```bash
gh run watch
gh release view vX.Y.Z
gh release view vX.Y.Z --json assets --jq '.assets[].name'
```

Expect six `libzvec_ffi-*` / `zvec-php*` archives plus `checksums.sha256`.

## 6. Close the milestone

```bash
gh api -X PATCH repos/{owner}/{repo}/milestones/<number> -f state=closed
```

Make sure the next milestone `vX.Y.(Z+1)` (or the next minor) exists.

## 7. After the release

- Check that install instructions work with the new version: `composer require crazy-goat/zvec`
  followed by `vendor/bin/zvec-install` downloads the new FFI asset.
- If something is wrong, do not move the tag. Fix forward with a patch release.

## Checklist

- [ ] Milestone has no open issues, CI is green
- [ ] CHANGELOG section `[X.Y.Z] - date` written, `[Unreleased]` is empty
- [ ] Release PR merged
- [ ] Annotated tag `vX.Y.Z` pushed
- [ ] GitHub Release exists with the CHANGELOG notes and all platform assets plus `checksums.sha256`
- [ ] Milestone closed, next milestone exists

## Known gaps

- `PHP_ZVEC_VERSION` in `php-ext/php_zvec.h` still says `0.4.10`; the extension
  reports a wrong version until it is bumped together with the release.
