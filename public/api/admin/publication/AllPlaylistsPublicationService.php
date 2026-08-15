<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/PlaylistIndexSerializer.php';
require_once __DIR__ . '/PlaylistPublicationService.php';
require_once __DIR__ . '/PublicationSemanticDelta.php';
require_once __DIR__ . '/PublicationStatusService.php';
require_once __DIR__ . '/PublicationUndoService.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Database;
use JsonException;
use Throwable;

class AllPlaylistsPublicationService
{
    private string $publicationRoot;
    private $playlistLoader;
    private $showLoader;
    private $timestampUpdater;
    private $clock;
    private PublicationUndoService $undoService;
    private $artifactWriter;

    public function __construct(
        ?string $publicationRoot = null,
        ?callable $playlistLoader = null,
        ?callable $showLoader = null,
        ?callable $timestampUpdater = null,
        ?callable $clock = null,
        ?PublicationUndoService $undoService = null,
        ?callable $artifactWriter = null
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
        $this->artifactWriter = $artifactWriter ?? fn(string $path, string $contents) =>
            $this->safeWrite($path, $contents);
    }

    public function publish(): array
    {
        $preview = $this->publicationState();
        if ($preview['changed_filenames'] === [] && !$preview['default_changed']) {
            return $this->noOpResult();
        }

        return $this->undoService->withLock(fn() => $this->publishLocked());
    }

    private function publishLocked(): array
    {
        $state = $this->publicationState();
        $changedFilenames = $state['changed_filenames'];
        $defaultChanged = $state['default_changed'];
        if ($changedFilenames === [] && !$defaultChanged) {
            return $this->noOpResult();
        }

        $publicationTimestamp = PublicationTimestamp::forOperation(($this->clock)());
        $playlistArtifacts = [];
        foreach ($changedFilenames as $filename) {
            $playlist = $state['playlists_by_filename'][$filename];
            $artifact = PlaylistPublicationSerializer::serialize(
                $playlist,
                $state['shows_by_playlist'][(int) self::value($playlist, 'id')],
                $publicationTimestamp
            );
            $deltaValidation = PublicationSemanticDelta::playlist(
                $artifact,
                self::artifactObject($artifact)
            );
            if ($deltaValidation['error'] !== null) {
                throw new PublicationException($deltaValidation['error'], 409);
            }
            $playlistArtifacts[$filename] = $this->encodeArtifact($artifact, 'playlist');
        }

        $publishedTimestamps = $this->loadPublishedTimestamps();
        $indexArtifact = PlaylistIndexSerializer::serializeChanged(
            $state['playlists'],
            $changedFilenames,
            $publicationTimestamp,
            $publishedTimestamps
        );
        $indexJson = $this->encodeArtifact($indexArtifact, 'playlist index');

        $relativePaths = array_map(
            static fn(string $filename): string => 'playlists/' . $filename,
            $changedFilenames
        );
        $relativePaths[] = 'playlists/index.json';
        $preparedUndo = $this->undoService->prepare(
            'playlist_all',
            'All Shows and Playlist Content',
            $relativePaths
        );
        try {
            $previousTimestamps = $this->undoService->preparedPlaylistTimestamps($preparedUndo);
        } catch (Throwable $exception) {
            $this->undoService->discardPrepared($preparedUndo);
            throw $exception;
        }
        $updatedFilenames = [];

        try {
            $playlistDirectory = $this->publicationRoot . DIRECTORY_SEPARATOR . 'playlists';
            foreach ($playlistArtifacts as $filename => $json) {
                ($this->artifactWriter)($playlistDirectory . DIRECTORY_SEPARATOR . $filename, $json);
            }
            ($this->artifactWriter)($playlistDirectory . DIRECTORY_SEPARATOR . 'index.json', $indexJson);

            foreach ($changedFilenames as $filename) {
                $playlist = $state['playlists_by_filename'][$filename];
                ($this->timestampUpdater)(
                    (int) self::value($playlist, 'id'),
                    PublicationTimestamp::toDatabase($publicationTimestamp)
                );
                $updatedFilenames[] = $filename;
            }
            $this->undoService->promote($preparedUndo);
        } catch (Throwable $exception) {
            try {
                $this->undoService->rollbackPrepared($preparedUndo);
                foreach ($updatedFilenames as $filename) {
                    $playlist = $state['playlists_by_filename'][$filename];
                    ($this->timestampUpdater)(
                        (int) self::value($playlist, 'id'),
                        PublicationTimestamp::toDatabase($previousTimestamps[$filename])
                    );
                }
            } catch (Throwable $rollbackException) {
                throw new PublicationException(
                    'Playlist-content publication failed and the previous state could not be restored'
                );
            }
            throw $exception instanceof PublicationException
                ? $exception
                : new PublicationException('Playlist-content publication failed: ' . $exception->getMessage());
        }

        return [
            'lastupdated' => $publicationTimestamp,
            'playlists' => $changedFilenames,
            'default_changed' => $defaultChanged,
            'no_op' => false,
        ];
    }

