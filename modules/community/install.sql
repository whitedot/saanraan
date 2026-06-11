CREATE TABLE IF NOT EXISTS sr_community_board_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_groups_key (group_key),
    KEY idx_sr_community_board_groups_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_community_boards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_group_id BIGINT UNSIGNED NULL,
    board_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    read_policy VARCHAR(30) NOT NULL DEFAULT 'public',
    write_policy VARCHAR(30) NOT NULL DEFAULT 'member',
    comment_policy VARCHAR(30) NOT NULL DEFAULT 'member',
    image_uploads_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_boards_key (board_key),
    KEY idx_sr_community_boards_group_sort (board_group_id, sort_order, id),
    KEY idx_sr_community_boards_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_settings_key (board_id, setting_key),
    KEY idx_sr_community_board_settings_board (board_id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_group_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_group_settings_key (group_id, setting_key),
    KEY idx_sr_community_board_group_settings_group (group_id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_setting_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'board',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_setting_sources_key (board_id, setting_key),
    KEY idx_sr_community_board_setting_sources_board (board_id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_managers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(60) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_managers_permission (board_id, account_id, permission_key),
    KEY idx_sr_community_board_managers_board_status (board_id, status),
    KEY idx_sr_community_board_managers_account_status (account_id, status)
);

CREATE TABLE IF NOT EXISTS sr_community_storage_cleanup_failures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type VARCHAR(60) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(512) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 1,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_storage_cleanup_status (status, updated_at),
    KEY idx_sr_community_storage_cleanup_source (source_type, source_id),
    KEY idx_sr_community_storage_cleanup_storage (storage_driver, storage_key(191))
);

CREATE TABLE IF NOT EXISTS sr_community_asset_policy_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    set_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    policies_json TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_asset_policy_sets_key (set_key),
    KEY idx_sr_community_asset_policy_sets_status (status, title)
);

CREATE TABLE IF NOT EXISTS sr_community_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    category_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_categories_board_key (board_id, category_key),
    KEY idx_sr_community_categories_board_status_sort (board_id, status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_community_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    author_account_id BIGINT UNSIGNED NOT NULL,
    author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    title VARCHAR(160) NOT NULL,
    body_text MEDIUMTEXT NOT NULL,
    body_format VARCHAR(20) NOT NULL DEFAULT 'plain',
    seo_title VARCHAR(160) NOT NULL DEFAULT '',
    seo_description VARCHAR(255) NOT NULL DEFAULT '',
    og_title VARCHAR(160) NOT NULL DEFAULT '',
    og_description VARCHAR(255) NOT NULL DEFAULT '',
    og_image_attachment_id BIGINT UNSIGNED NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    hidden_at DATETIME NULL,
    hidden_until DATETIME NULL,
    hidden_reason VARCHAR(40) NOT NULL DEFAULT '',
    hidden_note TEXT NULL,
    hidden_by_account_id BIGINT UNSIGNED NULL,
    hidden_before_status VARCHAR(30) NOT NULL DEFAULT '',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_commented_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_posts_board_status_id (board_id, status, id),
    KEY idx_sr_community_posts_board_category_status_id (board_id, category_id, status, id),
    KEY idx_sr_community_posts_author_id (author_account_id, id),
    KEY idx_sr_community_posts_og_image_attachment (og_image_attachment_id),
    KEY idx_sr_community_posts_status_updated (status, updated_at)
);

CREATE TABLE IF NOT EXISTS sr_community_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    parent_comment_id BIGINT UNSIGNED NULL,
    thread_root_id BIGINT UNSIGNED NULL,
    depth TINYINT UNSIGNED NOT NULL DEFAULT 1,
    author_account_id BIGINT UNSIGNED NOT NULL,
    author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    body_text TEXT NOT NULL,
    is_secret TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    hidden_at DATETIME NULL,
    hidden_until DATETIME NULL,
    hidden_reason VARCHAR(40) NOT NULL DEFAULT '',
    hidden_note TEXT NULL,
    hidden_by_account_id BIGINT UNSIGNED NULL,
    hidden_before_status VARCHAR(30) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_comments_post_status_id (post_id, status, id),
    KEY idx_sr_community_comments_thread (post_id, status, thread_root_id, parent_comment_id, id),
    KEY idx_sr_community_comments_parent (parent_comment_id),
    KEY idx_sr_community_comments_author_id (author_account_id, id)
);

