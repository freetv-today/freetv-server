<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PlaylistPublicationService.php';
require_once __DIR__ . '/PublicationStatusService.php';
require_once __DIR__ . '/PublicationUndoService.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Database;
use InvalidArgumentException;
use JsonException;
use Throwable;

class DataExportService
{
    private string $publicationRoot;
    private string $serverRoot;
    private PublicationStatusService $statusService;
    private PublicationUndoService $undoService;
    private $playlistTimestampLoader;
    private $clock;
    private $revisionResolver;

    public function __construct(
        ?string $publicationRoot = null,
        ?PublicationStatusService $statusService = null,
        ?callable $playlistTimestampLoader = null,
        ?callable $clock = null,
        ?callable $revisionResolver = null,
        ?PublicationUndoService $undoService = null
    ) {
        $this->serverRoot = dirname(__DIR__, 4);
        $this->publicationRoot = rtrim(
            $publicationRoot ?? $this->serverRoot . '/public',
            DIRECTORY_SEPARATOR
        );
        $this->statusService = $statusService ?? new PublicationStatusService($this->publicationRoot);
        $this->playlistTimestampLoader = $playlistTimestampLoader ?? static fn() =>
            Database::table('playlists')
                ->select(['filename', 'lastupdated'])
                ->get();
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->revisionResolver = $revisionResolver ?? fn(): ?string => $this->resolveServerRevision();
        $this->undoService = $undoService ?? new PublicationUndoService($this->publicationRoot);
    }

    public function export(string $destination): array
    {
        $resolvedDestination = $this->validateDestination($destination);

        return $this->undoService->withExistingLock(
            fn(): array => $this->exportLocked($resolvedDestination)
        );
    }

    private function exportLocked(string $destination): array
    {
        $status = $this->statusService->status();
        $this->assertSynchronized($status);
        $snapshot = $this->loadSnapshot($status);
        $manifest = $this->buildManifest($snapshot);

        if (!mkdir($destination, 0755)) {
            throw new PublicationException('Could not create data export destination');
        }

        try {
            $playlistDestination = $destination . '/playlists';
            if (!mkdir($playlistDestination, 0755)) {
                throw new PublicationException('Could not create data export playlist directory');
            }

            foreach ($snapshot['files'] as $relativePath => $file) {
                $stagedPath = $destination . '/' . $relativePath;
                $this->writeFile($stagedPath, $file['contents']);
                clearstatcache(true, $stagedPath);
                $hash = hash_file('sha256', $stagedPath);
                $bytes = filesize($stagedPath);
                if ($hash === false || $bytes === false) {
                    throw new PublicationException('Could not describe staged file ' . $relativePath);
                }
                $manifest['files'][] = [
                    'path' => $relativePath,
                    'sha256' => $hash,
                    'bytes' => $bytes,
                ];
            }

            $this->writeFile($destination . '/manifest.json', $this->encodeManifest($manifest));
        } catch (Throwable $exception) {
            $this->removeOwnedDestination($destination);
            throw $exception instanceof PublicationException
                ? $exception
                : new PublicationException('Data export failed');
        }

        return $manifest;
    }

    private function assertSynchronized(array $status): void
    {
        if (!is_array($status['playlists'] ?? null)) {
            throw new PublicationException('Publication status is malformed', 409);
        }
        foreach ($status['playlists'] as $playlist) {
            $filename = is_array($playlist) && is_string($playlist['filename'] ?? null)
                ? $playlist['filename']
                : 'Unknown playlist';
            if (!is_array($playlist) || ($playlist['error'] ?? null) !== null) {
                $error = is_string($playlist['error'] ?? null)
                    ? $playlist['error']
                    : 'publication status is invalid';
                throw new PublicationException($filename . ': ' . $error, 409);
            }
            if (($playlist['changed'] ?? null) !== false) {
                throw new PublicationException($filename . ' has unpublished changes', 409);
            }
        }

        $this->assertCleanStatusItem($status['config'] ?? null, 'Config');
        $this->assertCleanStatusItem($status['default_playlist'] ?? null, 'Default playlist');
    }

