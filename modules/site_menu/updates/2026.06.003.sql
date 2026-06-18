CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}site_menu_draft_menus (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_key VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_site_menu_draft_menus_key (menu_key),
    KEY idx_sr_site_menu_draft_menus_status (status)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}site_menu_draft_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon_name VARCHAR(80) NOT NULL DEFAULT '',
    target VARCHAR(20) NOT NULL DEFAULT 'self',
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_site_menu_draft_items_menu (menu_id, status, sort_order, id),
    KEY idx_sr_site_menu_draft_items_parent (parent_id)
);

INSERT INTO {{SR_TABLE_PREFIX}}site_menu_draft_menus (id, menu_key, label, status, created_at, updated_at)
SELECT id, menu_key, label, status, created_at, updated_at
FROM {{SR_TABLE_PREFIX}}site_menus m
ON DUPLICATE KEY UPDATE
    menu_key = VALUES(menu_key),
    label = VALUES(label),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO {{SR_TABLE_PREFIX}}site_menu_draft_items (id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, updated_at)
SELECT i.id, i.menu_id, i.parent_id, i.label, i.url, i.icon_name, i.target, i.status, i.sort_order, i.created_at, i.updated_at
FROM {{SR_TABLE_PREFIX}}site_menu_items i
ON DUPLICATE KEY UPDATE
    menu_id = VALUES(menu_id),
    parent_id = VALUES(parent_id),
    label = VALUES(label),
    url = VALUES(url),
    icon_name = VALUES(icon_name),
    target = VALUES(target),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.003',
    updated_at = NOW()
WHERE module_key = 'site_menu';
