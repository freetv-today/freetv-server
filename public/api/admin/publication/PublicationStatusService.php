<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationSemanticHasher.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/ConfigPublicationSerializer.php';

use FreeTV\Admin\Database;
use FreeTV\Admin\Settings;
use JsonException;

class PublicationStatusService
{
    private const COMPARISON_TIMESTAMP = '2000-01-01T00:00:00.000Z';

    private string $publicationRoot;
    private $playlistLoader;
    private $showLoader;
    private $settingsLoader;

    public function __construct(
        ?string $publicationRoot = null,
        ?callable $playlistLoader = null,
        ?callable $showLoader = null,
        ?callable $settingsLoader = null
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
        $this->settingsLoader = $settingsLoader ?? static fn() => Settings::readPublishable();
    }

    public function status(): array
    {
        $playlists = [];
        foreach (($this->playlistLoader)() as $playlist) {
            $playlists[] = $playlist;
        }

        $playlistStatuses = [];
        foreach ($playlists as $playlist) {
            $filename = self::value($playlist, 'filename');
            $authoritative = PlaylistPublicationSerializer::serialize(
                $playlist,
                ($this->showLoader)((int) self::value($playlist, 'id')),
                self::COMPARISON_TIMESTAMP
            );
            $playlistStatuses[] = array_merge(
                [
                    'filename' => $filename,
                    'dbtitle' => self::value($playlist, 'dbtitle'),
                ],
                $this->compareArtifact(
                    $authoritative,
                    $this->publicationRoot . '/playlists/' . $filename,
                    'Published playlist'
                )
            );
        }

        $config = ConfigPublicationSerializer::serialize(
            ($this->settingsLoader)(),
            self::COMPARISON_TIMESTAMP
        );

        return [
            'playlists' => $playlistStatuses,
            'config' => $this->compareArtifact(
                $config,
                $this->publicationRoot . '/config.json',
                'Published config'
            ),
            'default_playlist' => $this->defaultPlaylistStatus($playlists),
        ];
    }

    private function compareArtifact(array $authoritative, string $path, string $label): array
    {
        if (!is_file($path)) {
            return ['changed' => true, 'error' => null];
        }

        try {
            $published = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['changed' => null, 'error' => $label . ' contains invalid JSON'];
        }
        if (!is_object($published)) {
            return ['changed' => null, 'error' => $label . ' is malformed'];
        }

        return [
            'changed' => PublicationSemanticHasher::hash($authoritative)
                !== PublicationSemanticHasher::hash($published),
            'error' => null,
        ];
    }

    private function defaultPlaylistStatus(array $playlists): array
    {
        $defaults = array_values(array_filter(
            $playlists,
            static fn(array|object $playlist): bool => (int) self::value($playlist, 'is_default') === 1
        ));
        if (count($defaults) !== 1) {
            return [
                'changed' => null,
                'database' => null,
                'published' => null,
                'error' => 'MariaDB must contain exactly one default playlist',
            ];
        }

        $databaseDefault = self::value($defaults[0], 'filename');
        $path = $this->publicationRoot . '/playlists/index.json';
        if (!is_file($path)) {
            return [
                'changed' => null,
                'database' => $databaseDefault,
                'published' => null,
                'error' => 'Published playlist index is missing',
            ];
        }

        try {
            $index = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'changed' => null,
                'database' => $databaseDefault,
                'published' => null,
                'error' => 'Published playlist index contains invalid JSON',
            ];
        }
        if (!is_object($index)
            || !property_exists($index, 'default')
            || !is_string($index->default)
            || $index->default === ''
            || !property_exists($index, 'playlists')
            || !is_array($index->playlists)
            || !$this->hasValidIndexEntries($index->playlists)) {
            return [
                'changed' => null,
                'database' => $databaseDefault,
                'published' => null,
                'error' => 'Published playlist index is malformed',
            ];
        }

        return [
            'changed' => $databaseDefault !== $index->default,
            'database' => $databaseDefault,
            'published' => $index->default,
            'error' => null,
        ];
    }

    private function hasValidIndexEntries(array $entries): bool
    {
        $filenames = [];
        foreach ($entries as $entry) {
            if (!is_object($entry)
                || !property_exists($entry, 'filename')
                || !is_string($entry->filename)
                || $entry->filename === ''
                || array_key_exists($entry->filename, $filenames)) {
                return false;
            }
            $filenames[$entry->filename] = true;
        }
        return true;
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