    private function assertCleanStatusItem(mixed $item, string $label): void
    {
        if (!is_array($item)) {
            throw new PublicationException($label . ' publication status is malformed', 409);
        }
        if (($item['error'] ?? null) !== null) {
            $error = is_string($item['error']) ? $item['error'] : 'publication status is invalid';
            throw new PublicationException($error, 409);
        }
        if (($item['changed'] ?? null) !== false) {
            throw new PublicationException($label . ' has unpublished changes', 409);
        }
    }

    private function loadSnapshot(array $status): array
    {
        $config = $this->readJsonArtifact('config.json');
        $index = $this->readJsonArtifact('playlists/index.json');
        $indexFields = array_keys($index['decoded']);
        sort($indexFields, SORT_STRING);
        if ($indexFields !== ['default', 'playlists'] || !is_array($index['decoded']['playlists'])) {
            throw new PublicationException('Published playlist index is malformed', 409);
        }

        $statusFilenames = [];
        foreach ($status['playlists'] as $playlistStatus) {
            $filename = $playlistStatus['filename'];
            PlaylistPublicationService::validateFilename($filename);
            if (isset($statusFilenames[$filename])) {
                throw new PublicationException('Publication status contains a duplicate playlist', 409);
            }
            $statusFilenames[$filename] = true;
        }

        $indexEntries = [];
        foreach ($index['decoded']['playlists'] as $entry) {
            if (!is_array($entry) || !is_string($entry['filename'] ?? null)) {
                throw new PublicationException('Published playlist index is malformed', 409);
            }
            $filename = $entry['filename'];
            PlaylistPublicationService::validateFilename($filename);
            if (isset($indexEntries[$filename])) {
                throw new PublicationException('Published playlist index contains duplicate playlists', 409);
            }
            $indexEntries[$filename] = $entry;
        }

        $expectedFilenames = array_keys($statusFilenames);
        $indexedFilenames = array_keys($indexEntries);
        $expectedOrder = $expectedFilenames;
        $indexedOrder = $indexedFilenames;
        sort($expectedFilenames, SORT_STRING);
        sort($indexedFilenames, SORT_STRING);
        if ($expectedFilenames !== $indexedFilenames) {
            throw new PublicationException(
                'Published playlist index does not match MariaDB playlist state',
                409
            );
        }
        if ($expectedOrder !== $indexedOrder) {
            throw new PublicationException(
                'Published playlist index order does not match MariaDB playlist state',
                409
            );
        }

        $databaseTimestamps = $this->loadDatabaseTimestamps($expectedFilenames);
        $playlistArtifacts = [];
        $playlistManifest = [];
        $showCount = 0;
        $publicationTimestamps = [];
        foreach ($expectedFilenames as $filename) {
            $artifact = $this->readJsonArtifact('playlists/' . $filename);
            $decoded = $artifact['decoded'];
            $publishedAt = $decoded['lastupdated'] ?? null;
            $this->assertCanonicalTimestamp($publishedAt, 'Published playlist ' . $filename);
            if (($indexEntries[$filename]['lastupdated'] ?? null) !== $publishedAt) {
                throw new PublicationException(
                    'Published playlist and index timestamps differ for ' . $filename,
                    409
                );
            }
            if (($databaseTimestamps[$filename] ?? null) !== $publishedAt) {
                throw new PublicationException(
                    'Published playlist timestamp differs from MariaDB for ' . $filename,
                    409
                );
            }
            $this->assertIndexEntryMatchesArtifact($indexEntries[$filename], $decoded, $filename);
            if (!is_array($decoded['shows'] ?? null)) {
                throw new PublicationException('Published playlist has an invalid shows array', 409);
            }

            $showCount += count($decoded['shows']);
            $playlistArtifacts[$filename] = $artifact;
            $playlistManifest[] = ['filename' => $filename, 'published_at' => $publishedAt];
            $publicationTimestamps[] = $publishedAt;
        }

        $configPublishedAt = $config['decoded']['lastupdated'] ?? null;
        $this->assertCanonicalTimestamp($configPublishedAt, 'Published config');
        $publicationTimestamps[] = $configPublishedAt;
        sort($publicationTimestamps, SORT_STRING);

        $files = [
            'config.json' => $config,
            'playlists/index.json' => $index,
        ];
        foreach ($playlistArtifacts as $filename => $artifact) {
            $files['playlists/' . $filename] = $artifact;
        }

        return [
            'config_published_at' => $configPublishedAt,
            'latest_published_at' => end($publicationTimestamps),
            'playlists' => $playlistManifest,
            'show_count' => $showCount,
            'files' => $files,
        ];
    }

