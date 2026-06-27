CREATE TABLE IF NOT EXISTS sr_member_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_identifier_hash CHAR(64) NOT NULL,
    login_id_hash CHAR(64) NULL,
    email VARCHAR(255) NOT NULL,
    email_hash CHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    locale VARCHAR(20) NOT NULL DEFAULT 'ko',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_identifier (account_identifier_hash),
    UNIQUE KEY uq_sr_member_login_id_hash (login_id_hash),
    UNIQUE KEY uq_sr_member_email_hash (email_hash)
);

CREATE TABLE IF NOT EXISTS sr_member_auth_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    result VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_auth_logs_account (account_id),
    KEY idx_sr_member_auth_logs_account_event_created (account_id, event_type, created_at),
    KEY idx_sr_member_auth_logs_ip_event_created (ip_address, event_type, created_at),
    KEY idx_sr_member_auth_logs_ip_created (ip_address, created_at),
    KEY idx_sr_member_auth_logs_created (created_at)
);

CREATE TABLE IF NOT EXISTS sr_member_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    birth_date DATE NULL,
    is_adult TINYINT(1) NULL,
    avatar_path VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_profiles_account (account_id)
);

CREATE TABLE IF NOT EXISTS sr_member_profile_field_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(60) NOT NULL,
    label_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    field_type_snapshot VARCHAR(30) NOT NULL DEFAULT 'text',
    visibility_snapshot VARCHAR(30) NOT NULL DEFAULT 'public',
    show_on_profile_snapshot TINYINT(1) NOT NULL DEFAULT 1,
    show_in_admin_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    privacy_purpose_snapshot VARCHAR(255) NOT NULL DEFAULT '',
    export_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'include',
    cleanup_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'anonymize',
    value_text TEXT NULL,
    value_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_profile_field_value_key (account_id, field_key),
    KEY idx_sr_member_profile_field_values_account (account_id),
    KEY idx_sr_member_profile_field_values_key (field_key)
);

CREATE TABLE IF NOT EXISTS sr_member_nicknames (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    nickname VARCHAR(80) NOT NULL,
    nickname_lookup VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_nicknames_account (account_id),
    UNIQUE KEY uq_sr_member_nicknames_lookup (nickname_lookup)
);

CREATE TABLE IF NOT EXISTS sr_member_follows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    follower_account_id BIGINT UNSIGNED NOT NULL,
    following_account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_follows_pair (follower_account_id, following_account_id),
    KEY idx_sr_member_follows_follower_status (follower_account_id, status, following_account_id),
    KEY idx_sr_member_follows_following_status (following_account_id, status, follower_account_id)
);

CREATE TABLE IF NOT EXISTS sr_member_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    remember_token_hash CHAR(64) NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_sessions_token (session_token_hash),
    KEY idx_sr_member_sessions_account (account_id),
    KEY idx_sr_member_sessions_expires (expires_at),
    KEY idx_sr_member_sessions_revoked (revoked_at)
);

CREATE TABLE IF NOT EXISTS sr_member_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    reset_token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_password_resets_token (reset_token_hash),
    KEY idx_sr_member_password_resets_account (account_id),
    KEY idx_sr_member_password_resets_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS sr_member_email_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    verification_token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_email_verifications_token (verification_token_hash),
    KEY idx_sr_member_email_verifications_account (account_id),
    KEY idx_sr_member_email_verifications_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS sr_member_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    consent_key VARCHAR(80) NOT NULL,
    consent_version VARCHAR(40) NOT NULL,
    policy_document_key_snapshot VARCHAR(80) NOT NULL DEFAULT '',
    policy_version_key_snapshot VARCHAR(40) NOT NULL DEFAULT '',
    policy_document_version_id BIGINT UNSIGNED NULL,
    consent_title_snapshot VARCHAR(190) NOT NULL DEFAULT '',
    consent_body_hash CHAR(64) NOT NULL DEFAULT '',
    consent_required TINYINT(1) NOT NULL DEFAULT 0,
    consented TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_consents_account (account_id),
    KEY idx_sr_member_consents_key (consent_key, consent_version)
);

CREATE TABLE IF NOT EXISTS sr_member_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_groups_key (group_key),
    KEY idx_sr_member_groups_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_member_group_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    assignment_type VARCHAR(30) NOT NULL DEFAULT 'manual',
    source_module_key VARCHAR(60) NOT NULL DEFAULT '',
    source_rule_key VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    granted_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_group_memberships_group_status_account (group_id, status, account_id),
    KEY idx_sr_member_group_memberships_account_status_group (account_id, status, group_id),
    KEY idx_sr_member_group_memberships_source_status (source_module_key, source_rule_key, status)
);

CREATE TABLE IF NOT EXISTS sr_member_group_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    source_module_key VARCHAR(60) NOT NULL,
    rule_key VARCHAR(120) NOT NULL,
    rule_params_json TEXT NOT NULL,
    evaluation_policy VARCHAR(30) NOT NULL DEFAULT 'grant_only',
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    last_evaluated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_group_rules_group_status (group_id, status),
    KEY idx_sr_member_group_rules_source_status (source_module_key, rule_key, status)
);

CREATE TABLE IF NOT EXISTS sr_member_group_membership_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    source_module_key VARCHAR(60) NOT NULL DEFAULT '',
    source_rule_key VARCHAR(120) NOT NULL DEFAULT '',
    actor_account_id BIGINT UNSIGNED NULL,
    message VARCHAR(255) NOT NULL DEFAULT '',
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_member_group_membership_logs_group_account (group_id, account_id),
    KEY idx_sr_member_group_membership_logs_account_created (account_id, created_at),
    KEY idx_sr_member_group_membership_logs_source (source_module_key, source_rule_key)
);
