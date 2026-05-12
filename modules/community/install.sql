CREATE TABLE IF NOT EXISTS toy_community_board_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_community_board_groups_key (group_key),
    KEY idx_toy_community_board_groups_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS toy_community_boards (
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
    UNIQUE KEY uq_toy_community_boards_key (board_key),
    KEY idx_toy_community_boards_group_sort (board_group_id, sort_order, id),
    KEY idx_toy_community_boards_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS toy_community_board_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_community_board_settings_key (board_id, setting_key),
    KEY idx_toy_community_board_settings_board (board_id)
);

CREATE TABLE IF NOT EXISTS toy_community_board_group_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_community_board_group_settings_key (group_id, setting_key),
    KEY idx_toy_community_board_group_settings_group (group_id)
);

CREATE TABLE IF NOT EXISTS toy_community_board_setting_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'board',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_community_board_setting_sources_key (board_id, setting_key),
    KEY idx_toy_community_board_setting_sources_board (board_id)
);

CREATE TABLE IF NOT EXISTS toy_community_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    author_account_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    body_text MEDIUMTEXT NOT NULL,
    body_format VARCHAR(20) NOT NULL DEFAULT 'plain',
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_commented_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_community_posts_board_status_id (board_id, status, id),
    KEY idx_toy_community_posts_author_id (author_account_id, id),
    KEY idx_toy_community_posts_status_updated (status, updated_at)
);

CREATE TABLE IF NOT EXISTS toy_community_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    author_account_id BIGINT UNSIGNED NOT NULL,
    body_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_community_comments_post_status_id (post_id, status, id),
    KEY idx_toy_community_comments_author_id (author_account_id, id)
);

CREATE TABLE IF NOT EXISTS toy_community_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    uploader_account_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(120) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_community_attachments_post_status_id (post_id, status, id),
    KEY idx_toy_community_attachments_uploader_id (uploader_account_id, id),
    KEY idx_toy_community_attachments_checksum (checksum_sha256)
);

CREATE TABLE IF NOT EXISTS toy_community_reports (
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
    UNIQUE KEY uq_toy_community_reports_target_reporter (reporter_account_id, target_type, target_id),
    KEY idx_toy_community_reports_target (target_type, target_id),
    KEY idx_toy_community_reports_status_created (status, created_at),
    KEY idx_toy_community_reports_reported_id (reported_account_id, id)
);

CREATE TABLE IF NOT EXISTS toy_community_messages (
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
    KEY idx_toy_community_messages_recipient_deleted_id (recipient_account_id, recipient_deleted_at, id),
    KEY idx_toy_community_messages_sender_deleted_id (sender_account_id, sender_deleted_at, id),
    KEY idx_toy_community_messages_status_created (status, created_at)
);

CREATE TABLE IF NOT EXISTS toy_community_scraps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_community_scraps_account_post (account_id, post_id),
    KEY idx_toy_community_scraps_account_id (account_id, id),
    KEY idx_toy_community_scraps_post_id (post_id, id)
);

CREATE TABLE IF NOT EXISTS toy_community_levels (
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
    UNIQUE KEY uq_toy_community_levels_value (level_value),
    KEY idx_toy_community_levels_status_score (status, min_score, level_value)
);

CREATE TABLE IF NOT EXISTS toy_community_account_levels (
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
    UNIQUE KEY uq_toy_community_account_levels_account (account_id),
    KEY idx_toy_community_account_levels_level (level_value, account_id)
);

CREATE TABLE IF NOT EXISTS toy_community_level_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    old_level_value INT UNSIGNED NOT NULL DEFAULT 0,
    new_level_value INT UNSIGNED NOT NULL DEFAULT 0,
    old_score_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    new_score_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reason_key VARCHAR(60) NOT NULL DEFAULT 'activity_changed',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_community_level_logs_account_id (account_id, id),
    KEY idx_toy_community_level_logs_created (created_at)
);

INSERT IGNORE INTO toy_community_boards
    (board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
VALUES
    ('free', '자유게시판', '기본 커뮤니티 게시판입니다.', 'enabled', 'public', 'member', 'member', 1, 10, NOW(), NOW());

INSERT IGNORE INTO toy_community_levels
    (level_value, title, description, min_score, status, sort_order, created_at, updated_at)
VALUES
    (1, '레벨 1', '기본 커뮤니티 레벨입니다.', 0, 'enabled', 10, NOW(), NOW()),
    (2, '레벨 2', '커뮤니티 활동 점수 10점 이상입니다.', 10, 'enabled', 20, NOW(), NOW()),
    (3, '레벨 3', '커뮤니티 활동 점수 50점 이상입니다.', 50, 'enabled', 30, NOW(), NOW()),
    (4, '레벨 4', '커뮤니티 활동 점수 100점 이상입니다.', 100, 'enabled', 40, NOW(), NOW()),
    (5, '레벨 5', '커뮤니티 활동 점수 300점 이상입니다.', 300, 'enabled', 50, NOW(), NOW());
