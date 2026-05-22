SET @schema_has_banner_click_count = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}banners'
      AND COLUMN_NAME = 'click_count'
);

SET @schema_sql = IF(
    @schema_has_banner_click_count = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}banners ADD COLUMN click_count BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER sort_order',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

CREATE TABLE IF NOT EXISTS sr_banner_clicks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id BIGINT UNSIGNED NOT NULL,
    click_key_hash CHAR(64) NOT NULL,
    clicked_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_banner_clicks_unique (banner_id, click_key_hash),
    KEY idx_sr_banner_clicks_banner_clicked (banner_id, clicked_at)
);

UPDATE sr_modules
SET version = '2026.04.002',
    updated_at = NOW()
WHERE module_key = 'banner';
