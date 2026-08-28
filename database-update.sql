-- CASTRO'S READY CMS - PREMIUM LAYOUT / MAINTENANCE / API UPDATE
-- Execute ONCE on the current database after making a backup.
SET NAMES utf8mb4;

ALTER TABLE api_integrations
  ADD COLUMN api_type VARCHAR(40) NOT NULL DEFAULT 'custom' AFTER provider_name,
  ADD COLUMN auth_type VARCHAR(40) NOT NULL DEFAULT 'api_key' AFTER environment;

INSERT INTO settings(setting_key,setting_value)
VALUES ('maintenance_image_path','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
