CREATE TABLE IF NOT EXISTS sr_member_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    remember_token_hash CHAR(64) NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_sessions_token (session_token_hash),
    KEY idx_sr_member_sessions_account (account_id),
    KEY idx_sr_member_sessions_expires (expires_at),
    KEY idx_sr_member_sessions_revoked (revoked_at)
);
