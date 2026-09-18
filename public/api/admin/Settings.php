<?php

namespace FreeTV\Admin;

class Settings
{
    private const DEFINITIONS = [
        'show_ads' => [
            'scope' => 'viewer',
            'type' => 'boolean',
            'default' => false,
            'publish' => true,
        ],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function validate(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (!is_string($key) || !isset(self::DEFINITIONS[$key])) {
                throw new \InvalidArgumentException("Unknown setting: {$key}");
            }

            if (self::DEFINITIONS[$key]['type'] === 'boolean' && !is_bool($value)) {
                throw new \InvalidArgumentException("Setting {$key} must be boolean");
            }
        }

        return $settings;
    }

    public static function read(): array
    {
        $rows = Database::table('app_settings')
            ->whereIn('setting_key', array_keys(self::DEFINITIONS))
            ->get(['setting_key', 'setting_value']);

        return self::fromRows($rows);
    }

    public static function fromRows(iterable $rows): array
    {
        $settings = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $settings[$key] = $definition['default'];
        }

        foreach ($rows as $row) {
            $key = is_array($row) ? ($row['setting_key'] ?? null) : ($row->setting_key ?? null);
            if (!isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $value = is_array($row) ? ($row['setting_value'] ?? null) : ($row->setting_value ?? null);
            $settings[$key] = self::deserialize($value, self::DEFINITIONS[$key]);
        }

        return $settings;
    }

    public static function publishable(array $settings): array
    {
        $publishable = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            if (($definition['publish'] ?? false) !== true) {
                continue;
            }

            $value = array_key_exists($key, $settings) ? $settings[$key] : $definition['default'];
            self::validate([$key => $value]);
            $publishable[$key] = $value;
        }

        return $publishable;
    }

    public static function readPublishable(): array
    {
        return self::publishable(self::read());
    }

    public static function write(array $settings): array
    {
        $settings = self::validate($settings);
        $connection = Database::init()->getConnection();

        $connection->transaction(function () use ($connection, $settings): void {
            foreach ($settings as $key => $value) {
                $definition = self::DEFINITIONS[$key];
                $connection->statement(
                    <<<'SQL'
                        INSERT INTO app_settings (setting_key, setting_value, scope)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            setting_value = VALUES(setting_value),
                            scope = VALUES(scope)
                        SQL,
                    [$key, self::serialize($value, $definition), $definition['scope']]
                );
            }
        });

        return self::read();
    }

    private static function serialize($value, array $definition): string
    {
        if ($definition['type'] === 'boolean') {
            return $value ? 'true' : 'false';
        }

        throw new \LogicException('Unsupported setting type');
    }

    private static function deserialize($value, array $definition)
    {
        if ($definition['type'] === 'boolean') {
            if ($value === 'true') {
                return true;
            }
            if ($value === 'false') {
                return false;
            }

            return $definition['default'];
        }

        throw new \LogicException('Unsupported setting type');
    }
}
