<?php

namespace FreeTV\Admin;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    private static $capsule = null;

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

    private static function getConfig()
    {
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

        $config = [
            'host'     => getenv('VITE_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1',
            'database' => getenv('VITE_DB_NAME') ?: getenv('DB_NAME') ?: 'freetv',
            'user'     => getenv('VITE_DB_USER') ?: getenv('DB_USER') ?: 'root',
            'pass'     => getenv('VITE_DB_PASS') ?: getenv('DB_PASS') ?: '',
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