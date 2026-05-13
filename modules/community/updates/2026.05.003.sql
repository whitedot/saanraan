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

INSERT IGNORE INTO sr_community_levels
    (level_value, title, description, min_score, status, sort_order, created_at, updated_at)
VALUES
    (1, '레벨 1', '기본 커뮤니티 레벨입니다.', 0, 'enabled', 10, NOW(), NOW()),
    (2, '레벨 2', '커뮤니티 활동 점수 10점 이상입니다.', 10, 'enabled', 20, NOW(), NOW()),
    (3, '레벨 3', '커뮤니티 활동 점수 50점 이상입니다.', 50, 'enabled', 30, NOW(), NOW()),
    (4, '레벨 4', '커뮤니티 활동 점수 100점 이상입니다.', 100, 'enabled', 40, NOW(), NOW()),
    (5, '레벨 5', '커뮤니티 활동 점수 300점 이상입니다.', 300, 'enabled', 50, NOW(), NOW());

UPDATE sr_modules
SET version = '2026.05.003',
    updated_at = NOW()
WHERE module_key = 'community';
