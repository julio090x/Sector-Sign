-- Ejecutar una sola vez y sólo tras hacer copia de seguridad completa.
ALTER TABLE media MODIFY COLUMN kind ENUM('image','video','audio','stream') NOT NULL;
ALTER TABLE playlists ADD COLUMN soundtrack_json LONGTEXT NULL;
ALTER TABLE playlist_items ADD COLUMN audio_json LONGTEXT NULL;
