<?php

namespace FreeTV\Admin\Publication;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

class PublicationTimestamp
{
    public static function format(
        DateTimeInterface|string $publicationTimestamp,
        DateTimeZone|string|null $defaultTimezone = null
    ): string {
        if (is_string($publicationTimestamp) && trim($publicationTimestamp) === '') {
            throw new InvalidArgumentException('Invalid publication timestamp');
        }

        try {
            if (is_string($publicationTimestamp)) {
                $timezone = $defaultTimezone instanceof DateTimeZone
                    ? $defaultTimezone
                    : new DateTimeZone($defaultTimezone ?? 'UTC');
                $timestamp = new DateTimeImmutable($publicationTimestamp, $timezone);
            } else {
                $timestamp = DateTimeImmutable::createFromInterface($publicationTimestamp);
            }
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid publication timestamp', 0, $exception);
        }

        return $timestamp
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * MariaDB stores playlist timestamps with second precision, so publication
     * operations deliberately use a canonical timestamp with zero milliseconds.
     */
    public static function forOperation(DateTimeInterface|string $timestamp): string
    {
        $canonical = self::format($timestamp);
        return substr($canonical, 0, 19) . '.000Z';
    }

    public static function toDatabase(string $canonicalTimestamp): string
    {
        try {
            return (new DateTimeImmutable($canonicalTimestamp))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid publication timestamp', 0, $exception);
        }
    }
}
