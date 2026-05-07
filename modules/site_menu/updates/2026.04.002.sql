DELETE i
FROM toy_site_menu_items i
INNER JOIN toy_site_menu_items kept
    ON kept.menu_id = i.menu_id
    AND kept.url = i.url
    AND kept.id < i.id;

ALTER TABLE toy_site_menu_items
    ADD UNIQUE KEY uq_toy_site_menu_items_menu_url (menu_id, url);

INSERT INTO toy_site_menus (menu_key, label, status, created_at, updated_at)
VALUES ('header', '헤더 메뉴', 'enabled', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    status = VALUES(status),
    updated_at = VALUES(updated_at);

INSERT INTO toy_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '홈', '/', 'self', 'enabled', 10, NOW(), NOW()
FROM toy_site_menus m
WHERE m.menu_key = 'header'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    target = VALUES(target),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

INSERT INTO toy_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '로그인', '/login', 'self', 'enabled', 20, NOW(), NOW()
FROM toy_site_menus m
WHERE m.menu_key = 'header'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    target = VALUES(target),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

INSERT INTO toy_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '회원가입', '/register', 'self', 'enabled', 30, NOW(), NOW()
FROM toy_site_menus m
WHERE m.menu_key = 'header'
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    target = VALUES(target),
    status = VALUES(status),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);
