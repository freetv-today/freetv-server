<?php

declare(strict_types=1);

namespace FreeTV\Admin;

final class FreshDatabaseInstaller
{
    public function hasUsers($connection, bool $lock = false): bool
    {
        $query = $connection->table('users')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first(['id']) !== null;
    }

    public function install(
        $connection,
        array $data,
        string $username,
        string $password,
        string $databaseTimestamp
    ): array {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed');
        }

        $connection->table('playlist_shows')->delete();
        $connection->table('playlists')->delete();

        $connection->table('users')->insert([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $playlist = $data['playlist'];
        $playlist['lastupdated'] = $databaseTimestamp;
        $playlistId = $connection->table('playlists')->insertGetId($playlist);

        $connection->table('app_settings')->updateOrInsert(
            ['setting_key' => 'show_ads'],
            ['setting_value' => 'false', 'scope' => 'viewer']
        );

        $playlist['id'] = $playlistId;
        return $playlist;
    }

    public function verify($connection, string $username): void
    {
        $admin = $connection->table('users')->first(['username', 'role', 'status']);
        $playlist = $connection->table('playlists')->first([
            'filename', 'dbtitle', 'is_default', 'sort_order',
        ]);
        $setting = $connection->table('app_settings')
            ->where('setting_key', 'show_ads')
            ->first(['setting_value', 'scope']);

        if ($connection->table('users')->count() !== 1
            || $admin === null
            || $admin->username !== $username
            || $admin->role !== 'admin'
            || $admin->status !== 'active'
            || $connection->table('playlists')->count() !== 1
            || $playlist === null
            || $playlist->filename !== 'playlist-one.json'
            || $playlist->dbtitle !== 'Playlist One'
            || (int) $playlist->is_default !== 1
            || (int) $playlist->sort_order !== 0
            || $connection->table('playlist_shows')->count() !== 0
            || $setting === null
            || $setting->setting_value !== 'false'
            || $setting->scope !== 'viewer') {
            throw new \RuntimeException('Fresh MariaDB baseline verification failed');
        }
    }
}
