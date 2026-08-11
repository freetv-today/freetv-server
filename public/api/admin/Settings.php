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
        $settings = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $settings[$key] = $definition['default'];
        }

        $rows = Database::table('app_settings')
            ->whereIn('setting_key', array_keys(self::DEFINITIONS))
            ->get(['setting_key', 'setting_value']);

        foreach ($rows as $row) {
            $key = $row->setting_key;
            if (!isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $settings[$key] = self::deserialize($row->setting_value, self::DEFINITIONS[$key]);
        }

        return $settings;
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
