CREATE TABLE IF NOT EXISTS sr_privacy_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    request_type VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'requested',
    requester_email_hash CHAR(64) NOT NULL DEFAULT '',
    requester_snapshot VARCHAR(255) NOT NULL DEFAULT '',
    request_message TEXT NULL,
    admin_note TEXT NULL,
    handled_by_account_id BIGINT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_privacy_requests_account (account_id),
    KEY idx_sr_privacy_requests_status (status),
    KEY idx_sr_privacy_requests_type (request_type),
    KEY idx_sr_privacy_requests_created (created_at)
);

INSERT INTO sr_modules (module_key, name, version, status, is_bundled, installed_at, updated_at)
VALUES ('privacy', '개인정보', '2026.05.001', 'enabled', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    version = VALUES(version),
    status = 'enabled',
    is_bundled = 1,
    updated_at = VALUES(updated_at);

INSERT IGNORE INTO sr_schema_versions (scope, module_key, version, applied_at)
VALUES ('module', 'privacy', '2026.05.001', NOW());
