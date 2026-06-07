CREATE TABLE IF NOT EXISTS sr_survey_forms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_key VARCHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    response_limit_policy VARCHAR(30) NOT NULL DEFAULT 'per_survey_once',
    member_group_keys_json LONGTEXT NULL,
    reward_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_by_account_id BIGINT UNSIGNED NULL,
    updated_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_survey_forms_key (survey_key),
    KEY idx_sr_survey_forms_status_dates (status, starts_at, ends_at),
    KEY idx_sr_survey_forms_reward (reward_enabled)
);

CREATE TABLE IF NOT EXISTS sr_survey_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id BIGINT UNSIGNED NOT NULL,
    question_key VARCHAR(64) NOT NULL,
    question_type VARCHAR(30) NOT NULL DEFAULT 'single_choice',
    prompt TEXT NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_survey_questions_key (survey_id, question_key),
    KEY idx_sr_survey_questions_sort (survey_id, sort_order)
);

CREATE TABLE IF NOT EXISTS sr_survey_choices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id BIGINT UNSIGNED NOT NULL,
    choice_key VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_survey_choices_key (question_id, choice_key),
    KEY idx_sr_survey_choices_sort (question_id, sort_order)
);

CREATE TABLE IF NOT EXISTS sr_survey_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    submitted_at DATETIME NOT NULL,
    rewarded_at DATETIME NULL,
    answer_snapshot_json LONGTEXT NULL,
    user_agent_hash VARCHAR(128) NULL,
    ip_hash VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_survey_responses_survey_account (survey_id, account_id, submitted_at),
    KEY idx_sr_survey_responses_account (account_id, submitted_at),
    KEY idx_sr_survey_responses_status (status, updated_at)
);

CREATE TABLE IF NOT EXISTS sr_survey_response_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    response_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NULL,
    question_key VARCHAR(64) NOT NULL,
    choice_id BIGINT UNSIGNED NULL,
    choice_key VARCHAR(64) NULL,
    answer_text TEXT NULL,
    answer_snapshot_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_survey_response_answers_response (response_id),
    KEY idx_sr_survey_response_answers_question (question_id),
    KEY idx_sr_survey_response_answers_choice (choice_id)
);

CREATE TABLE IF NOT EXISTS sr_survey_reward_policies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    reward_provider VARCHAR(30) NOT NULL DEFAULT 'ledger_asset',
    reward_module VARCHAR(40) NOT NULL,
    reward_code VARCHAR(120) NOT NULL,
    reward_amount INT NULL,
    dedupe_scope VARCHAR(20) NOT NULL DEFAULT 'per_survey',
    sort_order INT NOT NULL DEFAULT 0,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_survey_reward_policies_survey (survey_id, status, sort_order),
    KEY idx_sr_survey_reward_policies_provider (reward_provider, reward_module)
);

CREATE TABLE IF NOT EXISTS sr_survey_reward_grants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_id BIGINT UNSIGNED NOT NULL,
    response_id BIGINT UNSIGNED NOT NULL,
    reward_policy_id BIGINT UNSIGNED NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    reward_provider VARCHAR(30) NOT NULL,
    reward_module VARCHAR(40) NOT NULL,
    reward_code VARCHAR(120) NOT NULL,
    reward_amount INT NULL,
    dedupe_scope VARCHAR(20) NOT NULL,
    dedupe_key VARCHAR(190) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    provider_reference_type VARCHAR(60) NULL,
    provider_reference_id VARCHAR(120) NULL,
    request_snapshot_json LONGTEXT NULL,
    result_snapshot_json LONGTEXT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    granted_at DATETIME NULL,
    failed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_survey_reward_grants_dedupe (dedupe_key),
    KEY idx_sr_survey_reward_grants_response (response_id),
    KEY idx_sr_survey_reward_grants_account (account_id, created_at),
    KEY idx_sr_survey_reward_grants_survey_status (survey_id, status),
    KEY idx_sr_survey_reward_grants_provider (reward_provider, reward_module)
);
