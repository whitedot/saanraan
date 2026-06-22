CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}member_profile_field_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(60) NOT NULL,
    label_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    field_type_snapshot VARCHAR(30) NOT NULL DEFAULT 'text',
    visibility_snapshot VARCHAR(30) NOT NULL DEFAULT 'public',
    show_on_profile_snapshot TINYINT(1) NOT NULL DEFAULT 1,
    show_in_admin_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    privacy_purpose_snapshot VARCHAR(255) NOT NULL DEFAULT '',
    export_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'include',
    cleanup_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'anonymize',
    value_text TEXT NULL,
    value_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_profile_field_value_key (account_id, field_key),
    KEY idx_sr_member_profile_field_values_account (account_id),
    KEY idx_sr_member_profile_field_values_key (field_key)
);

SET @schema_has_member_profiles_is_adult = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = 'is_adult'
);

SET @schema_sql = IF(
    @schema_has_member_profiles_is_adult = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles ADD COLUMN is_adult TINYINT(1) NULL AFTER birth_date',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

DELETE FROM {{SR_TABLE_PREFIX}}module_settings
WHERE module_id IN (
    SELECT id
    FROM {{SR_TABLE_PREFIX}}modules
    WHERE module_key = 'member'
)
  AND setting_key IN (
    'profile_phone_enabled',
    'profile_phone_required',
    'profile_text_enabled',
    'profile_text_required'
  );

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.003',
    updated_at = NOW()
WHERE module_key = 'member';
