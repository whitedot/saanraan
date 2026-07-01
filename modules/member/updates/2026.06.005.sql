CREATE TABLE IF NOT EXISTS sr_member_mfa_factors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    factor_type VARCHAR(30) NOT NULL DEFAULT 'totp',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    secret_ciphertext TEXT NOT NULL,
    secret_fingerprint CHAR(64) NOT NULL DEFAULT '',
    issuer VARCHAR(120) NOT NULL DEFAULT '',
    label VARCHAR(190) NOT NULL DEFAULT '',
    last_used_step BIGINT NULL,
    activated_at DATETIME NULL,
    disabled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_mfa_factors_account_status (account_id, status, factor_type),
    KEY idx_sr_member_mfa_factors_fingerprint (secret_fingerprint)
);

CREATE TABLE IF NOT EXISTS sr_member_mfa_recovery_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    factor_id BIGINT UNSIGNED NULL,
    code_hash CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'unused',
    batch_uid VARCHAR(80) NOT NULL DEFAULT '',
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_mfa_recovery_account_hash (account_id, code_hash),
    KEY idx_sr_member_mfa_recovery_account_status (account_id, status),
    KEY idx_sr_member_mfa_recovery_factor (factor_id)
);

UPDATE sr_modules
SET version = '2026.06.005',
    updated_at = NOW()
WHERE module_key = 'member';
