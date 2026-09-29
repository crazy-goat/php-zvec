#!/bin/bash
# Downloads the official prebuilt zvec SDK (github.com/alibaba/zvec releases)
# for the current platform into sdk/. Skips the download when sdk/ already
# holds the requested version.
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

ZVEC_VERSION="${1:-v0.7.0}"
SDK_DIR="${ZVEC_SDK_DIR:-$SCRIPT_DIR/sdk}"
STAMP_FILE="$SDK_DIR/.zvec_sdk_version"

OS="$(uname -s)"
ARCH="$(uname -m)"

# Detect the libc the SDK must match. Do NOT test for the mere presence of
# /lib/ld-musl-*.so.1: glibc distributions that also ship the musl package
# (Ubuntu/Debian with musl-tools installed) pass that check, and we would then
# download a musl SDK that a glibc process cannot dlopen -- it fails at runtime
# with "libc.musl-x86_64.so.1: cannot open shared object file".
#
# The authoritative signal is the loader of the binary that will load the SDK.
# For local development that is php; fall back to the dynamic loader itself.
is_musl() {
    command -v ldd >/dev/null 2>&1 || return 1

    # Prefer the binary that will actually load the SDK. `command -v` yields an
    # absolute path; a bare "php" is only a valid argument to ldd when a php
    # happens to sit in the current directory, so resolve it first.
    local probe
    probe="$(command -v "${PHP_BINARY:-php}" 2>/dev/null)" || probe=""
    if [ -z "$probe" ]; then
        # No php available (e.g. a pure build container): ask the dynamic linker.
        probe="/bin/sh"
    fi

    local deps
    deps="$(ldd "$probe" 2>/dev/null)" || return 1
    # musl-linked binaries list libc.musl-*.so.1 and no plain libc.so.
    if printf '%s' "$deps" | grep -qi musl; then
        return 0
    fi
    if printf '%s' "$deps" | grep -q 'libc\.so'; then
        return 1
    fi
    # Neither marker: cannot tell, so assume the mainstream glibc SDK.
    return 1
}

case "$OS-$ARCH" in
    Linux-x86_64)
        if is_musl; then ASSET="zvec-sdk-linux-musl-amd64.tar.gz"; else ASSET="zvec-sdk-linux-amd64.tar.gz"; fi ;;
    Linux-aarch64|Linux-arm64)
        if is_musl; then ASSET="zvec-sdk-linux-musl-arm64.tar.gz"; else ASSET="zvec-sdk-linux-arm64.tar.gz"; fi ;;
    Darwin-arm64)
        ASSET="zvec-sdk-osx-arm64.tar.gz" ;;
    *)
        echo "Unsupported platform: $OS $ARCH (upstream ships no prebuilt SDK for it)"
        exit 1 ;;
esac

# SHA-256 of the upstream release assets, keyed by "<version>/<asset>".
# Take new values from: gh release view <version> -R alibaba/zvec --json assets
expected_sha256() {
    case "$1" in
        v0.7.0/zvec-sdk-linux-amd64.tar.gz)      echo db9472ef2146b8f435b45b47a644eb7a75eb84b9946334debfcfc21112cec7f9 ;;
        v0.7.0/zvec-sdk-linux-arm64.tar.gz)      echo 9a2a2867fb6ddb53212029b7f50eac288ad86bebfccb6ea6d52b3f35c4e026a8 ;;
        v0.7.0/zvec-sdk-linux-musl-amd64.tar.gz) echo 8d863da76921cb46cf3799236cb83ee026cc94a76e97b1cd697129b64a89dd3d ;;
        v0.7.0/zvec-sdk-linux-musl-arm64.tar.gz) echo 6385fe59639639fb60163649d8b1ce137e1c6f5428af57cc7d47d8d0e655d4ce ;;
        v0.7.0/zvec-sdk-osx-arm64.tar.gz)        echo ac909c57e084bb39f7f98ef89b28db322df88348254158eedefa9c00e2c34dfc ;;
        *) echo "${ZVEC_SDK_SHA256:-}" ;;
    esac
}

STAMP="$ZVEC_VERSION/$ASSET"
if [ -f "$STAMP_FILE" ] && [ "$(cat "$STAMP_FILE")" = "$STAMP" ] && [ -d "$SDK_DIR/lib" ]; then
    echo "zvec SDK ${ZVEC_VERSION} already present in ${SDK_DIR} (${ASSET}), skipping download"
    exit 0
fi

SHA256="$(expected_sha256 "$STAMP")"
if [ -z "$SHA256" ]; then
    echo "No known SHA-256 for ${STAMP}. Add it to expected_sha256() or set ZVEC_SDK_SHA256."
    exit 1
fi

URL="https://github.com/alibaba/zvec/releases/download/${ZVEC_VERSION}/${ASSET}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Downloading ${URL}..."
curl -fsSL --retry 3 --connect-timeout 20 -o "$TMP/$ASSET" "$URL"

if command -v sha256sum >/dev/null 2>&1; then
    ACTUAL="$(sha256sum "$TMP/$ASSET" | cut -d' ' -f1)"
else
    ACTUAL="$(shasum -a 256 "$TMP/$ASSET" | cut -d' ' -f1)"
fi
if [ "$ACTUAL" != "$SHA256" ]; then
    echo "Checksum mismatch for ${ASSET}: expected ${SHA256}, got ${ACTUAL}"
    exit 1
fi

rm -rf "$SDK_DIR"
mkdir -p "$SDK_DIR"
tar -xzf "$TMP/$ASSET" -C "$SDK_DIR"
test -f "$SDK_DIR/include/zvec/db/collection.h"
echo "$STAMP" > "$STAMP_FILE"
echo "zvec SDK ${ZVEC_VERSION} ready in ${SDK_DIR}"
