CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_report_auto_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(30) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    active_target_uid VARCHAR(80) NULL,
    source_report_id BIGINT UNSIGNED NULL,
    action_key VARCHAR(40) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    target_before_status VARCHAR(30) NOT NULL DEFAULT '',
    target_hidden_at_snapshot DATETIME NULL,
    target_hidden_reason VARCHAR(40) NOT NULL DEFAULT '',
    target_hidden_by_account_id BIGINT UNSIGNED NULL,
    threshold_value INT UNSIGNED NOT NULL DEFAULT 0,
    total_reporter_count INT UNSIGNED NOT NULL DEFAULT 0,
    eligible_reporter_count INT UNSIGNED NOT NULL DEFAULT 0,
    excluded_reporter_count INT UNSIGNED NOT NULL DEFAULT 0,
    excluded_report_count INT UNSIGNED NOT NULL DEFAULT 0,
    abuse_guard_summary_json TEXT NULL,
    settings_snapshot_json TEXT NULL,
    failure_reason VARCHAR(80) NOT NULL DEFAULT '',
    metadata_json TEXT NULL,
    applied_at DATETIME NULL,
    released_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewer_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_report_auto_actions_active_target (active_target_uid),
    KEY idx_sr_community_report_auto_actions_target_status (target_type, target_id, status),
    KEY idx_sr_community_report_auto_actions_status_updated (status, updated_at),
    KEY idx_sr_community_report_auto_actions_source_report (source_report_id),
    KEY idx_sr_community_report_auto_actions_reviewer (reviewer_account_id)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.046',
    updated_at = NOW()
WHERE module_key = 'community';
