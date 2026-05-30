CREATE TABLE IF NOT EXISTS sr_content_file_download_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    download_type VARCHAR(20) NOT NULL DEFAULT 'free',
    charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    asset_module VARCHAR(60) NOT NULL DEFAULT '',
    amount BIGINT NOT NULL DEFAULT 0,
    asset_access_log_ids_json TEXT NULL,
    refund_status VARCHAR(20) NOT NULL DEFAULT '',
    refund_transaction_ids_json TEXT NULL,
    refund_note VARCHAR(255) NOT NULL DEFAULT '',
    refunded_by_account_id BIGINT UNSIGNED NULL,
    refunded_at DATETIME NULL,
    access_revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_content_file_downloads_content (content_id, created_at),
    KEY idx_sr_content_file_downloads_file (file_id, created_at),
    KEY idx_sr_content_file_downloads_account (account_id, created_at),
    KEY idx_sr_content_file_downloads_type (download_type, created_at),
    KEY idx_sr_content_file_downloads_refund (refund_status, refunded_at)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/file-downloads',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path IN ('/admin/content', '/admin/content/files')
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.05.017',
    updated_at = NOW()
WHERE module_key = 'content';
