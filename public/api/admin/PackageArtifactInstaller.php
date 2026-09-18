<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';

final class PackageArtifactInstaller implements PackageArtifactStager
{
    private $rename;

    public function __construct(private string $publicRoot, ?callable $rename = null)
    {
        $this->publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR);
        $this->rename = $rename ?? static fn(string $from, string $to): bool => rename($from, $to);
    }

    public function prepare(DatasetPackage $package): StagedPackageArtifacts
    {
        if (!is_dir($this->publicRoot)) {
            throw new \RuntimeException('Viewer public root does not exist');
        }
        $stage = $this->publicRoot . '/.freetv-package-' . bin2hex(random_bytes(8));
        if (!mkdir($stage, 0700)) {
            throw new \RuntimeException('Could not create Viewer package staging directory');
        }

        try {
            if (!mkdir($stage . '/live-playlists', 0775)
                || !chmod($stage . '/live-playlists', 0775)
                || !mkdir($stage . '/live-thumbs', 0775)
                || !chmod($stage . '/live-thumbs', 0775)) {
                throw new \RuntimeException('Could not create staged Viewer directories');
            }
            $expectedHashes = [];
            foreach ($package->files() as $relativePath => $hash) {
                if ($relativePath === 'database.sql') {
                    continue;
                }
                if ($relativePath === 'config.json') {
                    $stagedPath = $stage . '/live-config.json';
                } elseif (str_starts_with($relativePath, 'playlists/')) {
                    $stagedPath = $stage . '/live-playlists/' . substr($relativePath, 10);
                } elseif (str_starts_with($relativePath, 'thumbs/')) {
                    $stagedPath = $stage . '/live-thumbs/' . substr($relativePath, 7);
                } else {
                    throw new \RuntimeException('Validated dataset contains an unsupported Viewer artifact');
                }
                if (!copy($package->path($relativePath), $stagedPath) || !chmod($stagedPath, 0644)) {
                    throw new \RuntimeException('Could not stage a dataset Viewer artifact');
                }
                $expectedHashes[$relativePath] = $hash;
            }

            return new PackageArtifactInstallation(
                $this->publicRoot,
                $stage,
                $expectedHashes,
                $this->rename
            );
        } catch (\Throwable $exception) {
            DatasetPackage::removeTree($stage);
            throw $exception;
        }
    }
}

final class PackageArtifactInstallation implements StagedPackageArtifacts
{
    private array $entries;
    private bool $completed = false;

    public function __construct(
        private string $publicRoot,
        private string $stage,
        private array $expectedHashes,
        private $rename
    ) {
        $this->entries = [
            ['live' => $publicRoot . '/config.json', 'staged' => $stage . '/live-config.json', 'backup' => $stage . '/backup-config.json', 'type' => 'file'],
            ['live' => $publicRoot . '/playlists', 'staged' => $stage . '/live-playlists', 'backup' => $stage . '/backup-playlists', 'type' => 'directory'],
            ['live' => $publicRoot . '/thumbs', 'staged' => $stage . '/live-thumbs', 'backup' => $stage . '/backup-thumbs', 'type' => 'directory'],
        ];
    }

    public function promote(): void
    {
        try {
            foreach ($this->entries as $index => $entry) {
                if (is_link($entry['live'])
                    || (file_exists($entry['live']) && $entry['type'] === 'file' && !is_file($entry['live']))
                    || (file_exists($entry['live']) && $entry['type'] === 'directory' && !is_dir($entry['live']))) {
                    throw new \RuntimeException('A managed Viewer package path has an unsafe type');
                }
                if (file_exists($entry['live'])) {
                    if (!($this->rename)($entry['live'], $entry['backup'])) {
                        throw new \RuntimeException('Could not back up existing Viewer package artifacts');
                    }
                    $this->entries[$index]['backed_up'] = true;
                }
                if (!($this->rename)($entry['staged'], $entry['live'])) {
                    throw new \RuntimeException('Could not promote Viewer package artifacts');
                }
                $this->entries[$index]['installed'] = true;
            }
            $this->verify();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function verify(): void
    {
        foreach ($this->expectedHashes as $relativePath => $expectedHash) {
            $path = $this->publicRoot . '/' . $relativePath;
            $actualHash = is_file($path) ? hash_file('sha256', $path) : false;
            if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
                throw new \RuntimeException('Installed Viewer package artifact verification failed');
            }
        }
    }

    public function commit(): void
    {
        foreach ($this->entries as $entry) {
            if (file_exists($entry['backup'])) {
                DatasetPackage::removeTree($entry['backup']);
            }
        }
        DatasetPackage::removeTree($this->stage);
        $this->completed = true;
    }

    public function rollback(): void
    {
        if ($this->completed) {
            return;
        }
        $failure = null;
        foreach (array_reverse($this->entries) as $entry) {
            if (($entry['installed'] ?? false) && file_exists($entry['live'])) {
                try {
                    DatasetPackage::removeTree($entry['live']);
                } catch (\Throwable $exception) {
                    $failure = $exception;
                }
            }
            if (($entry['backed_up'] ?? false) && file_exists($entry['backup'])
                && !($this->rename)($entry['backup'], $entry['live'])) {
                $failure = new \RuntimeException('Could not restore previous Viewer package artifacts');
            }
        }
        try {
            DatasetPackage::removeTree($this->stage);
        } catch (\Throwable $exception) {
            $failure ??= $exception;
        }
        $this->completed = true;
        if ($failure !== null) {
            throw $failure;
        }
    }
}
