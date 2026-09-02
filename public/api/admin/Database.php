<?php

namespace FreeTV\Admin;

require_once __DIR__ . '/ServerPaths.php';

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

        $capsule->addConnection(self::getConnectionConfig());

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
        RuntimeEnvironment::load((new ServerPaths())->appRoot());
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

    private static function getConnectionConfig($database = null)
    {
        $config = self::getConfig();

        $connectionConfig = [
            'driver'    => 'mysql',
            'host'      => $config['host'],
            'username'  => $config['user'],
            'password'  => $config['pass'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        if ($database !== null) {
            $connectionConfig['database'] = $database;
        } else {
            $connectionConfig['database'] = $config['database'];
        }

        return $connectionConfig;
    }

    public static function createBootstrapConnection()
    {
        $capsule = new Capsule;
        $config = self::getConnectionConfig();
        unset($config['database']);
        $capsule->addConnection($config, 'readiness_bootstrap');
        return $capsule->getConnection('readiness_bootstrap');
    }

    public static function createConfiguredConnection()
    {
        $capsule = new Capsule;
        $capsule->addConnection(self::getConnectionConfig(), 'readiness_configured');
        return $capsule->getConnection('readiness_configured');
    }

    public static function createReadinessConnection($database)
    {
        if (!is_string($database)
            || preg_match('/^freetv_readiness_[a-f0-9]{12}$/D', $database) !== 1) {
            throw new \InvalidArgumentException('Unsafe readiness database name');
        }

        $capsule = new Capsule;
        $capsule->addConnection(self::getConnectionConfig($database), 'readiness_probe');
        return $capsule->getConnection('readiness_probe');
    }

    public static function table($table)
    {
        self::init();
        return Capsule::table($table);
    }
}
