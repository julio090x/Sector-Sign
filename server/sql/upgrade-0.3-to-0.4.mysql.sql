-- Sector Sign 0.3.0 -> 0.4.0: run ONCE after a verified backup on your private database.
-- Store the new code only after this migration completed successfully.
ALTER TABLE users ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0 AFTER enabled;
ALTER TABLE users ADD UNIQUE KEY uniq_oauth_google(oauth_google);
ALTER TABLE users ADD UNIQUE KEY uniq_oauth_microsoft(oauth_microsoft);
ALTER TABLE publications ADD UNIQUE KEY uniq_pub_playlist_version(playlist_id,version);
