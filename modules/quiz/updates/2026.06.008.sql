ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets
    ADD COLUMN comments_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER member_group_keys_json;

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}quiz_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    author_account_id BIGINT UNSIGNED NULL,
    author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    body_text TEXT NOT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sr_quiz_comments_quiz_status_id (quiz_id, status, id),
    KEY idx_sr_quiz_comments_author_id (author_account_id, id),
    KEY idx_sr_quiz_comments_created (created_at, id)
);
