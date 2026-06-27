CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_attachment_download_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    post_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    download_type VARCHAR(20) NOT NULL DEFAULT 'free',
    charge_policy VARCHAR(30) NOT NULL DEFAULT 'once',
    asset_module VARCHAR(120) NOT NULL DEFAULT '',
    amount BIGINT NOT NULL DEFAULT 0,
    asset_access_log_ids_json TEXT NULL,
    post_title_snapshot VARCHAR(160) NOT NULL DEFAULT '',
    attachment_original_name_snapshot VARCHAR(160) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_attachment_downloads_board (board_id, created_at),
    KEY idx_sr_community_attachment_downloads_post (post_id, created_at),
    KEY idx_sr_community_attachment_downloads_attachment (attachment_id, created_at),
    KEY idx_sr_community_attachment_downloads_account (account_id, created_at),
    KEY idx_sr_community_attachment_downloads_type (download_type, created_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.036',
    updated_at = NOW()
WHERE module_key = 'community';
