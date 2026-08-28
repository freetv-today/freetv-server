<?php

namespace FreeTV\Admin\DataSnapshot;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DataSnapshotManifest.php';
require_once __DIR__ . '/publication/PublicationTimestamp.php';

use FreeTV\Admin\Database;
use FreeTV\Admin\Publication\PublicationTimestamp;
use RuntimeException;

class DataSnapshotSourceException extends RuntimeException
{
}

class DataSnapshotStatusService
{
    public const MANIFEST_URL =
        'https://raw.githubusercontent.com/freetv-today/freetv-data/refs/heads/main/manifest.json';

    private $manifestFetcher;
    private $tableResolver;

    public function __construct(?callable $manifestFetcher = null, ?callable $tableResolver = null)
    {
        $this->manifestFetcher = $manifestFetcher ?? static fn(): string => self::fetchManifest();
        $this->tableResolver = $tableResolver ?? static fn(string $table) => Database::table($table);
    }

    public function status(): array
    {
        $manifestJson = ($this->manifestFetcher)();
        if (!is_string($manifestJson)) {
            throw new DataSnapshotSourceException('Official dataset manifest response was invalid');
        }

        $manifest = DataSnapshotManifest::fromJson($manifestJson);
        $snapshotAt = PublicationTimestamp::toDatabase($manifest['production_snapshot_at']);

        $playlists = $this->tableStatus('playlists', $snapshotAt);
        $shows = $this->tableStatus('playlist_shows', $snapshotAt);

        return [
            'official_dataset' => $manifest,
            'production' => [
                'counts' => [
                    'playlists' => $playlists['count'],
                    'shows' => $shows['count'],
                ],
            ],
            'changes_since_snapshot' => [
                'playlists' => [
                    'new' => $playlists['new'],
                    'updated' => $playlists['updated'],
                ],
                'shows' => [
                    'new' => $shows['new'],
                    'updated' => $shows['updated'],
                ],
            ],
        ];
    }

    private function tableStatus(string $table, string $snapshotAt): array
    {
        $query = ($this->tableResolver)($table);

        return [
            'count' => (int) (clone $query)->count(),
            'new' => (int) (clone $query)
                ->where('created_at', '>', $snapshotAt)
                ->count(),
            'updated' => (int) (clone $query)
                ->where('updated_at', '>', $snapshotAt)
                ->where('created_at', '<=', $snapshotAt)
                ->count(),
        ];
    }

    private static function fetchManifest(): string
    {
        $curl = curl_init(self::MANIFEST_URL);
        if ($curl === false) {
            throw new DataSnapshotSourceException('Could not initialize the official dataset request');
        }

        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'FreeTV-Admin-Data-Snapshot/1.0',
        ]);

        $contents = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($contents === false) {
            throw new DataSnapshotSourceException(
                $curlError !== ''
                    ? "Could not fetch the official dataset manifest: {$curlError}"
                    : 'Could not fetch the official dataset manifest'
            );
        }
        if ($httpStatus !== 200) {
            throw new DataSnapshotSourceException("Official dataset manifest returned HTTP {$httpStatus}");
        }
        if ($contents === '') {
            throw new DataSnapshotSourceException('Official dataset manifest response was empty');
        }

        return $contents;
    }
}
