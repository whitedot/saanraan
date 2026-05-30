ALTER TABLE sr_community_asset_logs
    ADD COLUMN log_status VARCHAR(20) NOT NULL DEFAULT 'completed' AFTER amount;

UPDATE sr_community_asset_logs
SET log_status = 'completed'
WHERE log_status = '';

UPDATE sr_modules
SET version = '2026.05.025',
    updated_at = NOW()
WHERE module_key = 'community';
