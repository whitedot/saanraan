CREATE TABLE IF NOT EXISTS toy_privacy_requests (
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
    KEY idx_toy_privacy_requests_account (account_id),
    KEY idx_toy_privacy_requests_status (status),
    KEY idx_toy_privacy_requests_type (request_type),
    KEY idx_toy_privacy_requests_created (created_at)
);
