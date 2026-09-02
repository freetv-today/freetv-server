<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Publication\PlaylistIndexSerializer;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationTimestamp;

final class Bootstrapper
{
    public const ALREADY_INITIALIZED = 'already_initialized';
    public const INITIALIZED = 'initialized';

    private $clock;

    public function __construct(
        private SchemaBootstrapper $schemaBootstrapper,
        private FreshBootstrapData $freshData,
        private FreshDatabaseInstaller $databaseInstaller,
        private FreshArtifactInstaller $artifactInstaller,
        ?callable $clock = null,
        private ?DatasetPackageSource $packageProvider = null,
        private ?PackageDatabaseInstallation $packageDatabaseInstaller = null,
        private ?PackageArtifactStager $packageArtifactInstaller = null
    ) {
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function sample(string $username, string $password): string
    {
        return $this->package('sample', $username, $password);
    }

    public function official(string $username, string $password): string
    {
        return $this->package('official', $username, $password);
    }

    public function fresh(string $username, string $password): string
    {
        $data = $this->freshData->load();
        $timestamp = PublicationTimestamp::forOperation(($this->clock)());
        $this->artifacts($data, $timestamp);

        [$connection, $schemaState] = $this->schemaBootstrapper->prepare();
        if ($schemaState === SchemaBootstrapper::ALREADY_INITIALIZED) {
            return self::ALREADY_INITIALIZED;
        }

        $artifactInstallation = null;
        $transactionStarted = false;
        $databaseCommitted = false;
        try {
            $connection->beginTransaction();
            $transactionStarted = true;

            if ($this->databaseInstaller->hasUsers($connection, true)) {
                $connection->rollBack();
                return self::ALREADY_INITIALIZED;
            }

            $playlist = $this->databaseInstaller->install(
                $connection,
                $data,
                $username,
                $password,
                PublicationTimestamp::toDatabase($timestamp)
            );
            $this->databaseInstaller->verify($connection, $username);

            // Rebuild from the row installed into MariaDB so artifact metadata and DB state share one source.
            $artifacts = $this->artifacts(
                ['settings' => $data['settings'], 'playlist' => $playlist, 'shows' => $data['shows']],
                $timestamp
            );
            $artifactInstallation = $this->artifactInstaller->prepare($artifacts);
            $artifactInstallation->promote();

            $connection->commit();
            $databaseCommitted = true;
            $artifactInstallation->commit();
        } catch (\Throwable $exception) {
            if ($transactionStarted && !$databaseCommitted) {
                try {
                    $connection->rollBack();
                } catch (\Throwable $rollbackException) {
                    error_log('Fresh bootstrap database rollback error: ' . $rollbackException->getMessage());
                }
            }
            if ($artifactInstallation !== null && !$databaseCommitted) {
                try {
                    $artifactInstallation->rollback();
                } catch (\Throwable $rollbackException) {
                    error_log('Fresh bootstrap artifact rollback error: ' . $rollbackException->getMessage());
                }
            }
            throw $exception;
        }

        return self::INITIALIZED;
    }

    private function package(string $dataset, string $username, string $password): string
    {
        if ($this->packageProvider === null
            || $this->packageDatabaseInstaller === null
            || $this->packageArtifactInstaller === null) {
            throw new \LogicException('Downloaded dataset bootstrap is not configured');
        }

        if ($this->schemaBootstrapper->isAlreadyInitialized()) {
            return self::ALREADY_INITIALIZED;
        }

        $package = $this->packageProvider->acquire($dataset);
        try {
            // Validate SQL before SchemaBootstrapper can create or modify the configured database.
            $dataStatements = $this->packageDatabaseInstaller->validatedDataStatements($package);
            [$connection, $schemaState] = $this->schemaBootstrapper->prepare();
            if ($schemaState === SchemaBootstrapper::ALREADY_INITIALIZED) {
                return self::ALREADY_INITIALIZED;
            }

            $artifactInstallation = null;
            $transactionStarted = false;
            $databaseCommitted = false;
            try {
                $connection->beginTransaction();
                $transactionStarted = true;
                if ($this->databaseInstaller->hasUsers($connection, true)) {
                    $connection->rollBack();
                    return self::ALREADY_INITIALIZED;
                }

                $this->packageDatabaseInstaller->install(
                    $connection,
                    $package,
                    $dataStatements,
                    $username,
                    $password
                );
                $this->packageDatabaseInstaller->verify($connection, $package, $username);
                $artifactInstallation = $this->packageArtifactInstaller->prepare($package);
                $artifactInstallation->promote();

                $connection->commit();
                $databaseCommitted = true;
                try {
                    $artifactInstallation->commit();
                } catch (\Throwable $cleanupException) {
                    error_log('Dataset artifact cleanup error: ' . $cleanupException->getMessage());
                }
            } catch (\Throwable $exception) {
                if ($transactionStarted && !$databaseCommitted) {
                    try {
                        $connection->rollBack();
                    } catch (\Throwable $rollbackException) {
                        error_log('Dataset bootstrap database rollback error: ' . $rollbackException->getMessage());
                    }
                }
                if ($artifactInstallation !== null && !$databaseCommitted) {
                    try {
                        $artifactInstallation->rollback();
                    } catch (\Throwable $rollbackException) {
                        error_log('Dataset bootstrap artifact rollback error: ' . $rollbackException->getMessage());
                    }
                }
                throw $exception;
            }

            return self::INITIALIZED;
        } finally {
            try {
                $package->cleanup();
            } catch (\Throwable $cleanupException) {
                error_log('Dataset package cleanup error: ' . $cleanupException->getMessage());
            }
        }
    }

    private function artifacts(array $data, string $timestamp): array
    {
        $playlist = $data['playlist'];
        $playlistArtifact = PlaylistPublicationSerializer::serialize(
            $playlist,
            $data['shows'],
            $timestamp
        );
        if ($playlistArtifact['shows'] !== []) {
            throw new \RuntimeException('Fresh playlist must contain an empty shows array');
        }

        return [
            'config.json' => ConfigPublicationSerializer::serialize($data['settings'], $timestamp),
            'playlists/index.json' => PlaylistIndexSerializer::serialize(
                [$playlist],
                $playlist['filename'],
                $timestamp,
                []
            ),
            'playlists/playlist-one.json' => $playlistArtifact,
        ];
    }
}
