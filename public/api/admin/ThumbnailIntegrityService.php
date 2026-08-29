<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ServerPaths.php';
require_once __DIR__ . '/ThumbnailService.php';

use RuntimeException;

final class ThumbnailIntegrityService
{
    private string $thumbnailDirectory;
    private $databaseLoader;

    public function __construct(
        ?string $thumbnailDirectory = null,
        ?callable $databaseLoader = null
    ) {
        $this->thumbnailDirectory = $thumbnailDirectory
            ?? (new ServerPaths())->publicRoot() . '/thumbs';
        $this->databaseLoader = $databaseLoader ?? static fn(): array => Database::table('playlist_shows as shows')
            ->leftJoin('playlists as playlists', 'playlists.id', '=', 'shows.playlist_id')
            ->get([
                'shows.id',
                'shows.playlist_id',
                'shows.identifier',
                'shows.title',
                'shows.category',
                'shows.imdb',
                'playlists.filename as playlist_filename',
                'playlists.dbtitle as playlist_title',
            ])
            ->map(static fn(object $row): array => (array) $row)
            ->all();
    }

    public function audit(): array
    {
        $rows = ($this->databaseLoader)();
        if (!is_iterable($rows)) {
            throw new RuntimeException('Thumbnail integrity database loader must return iterable rows');
        }

        $validGroups = [];
        $invalidGroups = [];
        foreach ($rows as $row) {
            if (!is_array($row) && !is_object($row)) {
                throw new RuntimeException('Thumbnail integrity database rows must be arrays or objects');
            }

            $rawImdb = $this->value($row, 'imdb');
            if ($rawImdb === null || trim((string) $rawImdb) === '') {
                continue;
            }

            $imdb = trim((string) $rawImdb);
            if (ThumbnailService::isValidImdb($imdb)) {
                $validGroups[$imdb][] = $row;
            } else {
                $invalidGroups[$imdb][] = $row;
            }
        }
        ksort($validGroups, SORT_STRING);
        ksort($invalidGroups, SORT_STRING);

        $thumbnailFiles = $this->thumbnailFiles();
        $thumbnailSet = array_fill_keys($thumbnailFiles, true);
        $present = [];
        $missing = [];
        foreach ($validGroups as $imdb => $groupRows) {
            $filename = $imdb . '.jpg';
            $item = [
                'imdb' => $imdb,
                'filename' => $filename,
                'usage' => $this->usage($groupRows),
            ];
            if (isset($thumbnailSet[$filename])) {
                $present[] = $item;
            } else {
                $item['representative_show'] = $this->representativeShow($groupRows);
                $missing[] = $item;
            }
        }

        $expectedSet = array_fill_keys(array_keys($validGroups), true);
        $orphaned = [];
        foreach ($thumbnailFiles as $filename) {
            $imdb = substr($filename, 0, -4);
            if (!isset($expectedSet[$imdb])) {
                $orphaned[] = ['imdb' => $imdb, 'filename' => $filename];
            }
        }

        $invalidDatabaseImdb = [];
        $invalidRowCount = 0;
        foreach ($invalidGroups as $value => $groupRows) {
            $usage = $this->usage($groupRows);
            $invalidRowCount += $usage['show_count'];
            $invalidDatabaseImdb[] = [
                'value' => $value,
                'usage' => $usage,
                'representative_show' => $this->representativeShow($groupRows),
            ];
        }

        return [
            'summary' => [
                'total_distinct_valid_imdb_references' => count($validGroups),
                'total_thumbnail_jpg_files_considered' => count($thumbnailFiles),
                'present' => count($present),
                'missing' => count($missing),
                'orphaned' => count($orphaned),
                'invalid_database_imdb_values' => count($invalidDatabaseImdb),
                'invalid_database_imdb_rows' => $invalidRowCount,
            ],
            'present' => $present,
            'missing' => $missing,
            'orphaned' => $orphaned,
            'invalid_database_imdb' => $invalidDatabaseImdb,
        ];
    }

    private function thumbnailFiles(): array
    {
        if (!is_dir($this->thumbnailDirectory)
            || is_link($this->thumbnailDirectory)
            || !is_readable($this->thumbnailDirectory)
        ) {
            throw new RuntimeException('Thumbnail directory is unavailable');
        }

        $entries = scandir($this->thumbnailDirectory);
        if ($entries === false) {
            throw new RuntimeException('Could not read thumbnail directory');
        }

        $files = [];
        foreach ($entries as $filename) {
            if (preg_match('/^tt\d+\.jpg$/', $filename) !== 1) {
                continue;
            }
            $path = $this->thumbnailDirectory . '/' . $filename;
            if (!is_link($path) && is_file($path)) {
                $files[] = $filename;
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function usage(array $rows): array
    {
        $playlists = [];
        foreach ($rows as $row) {
            $playlistId = $this->value($row, 'playlist_id');
            $playlistKey = $playlistId === null
                ? 'filename:' . (string) $this->value($row, 'playlist_filename')
                : 'id:' . (string) $playlistId;
            $playlists[$playlistKey] = true;
        }
        return [
            'show_count' => count($rows),
            'playlist_count' => count($playlists),
        ];
    }

    private function representativeShow(array $rows): array
    {
        usort($rows, function ($left, $right): int {
            foreach (['playlist_filename', 'identifier', 'id'] as $field) {
                $comparison = strcmp(
                    (string) $this->value($left, $field),
                    (string) $this->value($right, $field)
                );
                if ($comparison !== 0) {
                    return $comparison;
                }
            }
            return 0;
        });
        $row = $rows[0];
        return [
            'playlist_filename' => $this->value($row, 'playlist_filename'),
            'playlist_title' => $this->value($row, 'playlist_title'),
            'identifier' => $this->value($row, 'identifier'),
            'title' => $this->value($row, 'title'),
            'category' => $this->value($row, 'category'),
        ];
    }

    private function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
