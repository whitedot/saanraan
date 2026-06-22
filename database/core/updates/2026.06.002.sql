INSERT IGNORE INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
VALUES
    ('site.title_suffix', '', 'string', NOW(), NOW()),
    ('site.meta_description', '', 'string', NOW(), NOW()),
    ('site.og_image', '', 'string', NOW(), NOW());

UPDATE sr_site_settings ss
INNER JOIN sr_module_settings ms ON ms.setting_key = 'title_suffix'
INNER JOIN sr_modules m ON m.id = ms.module_id AND m.module_key = 'seo'
SET ss.setting_value = ms.setting_value,
    ss.value_type = 'string',
    ss.updated_at = NOW()
WHERE ss.setting_key = 'site.title_suffix'
  AND TRIM(COALESCE(ss.setting_value, '')) = ''
  AND TRIM(COALESCE(ms.setting_value, '')) <> '';

UPDATE sr_site_settings ss
INNER JOIN sr_module_settings ms ON ms.setting_key = 'default_description'
INNER JOIN sr_modules m ON m.id = ms.module_id AND m.module_key = 'seo'
SET ss.setting_value = ms.setting_value,
    ss.value_type = 'string',
    ss.updated_at = NOW()
WHERE ss.setting_key = 'site.meta_description'
  AND TRIM(COALESCE(ss.setting_value, '')) = ''
  AND TRIM(COALESCE(ms.setting_value, '')) <> '';

UPDATE sr_site_settings ss
INNER JOIN sr_module_settings ms ON ms.setting_key = 'default_og_image'
INNER JOIN sr_modules m ON m.id = ms.module_id AND m.module_key = 'seo'
SET ss.setting_value = ms.setting_value,
    ss.value_type = 'string',
    ss.updated_at = NOW()
WHERE ss.setting_key = 'site.og_image'
  AND TRIM(COALESCE(ss.setting_value, '')) = ''
  AND TRIM(COALESCE(ms.setting_value, '')) <> '';
