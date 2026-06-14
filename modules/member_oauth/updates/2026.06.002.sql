UPDATE {{SR_TABLE_PREFIX}}member_oauth_accounts
SET provider_subject_display = CONCAT('subject:', LEFT(provider_subject_hash, 12))
WHERE provider_subject_hash <> ''
  AND provider_subject_display <> CONCAT('subject:', LEFT(provider_subject_hash, 12));

UPDATE {{SR_TABLE_PREFIX}}member_oauth_states
SET provider_subject_display = CONCAT('subject:', LEFT(provider_subject_hash, 12))
WHERE provider_subject_hash <> ''
  AND provider_subject_display <> CONCAT('subject:', LEFT(provider_subject_hash, 12));

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'member_oauth';
