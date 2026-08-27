<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class RuntimeEnvironment
{
    /** @var array<string, true> */
    private static array $loadedRoots = [];

    public static function load(string $appRoot): void
    {
        $appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
        if (isset(self::$loadedRoots[$appRoot])) {
            return;
        }

        $envPath = $appRoot . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath)) {
            if (!class_exists('\Dotenv\Dotenv')) {
                return;
            }
            \Dotenv\Dotenv::createImmutable($appRoot)->safeLoad();
            foreach ($_ENV as $key => $value) {
                if (is_string($key) && (is_string($value) || is_numeric($value))) {
                    putenv($key . '=' . (string) $value);
                }
            }
        }

        self::$loadedRoots[$appRoot] = true;
    }

    /** @return array{configured: bool, value: string} */
    public static function configuredValue(string $name, string $appRoot): array
    {
        self::load($appRoot);
        $value = getenv($name);
        if ($value !== false) {
            return ['configured' => true, 'value' => (string) $value];
        }
        if (array_key_exists($name, $_ENV)) {
            return ['configured' => true, 'value' => (string) $_ENV[$name]];
        }
        return ['configured' => false, 'value' => ''];
    }
}