    private function loadDatabaseTimestamps(array $expectedFilenames): array
    {
        $timestamps = [];
        foreach (($this->playlistTimestampLoader)() as $row) {
            $filename = self::value($row, 'filename');
            $timestamp = self::value($row, 'lastupdated');
            if (!is_string($filename) || isset($timestamps[$filename])) {
                throw new PublicationException('MariaDB playlist timestamp state is invalid', 409);
            }
            try {
                if (!is_string($timestamp)) {
                    throw new InvalidArgumentException('Timestamp is not a string');
                }
                $timestamps[$filename] = PublicationTimestamp::format($timestamp, 'UTC');
            } catch (InvalidArgumentException $exception) {
                throw new PublicationException(
                    'MariaDB playlist has an invalid lastupdated for ' . $filename,
                    409
                );
            }
        }

        $databaseFilenames = array_keys($timestamps);
        sort($databaseFilenames, SORT_STRING);
        if ($databaseFilenames !== $expectedFilenames) {
            throw new PublicationException('MariaDB playlist timestamp state is incomplete', 409);
        }

        return $timestamps;
    }

    private function assertIndexEntryMatchesArtifact(array $entry, array $artifact, string $filename): void
    {
        $expected = [
            'filename' => $filename,
            'dbtitle' => $artifact['dbtitle'] ?? null,
            'lastupdated' => $artifact['lastupdated'] ?? null,
        ];
        if (($artifact['author'] ?? null) !== null) {
            $expected['author'] = $artifact['author'];
        }
        ksort($entry, SORT_STRING);
        ksort($expected, SORT_STRING);
        if ($entry !== $expected) {
            throw new PublicationException(
                'Published playlist index metadata differs for ' . $filename,
                409
            );
        }
    }

    private function readJsonArtifact(string $relativePath): array
    {
        $path = $this->publicationRoot . '/' . $relativePath;
        if (!is_file($path) || !is_readable($path)) {
            throw new PublicationException('Published artifact is missing: ' . $relativePath, 409);
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new PublicationException('Could not read published artifact ' . $relativePath, 409);
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicationException('Published artifact contains invalid JSON: ' . $relativePath, 409);
        }
        if (!is_array($decoded)) {
            throw new PublicationException('Published artifact is malformed: ' . $relativePath, 409);
        }

        return ['contents' => $contents, 'decoded' => $decoded];
    }

    private function assertCanonicalTimestamp(mixed $timestamp, string $label): void
    {
        try {
            if (!is_string($timestamp) || PublicationTimestamp::format($timestamp) !== $timestamp) {
                throw new InvalidArgumentException('Timestamp is not canonical');
            }
        } catch (InvalidArgumentException $exception) {
            throw new PublicationException($label . ' has an invalid lastupdated', 409);
        }
    }

    private function buildManifest(array $snapshot): array
    {
        $revision = ($this->revisionResolver)();
        if (!is_string($revision)
            || preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $revision) !== 1) {
            $revision = null;
        } else {
            $revision = strtolower($revision);
        }

