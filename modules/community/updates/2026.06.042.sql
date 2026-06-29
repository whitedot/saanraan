DELETE s
FROM {{SR_TABLE_PREFIX}}module_settings s
INNER JOIN {{SR_TABLE_PREFIX}}modules m ON m.id = s.module_id
WHERE m.module_key = 'community'
  AND s.setting_key IN (
      'layout_secondary_menu_key',
      'layout_tertiary_menu_key',
      'layout_quaternary_menu_key',
      'layout_quinary_menu_key'
  );

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.042',
    updated_at = NOW()
WHERE module_key = 'community';
