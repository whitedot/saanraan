CREATE TABLE IF NOT EXISTS sr_content_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_key VARCHAR(60) NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_series_key (series_key),
    KEY idx_sr_content_series_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_content_series_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id BIGINT UNSIGNED NOT NULL,
    content_id BIGINT UNSIGNED NOT NULL,
    active_content_id BIGINT UNSIGNED NULL,
    episode_label VARCHAR(120) NOT NULL DEFAULT '',
    item_status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_content_series_items_series_content (series_id, content_id),
    UNIQUE KEY uq_sr_content_series_items_active_content (active_content_id),
    KEY idx_sr_content_series_items_series_sort (series_id, item_status, sort_order, id)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/series',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/content'
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'content';
