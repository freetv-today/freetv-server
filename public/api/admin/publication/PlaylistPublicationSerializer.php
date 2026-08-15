<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationTimestamp.php';

use DateTimeInterface;
use FreeTV\Admin\Database;
use RuntimeException;

class PlaylistPublicationSerializer
{
    /**
     * Build a Viewer-compatible playlist artifact from the authoritative database.
     *
     * This method only reads data and returns the artifact as an array. It does not
     * write files or derive its own publication timestamp.
     */
    public static function buildFromDatabase(
        string $filename,
        DateTimeInterface|string $publicationTimestamp
    ): array {
        $playlist = Database::table('playlists')
            ->where('filename', $filename)
            ->first([
                'id',
                'dbtitle',
                'filename',
                'dbversion',
                'author',
                'email',
                'link',
            ]);

        if ($playlist === null) {
            throw new RuntimeException('Playlist not found');
        }

        $shows = Database::table('playlist_shows')
            ->where('playlist_id', $playlist->id)
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

        return self::serialize($playlist, $shows, $publicationTimestamp);
    }

    /**
     * Serialize controlled playlist/show rows without requiring a database connection.
     */
    public static function serialize(
        array|object $playlist,
        iterable $shows,
        DateTimeInterface|string $publicationTimestamp
    ): array {
        $orderedShows = [];
        foreach ($shows as $show) {
            $orderedShows[] = $show;
        }

        usort($orderedShows, static function (array|object $left, array|object $right): int {
            $sortOrderComparison = (int) self::value($left, 'sort_order')
                <=> (int) self::value($right, 'sort_order');

            return $sortOrderComparison !== 0
                ? $sortOrderComparison
                : (int) self::value($left, 'id') <=> (int) self::value($right, 'id');
        });

        $serializedShows = [];
        foreach ($orderedShows as $show) {
            $serializedShow = [
                'category' => self::value($show, 'category'),
                'status' => self::value($show, 'status'),
                'identifier' => self::value($show, 'identifier'),
                'title' => self::value($show, 'title'),
                'desc' => self::value($show, 'description'),
                'start' => self::value($show, 'start_year'),
                'end' => self::value($show, 'end_year'),
                'imdb' => self::value($show, 'imdb'),
            ];

            $group = self::value($show, 'group_name');
            if (is_string($group) && trim($group) !== '') {
                $serializedShow['group'] = trim($group);
            }

            $serializedShows[] = $serializedShow;
        }

        return [
            'lastupdated' => PublicationTimestamp::format($publicationTimestamp),
            'dbtitle' => self::value($playlist, 'dbtitle'),
            'dbversion' => self::value($playlist, 'dbversion'),
            'author' => self::value($playlist, 'author'),
            'email' => self::value($playlist, 'email'),
            'link' => self::value($playlist, 'link'),
            'shows' => $serializedShows,
        ];
    }

    private static function value(array|object $row, string $field): mixed
    {
        return is_array($row) ? ($row[$field] ?? null) : ($row->{$field} ?? null);
    }
}
