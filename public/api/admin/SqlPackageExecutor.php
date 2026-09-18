<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class SqlPackageExecutor
{
    public function executeFile($connection, string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Canonical FreeTV schema package is missing or unreadable');
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('Canonical FreeTV schema package could not be read');
        }

        $this->executeStatements($connection, $this->statements($sql));
    }

    /** @param list<string> $statements */
    public function executeStatements($connection, array $statements): void
    {
        foreach ($statements as $statement) {
            if ($connection->unprepared($statement) !== true) {
                throw new \RuntimeException('A canonical FreeTV schema statement failed');
            }
        }
    }

    /** @return list<string> */
    public function statements(string $sql): array
    {
        $statements = [];
        $statement = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($lineComment) {
                if ($character === "\n") {
                    $lineComment = false;
                    $statement .= $character;
                }
                continue;
            }
            if ($blockComment) {
                if ($character === '*' && $next === '/') {
                    $blockComment = false;
                    $index++;
                }
                continue;
            }
            if ($quote === null && $character === '-' && $next === '-'
                && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                $lineComment = true;
                $index++;
                continue;
            }
            if ($quote === null && $character === '#') {
                $lineComment = true;
                continue;
            }
            if ($quote === null && $character === '/' && $next === '*') {
                $blockComment = true;
                $index++;
                continue;
            }

            if ($quote !== null) {
                $statement .= $character;
                if ($character === '\\' && $index + 1 < $length) {
                    $statement .= $sql[++$index];
                } elseif ($character === $quote) {
                    if ($next === $quote) {
                        $statement .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $statement .= $character;
            } elseif ($character === ';') {
                if (trim($statement) !== '') {
                    $statements[] = trim($statement);
                }
                $statement = '';
            } else {
                $statement .= $character;
            }
        }

        if ($quote !== null || $blockComment) {
            throw new \RuntimeException('Canonical FreeTV schema package contains unterminated SQL');
        }
        if (trim($statement) !== '') {
            $statements[] = trim($statement);
        }

        return $statements;
    }
}
