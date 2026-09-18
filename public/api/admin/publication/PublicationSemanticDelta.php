<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationSemanticHasher.php';

class PublicationSemanticDelta
{
    public static function playlist(array $authoritative, object $published): array
    {
        [$authoritativeShows, $authoritativeOrder, $authoritativeError] = self::indexShows(
            $authoritative['shows'],
            'MariaDB-derived playlist'
        );
        if ($authoritativeError !== null) {
            return ['delta' => null, 'error' => $authoritativeError];
        }

        [$publishedShows, $publishedOrder, $publishedError] = self::indexShows(
            $published->shows,
            'Published playlist'
        );
        if ($publishedError !== null) {
            return ['delta' => null, 'error' => $publishedError];
        }

        $added = array_values(array_diff($authoritativeOrder, $publishedOrder));
        $removed = array_values(array_diff($publishedOrder, $authoritativeOrder));
        $edited = 0;
        foreach ($authoritativeOrder as $identifier) {
            if (!array_key_exists($identifier, $publishedShows)) {
                continue;
            }
            if (PublicationSemanticHasher::hash($authoritativeShows[$identifier])
                !== PublicationSemanticHasher::hash($publishedShows[$identifier])) {
                $edited++;
            }
        }

        $commonAuthoritativeOrder = array_values(array_filter(
            $authoritativeOrder,
            static fn(string $identifier): bool => array_key_exists($identifier, $publishedShows)
        ));
        $commonPublishedOrder = array_values(array_filter(
            $publishedOrder,
            static fn(string $identifier): bool => array_key_exists($identifier, $authoritativeShows)
        ));

        $metadataFields = [];
        foreach ($authoritative as $field => $value) {
            if ($field === 'lastupdated' || $field === 'shows') {
                continue;
            }
            if ($value !== $published->{$field}) {
                $metadataFields[] = $field;
            }
        }

        return [
            'delta' => [
                'shows_added' => count($added),
                'shows_removed' => count($removed),
                'shows_edited' => $edited,
                'order_changed' => $commonAuthoritativeOrder !== $commonPublishedOrder,
                'metadata_changed' => $metadataFields !== [],
                'metadata_fields' => $metadataFields,
            ],
            'error' => null,
        ];
    }

    public static function config(array $authoritative, object $published): array
    {
        $fields = [];
        foreach ($authoritative as $field => $value) {
            if ($field !== 'lastupdated' && $value !== $published->{$field}) {
                $fields[] = $field;
            }
        }

        return ['fields' => $fields];
    }

    private static function indexShows(array $shows, string $label): array
    {
        $indexed = [];
        $order = [];
        foreach ($shows as $show) {
            $identifier = self::value($show, 'identifier');
            if (array_key_exists($identifier, $indexed)) {
                return [[], [], $label . ' contains duplicate show identifier ' . $identifier];
            }
            $indexed[$identifier] = $show;
            $order[] = $identifier;
        }

        return [$indexed, $order, null];
    }

    private static function value(array|object $value, string $field): mixed
    {
        return is_array($value) ? $value[$field] : $value->{$field};
    }
}
