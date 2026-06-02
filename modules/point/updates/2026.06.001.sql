SET @schema_sql = IF(
    NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{{SR_TABLE_PREFIX}}point_transactions'
          AND COLUMN_NAME = 'expires_at'
    ),
    'ALTER TABLE {{SR_TABLE_PREFIX}}point_transactions ADD COLUMN expires_at DATETIME NULL AFTER created_by_account_id',
    'SELECT 1'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{{SR_TABLE_PREFIX}}point_transactions'
          AND COLUMN_NAME = 'expires_remaining'
    ),
    'ALTER TABLE {{SR_TABLE_PREFIX}}point_transactions ADD COLUMN expires_remaining BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER expires_at',
    'SELECT 1'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{{SR_TABLE_PREFIX}}point_transactions'
          AND COLUMN_NAME = 'expired_at'
    ),
    'ALTER TABLE {{SR_TABLE_PREFIX}}point_transactions ADD COLUMN expired_at DATETIME NULL AFTER expires_remaining',
    'SELECT 1'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '{{SR_TABLE_PREFIX}}point_transactions'
          AND INDEX_NAME = 'idx_sr_point_transactions_expiration'
    ),
    'ALTER TABLE {{SR_TABLE_PREFIX}}point_transactions ADD KEY idx_sr_point_transactions_expiration (expires_at, expires_remaining)',
    'SELECT 1'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}point_expiration_consumptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    consume_transaction_id BIGINT UNSIGNED NOT NULL,
    source_transaction_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    source_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_point_expiration_consumptions_consume (consume_transaction_id, id),
    KEY idx_sr_point_expiration_consumptions_source (source_transaction_id, id),
    KEY idx_sr_point_expiration_consumptions_account_created (account_id, created_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.001',
    updated_at = NOW()
WHERE module_key = 'point';
