CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guard_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL DEFAULT '',
    source_id BIGINT UNSIGNED NULL,
    guard_type VARCHAR(40) NOT NULL,
    trigger_reason VARCHAR(80) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    released_at DATETIME NULL,
    reviewer_account_id BIGINT UNSIGNED NULL,
    trigger_fingerprint VARCHAR(160) NOT NULL DEFAULT '',
    snapshot_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_account_guard_events_account_status (account_id, status, expires_at),
    KEY idx_sr_community_account_guard_events_source (source_type, source_id),
    KEY idx_sr_community_account_guard_events_reviewer (reviewer_account_id),
    KEY idx_sr_community_account_guard_events_fingerprint (account_id, guard_type, trigger_fingerprint)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    guard_type VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    active_guard_uid VARCHAR(100) NULL,
    source_event_id BIGINT UNSIGNED NULL,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    released_at DATETIME NULL,
    reviewer_account_id BIGINT UNSIGNED NULL,
    snapshot_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_account_guards_active_uid (active_guard_uid),
    KEY idx_sr_community_account_guards_account_status (account_id, status, expires_at),
    KEY idx_sr_community_account_guards_source_event (source_event_id),
    KEY idx_sr_community_account_guards_reviewer (reviewer_account_id)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guard_locks (
    account_id BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (account_id)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.047',
    updated_at = NOW()
WHERE module_key = 'community';
