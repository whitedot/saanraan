CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_level_recalculate_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requested_by BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'running',
    stage VARCHAR(40) NOT NULL DEFAULT 'accounts',
    cursor_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    processed_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    batch_size INT UNSIGNED NOT NULL DEFAULT 50,
    lock_token VARCHAR(80) NOT NULL DEFAULT '',
    failure_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_level_recalc_status_updated (status, updated_at, id),
    KEY idx_sr_community_level_recalc_requested (requested_by, created_at, id),
    KEY idx_sr_community_level_recalc_lock (status, lock_token)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.034',
    updated_at = NOW()
WHERE module_key = 'community';
