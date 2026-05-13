SET @schema_has_sites = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_sites'
);

SET @schema_has_site_settings_public = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_site_settings'
      AND COLUMN_NAME = 'is_public'
);

SET @schema_sql = IF(
    @schema_has_sites > 0 AND @schema_has_site_settings_public > 0,
    'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, is_public, created_at, updated_at)
     SELECT ''site.name'', name, ''string'', 1, NOW(), NOW()
     FROM sr_sites
     ORDER BY id ASC
     LIMIT 1
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_public = VALUES(is_public), updated_at = VALUES(updated_at)',
    IF(
        @schema_has_sites > 0,
        'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
         SELECT ''site.name'', name, ''string'', NOW(), NOW()
         FROM sr_sites
         ORDER BY id ASC
         LIMIT 1
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_sites > 0 AND @schema_has_site_settings_public > 0,
    'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, is_public, created_at, updated_at)
     SELECT ''site.base_url'', base_url, ''string'', 1, NOW(), NOW()
     FROM sr_sites
     ORDER BY id ASC
     LIMIT 1
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_public = VALUES(is_public), updated_at = VALUES(updated_at)',
    IF(
        @schema_has_sites > 0,
        'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
         SELECT ''site.base_url'', base_url, ''string'', NOW(), NOW()
         FROM sr_sites
         ORDER BY id ASC
         LIMIT 1
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_sites > 0 AND @schema_has_site_settings_public > 0,
    'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, is_public, created_at, updated_at)
     SELECT ''site.timezone'', timezone, ''string'', 0, NOW(), NOW()
     FROM sr_sites
     ORDER BY id ASC
     LIMIT 1
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_public = VALUES(is_public), updated_at = VALUES(updated_at)',
    IF(
        @schema_has_sites > 0,
        'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
         SELECT ''site.timezone'', timezone, ''string'', NOW(), NOW()
         FROM sr_sites
         ORDER BY id ASC
         LIMIT 1
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_sites > 0 AND @schema_has_site_settings_public > 0,
    'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, is_public, created_at, updated_at)
     SELECT ''site.default_locale'', default_locale, ''string'', 1, NOW(), NOW()
     FROM sr_sites
     ORDER BY id ASC
     LIMIT 1
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_public = VALUES(is_public), updated_at = VALUES(updated_at)',
    IF(
        @schema_has_sites > 0,
        'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
         SELECT ''site.default_locale'', default_locale, ''string'', NOW(), NOW()
         FROM sr_sites
         ORDER BY id ASC
         LIMIT 1
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_sites > 0 AND @schema_has_site_settings_public > 0,
    'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, is_public, created_at, updated_at)
     SELECT ''site.status'', status, ''string'', 1, NOW(), NOW()
     FROM sr_sites
     ORDER BY id ASC
     LIMIT 1
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), is_public = VALUES(is_public), updated_at = VALUES(updated_at)',
    IF(
        @schema_has_sites > 0,
        'INSERT INTO sr_site_settings (setting_key, setting_value, value_type, created_at, updated_at)
         SELECT ''site.status'', status, ''string'', NOW(), NOW()
         FROM sr_sites
         ORDER BY id ASC
         LIMIT 1
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

DROP TABLE IF EXISTS sr_sites;
