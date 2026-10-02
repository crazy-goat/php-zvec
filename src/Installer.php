<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use RuntimeException;

class Installer
{
    private const GITHUB_REPO = 'crazy-goat/php-zvec';
    private const LIB_DIR = __DIR__ . '/../lib';
    private const STAMP_FILE = '.installed';

    /** Official prebuilt zvec SDK the FFI adapter is compiled against. Keep in sync with fetch_zvec_sdk.sh. */
    public const ZVEC_SDK_REPO = 'alibaba/zvec';
    public const ZVEC_SDK_VERSION = 'v0.7.0';
    private const ZVEC_SDK_SHA256 = [
        'zvec-sdk-linux-amd64.tar.gz' => 'db9472ef2146b8f435b45b47a644eb7a75eb84b9946334debfcfc21112cec7f9',
        'zvec-sdk-linux-arm64.tar.gz' => '9a2a2867fb6ddb53212029b7f50eac288ad86bebfccb6ea6d52b3f35c4e026a8',
        'zvec-sdk-linux-musl-amd64.tar.gz' => '8d863da76921cb46cf3799236cb83ee026cc94a76e97b1cd697129b64a89dd3d',
        'zvec-sdk-linux-musl-arm64.tar.gz' => '6385fe59639639fb60163649d8b1ce137e1c6f5428af57cc7d47d8d0e655d4ce',
        'zvec-sdk-osx-arm64.tar.gz' => 'ac909c57e084bb39f7f98ef89b28db322df88348254158eedefa9c00e2c34dfc',
    ];

