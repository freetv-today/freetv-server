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
        if ($formatVersion !== 1) {
            throw new InvalidArgumentException('Official dataset format_version is unsupported');
        }

        $generatedAt = self::requiredTimestamp($manifest, 'generated_at');

        if (!isset($manifest['reconciled_snapshot']) || !is_array($manifest['reconciled_snapshot'])) {
            throw new InvalidArgumentException('Official dataset manifest reconciled_snapshot must be an object');
        }

        $reconciledSnapshot = $manifest['reconciled_snapshot'];
        $snapshotName = self::requiredNonEmptyString($reconciledSnapshot, 'name', 'reconciled_snapshot.name');
        $capturedAt = self::requiredTimestamp(
            $reconciledSnapshot,
            'captured_at',
            'reconciled_snapshot.captured_at'
        );

        if (!isset($manifest['counts']) || !is_array($manifest['counts'])) {
            throw new InvalidArgumentException('Official dataset manifest counts must be an object');
        }

        return [
            'format_version' => $formatVersion,
            'generated_at' => $generatedAt,
            'reconciled_snapshot' => [
                'name' => $snapshotName,
                'captured_at' => $capturedAt,
            ],
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

    private static function requiredNonEmptyString(array $values, string $key, string $path): string
    {
        if (!array_key_exists($key, $values)
            || !is_string($values[$key])
            || trim($values[$key]) === '') {
            throw new InvalidArgumentException("Official dataset manifest {$path} must be a non-empty string");
        }

        return $values[$key];
    }

    private static function requiredTimestamp(array $manifest, string $key, ?string $path = null): string
    {
        $path ??= $key;
        if (!array_key_exists($key, $manifest) || !is_string($manifest[$key])) {
            throw new InvalidArgumentException("Official dataset manifest {$path} must be a timestamp");
        }

        try {
            return PublicationTimestamp::format($manifest[$key]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                "Official dataset manifest {$path} is invalid",
                0,
                $exception
            );
        }
    }
}
