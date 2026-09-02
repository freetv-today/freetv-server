<?php

declare(strict_types=1);

namespace FreeTV\Admin;

interface DatasetPackageSource
{
    public function acquire(string $dataset): DatasetPackage;
}

interface PackageDatabaseInstallation
{
    public function validatedDataStatements(DatasetPackage $package): array;

    public function install(
        $connection,
        DatasetPackage $package,
        array $dataStatements,
        string $username,
        string $password
    ): void;

    public function verify($connection, DatasetPackage $package, string $username): void;
}

interface StagedPackageArtifacts
{
    public function promote(): void;

    public function commit(): void;

    public function rollback(): void;
}

interface PackageArtifactStager
{
    public function prepare(DatasetPackage $package): StagedPackageArtifacts;
}
