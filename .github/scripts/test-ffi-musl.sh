#!/bin/bash
# Runs the phpt suite in a clean Alpine container (no compiler, no libstdc++)
# as a non-root user, to prove the musl adapter is self-contained.
set -euo pipefail

docker run --rm -v "$PWD:/src" -w /src alpine:3.22 sh -c "
    apk add -q bash libaio php84 php84-ffi php84-posix php84-pcntl php84-mbstring php84-ctype php84-openssl &&
    ln -sf /usr/bin/php84 /usr/bin/php &&
    ! apk info -e libstdc++ &&
    adduser -D -u $(id -u) runner &&
    su runner -c 'PHP_EXTRA_EXTENSIONS=\"posix pcntl mbstring ctype openssl\" bash .github/scripts/run-tests.sh'"
