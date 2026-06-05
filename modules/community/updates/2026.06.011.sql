CREATE TABLE IF NOT EXISTS sr_community_board_managers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(60) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_managers_permission (board_id, account_id, permission_key),
    KEY idx_sr_community_board_managers_board_status (board_id, status),
    KEY idx_sr_community_board_managers_account_status (account_id, status)
);

UPDATE sr_modules
SET version = '2026.06.011',
    updated_at = NOW()
WHERE module_key = 'community';
