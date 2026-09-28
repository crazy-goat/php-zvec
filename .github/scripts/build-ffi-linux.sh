#!/bin/bash
# Builds ffi/build/libzvec_ffi.so against the official zvec SDK inside a
# container, so the adapter runs on old glibc (manylinux_2_28) or on musl.
# Usage: build-ffi-linux.sh <glibc|musl> [zvec-version]
set -euo pipefail

LIBC="$1"
ZVEC_VERSION="${2:-v0.7.0}"
ARCH="$(uname -m)"
BUILD="./fetch_zvec_sdk.sh $ZVEC_VERSION && ./build_ffi.sh -DZVEC_FFI_STATIC_LIBSTDCXX=ON"

case "$LIBC" in
    glibc)
        docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD:/src" -w /src \
            "quay.io/pypa/manylinux_2_28_${ARCH}" bash -c "$BUILD" ;;
    musl)
        docker run --rm -v "$PWD:/src" -w /src alpine:3.22 sh -c \
            "apk add -q bash build-base cmake curl linux-headers && $BUILD && chown -R $(id -u):$(id -g) sdk ffi/build" ;;
    *)
        echo "Usage: $0 <glibc|musl> [zvec-version]"; exit 1 ;;
esac

readelf -d ffi/build/libzvec_ffi.so | grep -E 'NEEDED|RUNPATH'
