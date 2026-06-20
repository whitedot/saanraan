UPDATE {{SR_TABLE_PREFIX}}banner_targets
SET point_key = 'site.layout',
    slot_key = 'before_layout'
WHERE module_key = 'core'
  AND point_key = 'site.home'
  AND slot_key = 'before_content';

UPDATE {{SR_TABLE_PREFIX}}banner_targets
SET point_key = 'site.layout',
    slot_key = 'after_layout'
WHERE module_key = 'core'
  AND point_key = 'site.home'
  AND slot_key = 'after_content';

UPDATE {{SR_TABLE_PREFIX}}module_settings
SET setting_value = 'core|site.layout|before_layout',
    updated_at = NOW()
WHERE setting_key = 'banner_default_target_option'
  AND setting_value IN ('__public__', 'core|site.home|before_content')
  AND module_id IN (
      SELECT id
      FROM {{SR_TABLE_PREFIX}}modules
      WHERE module_key = 'banner'
  );

UPDATE {{SR_TABLE_PREFIX}}module_settings
SET setting_value = 'core|site.layout|after_layout',
    updated_at = NOW()
WHERE setting_key = 'banner_default_target_option'
  AND setting_value = 'core|site.home|after_content'
  AND module_id IN (
      SELECT id
      FROM {{SR_TABLE_PREFIX}}modules
      WHERE module_key = 'banner'
  );

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'banner';
