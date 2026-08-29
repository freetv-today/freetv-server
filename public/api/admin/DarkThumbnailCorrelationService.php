<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/ThumbnailIntegrityService.php';
require_once __DIR__ . '/ThumbnailService.php';

use JsonException;
use RuntimeException;

final class DarkThumbnailCorrelationException extends RuntimeException
{
}

final class DarkThumbnailCorrelationService
{
    private $auditLoader;

    public function __construct(?callable $auditLoader = null)
    {
        $this->auditLoader = $auditLoader
            ?? static fn(): array => (new ThumbnailIntegrityService())->audit();
    }

    public function correlate(string $resultsPath, string $sourcePlaylistDirectory): array
    {
        $evidence = $this->loadDarkEvidence($resultsPath);
        $mappedRecords = $this->mapEvidenceToSourcePlaylists($evidence, $sourcePlaylistDirectory);
        $audit = ($this->auditLoader)();
        if (!is_array($audit)) {
            throw new DarkThumbnailCorrelationException('Thumbnail audit loader must return an array');
        }

        $orphans = $this->auditItems($audit, 'orphaned');
        $currentReferences = [];
        foreach (['present', 'missing'] as $category) {
            foreach ($this->auditItems($audit, $category) as $item) {
                $currentReferences[$item['imdb']] = true;
            }
        }

        $recordsByImdb = [];
        foreach ($mappedRecords as $record) {
            if ($record['imdb'] !== null) {
                $recordsByImdb[$record['imdb']][] = $record;
            }
        }
        ksort($recordsByImdb, SORT_STRING);

        $removedDarkShowMatches = [];
        $unexplainedOrphans = [];
        $matchedRecordKeys = [];
        foreach ($orphans as $orphan) {
            $records = $recordsByImdb[$orphan['imdb']] ?? [];
            if ($records === []) {
                $unexplainedOrphans[] = $orphan;
                continue;
            }
            foreach ($records as $record) {
                $matchedRecordKeys[$this->recordKey($record)] = true;
            }
            $removedDarkShowMatches[] = [
                'imdb' => $orphan['imdb'],
                'filename' => $orphan['filename'],
                'removed_records' => array_map([$this, 'publicRecord'], $records),
            ];
        }

        $withoutOrphanMatch = [];
        $withoutValidMapping = 0;
        foreach ($mappedRecords as $record) {
            if (isset($matchedRecordKeys[$this->recordKey($record)])) {
                continue;
            }
            if ($record['imdb'] === null) {
                $withoutValidMapping++;
                $reason = $record['mapping_issue'];
            } elseif (isset($currentReferences[$record['imdb']])) {
                $reason = 'currently_referenced';
            } else {
                $reason = 'no_orphan_thumbnail_file';
            }
            $withoutOrphanMatch[] = array_merge($this->publicRecord($record), ['reason' => $reason]);
        }

        $sharedRemovedImdb = [];
        foreach ($recordsByImdb as $imdb => $records) {
            if (count($records) > 1) {
                $sharedRemovedImdb[] = [
                    'imdb' => $imdb,
                    'removed_records' => array_map([$this, 'publicRecord'], $records),
                ];
            }
        }

        return [
            'summary' => [
                'current_orphan_thumbnails' => count($orphans),
                'removed_is_dark_records' => count($mappedRecords),
                'removed_is_dark_records_with_valid_imdb' => array_sum(array_map(
                    static fn(array $records): int => count($records),
                    $recordsByImdb
                )),
                'distinct_removed_is_dark_imdb' => count($recordsByImdb),
                'orphan_matches_to_removed_dark_shows' => count($removedDarkShowMatches),
                'removed_dark_records_matched_to_orphans' => count($matchedRecordKeys),
                'unexplained_orphans' => count($unexplainedOrphans),
                'removed_dark_records_without_orphan_match' => count($withoutOrphanMatch),
                'removed_dark_records_without_valid_imdb_mapping' => $withoutValidMapping,
                'shared_removed_imdb_values' => count($sharedRemovedImdb),
            ],
            'removed_dark_show_matches' => $removedDarkShowMatches,
            'unexplained_orphans' => $unexplainedOrphans,
            'removed_dark_records_without_orphan_match' => $withoutOrphanMatch,
            'shared_removed_imdb' => $sharedRemovedImdb,
        ];
    }

    private function loadDarkEvidence(string $resultsPath): array
    {
        $document = $this->readJsonFile($resultsPath, 'Cleanup results');
        if (!is_array($document) || !isset($document['results']) || !is_array($document['results'])) {
            throw new DarkThumbnailCorrelationException('Cleanup results JSON must contain a results array');
        }

        $recordsByKey = [];
        foreach ($document['results'] as $index => $record) {
            if (!is_array($record)
                || !isset($record['playlist'], $record['identifier'])
                || !is_string($record['playlist'])
                || !is_string($record['identifier'])
                || trim($record['playlist']) === ''
                || trim($record['identifier']) === ''
                || !array_key_exists('is_dark', $record)
                || !is_bool($record['is_dark'])
            ) {
                throw new DarkThumbnailCorrelationException(
                    'Cleanup results record ' . $index . ' is invalid'
                );
            }
            $normalized = [
                'playlist' => trim($record['playlist']),
                'identifier' => trim($record['identifier']),
                'is_dark' => $record['is_dark'],
            ];
            $key = $this->recordKey($normalized);
            if (isset($recordsByKey[$key])
                && $recordsByKey[$key]['is_dark'] !== $normalized['is_dark']
            ) {
                throw new DarkThumbnailCorrelationException(
                    'Cleanup results contain conflicting duplicate evidence for '
                    . $normalized['playlist'] . ' / ' . $normalized['identifier']
                );
            }
            $recordsByKey[$key] = $normalized;
        }

        $darkRecords = array_values(array_filter(
            $recordsByKey,
            static fn(array $record): bool => $record['is_dark']
        ));
        usort($darkRecords, [$this, 'compareRecords']);
        return $darkRecords;
    }

