CREATE DATABASE IF NOT EXISTS `freetv`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `freetv`;
-- FreeTV MariaDB schema installation package.
-- Includes the complete current schema and canonical app_settings defaults.
-- Includes no playlist/show content, problem reports, or users.

CREATE TABLE IF NOT EXISTS app_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  scope VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_app_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value, scope)
VALUES ('show_ads', 'false', 'viewer');

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'admin',
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playlists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(255) NOT NULL,
  dbtitle VARCHAR(255) NOT NULL,
  dbversion VARCHAR(50) NULL,
  author VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  lastupdated DATETIME NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_playlists_filename (filename),
  KEY idx_playlists_is_default (is_default),
  KEY idx_playlists_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS playlist_shows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  playlist_id INT UNSIGNED NOT NULL,
  category VARCHAR(100) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  identifier VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  start_year VARCHAR(20) NULL,
  end_year VARCHAR(20) NULL,
  imdb VARCHAR(50) NULL,
  group_name VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_playlist_shows_playlist_identifier (playlist_id, identifier),
  KEY idx_playlist_shows_status (status),
  KEY idx_playlist_shows_category (category),
  KEY idx_playlist_shows_sort_order (sort_order),
  CONSTRAINT fk_playlist_shows_playlist
    FOREIGN KEY (playlist_id)
    REFERENCES playlists (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS problem_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  playlist_id INT UNSIGNED NULL,
  playlist_show_id INT UNSIGNED NULL,
  identifier VARCHAR(255) NOT NULL,
  title VARCHAR(255) NULL,
  category VARCHAR(100) NULL,
  imdb VARCHAR(50) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'reported',
  report_count INT UNSIGNED NOT NULL DEFAULT 1,
  archive_api_error TINYINT(1) NOT NULL DEFAULT 0,
  first_reported_at DATETIME NOT NULL,
  last_reported_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_problem_reports_playlist_identifier (playlist_id, identifier),
  KEY idx_problem_reports_status (status),
  KEY idx_problem_reports_playlist_id (playlist_id),
  KEY idx_problem_reports_identifier (identifier),
  CONSTRAINT fk_problem_reports_playlist
    FOREIGN KEY (playlist_id)
    REFERENCES playlists (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT fk_problem_reports_show
    FOREIGN KEY (playlist_show_id)
    REFERENCES playlist_shows (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS problem_report_ips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_problem_report_ips_ip_attempted (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
