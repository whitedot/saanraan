CREATE TABLE IF NOT EXISTS sr_community_board_delete_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    stage VARCHAR(40) NOT NULL DEFAULT 'prepare',
    board_snapshot_json MEDIUMTEXT NULL,
    counts_json TEXT NULL,
    processed_json TEXT NULL,
    last_error TEXT NULL,
    lock_token VARCHAR(80) NOT NULL DEFAULT '',
    locked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_board_delete_jobs_status_stage_updated (status, stage, updated_at, id),
    KEY idx_sr_community_board_delete_jobs_board (board_id, id),
    KEY idx_sr_community_board_delete_jobs_requested (requested_by, created_at, id),
    KEY idx_sr_community_board_delete_jobs_lock (status, locked_at, id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_delete_job_maps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    error_text TEXT NULL,
    storage_driver VARCHAR(20) NOT NULL DEFAULT '',
    storage_key VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_delete_job_maps_source (job_id, entity_type, source_id),
    KEY idx_sr_community_board_delete_job_maps_status (job_id, entity_type, status, id),
    KEY idx_sr_community_board_delete_job_maps_storage (job_id, status, storage_driver, storage_key)
);

UPDATE sr_modules
SET version = '2026.07.004',
    updated_at = NOW()
WHERE module_key = 'community';
