CREATE TABLE IF NOT EXISTS sr_community_hidden_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(20) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    hidden_at DATETIME NOT NULL,
    hidden_until DATETIME NULL,
    hidden_reason VARCHAR(40) NOT NULL DEFAULT '',
    hidden_note TEXT NULL,
    hidden_by_account_id BIGINT UNSIGNED NULL,
    hidden_before_status VARCHAR(30) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_hidden_targets_target (target_type, target_id),
    KEY idx_sr_community_hidden_targets_actor (hidden_by_account_id, id),
    KEY idx_sr_community_hidden_targets_until (hidden_until)
);

INSERT INTO sr_community_hidden_targets
    (target_type, target_id, hidden_at, hidden_until, hidden_reason, hidden_note, hidden_by_account_id, hidden_before_status, created_at, updated_at)
SELECT 'post', id, COALESCE(hidden_at, updated_at), hidden_until, hidden_reason, hidden_note, hidden_by_account_id,
       CASE WHEN hidden_before_status <> '' THEN hidden_before_status ELSE 'published' END,
       COALESCE(hidden_at, updated_at), updated_at
FROM sr_community_posts
WHERE status = 'hidden'
ON DUPLICATE KEY UPDATE
    hidden_at = VALUES(hidden_at),
    hidden_until = VALUES(hidden_until),
    hidden_reason = VALUES(hidden_reason),
    hidden_note = VALUES(hidden_note),
    hidden_by_account_id = VALUES(hidden_by_account_id),
    hidden_before_status = VALUES(hidden_before_status),
    updated_at = VALUES(updated_at);

INSERT INTO sr_community_hidden_targets
    (target_type, target_id, hidden_at, hidden_until, hidden_reason, hidden_note, hidden_by_account_id, hidden_before_status, created_at, updated_at)
SELECT 'comment', id, COALESCE(hidden_at, updated_at), hidden_until, hidden_reason, hidden_note, hidden_by_account_id,
       CASE WHEN hidden_before_status <> '' THEN hidden_before_status ELSE 'published' END,
       COALESCE(hidden_at, updated_at), updated_at
FROM sr_community_comments
WHERE status = 'hidden'
ON DUPLICATE KEY UPDATE
    hidden_at = VALUES(hidden_at),
    hidden_until = VALUES(hidden_until),
    hidden_reason = VALUES(hidden_reason),
    hidden_note = VALUES(hidden_note),
    hidden_by_account_id = VALUES(hidden_by_account_id),
    hidden_before_status = VALUES(hidden_before_status),
    updated_at = VALUES(updated_at);

ALTER TABLE sr_community_posts
    DROP COLUMN reaction_preset_key,
    DROP COLUMN reaction_comment_preset_key,
    DROP COLUMN hidden_at,
    DROP COLUMN hidden_until,
    DROP COLUMN hidden_reason,
    DROP COLUMN hidden_note,
    DROP COLUMN hidden_by_account_id,
    DROP COLUMN hidden_before_status;

ALTER TABLE sr_community_comments
    DROP COLUMN hidden_at,
    DROP COLUMN hidden_until,
    DROP COLUMN hidden_reason,
    DROP COLUMN hidden_note,
    DROP COLUMN hidden_by_account_id,
    DROP COLUMN hidden_before_status;