        return [
            'contract_version' => 1,
            'created_at' => PublicationTimestamp::format(($this->clock)()),
            'server_revision' => $revision,
            'publication' => [
                'config' => $snapshot['config_published_at'],
                'latest' => $snapshot['latest_published_at'],
            ],
            'dataset' => [
                'playlist_count' => count($snapshot['playlists']),
                'show_count' => $snapshot['show_count'],
            ],
            'playlists' => $snapshot['playlists'],
            'files' => [],
        ];
    }

    private function validateDestination(string $destination): string
    {
        if ($destination === '' || str_contains($destination, "\0")) {
            throw new PublicationException('Data export destination is required', 400);
        }
        $trimmed = rtrim($destination, DIRECTORY_SEPARATOR);
        if ($trimmed === '' || basename($trimmed) === '.' || basename($trimmed) === '..') {
            throw new PublicationException('Data export destination is unsafe', 400);
        }
        if (file_exists($trimmed) || is_link($trimmed)) {
            throw new PublicationException('Data export destination must not already exist', 409);
        }

        $parent = realpath(dirname($trimmed));
        if ($parent === false || !is_dir($parent) || !is_writable($parent)) {
            throw new PublicationException('Data export destination parent is not writable', 400);
        }
        $resolved = $parent . DIRECTORY_SEPARATOR . basename($trimmed);
        $publicationRoot = realpath($this->publicationRoot);
        if ($publicationRoot !== false && $resolved === $publicationRoot) {
            throw new PublicationException('Data export cannot overwrite the publication root', 400);
        }

        return $resolved;
    }

    private function writeFile(string $path, string $contents): void
    {
        $bytes = file_put_contents($path, $contents, LOCK_EX);
        if ($bytes !== strlen($contents) || !chmod($path, 0644)) {
            throw new PublicationException('Could not write complete data export file');
        }
    }

    private function encodeManifest(array $manifest): string
    {
        try {
            return json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new PublicationException('Could not encode data export manifest');
        }
    }

    private function resolveServerRevision(): ?string
    {
        $gitPath = $this->serverRoot . '/.git';
        if (is_file($gitPath)) {
            $gitFile = file_get_contents($gitPath);
            if ($gitFile === false || preg_match('/^gitdir: (.+)\s*$/', $gitFile, $matches) !== 1) {
                return null;
            }
            $gitPath = $matches[1];
            if (!str_starts_with($gitPath, DIRECTORY_SEPARATOR)) {
                $gitPath = $this->serverRoot . '/' . $gitPath;
            }
        }
        if (!is_dir($gitPath)) {
            return null;
        }

        $head = $this->readTrimmedFile($gitPath . '/HEAD');
        if ($head === null) {
            return null;
        }
        if (preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $head) === 1) {
            return strtolower($head);
        }
        if (preg_match('#^ref: (refs/[A-Za-z0-9._/-]+)$#', $head, $matches) !== 1
            || str_contains($matches[1], '..')) {
            return null;
        }

        $revision = $this->readTrimmedFile($gitPath . '/' . $matches[1]);
        if ($revision === null) {
            $revision = $this->revisionFromPackedRefs($gitPath, $matches[1]);
        }
        return is_string($revision)
            && preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $revision) === 1
                ? strtolower($revision)
                : null;
    }

    private function readTrimmedFile(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        return $contents === false ? null : trim($contents);
    }

    private function revisionFromPackedRefs(string $gitPath, string $reference): ?string
    {
        $packedRefs = $this->readTrimmedFile($gitPath . '/packed-refs');
        if ($packedRefs === null) {
            return null;
        }
        foreach (preg_split('/\R/', $packedRefs) ?: [] as $line) {
            if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                continue;
            }
            [$revision, $name] = array_pad(explode(' ', $line, 2), 2, null);
            if ($name === $reference) {
                return $revision;
            }
        }
        return null;
    }

    private function removeOwnedDestination(string $destination): void
    {
        if (!is_dir($destination) || is_link($destination)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destination, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($destination);
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
