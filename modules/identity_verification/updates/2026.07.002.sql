CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}identity_verification_identity_locks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ci_hash CHAR(64) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    result_id BIGINT UNSIGNED NOT NULL,
    first_linked_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_identity_lock_ci_hash (ci_hash),
    KEY idx_sr_identity_lock_account (account_id, updated_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'identity_verification';
