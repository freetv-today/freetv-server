<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class DatabaseIdentifier
{
    private const PATTERN = '/\A[A-Za-z0-9_$-]{1,64}\z/D';

    public static function quote(string $name): string
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException('Configured database name is not a safe MariaDB identifier');
        }

        return '`' . $name . '`';
    }
}
