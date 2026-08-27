<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/RuntimeEnvironment.php';

use InvalidArgumentException;

final class ServerPaths
{
    private string $appRoot;
    private string $publicRoot;
    private string $tempRoot;

    public function __construct(?string $appRoot = null, ?string $publicPath = null)
    {
        $resolvedAppRoot = $appRoot ?? dirname(__DIR__, 3);
        $realAppRoot = realpath($resolvedAppRoot);
        $this->appRoot = rtrim($realAppRoot !== false ? $realAppRoot : $resolvedAppRoot, DIRECTORY_SEPARATOR);

        if ($publicPath === null) {
            $configured = RuntimeEnvironment::configuredValue('FREETV_PUBLIC_PATH', $this->appRoot);
            $publicPath = $configured['configured'] ? $configured['value'] : 'public';
        }
        $publicPath = self::validatePublicPath($publicPath);

        $this->publicRoot = $this->appRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
        $this->tempRoot = $this->appRoot . DIRECTORY_SEPARATOR . 'temp';
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }

    public function publicRoot(): string
    {
        return $this->publicRoot;
    }

    public function tempRoot(): string
    {
        return $this->tempRoot;
    }

    private static function validatePublicPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('FREETV_PUBLIC_PATH contains a NUL byte');
        }
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException('FREETV_PUBLIC_PATH must not be empty');
        }
        if (str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            throw new InvalidArgumentException('FREETV_PUBLIC_PATH must be relative to the application root');
        }
        $path = rtrim($path, '/');
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1) {
                throw new InvalidArgumentException('FREETV_PUBLIC_PATH contains an unsafe path segment');
            }
        }
        return implode('/', $segments);
    }
}
