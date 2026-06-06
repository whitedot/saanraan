CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NULL,
    content_group_id BIGINT UNSIGNED NULL,
    author_account_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL DEFAULT '',
    title VARCHAR(160) NOT NULL,
    summary TEXT NULL,
    body_text MEDIUMTEXT NOT NULL,
    body_format VARCHAR(20) NOT NULL DEFAULT 'plain',
    review_status VARCHAR(30) NOT NULL DEFAULT 'member_draft',
    publish_target_status VARCHAR(30) NOT NULL DEFAULT 'published',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_content_submissions_author_status (author_account_id, review_status, updated_at),
    KEY idx_sr_content_submissions_status_updated (review_status, updated_at),
    KEY idx_sr_content_submissions_content (content_id, id),
    KEY idx_sr_content_submissions_group (content_group_id, review_status, updated_at)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_author_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'allowed',
    review_required_override VARCHAR(20) NOT NULL DEFAULT 'inherit',
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_author_permissions_account (account_id),
    KEY idx_sr_content_author_permissions_status (status, updated_at)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_author_reward_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    author_account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    amount BIGINT NOT NULL DEFAULT 0,
    transaction_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    failure_reason TEXT NULL,
    dedupe_key VARCHAR(191) NOT NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_author_reward_dedupe (dedupe_key),
    KEY idx_sr_content_author_reward_author (author_account_id, created_at),
    KEY idx_sr_content_author_reward_submission (submission_id),
    KEY idx_sr_content_author_reward_content (content_id),
    KEY idx_sr_content_author_reward_status (status, updated_at)
);

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/submissions',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/content'
  AND action_key IN ('view', 'edit');

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/authors',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/content/settings'
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.013',
    updated_at = NOW()
WHERE module_key = 'content';
