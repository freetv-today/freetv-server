<?php

namespace FreeTV\Admin\DataSnapshot;

require_once __DIR__ . '/publication/PublicationTimestamp.php';

use FreeTV\Admin\Publication\PublicationTimestamp;
use InvalidArgumentException;
use JsonException;

class DataSnapshotManifest
{
    public static function fromJson(string $json): array
    {
        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Official dataset manifest is not valid JSON', 0, $exception);
        }

        if (!is_array($manifest)) {
            throw new InvalidArgumentException('Official dataset manifest must be an object');
        }

        $formatVersion = self::requiredInteger($manifest, 'format_version');
        if ($formatVersion < 1) {
            throw new InvalidArgumentException('Official dataset format_version must be at least 1');
        }

        $productionSnapshotAt = self::requiredTimestamp($manifest, 'production_snapshot_at');
        $generatedAt = self::requiredTimestamp($manifest, 'generated_at');

        if (!isset($manifest['counts']) || !is_array($manifest['counts'])) {
            throw new InvalidArgumentException('Official dataset manifest counts must be an object');
        }

        return [
            'format_version' => $formatVersion,
            'production_snapshot_at' => $productionSnapshotAt,
            'generated_at' => $generatedAt,
            'counts' => [
                'playlists' => self::requiredCount($manifest['counts'], 'playlists'),
                'shows' => self::requiredCount($manifest['counts'], 'shows'),
                'thumbnails' => self::requiredCount($manifest['counts'], 'thumbnails'),
            ],
        ];
    }

    private static function requiredInteger(array $values, string $key): int
    {
        if (!array_key_exists($key, $values) || !is_int($values[$key])) {
            throw new InvalidArgumentException("Official dataset manifest {$key} must be an integer");
        }

        return $values[$key];
    }

    private static function requiredCount(array $counts, string $key): int
    {
        $count = self::requiredInteger($counts, $key);
        if ($count < 0) {
            throw new InvalidArgumentException("Official dataset manifest counts.{$key} must not be negative");
        }

        return $count;
    }

    private static function requiredTimestamp(array $manifest, string $key): string
    {
        if (!array_key_exists($key, $manifest) || !is_string($manifest[$key])) {
            throw new InvalidArgumentException("Official dataset manifest {$key} must be a timestamp");
        }

        try {
            return PublicationTimestamp::format($manifest[$key]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                "Official dataset manifest {$key} is invalid",
                0,
                $exception
            );
        }
    }
}
