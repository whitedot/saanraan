CREATE TABLE IF NOT EXISTS sr_reward_withdrawal_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    bank_name VARCHAR(80) NOT NULL,
    bank_account_number VARCHAR(80) NOT NULL,
    bank_account_holder VARCHAR(80) NOT NULL,
    requester_note VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NOT NULL DEFAULT '',
    transaction_id BIGINT UNSIGNED NULL,
    processed_by_account_id BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_reward_withdrawal_requests_account_status (account_id, status),
    KEY idx_sr_reward_withdrawal_requests_status_requested (status, requested_at),
    KEY idx_sr_reward_withdrawal_requests_transaction (transaction_id),
    KEY idx_sr_reward_withdrawal_requests_processed_by (processed_by_account_id)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/rewards/withdrawal-requests',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/rewards/balances'
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'reward';
