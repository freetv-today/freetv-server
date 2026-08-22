<?php

namespace FreeTV\Admin;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    private static $capsule = null;
    private static $environmentLoaded = false;

    public static function init()
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule;

        $config = self::getConfig();

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $config['host'],
            'database'  => $config['database'],
            'username'  => $config['user'],
            'password'  => $config['pass'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
        error_log("✅ Illuminate Database initialized successfully");
        return $capsule;
    }

    public static function hasExplicitConfig()
    {
        self::loadEnvironment();

        foreach ([
            ['VITE_DB_HOST', 'DB_HOST'],
            ['VITE_DB_NAME', 'DB_NAME'],
            ['VITE_DB_USER', 'DB_USER'],
        ] as $names) {
            [$configured] = self::getFirstConfiguredValue($names, false);
            if (!$configured) {
                return false;
            }
        }

        // A password setting must be explicit, but an empty password is valid.
        [$passwordConfigured] = self::getFirstConfiguredValue(['VITE_DB_PASS', 'DB_PASS']);
        return $passwordConfigured;
    }

    private static function loadEnvironment()
    {
        if (self::$environmentLoaded) {
            return;
        }

        // From public/api/admin/ -> go up 3 levels to root
        $rootPath = dirname(__DIR__, 3);
        $envPath = $rootPath . '/.env';

        error_log("Looking for .env at: " . $envPath);

        if (file_exists($envPath) && class_exists('\\Dotenv\\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
            
            // Force export
            foreach ($_ENV as $key => $value) {
                putenv("$key=$value");
            }
            
            error_log("✅ .env loaded and exported successfully from PHP");
        } else {
            error_log("❌ Could not load .env file at " . $envPath);
        }

        self::$environmentLoaded = true;
    }

    private static function getFirstConfiguredValue($names, $allowEmpty = true)
    {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false && ($allowEmpty || trim((string) $value) !== '')) {
                return [true, (string) $value];
            }
        }

        return [false, ''];
    }

    private static function getConfig()
    {
        self::loadEnvironment();

        [, $host] = self::getFirstConfiguredValue(['VITE_DB_HOST', 'DB_HOST'], false);
        [, $database] = self::getFirstConfiguredValue(['VITE_DB_NAME', 'DB_NAME'], false);
        [, $user] = self::getFirstConfiguredValue(['VITE_DB_USER', 'DB_USER'], false);
        [, $pass] = self::getFirstConfiguredValue(['VITE_DB_PASS', 'DB_PASS']);

        $config = [
            'host'     => $host !== '' ? $host : '127.0.0.1',
            'database' => $database !== '' ? $database : 'freetv',
            'user'     => $user !== '' ? $user : 'root',
            'pass'     => $pass,
        ];

        error_log("Final DB Config - User: " . $config['user'] . " | Pass length: " . strlen($config['pass']));

        return $config;
    }

    public static function table($table)
    {
        self::init();
        return Capsule::table($table);
    }
}
