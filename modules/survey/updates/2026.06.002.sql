ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms
    ADD COLUMN research_purpose TEXT NULL AFTER description,
    ADD COLUMN target_population TEXT NULL AFTER research_purpose,
    ADD COLUMN recruitment_method TEXT NULL AFTER target_population,
    ADD COLUMN estimated_minutes INT UNSIGNED NULL AFTER recruitment_method,
    ADD COLUMN organizer_name VARCHAR(120) NOT NULL DEFAULT '' AFTER estimated_minutes,
    ADD COLUMN contact_text VARCHAR(190) NOT NULL DEFAULT '' AFTER organizer_name,
    ADD COLUMN consent_required TINYINT(1) NOT NULL DEFAULT 0 AFTER contact_text,
    ADD COLUMN consent_text TEXT NULL AFTER consent_required,
    ADD COLUMN privacy_notice TEXT NULL AFTER consent_text,
    ADD COLUMN anonymous_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER privacy_notice,
    ADD COLUMN login_required TINYINT(1) NOT NULL DEFAULT 1 AFTER anonymous_allowed,
    ADD COLUMN public_listed TINYINT(1) NOT NULL DEFAULT 1 AFTER login_required,
    ADD COLUMN robots_policy VARCHAR(30) NOT NULL DEFAULT 'auto' AFTER public_listed,
    ADD COLUMN response_limit_period_seconds INT UNSIGNED NULL AFTER response_limit_policy;

ALTER TABLE {{SR_TABLE_PREFIX}}survey_questions
    ADD COLUMN analysis_note TEXT NULL AFTER prompt,
    ADD COLUMN min_choices INT UNSIGNED NULL AFTER required,
    ADD COLUMN max_choices INT UNSIGNED NULL AFTER min_choices,
    ADD COLUMN scale_points INT UNSIGNED NULL AFTER max_choices,
    ADD COLUMN scale_min_label VARCHAR(120) NOT NULL DEFAULT '' AFTER scale_points,
    ADD COLUMN scale_max_label VARCHAR(120) NOT NULL DEFAULT '' AFTER scale_min_label,
    ADD COLUMN number_unit VARCHAR(60) NOT NULL DEFAULT '' AFTER scale_max_label,
    ADD COLUMN number_min DECIMAL(20,6) NULL AFTER number_unit,
    ADD COLUMN number_max DECIMAL(20,6) NULL AFTER number_min,
    ADD COLUMN allow_decimal TINYINT(1) NOT NULL DEFAULT 0 AFTER number_max,
    ADD COLUMN allow_other TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_decimal,
    ADD COLUMN nonresponse_policy VARCHAR(30) NOT NULL DEFAULT 'none' AFTER allow_other;

ALTER TABLE {{SR_TABLE_PREFIX}}survey_choices
    ADD COLUMN is_other TINYINT(1) NOT NULL DEFAULT 0 AFTER label,
    ADD COLUMN is_nonresponse TINYINT(1) NOT NULL DEFAULT 0 AFTER is_other;

ALTER TABLE {{SR_TABLE_PREFIX}}survey_responses
    MODIFY COLUMN account_id BIGINT UNSIGNED NULL,
    ADD COLUMN quality_status VARCHAR(30) NOT NULL DEFAULT 'accepted' AFTER status,
    ADD COLUMN quality_note TEXT NULL AFTER quality_status,
    ADD COLUMN consent_snapshot_json LONGTEXT NULL AFTER quality_note,
    ADD COLUMN metadata_snapshot_json LONGTEXT NULL AFTER consent_snapshot_json,
    ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER metadata_snapshot_json,
    ADD KEY idx_sr_survey_responses_quality (quality_status, updated_at);

ALTER TABLE {{SR_TABLE_PREFIX}}survey_response_answers
    ADD COLUMN answer_number DECIMAL(20,6) NULL AFTER answer_text,
    ADD COLUMN other_text TEXT NULL AFTER answer_number;
