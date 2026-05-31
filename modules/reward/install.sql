CREATE TABLE IF NOT EXISTS sr_reward_balances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reward_balances_account (account_id),
    KEY idx_sr_reward_balances_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS sr_reward_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT NOT NULL,
    transaction_type VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    reference_type VARCHAR(60) NOT NULL DEFAULT '',
    reference_id VARCHAR(120) NOT NULL DEFAULT '',
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_reward_transactions_account_created (account_id, created_at),
    KEY idx_sr_reward_transactions_reference (reference_type, reference_id),
    KEY idx_sr_reward_transactions_created_by (created_by_account_id),
    KEY idx_sr_reward_transactions_created (created_at)
);

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
