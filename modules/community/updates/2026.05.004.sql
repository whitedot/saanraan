INSERT IGNORE INTO toy_community_levels
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

UPDATE toy_modules
SET version = '2026.05.004',
    updated_at = NOW()
WHERE module_key = 'community';