    /**
     * Download and install the FFI adapter and the official zvec SDK library for the current platform.
     *
     * Two archives are installed into lib/:
     *  - libzvec_ffi (our adapter) from this package's GitHub release, verified against its checksums.sha256;
     *  - libzvec + data/ (jieba dictionary) from the official alibaba/zvec SDK release, verified against
     *    the SHA-256 values pinned in ZVEC_SDK_SHA256.
     *
     * Uses cryptographically random temp directories (created 0700, removed in finally blocks) to prevent
     * symlink race attacks. Concurrent installations are serialized via flock(); the installed state is
     * re-checked inside the lock. The lock file persists as a sentinel so all processes share one inode.
     * A stamp file records the installed package + SDK versions, so an upgrade replaces stale libraries.
     *
     * @param string|null $version Release version tag (e.g., "v0.7.0"). Auto-detected from composer if null.
     * @throws RuntimeException On download failure, checksum mismatch, extraction failure, lock failure, or missing lib in archive.
     */
    public static function install(?string $version = null): void
    {
        $assetName = self::resolveAssetName();
        $sdkAssetName = self::resolveSdkAssetName();
        if ($assetName === null || $sdkAssetName === null) {
            echo "zvec FFI library auto-download is not supported on your platform (" . PHP_OS_FAMILY . " " . php_uname('m') . ").\n";
            echo "See https://github.com/" . self::GITHUB_REPO . " for build instructions.\n";
            return;
        }

        $version ??= self::detectVersion();
        if (!preg_match('/^v\d+\.\d+\.\d+(-[\w.-]*\w)?$/', $version)) {
            throw new RuntimeException("Invalid version format: {$version}. Expected semver format (e.g. v0.4.0)");
        }

        $libDir = self::LIB_DIR;
        if (!is_dir($libDir) && !mkdir($libDir, 0755, true)) {
            throw new RuntimeException("Failed to create lib directory: {$libDir}");
        }

        $libPath = $libDir . '/' . self::libName();
        $sdkLibPath = $libDir . '/' . self::sdkLibName();
        $stampPath = $libDir . '/' . self::STAMP_FILE;
        $stamp = "{$version} " . self::ZVEC_SDK_VERSION;

        // Acquire exclusive lock to serialize concurrent installations (TOCTOU mitigation)
        $lockFile = $libDir . '/install.lock';
        $lockFh = fopen($lockFile, 'w+');
        if (!$lockFh) {
            throw new RuntimeException("Could not create lock file: {$lockFile}");
        }
        if (!flock($lockFh, LOCK_EX)) {
            fclose($lockFh);
            throw new RuntimeException("Could not acquire installation lock");
        }

        try {
            // Double-check after acquiring lock — another process may have installed it
            if (file_exists($libPath) && file_exists($sdkLibPath)
                && file_exists($stampPath) && file_get_contents($stampPath) === $stamp) {
                echo "zvec FFI library already installed at {$libPath}\n";
                return;
            }

            // Stale or partial install (e.g. a pre-SDK statically linked adapter): start over.
            foreach ([$libPath, $sdkLibPath, $stampPath] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            exec("rm -rf " . escapeshellarg($libDir . '/zvec_data'));

            echo "Downloading zvec FFI library {$version} for " . self::platformLabel() . "...\n";
            $url = "https://github.com/" . self::GITHUB_REPO . "/releases/download/{$version}/{$assetName}";
            self::downloadAndExtract($url, self::getExpectedHash($version, $assetName), $libDir);
            if (!file_exists($libPath)) {
                throw new RuntimeException("Download succeeded but " . self::libName() . " not found in archive.");
            }

            echo "Downloading zvec SDK " . self::ZVEC_SDK_VERSION . " from " . self::ZVEC_SDK_REPO . "...\n";
            self::installSdk($sdkAssetName, $libDir);

            file_put_contents($stampPath, $stamp);
            echo "zvec FFI library installed at {$libPath}\n";
        } finally {
            flock($lockFh, LOCK_UN);
            fclose($lockFh);
            // Lock file persists as a sentinel. Never delete — removing it would let
            // a new process create a different inode and bypass flock() serialization.
        }
    }

    private static function installSdk(string $sdkAssetName, string $libDir): void
    {
        $url = "https://github.com/" . self::ZVEC_SDK_REPO . "/releases/download/"
            . self::ZVEC_SDK_VERSION . "/{$sdkAssetName}";

        // Staging lives inside lib/ so the final moves are same-filesystem renames.
        $staging = $libDir . '/.sdk_' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0700)) {
            throw new RuntimeException("Failed to create SDK staging directory");
        }
        try {
            self::downloadAndExtract($url, self::ZVEC_SDK_SHA256[$sdkAssetName], $staging);

            $sdkLib = $staging . '/lib/' . self::sdkLibName();
            if (!file_exists($sdkLib)) {
                throw new RuntimeException("Download succeeded but " . self::sdkLibName() . " not found in SDK archive.");
            }
            if (!rename($sdkLib, $libDir . '/' . self::sdkLibName())) {
                throw new RuntimeException("Failed to install " . self::sdkLibName());
            }
            if (is_dir($staging . '/data') && !rename($staging . '/data', $libDir . '/zvec_data')) {
                throw new RuntimeException("Failed to install zvec SDK data directory");
            }
        } finally {
            exec("rm -rf " . escapeshellarg($staging));
        }
    }

    private static function downloadAndExtract(string $url, string $expectedHash, string $destDir): void
    {
        $tmpDir = sys_get_temp_dir() . '/zvec_ffi_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700)) {
            throw new RuntimeException("Failed to create temporary directory");
        }
        $tmpFile = $tmpDir . '/download.tar.gz';

        try {
            self::download($url, $tmpFile);
            self::verifyChecksum($tmpFile, $expectedHash);
            self::extract($tmpFile, $destDir);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            exec("rm -rf " . escapeshellarg($tmpDir));
        }
    }

    public static function verifyChecksum(string $filePath, string $expectedHash): void
    {
        $actualHash = hash_file('sha256', $filePath);
        if (!hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException(
                "Checksum mismatch for " . basename($filePath) . ". " .
                "Expected: {$expectedHash}, Got: {$actualHash}"
            );
        }
    }

    public static function platformLabel(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return 'macOS ' . php_uname('m');
        }
        if (PHP_OS_FAMILY === 'Linux') {
            $libc = self::isMusl() ? 'musl' : 'glibc';
            return 'Linux ' . php_uname('m') . ' (' . $libc . ')';
        }
        return PHP_OS_FAMILY . ' ' . php_uname('m');
    }

    private static function resolveAssetName(): ?string
    {
        $platform = self::platformKey();
        return $platform === null ? null : "libzvec_ffi-{$platform}.tar.gz";
    }

    private static function resolveSdkAssetName(): ?string
    {
        return match (self::platformKey()) {
            'linux-x86_64' => 'zvec-sdk-linux-amd64.tar.gz',
            'linux-aarch64' => 'zvec-sdk-linux-arm64.tar.gz',
            'linux-musl-x86_64' => 'zvec-sdk-linux-musl-amd64.tar.gz',
            'linux-musl-aarch64' => 'zvec-sdk-linux-musl-arm64.tar.gz',
            'darwin-aarch64' => 'zvec-sdk-osx-arm64.tar.gz',
            default => null,
        };
    }

    /** Platforms with an official prebuilt zvec SDK (macOS x86_64 has none). */
    private static function platformKey(): ?string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');
        $libc = self::isMusl() ? 'linux-musl' : 'linux';
        return match (true) {
            $os === 'Linux' && $arch === 'x86_64' => "{$libc}-x86_64",
            $os === 'Linux' && ($arch === 'aarch64' || $arch === 'arm64') => "{$libc}-aarch64",
            $os === 'Darwin' && $arch === 'arm64' => 'darwin-aarch64',
            default => null,
        };
    }

    private static function isMusl(): bool
    {
        return file_exists('/lib/ld-musl-x86_64.so.1')
            || file_exists('/lib/ld-musl-aarch64.so.1')
            || file_exists('/lib/ld-musl-arm.so.1');
    }

    private static function libName(): string
    {
        return PHP_OS_FAMILY === 'Darwin' ? 'libzvec_ffi.dylib' : 'libzvec_ffi.so';
    }

    private static function sdkLibName(): string
    {
        return PHP_OS_FAMILY === 'Darwin' ? 'libzvec.dylib' : 'libzvec.so';
    }

    private static function detectVersion(): string
    {
        $version = self::versionFromInstalledJson();
        if ($version !== null) {
            return $version;
        }
        throw new RuntimeException(
            "Could not determine package version. " .
            "Run 'composer install' first, or specify a version: vendor/bin/zvec-install v0.4.10"
        );
    }

    private static function versionFromInstalledJson(): ?string
    {
        $path = __DIR__ . '/../../../composer/installed.json';
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }
        $packages = $data['packages'] ?? $data;
        foreach ((array)$packages as $key => $pkg) {
            $name = $pkg['name'] ?? (is_array($pkg) && isset($data['packages']) ? $key : null);
            if ($name === 'crazy-goat/zvec' || ($name === null && isset($pkg['name']) && $pkg['name'] === 'crazy-goat/zvec')) {
                $version = $pkg['version'] ?? null;
                if ($version !== null && $version !== '*') {
                    return 'v' . ltrim($version, 'v');
                }
            }
        }
        return null;
    }

    private static function download(string $url, string $dest): void
    {
        $ctx = stream_context_create([
            'https' => [
                'header' => "User-Agent: crazy-goat/zvec-installer\r\n",
                'verify_peer' => true,
                'verify_peer_name' => true,
                'verify_depth' => 5,
            ],
        ]);
        $data = file_get_contents($url, false, $ctx);
        if ($data === false) {
            $error = error_get_last();
            throw new RuntimeException(
                'Failed to download ' . $url . ': ' . ($error['message'] ?? 'unknown error')
            );
        }
        file_put_contents($dest, $data);
    }

    private static function getExpectedHash(string $version, string $assetName): string
    {
        $sumsUrl = "https://github.com/" . self::GITHUB_REPO . "/releases/download/{$version}/checksums.sha256";
        $ctx = stream_context_create([
            'https' => [
                'header' => "User-Agent: crazy-goat/zvec-installer\r\n",
                'verify_peer' => true,
                'verify_peer_name' => true,
                'verify_depth' => 5,
            ],
        ]);
        $sumsContent = file_get_contents($sumsUrl, false, $ctx);
        if ($sumsContent === false) {
            $error = error_get_last();
            throw new RuntimeException(
                'Failed to fetch checksums for ' . $version . ': ' . ($error['message'] ?? 'unknown error')
            );
        }
        foreach (explode("\n", $sumsContent) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2 && $parts[1] === $assetName) {
                return strtolower($parts[0]);
            }
        }
        throw new RuntimeException("No checksum found for {$assetName} in release {$version}");
    }

    private static function extract(string $tarGz, string $destDir): void
    {
        $cmd = sprintf('tar -xzf %s -C %s 2>&1', escapeshellarg($tarGz), escapeshellarg($destDir));
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("Failed to extract archive: " . implode("\n", $output));
        }
    }
}
