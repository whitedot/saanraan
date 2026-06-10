ALTER TABLE sr_community_posts
    ADD COLUMN hidden_at DATETIME NULL AFTER status,
    ADD COLUMN hidden_until DATETIME NULL AFTER hidden_at,
    ADD COLUMN hidden_reason VARCHAR(40) NOT NULL DEFAULT '' AFTER hidden_until,
    ADD COLUMN hidden_note TEXT NULL AFTER hidden_reason,
    ADD COLUMN hidden_by_account_id BIGINT UNSIGNED NULL AFTER hidden_note,
    ADD COLUMN hidden_before_status VARCHAR(30) NOT NULL DEFAULT '' AFTER hidden_by_account_id;

ALTER TABLE sr_community_comments
    ADD COLUMN hidden_at DATETIME NULL AFTER status,
    ADD COLUMN hidden_until DATETIME NULL AFTER hidden_at,
    ADD COLUMN hidden_reason VARCHAR(40) NOT NULL DEFAULT '' AFTER hidden_until,
    ADD COLUMN hidden_note TEXT NULL AFTER hidden_reason,
    ADD COLUMN hidden_by_account_id BIGINT UNSIGNED NULL AFTER hidden_note,
    ADD COLUMN hidden_before_status VARCHAR(30) NOT NULL DEFAULT '' AFTER hidden_by_account_id;

UPDATE sr_modules
SET version = '2026.06.017',
    updated_at = NOW()
WHERE module_key = 'community';
