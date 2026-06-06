CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}content_author_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    application_note TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_author_applications_account (account_id),
    KEY idx_sr_content_author_applications_status (status, updated_at)
);

ALTER TABLE {{SR_TABLE_PREFIX}}content_author_applications
    MODIFY account_id BIGINT UNSIGNED NULL;

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/author-applications',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path = '/admin/content/authors'
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.014',
    updated_at = NOW()
WHERE module_key = 'content';
