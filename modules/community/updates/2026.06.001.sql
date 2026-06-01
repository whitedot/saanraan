CREATE TABLE IF NOT EXISTS sr_community_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    category_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_categories_board_key (board_id, category_key),
    KEY idx_sr_community_categories_board_status_sort (board_id, status, sort_order, id)
);

ALTER TABLE sr_community_posts
    ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER board_id;

ALTER TABLE sr_community_posts
    ADD KEY idx_sr_community_posts_board_category_status_id (board_id, category_id, status, id);

CREATE TABLE IF NOT EXISTS sr_community_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    owner_account_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    admin_note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    moderated_by BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_series_owner_status (owner_account_id, status, id),
    KEY idx_sr_community_series_board_status (board_id, status, id)
);

CREATE TABLE IF NOT EXISTS sr_community_series_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    active_post_id BIGINT UNSIGNED NULL,
    episode_label VARCHAR(120) NOT NULL DEFAULT '',
    item_status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_series_items_series_post (series_id, post_id),
    UNIQUE KEY uq_sr_community_series_items_active_post (active_post_id),
    KEY idx_sr_community_series_items_series_sort (series_id, item_status, sort_order, id)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/community/series',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/community/posts'
  AND action_key IN ('view', 'edit');

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'community';