CREATE TABLE IF NOT EXISTS sr_community_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    uploader_account_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(120) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_key VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_attachments_post_status_id (post_id, status, id),
    KEY idx_sr_community_attachments_uploader_id (uploader_account_id, id),
    KEY idx_sr_community_attachments_storage (storage_driver, storage_key),
    KEY idx_sr_community_attachments_checksum (checksum_sha256)
);

CREATE TABLE IF NOT EXISTS sr_community_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type VARCHAR(30) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    reporter_account_id BIGINT UNSIGNED NOT NULL,
    reported_account_id BIGINT UNSIGNED NULL,
    reason_key VARCHAR(40) NOT NULL,
    memo_text TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    reviewer_account_id BIGINT UNSIGNED NULL,
    review_note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_reports_target_reporter (reporter_account_id, target_type, target_id),
    KEY idx_sr_community_reports_target (target_type, target_id),
    KEY idx_sr_community_reports_status_created (status, created_at),
    KEY idx_sr_community_reports_reported_id (reported_account_id, id)
);

CREATE TABLE IF NOT EXISTS sr_community_submission_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    action_key VARCHAR(60) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    consent_title_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    consent_body_snapshot TEXT NULL,
    consent_version_snapshot VARCHAR(60) NOT NULL DEFAULT '',
    consent_required TINYINT(1) NOT NULL DEFAULT 1,
    consent_accepted TINYINT(1) NOT NULL DEFAULT 1,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_submission_consents_account (account_id, id),
    KEY idx_sr_community_submission_consents_subject (subject_type, subject_id, action_key),
    KEY idx_sr_community_submission_consents_board (board_id, created_at)
);

CREATE TABLE IF NOT EXISTS sr_community_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_account_id BIGINT UNSIGNED NOT NULL,
    recipient_account_id BIGINT UNSIGNED NOT NULL,
    body_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    read_at DATETIME NULL,
    sender_deleted_at DATETIME NULL,
    recipient_deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_messages_recipient_deleted_id (recipient_account_id, recipient_deleted_at, id),
    KEY idx_sr_community_messages_sender_deleted_id (sender_account_id, sender_deleted_at, id),
    KEY idx_sr_community_messages_status_created (status, created_at)
);

CREATE TABLE IF NOT EXISTS sr_community_scraps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_scraps_account_post (account_id, post_id),
    KEY idx_sr_community_scraps_account_id (account_id, id),
    KEY idx_sr_community_scraps_post_id (post_id, id)
);

CREATE TABLE IF NOT EXISTS sr_community_link_refs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    target_module VARCHAR(60) NOT NULL,
    target_entity_type VARCHAR(60) NOT NULL,
    target_entity_id VARCHAR(120) NOT NULL,
    slot_key VARCHAR(60) NOT NULL DEFAULT 'body',
    variant VARCHAR(60) NOT NULL DEFAULT 'compact',
    label VARCHAR(120) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_link_refs_unique (post_id, target_module, target_entity_type, target_entity_id, slot_key, variant, label),
    KEY idx_sr_community_link_refs_post (post_id, sort_order, id),
    KEY idx_sr_community_link_refs_target (target_module, target_entity_type, target_entity_id)
);

CREATE TABLE IF NOT EXISTS sr_community_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    owner_account_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    admin_note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    moderated_by BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_series_owner_status (owner_account_id, status, id),
    KEY idx_sr_community_series_board_status (board_id, status, id)
);

