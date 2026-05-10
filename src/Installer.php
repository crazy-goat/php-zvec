<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use RuntimeException;

class Installer
{
    private const GITHUB_REPO = 'crazy-goat/php-zvec';
    private const LIB_DIR = __DIR__ . '/../lib';

    public static function install(?string $version = null): void
    {
        $assetName = self::resolveAssetName();
        if ($assetName === null) {
            echo "zvec FFI library auto-download is not supported on your platform (" . PHP_OS_FAMILY . " " . php_uname('m') . ").\n";
            echo "See https://github.com/" . self::GITHUB_REPO . " for build instructions.\n";
            return;
        }

        $version = $version ?? self::detectVersion();
        $url = "https://github.com/" . self::GITHUB_REPO . "/releases/download/{$version}/{$assetName}";

        $libDir = self::LIB_DIR;
        if (!is_dir($libDir) && !mkdir($libDir, 0755, true)) {
            throw new RuntimeException("Failed to create lib directory: {$libDir}");
        }

        $libName = self::libName();
        $libPath = $libDir . '/' . $libName;
        if (file_exists($libPath)) {
            echo "zvec FFI library already installed at {$libPath}\n";
            return;
        }

        echo "Downloading zvec FFI library {$version} for " . self::platformLabel() . "...\n";

        $tmpFile = tempnam(sys_get_temp_dir(), 'zvec_ffi_') . '.tar.gz';

        try {
            self::download($url, $tmpFile);
            self::extract($tmpFile, $libDir);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        if (!file_exists($libPath)) {
            throw new RuntimeException("Download succeeded but {$libName} not found in archive.");
        }

        echo "zvec FFI library installed at {$libPath}\n";
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
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        return match (true) {
            $os === 'Linux' && $arch === 'x86_64' && !self::isMusl()
                => 'libzvec_ffi-ubuntu24-x86_64.tar.gz',
            $os === 'Linux' && $arch === 'x86_64' && self::isMusl()
                => 'libzvec_ffi-alpine-x86_64.tar.gz',
            $os === 'Darwin' && $arch === 'x86_64'
                => 'libzvec_ffi-darwin-x86_64.tar.gz',
            $os === 'Darwin' && $arch === 'arm64'
                => 'libzvec_ffi-darwin-aarch64.tar.gz',
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
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: crazy-goat/zvec-installer\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            throw new RuntimeException("Failed to download: {$url}");
        }
        file_put_contents($dest, $data);
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
