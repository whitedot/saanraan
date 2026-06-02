CREATE TABLE IF NOT EXISTS sr_point_balances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_point_balances_account (account_id),
    KEY idx_sr_point_balances_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS sr_point_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT NOT NULL,
    transaction_type VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    reference_type VARCHAR(60) NOT NULL DEFAULT '',
    reference_id VARCHAR(120) NOT NULL DEFAULT '',
    created_by_account_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    expires_remaining BIGINT UNSIGNED NOT NULL DEFAULT 0,
    expired_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_point_transactions_account_created (account_id, created_at),
    KEY idx_sr_point_transactions_expiration (expires_at, expires_remaining),
    KEY idx_sr_point_transactions_reference (reference_type, reference_id),
    KEY idx_sr_point_transactions_created_by (created_by_account_id),
    KEY idx_sr_point_transactions_created (created_at)
);

CREATE TABLE IF NOT EXISTS sr_point_expiration_consumptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    consume_transaction_id BIGINT UNSIGNED NOT NULL,
    source_transaction_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    source_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_point_expiration_consumptions_consume (consume_transaction_id, id),
    KEY idx_sr_point_expiration_consumptions_source (source_transaction_id, id),
    KEY idx_sr_point_expiration_consumptions_account_created (account_id, created_at)
);