CREATE TABLE IF NOT EXISTS sr_community_series_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    active_post_id BIGINT UNSIGNED NULL,
    episode_label VARCHAR(120) NOT NULL DEFAULT '',
    item_status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_series_items_series_post (series_id, post_id),
    UNIQUE KEY uq_sr_community_series_items_active_post (active_post_id),
    KEY idx_sr_community_series_items_series_sort (series_id, item_status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_community_series_scraps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    series_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_series_scraps_account_series (account_id, series_id),
    KEY idx_sr_community_series_scraps_account_id (account_id, id),
    KEY idx_sr_community_series_scraps_series_id (series_id, id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_copy_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_board_id BIGINT UNSIGNED NOT NULL,
    target_board_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    requested_by BIGINT UNSIGNED NULL,
    mode VARCHAR(40) NOT NULL DEFAULT 'posts_comments_attachments',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    stage VARCHAR(40) NOT NULL DEFAULT 'prepare',
    source_snapshot_json MEDIUMTEXT NULL,
    options_json TEXT NULL,
    counts_json TEXT NULL,
    processed_json TEXT NULL,
    last_error TEXT NULL,
    cleanup_error TEXT NULL,
    lock_token VARCHAR(80) NOT NULL DEFAULT '',
    locked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_board_copy_jobs_status_stage_updated (status, stage, updated_at, id),
    KEY idx_sr_community_board_copy_jobs_source (source_board_id, id),
    KEY idx_sr_community_board_copy_jobs_target (target_board_id, id),
    KEY idx_sr_community_board_copy_jobs_requested (requested_by, created_at, id),
    KEY idx_sr_community_board_copy_jobs_lock (status, locked_at, id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_copy_job_maps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    error_text TEXT NULL,
    created_storage_driver VARCHAR(20) NOT NULL DEFAULT '',
    created_storage_key VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_copy_job_maps_source (job_id, entity_type, source_id),
    KEY idx_sr_community_board_copy_job_maps_status (job_id, entity_type, status, id),
    KEY idx_sr_community_board_copy_job_maps_cleanup (job_id, status, created_storage_driver, created_storage_key),
    KEY idx_sr_community_board_copy_job_maps_target (job_id, entity_type, target_id)
);

CREATE TABLE IF NOT EXISTS sr_community_levels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    level_value INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    min_score BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_levels_value (level_value),
    KEY idx_sr_community_levels_status_score (status, min_score, level_value)
);

CREATE TABLE IF NOT EXISTS sr_community_account_levels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    level_value INT UNSIGNED NOT NULL DEFAULT 0,
    score_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    post_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    comment_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    evaluated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_account_levels_account (account_id),
    KEY idx_sr_community_account_levels_level (level_value, account_id)
);

CREATE TABLE IF NOT EXISTS sr_community_level_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    old_level_value INT UNSIGNED NOT NULL DEFAULT 0,
    new_level_value INT UNSIGNED NOT NULL DEFAULT 0,
    old_score_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    new_score_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reason_key VARCHAR(60) NOT NULL DEFAULT 'activity_changed',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_community_level_logs_account_id (account_id, id),
    KEY idx_sr_community_level_logs_created (created_at)
);

CREATE TABLE IF NOT EXISTS sr_community_asset_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reference_type VARCHAR(60) NOT NULL,
    reference_id VARCHAR(120) NOT NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(60) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    amount BIGINT NOT NULL,
    settlement_amount BIGINT NOT NULL DEFAULT 0,
    settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW',
    purchase_power_snapshot_json TEXT NULL,
    settlement_kind VARCHAR(30) NOT NULL DEFAULT 'legacy_unknown',
    snapshot_schema_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_snapshot_v1',
    rounding_policy_version VARCHAR(40) NOT NULL DEFAULT 'asset_settlement_rounding_v1',
    log_status VARCHAR(20) NOT NULL DEFAULT 'completed',
    group_policy_snapshot_json TEXT NULL,
    dedupe_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_asset_logs_dedupe (dedupe_key),
    KEY idx_sr_community_asset_logs_account (account_id, created_at),
    KEY idx_sr_community_asset_logs_subject (subject_type, subject_id, account_id),
    KEY idx_sr_community_asset_logs_transaction (asset_module, transaction_id)
);

