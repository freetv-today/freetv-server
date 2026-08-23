<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';

use FreeTV\Admin\Database;
use JsonException;
use Throwable;

class PublicationUndoService
{
    private const HASH_PATTERN = '/^[a-f0-9]{64}$/';

    private string $publicationRoot;
    private string $undoRoot;
    private $playlistTimestampUpdater;

    public function __construct(
        ?string $publicationRoot = null,
        ?string $undoRoot = null,
        ?callable $playlistTimestampUpdater = null
    ) {
        $serverRoot = dirname(__DIR__, 4);
        $this->publicationRoot = rtrim($publicationRoot ?? $serverRoot . '/public', DIRECTORY_SEPARATOR);
        $this->undoRoot = rtrim($undoRoot ?? $serverRoot . '/temp/publication-undo', DIRECTORY_SEPARATOR);
        $this->playlistTimestampUpdater = $playlistTimestampUpdater ?? static function (
            string $filename,
            string $databaseTimestamp
        ): void {
            $updatedRows = Database::table('playlists')
                ->where('filename', $filename)
                ->update(['lastupdated' => $databaseTimestamp]);
            if ($updatedRows === 0
                && Database::table('playlists')->where('filename', $filename)->exists()) {
                return;
            }
            if ($updatedRows !== 1) {
                throw new PublicationException('Could not restore the playlist publication timestamp');
            }
        };
    }

    public function withLock(callable $operation)
    {
        $this->ensureUndoRoot();
        $lock = fopen($this->undoRoot . '/.lock', 'c');
        return $this->runWithLock($lock, $operation);
    }

    public function withExistingLock(callable $operation)
    {
        $lockPath = $this->undoRoot . '/.lock';
        $lock = is_file($lockPath) && is_readable($lockPath)
            ? fopen($lockPath, 'r')
            : false;
        return $this->runWithLock($lock, $operation);
    }

    private function runWithLock($lock, callable $operation)
    {
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new PublicationException('Could not lock publication Undo state');
        }

        try {
            return $operation($this);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function prepare(string $operation, string $target, array $relativePaths): string
    {
        if (!in_array($operation, ['playlist', 'playlist_all', 'config'], true) || $relativePaths === []) {
            throw new PublicationException('Invalid publication Undo operation');
        }

        $preparedName = '.prepared-' . bin2hex(random_bytes(16));
        $preparedRoot = $this->undoRoot . '/' . $preparedName;
        if (!mkdir($preparedRoot . '/files', 0700, true)) {
            throw new PublicationException('Could not prepare publication Undo state');
        }

        $files = [];
        try {
            foreach ($relativePaths as $relativePath) {
                $this->validateRelativePath($relativePath);
                $livePath = $this->publicationRoot . '/' . $relativePath;
                if (!is_file($livePath) || !is_readable($livePath)) {
                    if ($operation === 'playlist_all' && $relativePath !== 'playlists/index.json'
                        && !file_exists($livePath)) {
                        $files[] = ['path' => $relativePath, 'existed' => false];
                        continue;
                    }
                    throw new PublicationException('Cannot publish without live artifact ' . $relativePath, 409);
                }

                $backupPath = $preparedRoot . '/files/' . $relativePath;
                $backupDirectory = dirname($backupPath);
                if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true)) {
                    throw new PublicationException('Could not prepare publication Undo backup');
                }
                if (!copy($livePath, $backupPath) || !chmod($backupPath, 0600)) {
                    throw new PublicationException('Could not preserve live artifact ' . $relativePath);
                }

                $liveHash = hash_file('sha256', $livePath);
                $backupHash = hash_file('sha256', $backupPath);
                if ($liveHash === false || $backupHash === false || !hash_equals($liveHash, $backupHash)) {
                    throw new PublicationException('Could not verify Undo backup ' . $relativePath);
                }
                $files[] = ['path' => $relativePath, 'existed' => true, 'backup_hash' => $backupHash];
            }

            $this->writeMetadata($preparedRoot, [
                'operation' => $operation,
                'target' => $target,
                'created_at' => PublicationTimestamp::format('now'),
                'files' => $files,
            ]);
            if ($operation === 'playlist') {
                $this->playlistTimestamp($preparedRoot, $target);
            } elseif ($operation === 'playlist_all') {
                $this->playlistTimestamps($preparedRoot, $files);
            }
        } catch (Throwable $exception) {
            $this->removeTree($preparedRoot);
            throw $exception;
        }

