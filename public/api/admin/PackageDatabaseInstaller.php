<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/publication/PublicationException.php';
require_once __DIR__ . '/publication/PublicationTimestamp.php';
require_once __DIR__ . '/publication/PublicationSemanticHasher.php';
require_once __DIR__ . '/publication/PublicationSemanticDelta.php';
require_once __DIR__ . '/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/publication/PublicationStatusService.php';

use FreeTV\Admin\Publication\PublicationStatusService;
use JsonException;

final class PackageDatabaseInstaller implements PackageDatabaseInstallation
{
    private SqlPackageExecutor $executor;
    private string $canonicalSchemaPath;

    public function __construct(SqlPackageExecutor $executor, string $canonicalSchemaPath)
    {
        $this->executor = $executor;
        $this->canonicalSchemaPath = $canonicalSchemaPath;
    }

    /** @return list<string> */
    public function validatedDataStatements(DatasetPackage $package): array
    {
        $packageStatements = $this->readStatements($package->path('database.sql'));
        $canonicalStatements = $this->readStatements($this->canonicalSchemaPath);
        $prefix = array_slice($packageStatements, 0, count($canonicalStatements));
        if ($prefix !== $canonicalStatements) {
            throw new \RuntimeException('Dataset SQL does not contain the canonical FreeTV schema prefix');
        }

        $dataStatements = array_slice($packageStatements, count($canonicalStatements));
        if (count($dataStatements) !== 4
            || strtoupper(trim($dataStatements[0])) !== 'START TRANSACTION'
            || strtoupper(trim($dataStatements[3])) !== 'COMMIT') {
            throw new \RuntimeException('Dataset SQL has an unsupported transaction structure');
        }
        $this->validateValuesInsert(
            $dataStatements[1],
            'playlists',
            ['id', 'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'lastupdated', 'is_default', 'sort_order']
        );
        $this->validateValuesInsert(
            $dataStatements[2],
            'playlist_shows',
            ['playlist_id', 'category', 'status', 'identifier', 'title', 'description', 'start_year', 'end_year', 'imdb', 'group_name', 'sort_order']
        );

        return [$dataStatements[1], $dataStatements[2]];
    }

    public function install(
        $connection,
        DatasetPackage $package,
        array $dataStatements,
        string $username,
        string $password
    ): void {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed');
        }
        $config = $this->readJson($package->path('config.json'));

        foreach (['problem_reports', 'problem_report_ips'] as $table) {
            if ($connection->getSchemaBuilder()->hasTable($table)) {
                $connection->table($table)->delete();
            }
        }
        $connection->table('playlist_shows')->delete();
        $connection->table('playlists')->delete();
        $this->executor->executeStatements($connection, $dataStatements);
        $connection->table('app_settings')->updateOrInsert(
            ['setting_key' => 'show_ads'],
            ['setting_value' => $config['show_ads'] ? 'true' : 'false', 'scope' => 'viewer']
        );
        $connection->table('users')->insert([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function verify($connection, DatasetPackage $package, string $username): void
    {
        $admin = $connection->table('users')->first(['username', 'role', 'status']);
        if ($connection->table('users')->count() !== 1
            || $admin === null
            || $admin->username !== $username
            || $admin->role !== 'admin'
            || $admin->status !== 'active'
            || $connection->table('playlists')->count() < 1) {
            throw new \RuntimeException('Dataset MariaDB initialization verification failed');
        }
        foreach (['problem_reports', 'problem_report_ips'] as $table) {
            if ($connection->getSchemaBuilder()->hasTable($table)
                && $connection->table($table)->count() !== 0) {
                throw new \RuntimeException('Dataset package must not initialize distributed report data');
            }
        }

        $playlists = $connection->table('playlists')
            ->select(['id', 'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'is_default', 'sort_order'])
            ->orderBy('sort_order')->orderBy('id')->get();
        $settingsRows = $connection->table('app_settings')->get(['setting_key', 'setting_value']);
        $index = $this->readJson($package->path('playlists/index.json'));
        $databaseFilenames = [];
        foreach ($playlists as $playlist) {
            $databaseFilenames[] = is_array($playlist) ? $playlist['filename'] : $playlist->filename;
        }
        $publishedFilenames = array_column($index['playlists'] ?? [], 'filename');
        sort($databaseFilenames, SORT_STRING);
        sort($publishedFilenames, SORT_STRING);
        if ($databaseFilenames !== $publishedFilenames) {
            throw new \RuntimeException('Dataset playlist index does not match initialized MariaDB playlists');
        }
        $status = (new PublicationStatusService(
            $package->root(),
            static fn() => $playlists,
            static fn(int $playlistId) => $connection->table('playlist_shows')
                ->where('playlist_id', $playlistId)
                ->select([
                    'id', 'sort_order', 'category', 'status', 'identifier', 'title', 'description',
                    'start_year', 'end_year', 'imdb', 'group_name',
                ])->orderBy('sort_order')->orderBy('id')->get(),
            static fn() => Settings::publishable(Settings::fromRows($settingsRows))
        ))->status();

        if (($status['config']['changed'] ?? null) !== false
            || ($status['config']['error'] ?? null) !== null
            || ($status['default_playlist']['changed'] ?? null) !== false
            || ($status['default_playlist']['error'] ?? null) !== null) {
            throw new \RuntimeException('Dataset Viewer artifacts do not match initialized MariaDB state');
        }
        foreach ($status['playlists'] as $playlistStatus) {
            if (($playlistStatus['changed'] ?? null) !== false
                || ($playlistStatus['error'] ?? null) !== null) {
                throw new \RuntimeException('Dataset playlist artifacts do not match initialized MariaDB state');
            }
        }
    }

    private function readStatements(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('Could not read dataset SQL package');
        }
        return $this->executor->statements($sql);
    }

    private function validateValuesInsert(string $statement, string $table, array $columns): void
    {
        $columnSql = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
        $prefix = 'INSERT INTO ' . $table . ' (' . $columnSql . ') VALUES';
        if (!str_starts_with($statement, $prefix)) {
            throw new \RuntimeException("Dataset SQL has an unsupported {$table} insert");
        }
        $values = substr($statement, strlen($prefix));
        $values = preg_replace('/CONVERT\(0x[0-9a-f]+ USING utf8mb4\)/i', '', $values);
        $values = preg_replace('/\bNULL\b/i', '', (string) $values);
        $values = preg_replace('/-?\d+/', '', (string) $values);
        $values = preg_replace('/[\s(),]+/', '', (string) $values);
        if ($values !== '') {
            throw new \RuntimeException("Dataset SQL {$table} values contain unsupported expressions");
        }
    }

    private function readJson(string $path): array
    {
        try {
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Could not read a validated dataset JSON artifact', 0, $exception);
        }
        if (!is_array($value)) {
            throw new \RuntimeException('Validated dataset JSON artifact is malformed');
        }
        return $value;
    }
}
