INSERT INTO sr_site_menus (menu_key, label, status, created_at, updated_at)
VALUES ('header', '헤더 메뉴', 'enabled', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    updated_at = updated_at;

INSERT INTO sr_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '홈', '/', 'self', 'enabled', 10, NOW(), NOW()
FROM sr_site_menus m
WHERE m.menu_key = 'header'
  AND NOT EXISTS (
      SELECT 1
      FROM sr_site_menu_items existing
      WHERE existing.menu_id = m.id
  )
ON DUPLICATE KEY UPDATE
    updated_at = updated_at;

INSERT INTO sr_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '로그인', '/login', 'self', 'enabled', 20, NOW(), NOW()
FROM sr_site_menus m
WHERE m.menu_key = 'header'
  AND NOT EXISTS (
      SELECT 1
      FROM sr_site_menu_items existing
      WHERE existing.menu_id = m.id
  )
ON DUPLICATE KEY UPDATE
    updated_at = updated_at;

INSERT INTO sr_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
SELECT m.id, NULL, '회원가입', '/register', 'self', 'enabled', 30, NOW(), NOW()
FROM sr_site_menus m
WHERE m.menu_key = 'header'
  AND NOT EXISTS (
      SELECT 1
      FROM sr_site_menu_items existing
      WHERE existing.menu_id = m.id
  )
ON DUPLICATE KEY UPDATE
    updated_at = updated_at;
