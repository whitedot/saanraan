ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets
    ADD COLUMN starts_at DATETIME NULL AFTER pass_score,
    ADD COLUMN ends_at DATETIME NULL AFTER starts_at,
    ADD COLUMN member_group_keys_json LONGTEXT NULL AFTER attempt_limit_period_seconds,
    ADD KEY idx_sr_quiz_sets_status_dates (status, starts_at, ends_at);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'quiz';
