#!/bin/bash
# Runs the phpt suite against the FFI bindings, or against the extension
# when ZVEC_EXT is set to the path of zvec.so.
set -euo pipefail

ARGS=(-n)
if [ -n "${ZVEC_EXT:-}" ]; then
    ARGS+=(-d "extension=$ZVEC_EXT")
# Not `php -m | grep -q`: grep exits on the first match, php gets SIGPIPE,
# and pipefail turns that into a false "FFI missing" (#236).
elif ! php -n -r 'exit(extension_loaded("FFI") ? 0 : 1);'; then
    ARGS+=(-d extension=ffi)
fi
# shellcheck disable=SC2086 # intentional word splitting of a space-separated list
for ext in ${PHP_EXTRA_EXTENSIONS:-}; do
    ARGS+=(-d "extension=$ext")
done

export TEST_PHP_ARGS="${ARGS[*]}"
php "${ARGS[@]}" run-tests.php -q --show-diff tests/
