SET @schema_has_survey_forms_project_brief = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'project_brief'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_project_brief = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN project_brief TEXT NULL AFTER recruitment_method',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_sponsor_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'sponsor_name'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_sponsor_name = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN sponsor_name VARCHAR(190) NOT NULL DEFAULT '''' AFTER project_brief',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_research_region = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'research_region'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_research_region = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN research_region VARCHAR(120) NOT NULL DEFAULT '''' AFTER sponsor_name',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_research_language = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'research_language'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_research_language = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN research_language VARCHAR(60) NOT NULL DEFAULT '''' AFTER research_region',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_fieldwork_method = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'fieldwork_method'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_fieldwork_method = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN fieldwork_method VARCHAR(120) NOT NULL DEFAULT '''' AFTER research_language',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_sample_frame = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'sample_frame'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_sample_frame = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN sample_frame TEXT NULL AFTER fieldwork_method',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_sample_method = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'sample_method'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_sample_method = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN sample_method VARCHAR(190) NOT NULL DEFAULT '''' AFTER sample_frame',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_target_sample_size = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'target_sample_size'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_target_sample_size = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN target_sample_size INT UNSIGNED NULL AFTER sample_method',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_quota_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'quota_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_quota_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN quota_policy TEXT NULL AFTER target_sample_size',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_response_rate_basis = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'response_rate_basis'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_response_rate_basis = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN response_rate_basis TEXT NULL AFTER quota_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_analysis_plan = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'analysis_plan'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_analysis_plan = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN analysis_plan TEXT NULL AFTER response_rate_basis',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_weighting_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'weighting_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_weighting_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN weighting_policy TEXT NULL AFTER analysis_plan',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_margin_error_note = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'margin_error_note'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_margin_error_note = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN margin_error_note TEXT NULL AFTER weighting_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_methodology_disclosure = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'methodology_disclosure'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_methodology_disclosure = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN methodology_disclosure TEXT NULL AFTER margin_error_note',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_ethics_note = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'ethics_note'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_ethics_note = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN ethics_note TEXT NULL AFTER methodology_disclosure',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_sensitive_data_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'sensitive_data_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_sensitive_data_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN sensitive_data_policy TEXT NULL AFTER ethics_note',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_recontact_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'recontact_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_recontact_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN recontact_policy TEXT NULL AFTER sensitive_data_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_withdrawal_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'withdrawal_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_withdrawal_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN withdrawal_policy TEXT NULL AFTER recontact_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_vendor_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'vendor_name'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_vendor_name = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN vendor_name VARCHAR(190) NOT NULL DEFAULT '''' AFTER withdrawal_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_external_channel_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'external_channel_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_external_channel_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN external_channel_policy TEXT NULL AFTER vendor_name',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_invite_token_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'invite_token_policy'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_invite_token_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN invite_token_policy TEXT NULL AFTER external_channel_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_qa_status = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'qa_status'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_qa_status = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN qa_status VARCHAR(30) NOT NULL DEFAULT ''unchecked'' AFTER invite_token_policy',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_qa_note = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'qa_note'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_qa_note = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN qa_note TEXT NULL AFTER qa_status',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_questionnaire_version = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'questionnaire_version'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_questionnaire_version = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN questionnaire_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER qa_note',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_revision_locked = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'revision_locked'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_revision_locked = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN revision_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER questionnaire_version',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_member_group_keys_json = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND COLUMN_NAME = 'member_group_keys_json'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_member_group_keys_json = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD COLUMN member_group_keys_json LONGTEXT NULL AFTER response_limit_period_seconds',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_survey_forms_qa_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}survey_forms'
      AND INDEX_NAME = 'idx_sr_survey_forms_qa'
);
SET @schema_sql = IF(
    @schema_has_survey_forms_qa_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms ADD KEY idx_sr_survey_forms_qa (qa_status, revision_locked)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
