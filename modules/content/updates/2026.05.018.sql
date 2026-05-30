ALTER TABLE sr_content_asset_access_logs
    ADD COLUMN log_status VARCHAR(20) NOT NULL DEFAULT 'completed' AFTER amount;

ALTER TABLE sr_content_asset_action_logs
    ADD COLUMN log_status VARCHAR(20) NOT NULL DEFAULT 'completed' AFTER amount;

UPDATE sr_content_asset_access_logs
SET log_status = 'completed'
WHERE log_status = '';

UPDATE sr_content_asset_action_logs
SET log_status = 'completed'
WHERE log_status = '';

UPDATE sr_modules
SET version = '2026.05.018',
    updated_at = NOW()
WHERE module_key = 'content';
