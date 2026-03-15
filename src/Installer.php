<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use Composer\Script\Event;
use RuntimeException;

class Installer
{
    private const GITHUB_REPO = 'crazy-goat/php-zvec';
    private const LIB_DIR = __DIR__ . '/../lib';

    public static function install(Event $event): void
    {
        $io = $event->getIO();

        $os = PHP_OS_FAMILY;
        if ($os !== 'Linux') {
            $io->writeError('<warning>zvec FFI library auto-download is only supported on Linux. Please build manually.</warning>');
            return;
        }

        $arch = php_uname('m');
        if ($arch !== 'x86_64') {
            $io->writeError('<warning>zvec FFI library auto-download is only supported on x86_64. Please build manually.</warning>');
            return;
        }

        $version = self::resolveVersion($event);
        $assetName = 'libzvec_ffi-ubuntu24-x86_64.tar.gz';
        $url = "https://github.com/" . self::GITHUB_REPO . "/releases/download/{$version}/{$assetName}";

        $libDir = self::LIB_DIR;
        if (!is_dir($libDir) && !mkdir($libDir, 0755, true)) {
            throw new RuntimeException("Failed to create lib directory: {$libDir}");
        }

        $libPath = $libDir . '/libzvec_ffi.so';
        if (file_exists($libPath)) {
            $io->write("<info>zvec FFI library already installed at {$libPath}</info>");
            return;
        }

        $io->write("<info>Downloading zvec FFI library {$version}...</info>");
        $io->write("<info>URL: {$url}</info>");

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
            throw new RuntimeException("Download succeeded but libzvec_ffi.so not found in archive.");
        }

        $io->write("<info>zvec FFI library installed at {$libPath}</info>");
    }

    private static function resolveVersion(Event $event): string
    {
        $package = $event->getComposer()->getPackage();
        $version = $package->getPrettyVersion();

        if (str_starts_with($version, 'dev-') || $version === 'No version set (parsed as 1.0.0)') {
            return self::fetchLatestVersion();
        }

        return 'v' . ltrim($version, 'v');
    }

    private static function fetchLatestVersion(): string
    {
        $url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: crazy-goat/zvec-installer\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            throw new RuntimeException("Failed to fetch latest release from GitHub API.");
        }
        $data = json_decode($json, true);
        return $data['tag_name'] ?? throw new RuntimeException("Could not determine latest release version.");
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
