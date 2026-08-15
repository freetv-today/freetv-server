<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationSemanticHasher.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/ConfigPublicationSerializer.php';

use FreeTV\Admin\Database;
use FreeTV\Admin\Settings;
use InvalidArgumentException;
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
                    'Published playlist',
                    'playlist'
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
                'Published config',
                'config'
            ),
            'default_playlist' => $this->defaultPlaylistStatus($playlists),
        ];
    }

    private function compareArtifact(
        array $authoritative,
        string $path,
        string $label,
        string $contract
    ): array {
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

        $contractError = $contract === 'playlist'
            ? $this->playlistContractError($published, $authoritative)
            : $this->configContractError($published, $authoritative);
        if ($contractError !== null) {
            return ['changed' => null, 'error' => $label . ' ' . $contractError];
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
            || !$this->hasValidIndexEntries($index->playlists)
            || $this->countIndexFilename($index->playlists, $index->default) !== 1) {
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
                || !property_exists($entry, 'dbtitle')
                || !is_string($entry->dbtitle)
                || $entry->dbtitle === ''
                || !property_exists($entry, 'lastupdated')
                || !$this->isCanonicalTimestamp($entry->lastupdated)
                || (property_exists($entry, 'author') && !is_string($entry->author))
                || array_diff(array_keys(get_object_vars($entry)), [
                    'filename',
                    'dbtitle',
                    'lastupdated',
                    'author',
                ]) !== []
                || array_key_exists($entry->filename, $filenames)) {
                return false;
            }
            $filenames[$entry->filename] = true;
        }
        return true;
    }

    private function playlistContractError(object $published, array $authoritative): ?string
    {
        $fields = get_object_vars($published);
        $expectedFields = array_keys($authoritative);
        foreach ($expectedFields as $field) {
            if (!property_exists($published, $field)) {
                return "is missing required field {$field}";
            }
        }
        if (array_diff(array_keys($fields), $expectedFields) !== []) {
            return 'contains fields outside the playlist contract';
        }
        if (!$this->isCanonicalTimestamp($published->lastupdated)) {
            return 'has an invalid lastupdated';
        }
        if (!is_string($published->dbtitle) || !is_string($published->filename)) {
            return 'has an invalid dbtitle or filename';
        }
        foreach (['dbversion', 'author', 'email', 'link'] as $field) {
            if ($published->{$field} !== null && !is_string($published->{$field})) {
                return "has an invalid {$field}";
            }
        }
        if (!is_array($published->shows)) {
            return 'has an invalid shows array';
        }
        foreach ($published->shows as $show) {
            $showError = $this->showContractError($show);
            if ($showError !== null) {
                return $showError;
            }
        }
        return null;
    }

    private function showContractError(mixed $show): ?string
    {
        if (!is_object($show)) {
            return 'contains a malformed show entry';
        }

        $requiredFields = ['category', 'status', 'identifier', 'title', 'desc', 'start', 'end', 'imdb'];
        foreach ($requiredFields as $field) {
            if (!property_exists($show, $field)) {
                return "contains a show missing required field {$field}";
            }
        }
        $allowedFields = array_merge($requiredFields, ['group']);
        if (array_diff(array_keys(get_object_vars($show)), $allowedFields) !== []) {
            return 'contains fields outside the show contract';
        }
        foreach (['status', 'identifier', 'title'] as $field) {
            if (!is_string($show->{$field})) {
                return "contains a show with invalid {$field}";
            }
        }
        foreach (['category', 'desc', 'start', 'end', 'imdb'] as $field) {
            if ($show->{$field} !== null && !is_string($show->{$field})) {
                return "contains a show with invalid {$field}";
            }
        }
        if (property_exists($show, 'group')
            && (!is_string($show->group) || trim($show->group) === '' || trim($show->group) !== $show->group)) {
            return 'contains a show with invalid group';
        }
        return null;
    }

    private function configContractError(object $published, array $authoritative): ?string
    {
        $expectedFields = array_keys($authoritative);
        $publishedFields = array_keys(get_object_vars($published));
        foreach ($expectedFields as $field) {
            if (!property_exists($published, $field)) {
                return "is missing required field {$field}";
            }
        }
        if (array_diff($publishedFields, $expectedFields) !== []) {
            return 'contains fields outside the config contract';
        }
        if (!$this->isCanonicalTimestamp($published->lastupdated)) {
            return 'has an invalid lastupdated';
        }
        foreach ($authoritative as $field => $expectedValue) {
            if ($field !== 'lastupdated' && gettype($published->{$field}) !== gettype($expectedValue)) {
                return "has an invalid {$field}";
            }
        }
        return null;
    }

    private function countIndexFilename(array $entries, string $filename): int
    {
        return count(array_filter(
            $entries,
            static fn(object $entry): bool => $entry->filename === $filename
        ));
    }

    private function isCanonicalTimestamp(mixed $timestamp): bool
    {
        if (!is_string($timestamp)) {
            return false;
        }
        try {
            return PublicationTimestamp::format($timestamp) === $timestamp;
        } catch (InvalidArgumentException $exception) {
            return false;
        }
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
