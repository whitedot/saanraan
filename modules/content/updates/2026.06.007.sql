CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_body_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploader_account_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(160) NOT NULL,
    stored_name VARCHAR(160) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(255) NOT NULL DEFAULT '',
    public_url VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'temporary',
    attempt_count INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    last_attempted_at DATETIME NULL,
    attached_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_content_body_files_content_status (content_id, status, id),
    KEY idx_sr_content_body_files_uploader_status (uploader_account_id, status, created_at),
    KEY idx_sr_content_body_files_storage (storage_driver, storage_key),
    KEY idx_sr_content_body_files_cleanup (status, expires_at, updated_at),
    KEY idx_sr_content_body_files_checksum (checksum_sha256)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_body_file_refs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    slot_key VARCHAR(60) NOT NULL DEFAULT 'body',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_body_file_refs_content_file_slot (content_id, file_id, slot_key),
    KEY idx_sr_content_body_file_refs_file_status (file_id, status),
    KEY idx_sr_content_body_file_refs_content_status (content_id, status, id)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.007',
    updated_at = NOW()
WHERE module_key = 'content';
