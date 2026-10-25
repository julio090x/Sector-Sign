-- SECTOR SIGN 0.7.0 | MySQL 8 / MariaDB 10.5+ | UTF8MB4
-- Create the EMPTY database first in aaPanel; the installer runs these statements.
-- No account data, credentials or real inventory data are included.
CREATE TABLE IF NOT EXISTS organizations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL, timezone VARCHAR(80) NOT NULL DEFAULT 'America/Lima',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL,
 password_hash VARCHAR(255) NULL, role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
 enabled TINYINT(1) NOT NULL DEFAULT 1, session_epoch INT UNSIGNED NOT NULL DEFAULT 0, oauth_google VARCHAR(160) NULL, oauth_microsoft VARCHAR(200) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_org_email(org_id,email), UNIQUE KEY uniq_oauth_google(oauth_google), UNIQUE KEY uniq_oauth_microsoft(oauth_microsoft), KEY idx_user_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS auth_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL, email VARCHAR(190) NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE, purpose ENUM('invite','reset') NOT NULL,
 role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer', expires_at DATETIME NOT NULL,
 used_at DATETIME NULL, created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_auth_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 remote_key CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_login_key_time(remote_key,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS locations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, address VARCHAR(255) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_locations_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS display_groups (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_groups_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media (
 id CHAR(32) NOT NULL PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 original_name VARCHAR(190) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE,
 mime VARCHAR(80) NOT NULL, kind ENUM('image','video','audio','stream') NOT NULL,
 bytes BIGINT UNSIGNED NOT NULL, sha256 CHAR(64) NOT NULL,
 backend ENUM('local','s3','ftps') NOT NULL DEFAULT 'local', object_key VARCHAR(200) DEFAULT NULL, stream_url TEXT NULL,
 deleted_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_media_org(org_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS playlists (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL, description VARCHAR(500) NOT NULL DEFAULT '',
 revision INT UNSIGNED NOT NULL DEFAULT 0, soundtrack_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_playlists_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS playlist_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, playlist_id BIGINT UNSIGNED NOT NULL,
 media_id CHAR(32) NOT NULL, position INT UNSIGNED NOT NULL, duration_sec SMALLINT UNSIGNED NOT NULL DEFAULT 8,
 fit ENUM('contain','cover') NOT NULL DEFAULT 'contain', transition ENUM('fade','none','slide') NOT NULL DEFAULT 'fade',
 schedule_json LONGTEXT NOT NULL, audio_json LONGTEXT NULL, KEY idx_items_playlist(playlist_id,position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS publications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 playlist_id BIGINT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL,
 manifest_json LONGTEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_pub_org(org_id),UNIQUE KEY uniq_pub_playlist_version(playlist_id,version),KEY idx_pub_playlist(playlist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS screens (
 id CHAR(32) NOT NULL PRIMARY KEY, org_id BIGINT UNSIGNED NULL,
 name VARCHAR(150) NOT NULL DEFAULT 'Nueva pantalla', location_id BIGINT UNSIGNED NULL,
 group_id BIGINT UNSIGNED NULL, orientation ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
 token_hash CHAR(64) NOT NULL UNIQUE, pair_code_hash CHAR(64) NOT NULL UNIQUE,
 pair_expires_at DATETIME NOT NULL, paired_at DATETIME NULL,
 publication_id BIGINT UNSIGNED NULL, applied_publication_id BIGINT UNSIGNED NULL,
 last_seen_at DATETIME NULL, last_error VARCHAR(300) NULL,
 last_capture_at DATETIME NULL, capture_file VARCHAR(80) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_screen_org(org_id), KEY idx_screen_last_seen(last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS screen_commands (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, screen_id CHAR(32) NOT NULL,
 action ENUM('pause','resume','reload','capture') NOT NULL,
 status ENUM('queued','delivered','ack','failed') NOT NULL DEFAULT 'queued',
 requested_by BIGINT UNSIGNED NOT NULL, result_text VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, delivered_at DATETIME NULL, ack_at DATETIME NULL,
 KEY idx_command_screen(screen_id,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS integrations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, key_id CHAR(20) NOT NULL UNIQUE,
 encrypted_secret TEXT NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, auto_publish TINYINT(1) NOT NULL DEFAULT 0,
 last_sync_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_integrations_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS integration_nonces (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, integration_id BIGINT UNSIGNED NOT NULL,
 nonce_hash CHAR(64) NOT NULL, seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_int_nonce(integration_id,nonce_hash), KEY idx_nonce_seen(seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS catalog_products (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NOT NULL,
 integration_id BIGINT UNSIGNED NOT NULL, external_id VARCHAR(160) NOT NULL,
 name VARCHAR(190) NOT NULL, presentation VARCHAR(100) NOT NULL DEFAULT '',
 public_price DECIMAL(12,2) NOT NULL DEFAULT 0, min_quantity DECIMAL(12,3) NULL,
 promotional TINYINT(1) NOT NULL DEFAULT 0, image_url VARCHAR(500) NOT NULL DEFAULT '',
 source_revision VARCHAR(100) NOT NULL DEFAULT '', source_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, media_id CHAR(32) DEFAULT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_product(integration_id,external_id),KEY idx_catalog_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id BIGINT UNSIGNED NULL,
 actor VARCHAR(180) NOT NULL, action VARCHAR(120) NOT NULL,
 object_type VARCHAR(70) NOT NULL DEFAULT '', object_id VARCHAR(100) NOT NULL DEFAULT '',
 info_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_audit_org_date(org_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS web_sessions (
 id_hash CHAR(64) NOT NULL PRIMARY KEY, payload MEDIUMBLOB NOT NULL,
 expires_at DATETIME NOT NULL, KEY idx_session_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS oauth_states (
 state_hash CHAR(64) NOT NULL PRIMARY KEY, provider VARCHAR(20) NOT NULL,
 verifier VARCHAR(128) NOT NULL, redirect_path VARCHAR(255) NOT NULL DEFAULT '/',
 expires_at DATETIME NOT NULL, KEY idx_oauth_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS screen_capture_blobs (
 screen_id CHAR(32) NOT NULL PRIMARY KEY,
 png_base64 MEDIUMTEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additive migration: does not modify users, advertisements or existing publications.
CREATE TABLE IF NOT EXISTS programs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 org_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1, definition_json LONGTEXT NOT NULL,
 last_publication_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_program_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS device_status (
 screen_id CHAR(32) NOT NULL PRIMARY KEY, telemetry_json LONGTEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 0.8: Walls are optional and are independent of campaign groups.
CREATE TABLE IF NOT EXISTS video_walls (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,org_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,mode ENUM('mirror','span') NOT NULL DEFAULT 'mirror',
 `rows` TINYINT UNSIGNED NOT NULL DEFAULT 1,cols TINYINT UNSIGNED NOT NULL DEFAULT 2,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_walls_org(org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS video_wall_members (
 wall_id BIGINT UNSIGNED NOT NULL,screen_id CHAR(32) NOT NULL,slot TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY(wall_id,slot),UNIQUE KEY uniq_wall_screen(screen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
