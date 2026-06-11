CREATE TABLE IF NOT EXISTS sr_community_submission_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    action_key VARCHAR(60) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    consent_title_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    consent_body_snapshot TEXT NULL,
    consent_version_snapshot VARCHAR(60) NOT NULL DEFAULT '',
    consent_required TINYINT(1) NOT NULL DEFAULT 1,
    consent_accepted TINYINT(1) NOT NULL DEFAULT 1,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_submission_consents_account (account_id, id),
    KEY idx_sr_community_submission_consents_subject (subject_type, subject_id, action_key),
    KEY idx_sr_community_submission_consents_board (board_id, created_at)
);

UPDATE sr_modules
SET version = '2026.06.019',
    updated_at = NOW()
WHERE module_key = 'community';
