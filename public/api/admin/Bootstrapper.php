<?php

declare(strict_types=1);

namespace FreeTV\Admin;

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
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
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
