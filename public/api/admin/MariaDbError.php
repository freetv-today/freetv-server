<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class MariaDbError
{
    public static function driverCode(\Throwable $exception): ?int
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException && is_array($current->errorInfo ?? null)
                && isset($current->errorInfo[1]) && is_numeric($current->errorInfo[1])) {
                return (int) $current->errorInfo[1];
            }
            if (is_numeric($current->getCode()) && (int) $current->getCode() >= 1000) {
                return (int) $current->getCode();
            }
        }

        return null;
    }

    public static function isUnknownDatabase(\Throwable $exception): bool
    {
        return self::driverCode($exception) === 1049;
    }
}
