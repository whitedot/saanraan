CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_storage_cleanup_failures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type VARCHAR(60) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(512) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 1,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_content_storage_cleanup_status (status, updated_at),
    KEY idx_sr_content_storage_cleanup_source (source_type, source_id),
    KEY idx_sr_content_storage_cleanup_storage (storage_driver, storage_key(191))
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.006',
    updated_at = NOW()
WHERE module_key = 'content';
