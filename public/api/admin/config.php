<?php

/**
 * Environment Configuration for Admin API
 * Automatically detects development vs production environment
 */

namespace FreeTV\Admin;

class AdminConfig
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        $this->detectEnvironment();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function detectEnvironment()
    {
        // Check if we're in development (localhost or dev server)
        $is_dev = (
            $_SERVER['HTTP_HOST'] === 'localhost' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0
        );

        if ($is_dev) {
            // Development environment paths
            $this->config = [
                'environment' => 'development',
                'apdata_path' => __DIR__ . '/../../assets/apdata.key',
                'base_url' => 'http://localhost:5173',
                'admin_url' => 'http://localhost:5173'
            ];
        } else {
            // Production environment paths
            $this->config = [
                'environment' => 'production',
                'apdata_path' => __DIR__ . '/../../admin/assets/apdata.key',
                'base_url' => 'https://freetv.today',
                'admin_url' => 'https://freetv.today/admin'
            ];
        }
    }

    public function get($key)
    {
        return $this->config[$key] ?? null;
    }

    public function getApDataPath()
    {
        return $this->config['apdata_path'];
    }

    public function getDatabaseConfig()
    {
        return [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '3306',
            'name' => getenv('DB_NAME') ?: 'freetv',
            'user' => getenv('DB_USER') ?: 'freetv',
            'pass' => getenv('DB_PASS') ?: '',
        ];
    }

    public function getPdo()
    {
        $db = $this->getDatabaseConfig();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int) $db['port'], $db['name']);
        return new \PDO($dsn, $db['user'], $db['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }
}
