<?php

namespace FreeTV\Admin;

class ThumbnailService
{
    private string $thumbnailDirectory;

    public function __construct(?string $thumbnailDirectory = null)
    {
        $this->thumbnailDirectory = $thumbnailDirectory ?? dirname(__DIR__, 2) . '/thumbs';
    }

    public static function isValidImdb($imdb): bool
    {
        return is_string($imdb) && preg_match('/^tt\d+$/', $imdb) === 1;
    }

    public static function calculateSummary(
        int $showCount,
        int $distinctThumbnailNeeds,
        int $existingThumbnailCount
    ): array {
        return [
            'number_of_shows' => $showCount,
            'number_of_thumbnails' => $existingThumbnailCount,
            'missing_thumbnails' => $distinctThumbnailNeeds - $existingThumbnailCount,
            'shared_thumbnails' => $showCount - $distinctThumbnailNeeds,
        ];
    }

    public function getPlaylistOverview(string $filename): ?array
    {
        $playlist = $this->resolvePlaylist($filename);
        if ($playlist === null) {
            return null;
        }

        $rows = Database::table('playlist_shows')
            ->where('playlist_id', $playlist->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'identifier', 'title', 'imdb']);

        $groups = $this->groupUsableShows($rows);
        $globalUsage = $this->getGlobalUsageFor(array_keys($groups));
        $existing = [];
        $missing = [];
        $shared = [];
        $showCount = 0;

        foreach ($groups as $imdb => $group) {
            $selectedPlaylistShowCount = count($group['shows']);
            $showCount += $selectedPlaylistShowCount;
            $item = $this->makeItem(
                $group['shows'][0],
                $group['has_thumbnail'],
                $selectedPlaylistShowCount,
                $globalUsage[$imdb] ?? ['show_count' => 0, 'playlist_count' => 0]
            );

            if ($group['has_thumbnail']) {
                $existing[] = $item;
            } else {
                $missing[] = $item;
            }

            if ($selectedPlaylistShowCount > 1) {
                $item['shows'] = $group['shows'];
                $shared[] = $item;
            }
        }

        $distinctThumbnailNeeds = count($groups);
        $summary = self::calculateSummary(
            $showCount,
            $distinctThumbnailNeeds,
            count($existing)
        );

