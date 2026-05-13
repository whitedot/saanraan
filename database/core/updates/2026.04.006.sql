CREATE TABLE IF NOT EXISTS sr_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(128) NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_sessions_session_id (session_id),
    KEY idx_sr_sessions_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS sr_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rate_key CHAR(64) NOT NULL,
    bucket VARCHAR(120) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_rate_limits_key (rate_key),
    KEY idx_sr_rate_limits_bucket_expires (bucket, expires_at),
    KEY idx_sr_rate_limits_expires (expires_at)
);
