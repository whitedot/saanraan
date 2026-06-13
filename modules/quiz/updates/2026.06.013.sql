INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id,
       'skin_key',
       theme_setting.setting_value,
       'string',
       NOW(),
       NOW()
FROM sr_modules m
INNER JOIN sr_module_settings theme_setting
    ON theme_setting.module_id = m.id
   AND theme_setting.setting_key = 'theme_key'
LEFT JOIN sr_module_settings skin_setting
    ON skin_setting.module_id = m.id
   AND skin_setting.setting_key = 'skin_key'
WHERE m.module_key = 'quiz'
  AND theme_setting.setting_value IN ('card', 'focus')
  AND (skin_setting.setting_value IS NULL OR skin_setting.setting_value = '' OR skin_setting.setting_value = 'basic')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    value_type = VALUES(value_type),
    updated_at = VALUES(updated_at);

SET @sr_quiz_has_theme_key := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}quiz_sets'
      AND COLUMN_NAME = 'theme_key'
);

SET @sr_quiz_migrate_theme_sql := IF(
    @sr_quiz_has_theme_key > 0,
    'UPDATE {{SR_TABLE_PREFIX}}quiz_sets SET skin_key = theme_key WHERE theme_key IN (''card'', ''focus'') AND (skin_key = '''' OR skin_key = ''basic'')',
    'DO 0'
);
PREPARE sr_quiz_migrate_theme_stmt FROM @sr_quiz_migrate_theme_sql;
EXECUTE sr_quiz_migrate_theme_stmt;
DEALLOCATE PREPARE sr_quiz_migrate_theme_stmt;

SET @sr_quiz_drop_theme_sql := IF(
    @sr_quiz_has_theme_key > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets DROP COLUMN theme_key',
    'DO 0'
);
PREPARE sr_quiz_drop_theme_stmt FROM @sr_quiz_drop_theme_sql;
EXECUTE sr_quiz_drop_theme_stmt;
DEALLOCATE PREPARE sr_quiz_drop_theme_stmt;

DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'quiz'
  AND s.setting_key = 'theme_key';

UPDATE sr_modules
SET version = '2026.06.013',
    updated_at = NOW()
WHERE module_key = 'quiz';
