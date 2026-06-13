ALTER TABLE {{SR_TABLE_PREFIX}}community_posts
    MODIFY author_account_id BIGINT UNSIGNED NULL,
    ADD COLUMN guest_author_name VARCHAR(120) NOT NULL DEFAULT '' AFTER author_public_name_snapshot,
    ADD COLUMN guest_password_hash VARCHAR(255) NULL AFTER guest_author_name,
    ADD COLUMN guest_ip_hash CHAR(64) NULL AFTER guest_password_hash,
    ADD COLUMN guest_user_agent_hash CHAR(64) NULL AFTER guest_ip_hash,
    ADD KEY idx_sr_community_posts_guest_ip_id (guest_ip_hash, id);

ALTER TABLE {{SR_TABLE_PREFIX}}community_comments
    MODIFY author_account_id BIGINT UNSIGNED NULL,
    ADD COLUMN guest_author_name VARCHAR(120) NOT NULL DEFAULT '' AFTER author_public_name_snapshot,
    ADD COLUMN guest_password_hash VARCHAR(255) NULL AFTER guest_author_name,
    ADD COLUMN guest_ip_hash CHAR(64) NULL AFTER guest_password_hash,
    ADD COLUMN guest_user_agent_hash CHAR(64) NULL AFTER guest_ip_hash,
    ADD KEY idx_sr_community_comments_guest_ip_id (guest_ip_hash, id);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.022',
    updated_at = NOW()
WHERE module_key = 'community';
