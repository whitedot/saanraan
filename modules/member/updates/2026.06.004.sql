CREATE TABLE IF NOT EXISTS sr_member_follows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    follower_account_id BIGINT UNSIGNED NOT NULL,
    following_account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_follows_pair (follower_account_id, following_account_id),
    KEY idx_sr_member_follows_follower_status (follower_account_id, status, following_account_id),
    KEY idx_sr_member_follows_following_status (following_account_id, status, follower_account_id)
);

UPDATE sr_modules
SET version = '2026.06.004',
    updated_at = NOW()
WHERE module_key = 'member';
