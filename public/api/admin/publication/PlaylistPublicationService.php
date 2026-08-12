<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/PlaylistIndexSerializer.php';
require_once __DIR__ . '/PublicationUndoService.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Database;
use JsonException;
use Throwable;

class PlaylistPublicationService
{
    private string $publicationRoot;
    private $playlistLoader;
    private $showLoader;
    private $timestampUpdater;
    private $clock;
    private PublicationUndoService $undoService;

    public function __construct(
        ?string $publicationRoot = null,
        ?callable $playlistLoader = null,
        ?callable $showLoader = null,
        ?callable $timestampUpdater = null,
        ?callable $clock = null,
        ?PublicationUndoService $undoService = null
    ) {
        $this->publicationRoot = rtrim($publicationRoot ?? dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
        $this->playlistLoader = $playlistLoader ?? static fn() => Database::table('playlists')
            ->select([
                'id',
                'filename',
                'dbtitle',
                'dbversion',
                'author',
                'email',
                'link',
                'is_default',
                'sort_order',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $this->showLoader = $showLoader ?? static fn(int $playlistId) => Database::table('playlist_shows')
            ->where('playlist_id', $playlistId)
            ->select([
                'id',
                'sort_order',
                'category',
                'status',
                'identifier',
                'title',
                'description',
                'start_year',
                'end_year',
                'imdb',
                'group_name',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $this->timestampUpdater = $timestampUpdater ?? static function (
            int $playlistId,
            string $databaseTimestamp
        ): void {
            $updatedRows = Database::table('playlists')
                ->where('id', $playlistId)
                ->update(['lastupdated' => $databaseTimestamp]);

            if ($updatedRows === 0 && Database::table('playlists')->where('id', $playlistId)->exists()) {
                return;
            }
            if ($updatedRows !== 1) {
                throw new PublicationException(
                    'Playlist was published, but its publication timestamp could not be saved'
                );
            }
        };
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->undoService = $undoService ?? new PublicationUndoService($this->publicationRoot);
    }

    public function publish(string $filename): array
    {
        return $this->undoService->withLock(fn() => $this->publishLocked($filename));
    }

    private function publishLocked(string $filename): array
    {
        self::validateFilename($filename);

        $playlists = [];
        foreach (($this->playlistLoader)() as $playlist) {
            $playlists[] = $playlist;
        }

        $selectedPlaylist = null;
        foreach ($playlists as $playlist) {
            if (self::value($playlist, 'filename') === $filename) {
                $selectedPlaylist = $playlist;
                break;
            }
        }

        if ($selectedPlaylist === null) {
            throw new PublicationException('Playlist not found', 404);
        }

        PlaylistIndexSerializer::validateDefault($playlists);
        $publishedTimestamps = $this->loadPublishedTimestamps();
        $publicationTimestamp = PublicationTimestamp::forOperation(($this->clock)());
        $playlistArtifact = PlaylistPublicationSerializer::serialize(
            $selectedPlaylist,
            ($this->showLoader)((int) self::value($selectedPlaylist, 'id')),
            $publicationTimestamp
        );
        $indexArtifact = PlaylistIndexSerializer::serialize(
            $playlists,
            $filename,
            $publicationTimestamp,
            $publishedTimestamps
        );

        $playlistJson = $this->encodeArtifact($playlistArtifact, 'playlist');
        $indexJson = $this->encodeArtifact($indexArtifact, 'playlist index');
        $playlistDirectory = $this->ensurePlaylistDirectory();
        $preparedUndo = $this->undoService->prepare(
            'playlist',
            $filename,
            ['playlists/' . $filename, 'playlists/index.json']
        );
        $databaseTimestampUpdated = false;
        try {
            $this->safeWrite($playlistDirectory . DIRECTORY_SEPARATOR . $filename, $playlistJson);
            $this->safeWrite($playlistDirectory . DIRECTORY_SEPARATOR . 'index.json', $indexJson);
            ($this->timestampUpdater)(
                (int) self::value($selectedPlaylist, 'id'),
                PublicationTimestamp::toDatabase($publicationTimestamp)
            );
            $databaseTimestampUpdated = true;
            $this->undoService->promote($preparedUndo);
        } catch (Throwable $exception) {
            try {
                $previousTimestamp = $this->undoService->preparedPlaylistTimestamp($preparedUndo);
                $this->undoService->rollbackPrepared($preparedUndo);
                if ($databaseTimestampUpdated) {
                    ($this->timestampUpdater)(
                        (int) self::value($selectedPlaylist, 'id'),
                        PublicationTimestamp::toDatabase($previousTimestamp)
                    );
                }
            } catch (Throwable $rollbackException) {
                throw new PublicationException(
                    'Playlist publication failed and the previous live artifacts could not be restored'
                );
            }
            throw $exception instanceof PublicationException
                ? $exception
                : new PublicationException('Playlist publication failed: ' . $exception->getMessage());
        }

        return [
            'filename' => $filename,
            'lastupdated' => $publicationTimestamp,
        ];
    }

    public static function validateFilename(string $filename): void
    {
        if (
            basename($filename) !== $filename
            || preg_match('/^[a-zA-Z0-9_-]+\.json$/', $filename) !== 1
            || strcasecmp($filename, 'index.json') === 0
        ) {
            throw new PublicationException('Invalid playlist filename', 400);
        }
    }

    private function encodeArtifact(array $artifact, string $artifactName): string
    {
        try {
            return json_encode(
                $artifact,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new PublicationException(
                "Could not encode {$artifactName} artifact: " . $exception->getMessage(),
                500
            );
        }
    }

    private function loadPublishedTimestamps(): array
    {
        $indexPath = $this->publicationRoot . DIRECTORY_SEPARATOR . 'playlists'
            . DIRECTORY_SEPARATOR . 'index.json';
        if (!is_file($indexPath) || !is_readable($indexPath)) {
            throw new PublicationException('Existing published playlist index is missing', 409);
        }

        $contents = file_get_contents($indexPath);
        if ($contents === false) {
            throw new PublicationException('Existing published playlist index could not be read', 409);
        }

        try {
            $index = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicationException('Existing published playlist index is invalid JSON', 409);
        }

        if (!is_object($index)
            || !property_exists($index, 'playlists')
            || !is_array($index->playlists)) {
            throw new PublicationException('Existing published playlist index is malformed', 409);
        }

        $timestamps = [];
        foreach ($index->playlists as $entry) {
            if (!is_object($entry)
                || !property_exists($entry, 'filename')
                || !is_string($entry->filename)
                || $entry->filename === '') {
                throw new PublicationException('Existing published playlist index has an invalid entry', 409);
            }

            $entryFilename = $entry->filename;
            if (array_key_exists($entryFilename, $timestamps)) {
                throw new PublicationException(
                    'Existing published playlist index has duplicate playlist ' . $entryFilename,
                    409
                );
            }

            $timestamps[$entryFilename] = $entry->lastupdated ?? null;
        }

        return $timestamps;
    }

    private function ensurePlaylistDirectory(): string
    {
        $playlistDirectory = $this->publicationRoot . DIRECTORY_SEPARATOR . 'playlists';
        if (is_dir($playlistDirectory)) {
            return $playlistDirectory;
        }
        if (file_exists($playlistDirectory)) {
            throw new PublicationException('Playlist publication path is not a directory');
        }
        if (!mkdir($playlistDirectory, 0775, true) && !is_dir($playlistDirectory)) {
            throw new PublicationException('Could not create playlist publication directory');
        }

        return $playlistDirectory;
    }

    private function safeWrite(string $destination, string $contents): void
    {
        $temporaryPath = tempnam(dirname($destination), '.publish-');
        if ($temporaryPath === false) {
            throw new PublicationException('Could not create temporary publication file');
        }

        try {
            $bytesWritten = file_put_contents($temporaryPath, $contents, LOCK_EX);
            if ($bytesWritten !== strlen($contents)) {
                throw new PublicationException('Could not write complete publication artifact');
            }
            if (!chmod($temporaryPath, 0644)) {
                throw new PublicationException('Could not set publication artifact permissions');
            }
            if (!rename($temporaryPath, $destination)) {
                throw new PublicationException('Could not replace publication artifact');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
