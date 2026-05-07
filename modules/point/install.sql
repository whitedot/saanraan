CREATE TABLE IF NOT EXISTS toy_point_balances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_point_balances_account (account_id),
    KEY idx_toy_point_balances_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS toy_point_transactions (
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
    KEY idx_toy_point_transactions_account_created (account_id, created_at),
    KEY idx_toy_point_transactions_reference (reference_type, reference_id),
    KEY idx_toy_point_transactions_created_by (created_by_account_id),
    KEY idx_toy_point_transactions_created (created_at)
);
