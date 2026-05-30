UPDATE sr_site_menus
SET label = '상단 메뉴',
    updated_at = NOW()
WHERE menu_key = 'header'
  AND label = '헤더 메뉴';

UPDATE sr_modules
SET version = '2026.05.001',
    updated_at = NOW()
WHERE module_key = 'site_menu';
