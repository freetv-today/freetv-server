<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';

use DateTimeInterface;
use InvalidArgumentException;

class PlaylistIndexSerializer
{
    public static function serialize(
        iterable $playlists,
        string $selectedFilename,
        DateTimeInterface|string $publicationTimestamp,
        array $publishedTimestamps
    ): array {
        return self::serializeChanged(
            $playlists,
            [$selectedFilename],
            $publicationTimestamp,
            $publishedTimestamps
        );
    }

    public static function serializeChanged(
        iterable $playlists,
        array $changedFilenames,
        DateTimeInterface|string $publicationTimestamp,
        array $publishedTimestamps
    ): array {
        $orderedPlaylists = [];
        foreach ($playlists as $playlist) {
            $orderedPlaylists[] = $playlist;
        }

        $defaultFilename = self::validateDefault($orderedPlaylists);

        usort($orderedPlaylists, static function (array|object $left, array|object $right): int {
            $sortOrderComparison = (int) self::value($left, 'sort_order')
                <=> (int) self::value($right, 'sort_order');

            return $sortOrderComparison !== 0
                ? $sortOrderComparison
                : (int) self::value($left, 'id') <=> (int) self::value($right, 'id');
        });

        $canonicalPublicationTimestamp = PublicationTimestamp::format($publicationTimestamp);
        $changed = array_fill_keys($changedFilenames, true);
        $entries = [];
        foreach ($orderedPlaylists as $playlist) {
            $filename = self::value($playlist, 'filename');
            $entry = [
                'filename' => $filename,
                'dbtitle' => self::value($playlist, 'dbtitle'),
                'lastupdated' => array_key_exists($filename, $changed)
                    ? $canonicalPublicationTimestamp
                    : self::publishedTimestamp($filename, $publishedTimestamps),
            ];

            $author = self::value($playlist, 'author');
            if ($author !== null) {
                $entry['author'] = $author;
            }

            $entries[] = $entry;
        }

        return [
            'default' => $defaultFilename,
            'playlists' => $entries,
        ];
    }

    public static function validateDefault(iterable $playlists): string
    {
        $defaults = [];
        foreach ($playlists as $playlist) {
            if ((int) self::value($playlist, 'is_default') === 1) {
                $defaults[] = $playlist;
            }
        }

        if (count($defaults) !== 1) {
            throw new PublicationException(
                'Publication requires exactly one default playlist; found ' . count($defaults),
                409
            );
        }

        return self::value($defaults[0], 'filename');
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }

    private static function publishedTimestamp(mixed $filename, array $publishedTimestamps): string
    {
        if (!is_string($filename) || !array_key_exists($filename, $publishedTimestamps)) {
            throw new PublicationException(
                'Existing published index is missing playlist ' . (string) $filename,
                409
            );
        }

        $timestamp = $publishedTimestamps[$filename];
        if (!is_string($timestamp)) {
            throw new PublicationException(
                'Existing published index has an invalid lastupdated for playlist ' . $filename,
                409
            );
        }

        try {
            if (PublicationTimestamp::format($timestamp) !== $timestamp) {
                throw new InvalidArgumentException('Timestamp is not canonical');
            }
        } catch (InvalidArgumentException $exception) {
            throw new PublicationException(
                'Existing published index has an invalid lastupdated for playlist ' . $filename,
                409
            );
        }

        return $timestamp;
    }
}
