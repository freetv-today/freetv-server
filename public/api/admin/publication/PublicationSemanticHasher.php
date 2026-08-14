<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/PublicationException.php';

use JsonException;

class PublicationSemanticHasher
{
    public static function hash(array|object $artifact): string
    {
        $content = is_object($artifact) ? get_object_vars($artifact) : $artifact;
        unset($content['lastupdated']);

        try {
            $json = json_encode(
                self::canonicalObject($content),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new PublicationException('Could not normalize publication content');
        }

        return hash('sha256', $json);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (is_object($value)) {
            return self::canonicalObject(get_object_vars($value));
        }
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            return self::canonicalObject($value);
        }

        return array_map([self::class, 'canonicalize'], $value);
    }

    private static function canonicalObject(array $values): object
    {
        ksort($values, SORT_STRING);
        $object = new \stdClass();
        foreach ($values as $key => $value) {
            $object->{$key} = self::canonicalize($value);
        }
        return $object;
    }
}
