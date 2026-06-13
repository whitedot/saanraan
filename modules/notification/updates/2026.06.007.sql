SET @sr_notification_delivery_table = '{{SR_TABLE_PREFIX}}notification_deliveries';

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_delivery_table AND COLUMN_NAME = 'locked_at') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_delivery_table, '` ADD COLUMN locked_at DATETIME NULL AFTER attempted_at'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_delivery_table AND COLUMN_NAME = 'locked_by') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_delivery_table, '` ADD COLUMN locked_by VARCHAR(80) NOT NULL DEFAULT '''' AFTER locked_at'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_delivery_table AND COLUMN_NAME = 'attempt_count') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_delivery_table, '` ADD COLUMN attempt_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER locked_by'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_delivery_table AND COLUMN_NAME = 'next_attempt_at') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_delivery_table, '` ADD COLUMN next_attempt_at DATETIME NULL AFTER attempt_count'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

SET @sr_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @sr_notification_delivery_table AND INDEX_NAME = 'idx_sr_notification_deliveries_runner') = 0,
    CONCAT('ALTER TABLE `', @sr_notification_delivery_table, '` ADD KEY idx_sr_notification_deliveries_runner (status, next_attempt_at, locked_at, id)'),
    'SELECT 1'
);
PREPARE sr_stmt FROM @sr_sql;
EXECUTE sr_stmt;
DEALLOCATE PREPARE sr_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.007',
    updated_at = NOW()
WHERE module_key = 'notification';
