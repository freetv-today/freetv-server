<?php

declare(strict_types=1);

namespace FreeTV\Admin;

use JsonException;

final class FreshArtifactInstaller
{
    private string $publicRoot;
    private $rename;

    public function __construct(string $publicRoot, ?callable $rename = null)
    {
        $this->publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR);
        $this->rename = $rename ?? static fn(string $from, string $to): bool => rename($from, $to);
    }

    public function prepare(array $artifacts): FreshArtifactInstallation
    {
        $expected = ['config.json', 'playlists/index.json', 'playlists/playlist-one.json'];
        if (array_keys($artifacts) !== $expected || !is_dir($this->publicRoot)) {
            throw new \RuntimeException('Fresh Viewer artifact installation inputs are invalid');
        }

        $stagingDirectory = $this->publicRoot . DIRECTORY_SEPARATOR
            . '.freetv-fresh-' . bin2hex(random_bytes(8));
        $playlistDirectory = $this->publicRoot . DIRECTORY_SEPARATOR . 'playlists';
        $createdPlaylistDirectory = false;
        if (!is_dir($playlistDirectory)) {
            if (file_exists($playlistDirectory)
                || (!mkdir($playlistDirectory, 0775) && !is_dir($playlistDirectory))) {
                throw new \RuntimeException('Could not create the Fresh playlist artifact directory');
            }
            $createdPlaylistDirectory = true;
        }

        if (!mkdir($stagingDirectory, 0700)) {
            if ($createdPlaylistDirectory) {
                rmdir($playlistDirectory);
            }
            throw new \RuntimeException('Could not create the Fresh artifact staging directory');
        }

        $entries = [];
        try {
            foreach ($expected as $index => $relativePath) {
                $destination = $this->publicRoot . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                if (file_exists($destination) && !is_file($destination)) {
                    throw new \RuntimeException('A managed Fresh artifact path is not a regular file');
                }
                try {
                    $json = json_encode(
                        $artifacts[$relativePath],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            | JSON_THROW_ON_ERROR
                    ) . "\n";
                } catch (JsonException $exception) {
                    throw new \RuntimeException('Could not encode a Fresh Viewer artifact', 0, $exception);
                }
                $staged = $stagingDirectory . DIRECTORY_SEPARATOR . $index . '.json';
                if (file_put_contents($staged, $json, LOCK_EX) !== strlen($json)
                    || !chmod($staged, 0644)) {
                    throw new \RuntimeException('Could not stage a complete Fresh Viewer artifact');
                }
                $entries[] = [
                    'artifact' => $artifacts[$relativePath],
                    'destination' => $destination,
                    'staged' => $staged,
                    'backup' => $stagingDirectory . DIRECTORY_SEPARATOR . $index . '.backup',
                ];
            }
        } catch (\Throwable $exception) {
            for ($index = 0; $index < count($expected); $index++) {
                $staged = $stagingDirectory . DIRECTORY_SEPARATOR . $index . '.json';
                if (is_file($staged)) {
                    unlink($staged);
                }
            }
            rmdir($stagingDirectory);
            if ($createdPlaylistDirectory) {
                rmdir($playlistDirectory);
            }
            throw $exception;
        }

        return new FreshArtifactInstallation(
            $entries,
            $stagingDirectory,
            $playlistDirectory,
            $createdPlaylistDirectory,
            $this->rename
        );
    }
}

final class FreshArtifactInstallation
{
    private array $entries;
    private string $stagingDirectory;
    private string $playlistDirectory;
    private bool $createdPlaylistDirectory;
    private $rename;
    private bool $completed = false;

    public function __construct(
        array $entries,
        string $stagingDirectory,
        string $playlistDirectory,
        bool $createdPlaylistDirectory,
        callable $rename
    ) {
        $this->entries = $entries;
        $this->stagingDirectory = $stagingDirectory;
        $this->playlistDirectory = $playlistDirectory;
        $this->createdPlaylistDirectory = $createdPlaylistDirectory;
        $this->rename = $rename;
    }

    public function promote(): void
    {
        try {
            foreach ($this->entries as $index => $entry) {
                if (is_file($entry['destination'])
                    && !($this->rename)($entry['destination'], $entry['backup'])) {
                    throw new \RuntimeException('Could not back up a stale Fresh Viewer artifact');
                }
                $this->entries[$index]['backed_up'] = is_file($entry['backup']);
                if (!($this->rename)($entry['staged'], $entry['destination'])) {
                    throw new \RuntimeException('Could not promote a Fresh Viewer artifact');
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
        foreach ($this->entries as $entry) {
            try {
                $actual = json_decode(
                    (string) file_get_contents($entry['destination']),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $exception) {
                throw new \RuntimeException('Installed Fresh Viewer artifact is invalid JSON', 0, $exception);
            }
            if ($actual !== $entry['artifact']) {
                throw new \RuntimeException('Installed Fresh Viewer artifact verification failed');
            }
        }
    }

    public function commit(): void
    {
        foreach ($this->entries as $entry) {
            if (is_file($entry['backup'])) {
                if (!unlink($entry['backup'])) {
                    error_log('Fresh bootstrap could not remove an obsolete artifact backup');
                }
            }
            if (is_file($entry['staged'])) {
                if (!unlink($entry['staged'])) {
                    error_log('Fresh bootstrap could not remove an obsolete staged artifact');
                }
            }
        }
        if (is_dir($this->stagingDirectory) && !rmdir($this->stagingDirectory)) {
            error_log('Fresh bootstrap could not remove its completed staging directory');
        }
        $this->completed = true;
    }

    public function rollback(): void
    {
        if ($this->completed) {
            return;
        }
        $failure = null;
        foreach (array_reverse($this->entries) as $entry) {
            if (($entry['installed'] ?? false) && is_file($entry['destination'])) {
                if (!unlink($entry['destination'])) {
                    $failure = new \RuntimeException('Could not remove a partial Fresh Viewer artifact');
                }
            }
            if (($entry['backed_up'] ?? false) && is_file($entry['backup'])
                && !($this->rename)($entry['backup'], $entry['destination'])) {
                $failure = new \RuntimeException('Could not restore a stale Fresh Viewer artifact');
            }
            if (is_file($entry['staged'])) {
                unlink($entry['staged']);
            }
        }
        if (is_dir($this->stagingDirectory)) {
            rmdir($this->stagingDirectory);
        }
        if ($this->createdPlaylistDirectory && is_dir($this->playlistDirectory)) {
            rmdir($this->playlistDirectory);
        }
        $this->completed = true;
        if ($failure !== null) {
            throw $failure;
        }
    }
}