    private function publicationState(): array
    {
        $playlists = [];
        $playlistsByFilename = [];
        $showsByPlaylist = [];
        foreach (($this->playlistLoader)() as $playlist) {
            $filename = self::value($playlist, 'filename');
            if (!is_string($filename)) {
                throw new PublicationException('Playlist has an invalid filename', 409);
            }
            PlaylistPublicationService::validateFilename($filename);
            $playlists[] = $playlist;
            $playlistsByFilename[$filename] = $playlist;
            $playlistId = (int) self::value($playlist, 'id');
            $showsByPlaylist[$playlistId] = [];
            foreach (($this->showLoader)($playlistId) as $show) {
                $showsByPlaylist[$playlistId][] = $show;
            }
        }

        $status = (new PublicationStatusService(
            $this->publicationRoot,
            static fn() => $playlists,
            static fn(int $playlistId) => $showsByPlaylist[$playlistId] ?? [],
            static fn() => []
        ))->status();

        $changedFilenames = [];
        foreach ($status['playlists'] as $playlistStatus) {
            if ($playlistStatus['error'] !== null) {
                throw new PublicationException(
                    $playlistStatus['filename'] . ': ' . $playlistStatus['error'],
                    409
                );
            }
            if ($playlistStatus['changed'] === true) {
                $changedFilenames[] = $playlistStatus['filename'];
            }
        }
        if ($status['default_playlist']['error'] !== null) {
            throw new PublicationException($status['default_playlist']['error'], 409);
        }

        return [
            'playlists' => $playlists,
            'playlists_by_filename' => $playlistsByFilename,
            'shows_by_playlist' => $showsByPlaylist,
            'changed_filenames' => $changedFilenames,
            'default_changed' => $status['default_playlist']['changed'] === true,
        ];
    }

    private function loadPublishedTimestamps(): array
    {
        $path = $this->publicationRoot . '/playlists/index.json';
        try {
            $index = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PublicationException('Existing published playlist index is invalid JSON', 409);
        }

        $timestamps = [];
        foreach ($index['playlists'] as $entry) {
            $timestamps[$entry['filename']] = $entry['lastupdated'];
        }
        return $timestamps;
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
                "Could not encode {$artifactName} artifact: " . $exception->getMessage()
            );
        }
    }

    private function safeWrite(string $destination, string $contents): void
    {
        $temporaryPath = tempnam(dirname($destination), '.publish-all-');
        if ($temporaryPath === false) {
            throw new PublicationException('Could not create temporary publication file');
        }
        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)
                || !chmod($temporaryPath, 0644)
                || !rename($temporaryPath, $destination)) {
                throw new PublicationException('Could not replace publication artifact');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function noOpResult(): array
    {
        return [
            'lastupdated' => null,
            'playlists' => [],
            'default_changed' => false,
            'no_op' => true,
        ];
    }

    private static function artifactObject(array $artifact): object
    {
        return json_decode(json_encode($artifact, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
