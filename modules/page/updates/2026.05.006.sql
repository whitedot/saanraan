CREATE TABLE IF NOT EXISTS sr_page_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_page_groups_group_key (group_key),
    KEY idx_sr_page_groups_status_sort (status, sort_order, id)
);

ALTER TABLE sr_pages
    ADD COLUMN page_group_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_sr_pages_group_status (page_group_id, status, updated_at);

ALTER TABLE sr_page_revisions
    ADD COLUMN page_group_id BIGINT UNSIGNED NULL AFTER page_id;

UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'page';