CREATE TABLE IF NOT EXISTS sr_community_publisher_reward_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    charge_asset_log_id BIGINT UNSIGNED NOT NULL,
    charge_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reversal_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    post_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED NOT NULL,
    downloader_account_id BIGINT UNSIGNED NOT NULL,
    publisher_account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    charge_amount BIGINT NOT NULL DEFAULT 0,
    reward_rate INT UNSIGNED NOT NULL DEFAULT 0,
    reward_amount BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    dedupe_key VARCHAR(160) NOT NULL,
    failure_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_publisher_reward_dedupe (dedupe_key),
    KEY idx_sr_community_publisher_reward_publisher (publisher_account_id, created_at),
    KEY idx_sr_community_publisher_reward_downloader (downloader_account_id, created_at),
    KEY idx_sr_community_publisher_reward_attachment (attachment_id, created_at),
    KEY idx_sr_community_publisher_reward_charge_log (charge_asset_log_id),
    KEY idx_sr_community_publisher_reward_status (status, updated_at)
);

CREATE TABLE IF NOT EXISTS sr_community_access_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(60) NOT NULL,
    source_kind VARCHAR(30) NOT NULL DEFAULT 'asset',
    source_asset_module VARCHAR(20) NOT NULL DEFAULT '',
    source_charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    source_reference VARCHAR(160) NOT NULL DEFAULT '',
    granted_at DATETIME NOT NULL,
    anonymized_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_access_entitlements_account_subject (account_id, subject_type, subject_id, event_key),
    KEY idx_sr_community_access_entitlements_subject (subject_type, subject_id, event_key),
    KEY idx_sr_community_access_entitlements_account (account_id, granted_at),
    KEY idx_sr_community_access_entitlements_anonymized (anonymized_at)
);

INSERT IGNORE INTO sr_community_boards
    (board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
VALUES
    ('free', '자유게시판', '기본 커뮤니티 게시판입니다.', 'enabled', 'public', 'member', 'member', 1, 10, NOW(), NOW());

INSERT IGNORE INTO sr_community_levels
    (level_value, title, description, min_score, status, sort_order, created_at, updated_at)
VALUES
    (1, '레벨 1', '기본 커뮤니티 레벨입니다.', 0, 'enabled', 10, NOW(), NOW()),
    (2, '레벨 2', '커뮤니티 활동 점수 10점 이상입니다.', 10, 'enabled', 20, NOW(), NOW()),
    (3, '레벨 3', '커뮤니티 활동 점수 50점 이상입니다.', 50, 'enabled', 30, NOW(), NOW()),
    (4, '레벨 4', '커뮤니티 활동 점수 100점 이상입니다.', 100, 'enabled', 40, NOW(), NOW()),
    (5, '레벨 5', '커뮤니티 활동 점수 300점 이상입니다.', 300, 'enabled', 50, NOW(), NOW()),
    (6, '레벨 6', '커뮤니티 활동 점수 600점 이상입니다.', 600, 'enabled', 60, NOW(), NOW()),
    (7, '레벨 7', '커뮤니티 활동 점수 1000점 이상입니다.', 1000, 'enabled', 70, NOW(), NOW()),
    (8, '레벨 8', '커뮤니티 활동 점수 1500점 이상입니다.', 1500, 'enabled', 80, NOW(), NOW()),
    (9, '레벨 9', '커뮤니티 활동 점수 2100점 이상입니다.', 2100, 'enabled', 90, NOW(), NOW()),
    (10, '레벨 10', '커뮤니티 활동 점수 3000점 이상입니다.', 3000, 'enabled', 100, NOW(), NOW());
