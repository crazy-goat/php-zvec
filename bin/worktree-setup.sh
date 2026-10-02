#!/usr/bin/env bash
# Prepare a fresh worktree: Composer dependencies and the FFI adapter the tests load.
# Called by bin/worktree.sh after a new worktree is created.
# No test containers are started and no host ports are used: the suite runs on the host.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

composer install --no-interaction --prefer-dist

if [[ ! -f sdk/.zvec_sdk_version || ! -d ffi/build ]]; then
  ./build_zvec.sh
fi
