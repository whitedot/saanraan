SET @schema_has_member_profiles_profile_image_path = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = 'profile_image_path'
);
SET @schema_has_member_profiles_avatar_path = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = 'avatar_path'
);
SET @schema_sql = IF(
    @schema_has_member_profiles_profile_image_path = 0 AND @schema_has_member_profiles_avatar_path > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles CHANGE COLUMN avatar_path profile_image_path VARCHAR(255) NOT NULL DEFAULT ''''',
    IF(
        @schema_has_member_profiles_profile_image_path = 0,
        'ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles ADD COLUMN profile_image_path VARCHAR(255) NOT NULL DEFAULT '''' AFTER is_adult',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy.module_id, 'profile_image_enabled', legacy.setting_value, legacy.value_type, legacy.created_at, legacy.updated_at
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key = 'profile_avatar_enabled'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy.module_id, 'profile_image_required', legacy.setting_value, legacy.value_type, legacy.created_at, legacy.updated_at
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key = 'profile_avatar_required'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy.module_id, 'profile_image_size_small', legacy.setting_value, legacy.value_type, legacy.created_at, legacy.updated_at
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key = 'profile_avatar_size_small'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy.module_id, 'profile_image_size_medium', legacy.setting_value, legacy.value_type, legacy.created_at, legacy.updated_at
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key = 'profile_avatar_size_medium'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy.module_id, 'profile_image_size_large', legacy.setting_value, legacy.value_type, legacy.created_at, legacy.updated_at
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key = 'profile_avatar_size_large'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

DELETE legacy
FROM {{SR_TABLE_PREFIX}}module_settings AS legacy
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = legacy.module_id
WHERE m.module_key = 'member'
  AND legacy.setting_key IN (
      'profile_avatar_enabled',
      'profile_avatar_required',
      'profile_avatar_size_small',
      'profile_avatar_size_medium',
      'profile_avatar_size_large'
  );

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'member';