    private function mapEvidenceToSourcePlaylists(array $evidence, string $sourceDirectory): array
    {
        if (!is_dir($sourceDirectory) || is_link($sourceDirectory) || !is_readable($sourceDirectory)) {
            throw new DarkThumbnailCorrelationException('Source playlist directory is unavailable');
        }

        $indexes = [];
        $mapped = [];
        foreach ($evidence as $record) {
            $playlist = $record['playlist'];
            if (preg_match('/^[A-Za-z0-9_-]+\.json$/', $playlist) !== 1) {
                throw new DarkThumbnailCorrelationException(
                    'Cleanup results contain an unsafe playlist filename: ' . $playlist
                );
            }
            if (!isset($indexes[$playlist])) {
                $indexes[$playlist] = $this->sourcePlaylistIndex(
                    rtrim($sourceDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $playlist,
                    $playlist
                );
            }

            $show = $indexes[$playlist][$record['identifier']] ?? null;
            if ($show === null) {
                $mapped[] = [
                    'playlist' => $playlist,
                    'identifier' => $record['identifier'],
                    'title' => null,
                    'category' => null,
                    'imdb' => null,
                    'mapping_issue' => 'source_show_not_found',
                ];
                continue;
            }

            $imdb = isset($show['imdb']) ? trim((string) $show['imdb']) : '';
            $validImdb = ThumbnailService::isValidImdb($imdb);
            $mapped[] = [
                'playlist' => $playlist,
                'identifier' => $record['identifier'],
                'title' => isset($show['title']) ? (string) $show['title'] : null,
                'category' => isset($show['category']) ? (string) $show['category'] : null,
                'imdb' => $validImdb ? $imdb : null,
                'mapping_issue' => $validImdb ? null : 'invalid_or_missing_source_imdb',
            ];
        }
        usort($mapped, [$this, 'compareRecords']);
        return $mapped;
    }

    private function sourcePlaylistIndex(string $path, string $playlist): array
    {
        $document = $this->readJsonFile($path, 'Source playlist ' . $playlist);
        if (!is_array($document) || !isset($document['shows']) || !is_array($document['shows'])) {
            throw new DarkThumbnailCorrelationException(
                'Source playlist ' . $playlist . ' must contain a shows array'
            );
        }

        $index = [];
        foreach ($document['shows'] as $show) {
            if (!is_array($show)
                || !isset($show['identifier'])
                || !is_string($show['identifier'])
                || trim($show['identifier']) === ''
            ) {
                throw new DarkThumbnailCorrelationException(
                    'Source playlist ' . $playlist . ' contains an invalid show record'
                );
            }
            $identifier = trim($show['identifier']);
            if (isset($index[$identifier])) {
                throw new DarkThumbnailCorrelationException(
                    'Source playlist ' . $playlist . ' contains duplicate identifier ' . $identifier
                );
            }
            $index[$identifier] = $show;
        }
        return $index;
    }

    private function auditItems(array $audit, string $category): array
    {
        if (!isset($audit[$category]) || !is_array($audit[$category])) {
            throw new DarkThumbnailCorrelationException(
                'Thumbnail audit result is missing the ' . $category . ' collection'
            );
        }

        $itemsByImdb = [];
        foreach ($audit[$category] as $item) {
            if (!is_array($item)
                || !isset($item['imdb'], $item['filename'])
                || !ThumbnailService::isValidImdb($item['imdb'])
                || $item['filename'] !== $item['imdb'] . '.jpg'
            ) {
                throw new DarkThumbnailCorrelationException(
                    'Thumbnail audit ' . $category . ' collection contains an invalid item'
                );
            }
            $itemsByImdb[$item['imdb']] = [
                'imdb' => $item['imdb'],
                'filename' => $item['filename'],
            ];
        }
        ksort($itemsByImdb, SORT_STRING);
        return array_values($itemsByImdb);
    }

    private function readJsonFile(string $path, string $label): array
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new DarkThumbnailCorrelationException($label . ' file is unavailable');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new DarkThumbnailCorrelationException($label . ' file could not be read');
        }
        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DarkThumbnailCorrelationException(
                $label . ' file contains invalid JSON',
                0,
                $exception
            );
        }
        if (!is_array($document)) {
            throw new DarkThumbnailCorrelationException($label . ' JSON must be an object');
        }
        return $document;
    }

    private function publicRecord(array $record): array
    {
        return [
            'playlist' => $record['playlist'],
            'identifier' => $record['identifier'],
            'title' => $record['title'],
            'category' => $record['category'],
            'imdb' => $record['imdb'],
        ];
    }

    private function recordKey(array $record): string
    {
        return $record['playlist'] . "\0" . $record['identifier'];
    }

    private function compareRecords(array $left, array $right): int
    {
        return strcmp($left['playlist'], $right['playlist'])
            ?: strcmp($left['identifier'], $right['identifier']);
    }
}
