<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class DatabaseReadiness
{
    private $configuredConnectionFactory;
    private $capabilityProbe;

    public function __construct(callable $configuredConnectionFactory, callable $capabilityProbe)
    {
        $this->configuredConnectionFactory = $configuredConnectionFactory;
        $this->capabilityProbe = $capabilityProbe;
    }

    public function check(): array
    {
        try {
            $connection = ($this->configuredConnectionFactory)();
            $connection->getPdo();
        } catch (\Throwable $exception) {
            if (!MariaDbError::isUnknownDatabase($exception)) {
                throw $exception;
            }

            return [
                'status' => 'database_missing',
                'http_status' => 503,
                'database_mode' => ($this->capabilityProbe)(),
            ];
        }

        $missingTables = [];
        $schema = $connection->getSchemaBuilder();
        foreach (SchemaBootstrapper::REQUIRED_TABLES as $table) {
            if (!$schema->hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if ($missingTables === [] && $connection->table('users')->exists()) {
            return ['status' => 'ready', 'http_status' => 200];
        }

        $mode = ($this->capabilityProbe)();
        if ($missingTables !== []) {
            return [
                'status' => 'schema_missing',
                'http_status' => 503,
                'missing_tables' => $missingTables,
                'database_mode' => $mode,
            ];
        }

        return [
            'status' => 'initialization_required',
            'http_status' => 200,
            'database_mode' => $mode,
        ];
    }
}