        return [
            'playlist' => [
                'filename' => $playlist->filename,
                'title' => $playlist->dbtitle,
            ],
            'summary' => $summary,
            'existing' => $existing,
            'missing' => $missing,
            'shared' => $shared,
        ];
    }

    public function searchPlaylist(string $filename, string $searchTerm): ?array
    {
        $playlist = $this->resolvePlaylist($filename);
        if ($playlist === null) {
            return null;
        }

        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return [
                'playlist' => [
                    'filename' => $playlist->filename,
                    'title' => $playlist->dbtitle,
                ],
                'query' => '',
                'results' => [],
            ];
        }

        $likeTerm = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $searchTerm
        ) . '%';

        $rows = Database::table('playlist_shows')
            ->where('playlist_id', $playlist->id)
            ->where(function ($query) use ($likeTerm): void {
                $query->where('title', 'like', $likeTerm)
                    ->orWhere('imdb', 'like', $likeTerm);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'identifier', 'title', 'imdb']);

        $groups = $this->groupUsableShows($rows);
        $imdbs = array_keys($groups);
        $selectedUsage = $this->getSelectedPlaylistUsage($playlist->id, $imdbs);
        $globalUsage = $this->getGlobalUsageFor($imdbs);
        $results = [];

        foreach ($groups as $imdb => $group) {
            $results[] = $this->makeItem(
                $group['shows'][0],
                $group['has_thumbnail'],
                $selectedUsage[$imdb] ?? count($group['shows']),
                $globalUsage[$imdb] ?? ['show_count' => 0, 'playlist_count' => 0]
            );
        }

        return [
            'playlist' => [
                'filename' => $playlist->filename,
                'title' => $playlist->dbtitle,
            ],
            'query' => $searchTerm,
            'results' => $results,
        ];
    }

    public function thumbnailExists(string $imdb): bool
    {
        if (!self::isValidImdb($imdb)) {
            throw new \InvalidArgumentException('Invalid IMDb ID');
        }

        return is_file($this->thumbnailDirectory . '/' . $imdb . '.jpg');
    }

    public function getGlobalUsage(string $imdb): array
    {
        if (!self::isValidImdb($imdb)) {
            throw new \InvalidArgumentException('Invalid IMDb ID');
        }

        $usage = $this->getGlobalUsageFor([$imdb]);
        return $usage[$imdb] ?? ['show_count' => 0, 'playlist_count' => 0];
    }

    public function getStatus(string $imdb): array
    {
        if (!self::isValidImdb($imdb)) {
            throw new \InvalidArgumentException('Invalid IMDb ID');
        }

        $exists = $this->thumbnailExists($imdb);
        $thumbnailUrl = null;

        if ($exists) {
            $path = $this->thumbnailDirectory . '/' . $imdb . '.jpg';
            $fingerprint = @hash_file('sha256', $path);
            $thumbnailUrl = '/thumbs/' . $imdb . '.jpg';
            if ($fingerprint !== false) {
                $thumbnailUrl .= '?v=' . substr($fingerprint, 0, 12);
            }
        }

        return [
            'imdb' => $imdb,
            'exists' => $exists,
            'thumbnail_url' => $thumbnailUrl,
            'global_usage' => $this->getGlobalUsage($imdb),
        ];
    }

    private function resolvePlaylist(string $filename)
    {
        return Database::table('playlists')
            ->where('filename', $filename)
            ->first(['id', 'filename', 'dbtitle']);
    }

    private function groupUsableShows(iterable $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $imdb = is_string($row->imdb) ? trim($row->imdb) : '';
            if (!self::isValidImdb($imdb)) {
                continue;
            }

            if (!isset($groups[$imdb])) {
                $groups[$imdb] = [
                    'has_thumbnail' => $this->thumbnailExists($imdb),
                    'shows' => [],
                ];
            }

            $groups[$imdb]['shows'][] = [
                'identifier' => $row->identifier,
                'title' => $row->title,
                'imdb' => $imdb,
            ];
        }

        return $groups;
    }

    private function makeItem(
        array $representative,
        bool $hasThumbnail,
        int $selectedPlaylistShowCount,
        array $globalUsage
    ): array {
        return [
            'identifier' => $representative['identifier'],
            'title' => $representative['title'],
            'imdb' => $representative['imdb'],
            'has_thumbnail' => $hasThumbnail,
            'selected_playlist_show_count' => $selectedPlaylistShowCount,
            'global_usage' => $globalUsage,
        ];
    }

    private function getSelectedPlaylistUsage(int $playlistId, array $imdbs): array
    {
        if ($imdbs === []) {
            return [];
        }

        $rows = Database::table('playlist_shows')
            ->where('playlist_id', $playlistId)
            ->whereIn('imdb', $imdbs)
            ->select('imdb')
            ->selectRaw('COUNT(*) AS show_count')
            ->groupBy('imdb')
            ->get();

        $usage = [];
        foreach ($rows as $row) {
            $usage[trim($row->imdb)] = (int) $row->show_count;
        }

        return $usage;
    }

    private function getGlobalUsageFor(array $imdbs): array
    {
        if ($imdbs === []) {
            return [];
        }

        $rows = Database::table('playlist_shows')
            ->whereIn('imdb', $imdbs)
            ->select('imdb')
            ->selectRaw('COUNT(*) AS show_count')
            ->selectRaw('COUNT(DISTINCT playlist_id) AS playlist_count')
            ->groupBy('imdb')
            ->get();

        $usage = [];
        foreach ($rows as $row) {
            $usage[trim($row->imdb)] = [
                'show_count' => (int) $row->show_count,
                'playlist_count' => (int) $row->playlist_count,
            ];
        }

        return $usage;
    }
}
