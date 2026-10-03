#!/usr/bin/env bash
# Run all static analysis, linters and formatter checks. --fix applies fixes first.
# Needs composer dependencies (composer install), clang-format, shellcheck and hadolint.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

FIX=0
[ "${1:-}" = "--fix" ] && FIX=1
failed=()

step() {
    local name="$1"; shift
    echo "==> $name"
    "$@" || failed+=("$name")
}

# C++ sources of the FFI adapter and of the PHP extension (no vendored code lives here).
cpp_sources() {
    git ls-files -z 'ffi/*.cc' 'ffi/*.h' 'php-ext/*.cc' 'php-ext/*.h'
}

# Every tracked shell script: by extension, plus extensionless files with a shell shebang.
shell_scripts() {
    local file
    while IFS= read -r -d '' file; do
        case "$file" in
            *.sh) printf '%s\0' "$file"; continue ;;
            *.*) continue ;;
        esac
        if [ -f "$file" ] && head -n 1 "$file" 2>/dev/null | grep -aqE '^#!.*[/ ](ba|da|k)?sh( |$)'; then
            printf '%s\0' "$file"
        fi
    done < <(git ls-files -z)
}

# Isolation for PHPStan and Rector. Both run inside a PHP process, so they see whatever
# that process has loaded — and this repository also builds a PHP extension (php-ext/,
# the legacy `zvec`), which a contributor can have installed and enabled in php.ini. The
# lint result would then depend on the machine instead of on the working tree, which is
# what happened in #246.
#
# `-n` is not usable here. PHPStan needs the Phar class and Rector needs `PhpToken` from
# the tokenizer extension, and on the CI image those come from conf.d inis, which `-n`
# drops: `lint` failed in #259 with `Class "Phar" not found` and `Class "PhpToken" not
# found`. So instead of dropping every ini, the machine's php.ini is replaced by one that
# is generated per run and holds what the analysis needs. `PHPRC` is an environment
# variable and is inherited, so this also reaches the processes PHPStan starts for itself
# — it restarts with `PHP_BINARY -d ...` and starts parallel workers, which drop `-n`.
#
# What the replaced php.ini took with it has to be put back explicitly:
#   - extension_dir: on a Homebrew/pecl setup it is set in php.ini, while the conf.d
#     entries name their extension without a path, so without it every extension
#     installed that way fails to load.
#   - memory_limit: without it the limit is the built-in 128M, far less than what the
#     analysis gets today.
#   - FFI, but only where the analysis would otherwise have none: phpstan.neon ignores
#     undefined FFI methods and properties, not a missing FFI class, so without FFI the
#     analysis reports errors on src/. Probed in the same isolated environment the tools
#     run in, and then written to the ini — which every one of those processes loads, so
#     it is not also passed as `-d`, or PHP would load it twice and warn.
ANALYSIS_INI="$(mktemp "${TMPDIR:-/tmp}/zvec-lint-ini.XXXXXX")"
trap 'rm -f "$ANALYSIS_INI"' EXIT
: >"$ANALYSIS_INI"
php_extension_dir="$(php -r 'echo (string) ini_get("extension_dir");' 2>/dev/null || true)"
# Quoted, so a path with a space is fine; a quote or a newline cannot be expressed in an
# ini value at all, and such a path is left out rather than written out broken.
case "$php_extension_dir" in
    *'"'* | *$'\n'*) php_extension_dir="" ;;
esac
if [ -n "$php_extension_dir" ]; then
    printf 'extension_dir="%s"\n' "$php_extension_dir" >"$ANALYSIS_INI"
fi
PHP_ANALYSIS_FLAGS=(-d memory_limit=-1)
if ! env PHPRC="$ANALYSIS_INI" php -r 'exit(extension_loaded("FFI") ? 0 : 1);' 2>/dev/null; then
    printf 'extension=ffi\n' >>"$ANALYSIS_INI"
fi

php_analysis() {
    env PHPRC="$ANALYSIS_INI" php "${PHP_ANALYSIS_FLAGS[@]}" "$@"
}

if [ "$FIX" = 1 ]; then
    php_analysis vendor/bin/rector process --no-progress-bar || true
    vendor/bin/php-cs-fixer fix || true # exits non-zero when it changed files
    cpp_sources | xargs -0 -r clang-format -i || true
fi

step "php-cs-fixer" vendor/bin/php-cs-fixer fix --dry-run --diff
step "phpstan" php_analysis vendor/bin/phpstan analyse --no-progress
step "rector" php_analysis vendor/bin/rector process --dry-run --no-progress-bar
step "clang-format" bash -c "$(declare -f cpp_sources); cpp_sources | xargs -0 -r clang-format --dry-run --Werror"
step "shellcheck" bash -c "$(declare -f shell_scripts); shell_scripts | xargs -0 -r shellcheck"
step "hadolint" bash -c "git ls-files -z '*Dockerfile*' | xargs -0 -r hadolint"

if [ "${#failed[@]}" -gt 0 ]; then
    echo "Failed: ${failed[*]}" >&2
    exit 1
fi
echo "All checks passed."