        return $preparedName;
    }

    public function promote(string $preparedName): void
    {
        $preparedRoot = $this->preparedRoot($preparedName);
        $metadata = $this->readMetadata($preparedRoot);
        foreach ($metadata['files'] as &$file) {
            $livePath = $this->publicationRoot . '/' . $file['path'];
            $liveHash = is_file($livePath) ? hash_file('sha256', $livePath) : false;
            if ($liveHash === false) {
                throw new PublicationException('Could not verify published artifact ' . $file['path']);
            }
            $file['published_hash'] = $liveHash;
        }
        unset($file);
        $this->writeMetadata($preparedRoot, $metadata);

        $activeRoot = $this->activeRoot();
        $priorRoot = $this->undoRoot . '/.prior-' . bin2hex(random_bytes(16));
        $hadPrior = is_dir($activeRoot);
        if ($hadPrior && !rename($activeRoot, $priorRoot)) {
            throw new PublicationException('Could not preserve existing publication Undo state');
        }

        if (!rename($preparedRoot, $activeRoot)) {
            if ($hadPrior) {
                rename($priorRoot, $activeRoot);
            }
            throw new PublicationException('Could not activate publication Undo state');
        }
        if ($hadPrior) {
            $this->removeTree($priorRoot);
        }
    }

    public function rollbackPrepared(string $preparedName): void
    {
        $preparedRoot = $this->preparedRoot($preparedName);
        $metadata = $this->readMetadata($preparedRoot);
        $this->restoreFiles($preparedRoot, $metadata['files'], true);
        $this->removeTree($preparedRoot);
    }

    public function discardPrepared(string $preparedName): void
    {
        $this->removeTree($this->preparedRoot($preparedName));
    }

    public function preparedPlaylistTimestamp(string $preparedName): string
    {
        $preparedRoot = $this->preparedRoot($preparedName);
        $metadata = $this->readMetadata($preparedRoot);
        if ($metadata['operation'] !== 'playlist') {
            throw new PublicationException('Undo state is not a playlist publication');
        }

        return $this->playlistTimestamp($preparedRoot, $metadata['target']);
    }

    public function preparedPlaylistTimestamps(string $preparedName): array
    {
        $preparedRoot = $this->preparedRoot($preparedName);
        $metadata = $this->readMetadata($preparedRoot);
        if (!in_array($metadata['operation'], ['playlist', 'playlist_all'], true)) {
            throw new PublicationException('Undo state is not a playlist publication');
        }
        if ($metadata['operation'] === 'playlist') {
            return [$metadata['target'] => $this->playlistTimestamp($preparedRoot, $metadata['target'])];
        }

        return $this->playlistTimestamps($preparedRoot, $metadata['files']);
    }

    public function status(): array
    {
        return $this->withLock(function (): array {
            if (!is_dir($this->activeRoot())) {
                return ['available' => false, 'operation' => null, 'target' => null];
            }

            $metadata = $this->readMetadata($this->activeRoot(), true);
            return [
                'available' => true,
                'operation' => $metadata['operation'],
                'target' => $metadata['target'],
            ];
        });
    }

    public function undo(): array
    {
        return $this->withLock(function (): array {
            $activeRoot = $this->activeRoot();
            if (!is_dir($activeRoot)) {
                throw new PublicationException('No publication Undo is available', 404);
            }

            $metadata = $this->readMetadata($activeRoot, true);
            $this->validateHashes($activeRoot, $metadata['files']);
            $rollbackRoot = $this->prepareLiveRollback($metadata['files']);

            try {
                $this->restoreFiles($activeRoot, $metadata['files'], true);
                if ($metadata['operation'] === 'playlist') {
                    $timestamp = $this->playlistTimestamp($activeRoot, $metadata['target']);
                    ($this->playlistTimestampUpdater)(
                        $metadata['target'],
                        PublicationTimestamp::toDatabase($timestamp)
                    );
                } elseif ($metadata['operation'] === 'playlist_all') {
                    foreach ($this->playlistTimestamps($activeRoot, $metadata['files']) as $filename => $timestamp) {
                        ($this->playlistTimestampUpdater)(
                            $filename,
                            PublicationTimestamp::toDatabase($timestamp)
                        );
                    }
                }
            } catch (Throwable $exception) {
                try {
                    $this->restoreFiles($rollbackRoot, $metadata['files'], false);
                } catch (Throwable $rollbackException) {
                    throw new PublicationException(
                        'Publication Undo failed and live artifacts could not be recovered'
                    );
                } finally {
                    $this->removeTree($rollbackRoot);
                }
                throw $exception;
            }

            $this->removeTree($rollbackRoot);
            $this->removeTree($activeRoot);

            return [
                'operation' => $metadata['operation'],
                'target' => $metadata['target'],
                'restored' => array_column($metadata['files'], 'path'),
            ];
        });
    }

    private function validateHashes(string $stateRoot, array $files): void
    {
        foreach ($files as $file) {
            if (preg_match(self::HASH_PATTERN, $file['published_hash'] ?? '') !== 1) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
            if ($this->fileExisted($file)) {
                $backupHash = hash_file('sha256', $stateRoot . '/files/' . $file['path']);
                if ($backupHash === false || !hash_equals($file['backup_hash'], $backupHash)) {
                    throw new PublicationException('Publication Undo backup is corrupted', 409);
                }
            }

            $livePath = $this->publicationRoot . '/' . $file['path'];
            $liveHash = is_file($livePath) ? hash_file('sha256', $livePath) : false;
            if ($liveHash === false || !hash_equals($file['published_hash'], $liveHash)) {
                throw new PublicationException(
                    'Live publication no longer matches the available Undo',
                    409
                );
            }
        }
    }

    private function restoreFiles(string $sourceRoot, array $files, bool $verifyBackup): void
    {
        foreach ($files as $file) {
            $source = $sourceRoot . '/files/' . $file['path'];
            $destination = $this->publicationRoot . '/' . $file['path'];
            if ($verifyBackup && !$this->fileExisted($file)) {
                if (is_file($destination) && !unlink($destination)) {
                    throw new PublicationException('Could not remove newly published artifact ' . $file['path']);
                }
                continue;
            }
            $expectedHash = $verifyBackup ? $file['backup_hash'] : hash_file('sha256', $source);
            $this->safeCopy($source, $destination);
            $restoredHash = hash_file('sha256', $destination);
            if ($expectedHash === false || $restoredHash === false || !hash_equals($expectedHash, $restoredHash)) {
                throw new PublicationException('Could not verify restored artifact ' . $file['path']);
            }
        }
    }

    private function prepareLiveRollback(array $files): string
    {
        $root = $this->undoRoot . '/.restore-' . bin2hex(random_bytes(16));
        if (!mkdir($root . '/files', 0700, true)) {
            throw new PublicationException('Could not prepare publication Undo restore');
        }
        try {
            foreach ($files as $file) {
                $source = $this->publicationRoot . '/' . $file['path'];
                $destination = $root . '/files/' . $file['path'];
                if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
                    throw new PublicationException('Could not prepare publication Undo restore');
                }
                if (!copy($source, $destination)) {
                    throw new PublicationException('Could not prepare publication Undo restore');
                }
            }
        } catch (Throwable $exception) {
            $this->removeTree($root);
            throw $exception;
        }
        return $root;
    }

    private function playlistTimestamp(string $stateRoot, string $target): string
    {
        $playlist = $this->readJson($stateRoot . '/files/playlists/' . $target);
        $index = $this->readJson($stateRoot . '/files/playlists/index.json');
        $playlistTimestamp = $playlist['lastupdated'] ?? null;
        $indexTimestamp = null;
        foreach (($index['playlists'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['filename'] ?? null) === $target) {
                $indexTimestamp = $entry['lastupdated'] ?? null;
                break;
            }
        }
        if (!is_string($playlistTimestamp)
            || $playlistTimestamp !== $indexTimestamp
            || PublicationTimestamp::format($playlistTimestamp) !== $playlistTimestamp) {
            throw new PublicationException('Playlist Undo timestamps are inconsistent', 409);
        }
        return $playlistTimestamp;
    }

    private function playlistTimestamps(string $stateRoot, array $files): array
    {
        $index = $this->readJson($stateRoot . '/files/playlists/index.json');
        $indexTimestamps = [];
        foreach (($index['playlists'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['filename'] ?? null)) {
                $indexTimestamps[$entry['filename']] = $entry['lastupdated'] ?? null;
            }
        }

        $timestamps = [];
        foreach ($files as $file) {
            if ($file['path'] === 'playlists/index.json' || !$this->fileExisted($file)) {
                continue;
            }
            $filename = basename($file['path']);
            $timestamp = $indexTimestamps[$filename] ?? null;
            $playlist = $this->readJson($stateRoot . '/files/' . $file['path']);
            if (($playlist['lastupdated'] ?? null) !== $timestamp) {
                throw new PublicationException('Playlist Undo timestamps are inconsistent', 409);
            }
            if (!is_string($timestamp) || PublicationTimestamp::format($timestamp) !== $timestamp) {
                throw new PublicationException('Playlist Undo timestamps are inconsistent', 409);
            }
            $timestamps[$filename] = $timestamp;
        }

        return $timestamps;
    }

    private function readJson(string $path): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicationException('Publication Undo contains invalid JSON', 409);
        }
        if (!is_array($decoded)) {
            throw new PublicationException('Publication Undo contains invalid JSON', 409);
        }
        return $decoded;
    }

    private function readMetadata(string $root, bool $requirePublishedHashes = false): array
    {
        $metadata = $this->readJson($root . '/operation.json');
        if (!in_array($metadata['operation'] ?? null, ['playlist', 'playlist_all', 'config'], true)
            || !is_string($metadata['target'] ?? null)
            || !is_string($metadata['created_at'] ?? null)
            || !is_array($metadata['files'] ?? null)
            || $metadata['files'] === []) {
            throw new PublicationException('Publication Undo metadata is invalid', 409);
        }
        try {
            if (PublicationTimestamp::format($metadata['created_at']) !== $metadata['created_at']) {
                throw new \InvalidArgumentException('Timestamp is not canonical');
            }
        } catch (\InvalidArgumentException $exception) {
            throw new PublicationException('Publication Undo metadata is invalid', 409);
        }
        foreach ($metadata['files'] as $file) {
            if (!is_array($file)) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
            $existed = $this->fileExisted($file);
            if (!is_string($file['path'] ?? null)
                || (array_key_exists('existed', $file) && !is_bool($file['existed']))
                || ($existed && preg_match(self::HASH_PATTERN, $file['backup_hash'] ?? '') !== 1)
                || (!$existed && array_key_exists('backup_hash', $file))
                || (array_key_exists('published_hash', $file)
                    && preg_match(self::HASH_PATTERN, $file['published_hash']) !== 1)) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
            if ($requirePublishedHashes
                && preg_match(self::HASH_PATTERN, $file['published_hash'] ?? '') !== 1) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
            $this->validateRelativePath($file['path']);
        }
        $paths = array_column($metadata['files'], 'path');
        sort($paths);
        if ($metadata['operation'] === 'config') {
            if ($metadata['target'] !== 'config.json' || $paths !== ['config.json']) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
        } elseif ($metadata['operation'] === 'playlist') {
            if (preg_match('/^[a-zA-Z0-9_-]+\.json$/', $metadata['target']) !== 1) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
            $expectedPaths = ['playlists/' . $metadata['target'], 'playlists/index.json'];
            sort($expectedPaths);
            if ($paths !== $expectedPaths) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
        } else {
            if ($metadata['target'] !== 'All Shows and Playlist Content'
                || !in_array('playlists/index.json', $paths, true)
                || count($paths) !== count(array_unique($paths))
                || array_filter(
                    $paths,
                    static fn(string $path): bool => !str_starts_with($path, 'playlists/')
                ) !== []) {
                throw new PublicationException('Publication Undo metadata is invalid', 409);
            }
        }
        return $metadata;
    }

    private function writeMetadata(string $root, array $metadata): void
    {
        try {
            $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicationException('Could not encode publication Undo metadata');
        }
        $path = $root . '/operation.json';
        $temporary = tempnam($root, '.metadata-');
        if ($temporary === false
            || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)
            || !chmod($temporary, 0600)
            || !rename($temporary, $path)) {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            throw new PublicationException('Could not write publication Undo metadata');
        }
    }

    private function safeCopy(string $source, string $destination): void
    {
        $temporary = tempnam(dirname($destination), '.undo-restore-');
        if ($temporary === false || !copy($source, $temporary) || !chmod($temporary, 0644)) {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            throw new PublicationException('Could not prepare restored publication artifact');
        }
        if (!rename($temporary, $destination)) {
            unlink($temporary);
            throw new PublicationException('Could not restore publication artifact');
        }
    }

    private function validateRelativePath(string $path): void
    {
        if ($path === 'config.json' || preg_match('#^playlists/[a-zA-Z0-9_-]+\.json$#', $path) === 1) {
            return;
        }
        throw new PublicationException('Publication Undo contains an invalid artifact path', 409);
    }

    private function fileExisted(array $file): bool
    {
        return ($file['existed'] ?? true) === true;
    }

    private function preparedRoot(string $preparedName): string
    {
        if (preg_match('/^\.prepared-[a-f0-9]{32}$/', $preparedName) !== 1) {
            throw new PublicationException('Invalid prepared publication Undo state');
        }
        return $this->undoRoot . '/' . $preparedName;
    }

    private function ensureUndoRoot(): void
    {
        if (!is_dir($this->undoRoot) && !mkdir($this->undoRoot, 0700, true) && !is_dir($this->undoRoot)) {
            throw new PublicationException('Could not create publication Undo directory');
        }
        if (!is_writable($this->undoRoot)) {
            throw new PublicationException('Publication Undo directory is not writable');
        }
    }

    private function activeRoot(): string
    {
        return $this->undoRoot . '/active';
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
