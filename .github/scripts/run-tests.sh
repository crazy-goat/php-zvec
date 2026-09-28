#!/bin/bash
# Runs the phpt suite against the FFI bindings, or against the extension
# when ZVEC_EXT is set to the path of zvec.so.
set -euo pipefail

ARGS="-n"
if [ -n "${ZVEC_EXT:-}" ]; then
    ARGS="$ARGS -d extension=$ZVEC_EXT"
elif ! php -n -m | grep -qx FFI; then
    ARGS="$ARGS -d extension=ffi"
fi
for ext in ${PHP_EXTRA_EXTENSIONS:-}; do
    ARGS="$ARGS -d extension=$ext"
done

export TEST_PHP_ARGS="$ARGS"
php $ARGS run-tests.php -q --show-diff tests/
