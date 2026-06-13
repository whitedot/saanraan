SET @sr_survey_has_theme_key := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'theme_key'
);

SET @sr_survey_drop_theme_sql := IF(
    @sr_survey_has_theme_key > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms DROP COLUMN theme_key',
    'DO 0'
);
PREPARE sr_survey_drop_theme_stmt FROM @sr_survey_drop_theme_sql;
EXECUTE sr_survey_drop_theme_stmt;
DEALLOCATE PREPARE sr_survey_drop_theme_stmt;

DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'survey'
  AND s.setting_key = 'theme_key';

UPDATE sr_modules
SET version = '2026.06.010',
    updated_at = NOW()
WHERE module_key = 'survey';
