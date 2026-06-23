CREATE TABLE IF NOT EXISTS sr_asset_recovery_failures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dedupe_key VARCHAR(190) NOT NULL,
    source_module VARCHAR(40) NOT NULL,
    source_log_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    original_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    subject_type VARCHAR(80) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    grant_event_key VARCHAR(100) NOT NULL,
    reversal_event_key VARCHAR(100) NOT NULL,
    operation_event_key VARCHAR(100) NOT NULL DEFAULT '',
    attempted_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    recovered_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unrecovered_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    failure_reason VARCHAR(40) NOT NULL DEFAULT 'balance_low',
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    actor_account_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(30) NOT NULL DEFAULT '',
    operation_context_json TEXT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_attempted_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_asset_recovery_dedupe (dedupe_key),
    KEY idx_sr_asset_recovery_source (source_module, source_log_id),
    KEY idx_sr_asset_recovery_account (account_id, status, updated_at),
    KEY idx_sr_asset_recovery_subject (subject_type, subject_id),
    KEY idx_sr_asset_recovery_status (status, updated_at)
);

CREATE TABLE IF NOT EXISTS sr_asset_recovery_reversal_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    failure_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    reversal_transaction_id BIGINT UNSIGNED NOT NULL,
    recovered_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_asset_recovery_reversal_link (failure_id, asset_module, reversal_transaction_id),
    KEY idx_sr_asset_recovery_reversal_failure (failure_id, created_at)
);

SET @sr_asset_recovery_has_community_failures = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_asset_recovery_failures'
);
SET @sr_asset_recovery_backfill_sql = IF(
    @sr_asset_recovery_has_community_failures = 1,
    'INSERT IGNORE INTO {{SR_TABLE_PREFIX}}asset_recovery_failures
        (dedupe_key, source_module, source_log_id, asset_module, account_id, original_transaction_id,
         subject_type, subject_id, grant_event_key, reversal_event_key, operation_event_key,
         attempted_amount, recovered_amount, unrecovered_amount, failure_reason, status,
         actor_account_id, actor_type, operation_context_json, attempt_count, version,
         created_at, updated_at, last_attempted_at, resolved_at)
     SELECT
        CONCAT(''source:community:'', original_asset_log_id, '':rev:'', CASE WHEN subject_type = ''community.comment'' THEN ''community.comment.reward_reversal'' ELSE ''community.post.reward_reversal'' END),
        ''community'',
        original_asset_log_id,
        asset_module,
        account_id,
        original_transaction_id,
        subject_type,
        subject_id,
        CASE WHEN subject_type = ''community.comment'' THEN ''community.comment.reward_grant'' ELSE ''community.post.reward_grant'' END,
        CASE WHEN subject_type = ''community.comment'' THEN ''community.comment.reward_reversal'' ELSE ''community.post.reward_reversal'' END,
        operation_event_key,
        attempted_amount,
        recovered_amount,
        unrecovered_amount,
        CASE WHEN failure_reason = ''resolved'' THEN ''recovered'' ELSE failure_reason END,
        CASE WHEN status = ''resolved'' THEN ''recovered'' ELSE status END,
        actor_account_id,
        actor_type,
        operation_context_json,
        attempt_count,
        1,
        created_at,
        updated_at,
        last_attempted_at,
        resolved_at
      FROM {{SR_TABLE_PREFIX}}community_asset_recovery_failures
      WHERE attempted_amount > 0',
    'DO 0'
);
PREPARE sr_asset_recovery_backfill_stmt FROM @sr_asset_recovery_backfill_sql;
EXECUTE sr_asset_recovery_backfill_stmt;
DEALLOCATE PREPARE sr_asset_recovery_backfill_stmt;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'asset_ledger';
