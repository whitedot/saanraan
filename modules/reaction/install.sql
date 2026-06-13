CREATE TABLE IF NOT EXISTS sr_reaction_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reaction_key VARCHAR(80) NOT NULL,
    label VARCHAR(80) NOT NULL,
    icon_type VARCHAR(20) NOT NULL DEFAULT 'emoji',
    icon_value VARCHAR(80) NOT NULL DEFAULT '',
    color_hex VARCHAR(7) NOT NULL DEFAULT '',
    color_swatch VARCHAR(40) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 100,
    is_seed TINYINT(1) NOT NULL DEFAULT 0,
    created_by_account_id BIGINT UNSIGNED NULL,
    updated_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reaction_definitions_key (reaction_key),
    KEY idx_sr_reaction_definitions_status_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sr_reaction_presets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    preset_key VARCHAR(80) NOT NULL,
    label VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    selection_policy VARCHAR(30) NOT NULL DEFAULT 'single',
    visible_key_limit TINYINT UNSIGNED NOT NULL DEFAULT 6,
    sort_order INT NOT NULL DEFAULT 100,
    created_by_account_id BIGINT UNSIGNED NULL,
    updated_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reaction_presets_key (preset_key),
    KEY idx_sr_reaction_presets_status_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sr_reaction_preset_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    preset_key VARCHAR(80) NOT NULL,
    reaction_key VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reaction_preset_items_key (preset_key, reaction_key),
    KEY idx_sr_reaction_preset_items_preset_public (preset_key, is_public, sort_order, id),
    KEY idx_sr_reaction_preset_items_reaction (reaction_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sr_reaction_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    target_module VARCHAR(60) NOT NULL,
    target_type VARCHAR(60) NOT NULL,
    target_id VARCHAR(80) NOT NULL,
    reaction_key VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reaction_records_account_target (account_id, target_module, target_type, target_id),
    KEY idx_sr_reaction_records_target_key (target_module, target_type, target_id, reaction_key),
    KEY idx_sr_reaction_records_account_updated (account_id, updated_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sr_reaction_definitions
    (reaction_key, label, icon_type, icon_value, color_hex, color_swatch, description, status, sort_order, is_seed, created_at, updated_at)
VALUES
    ('like', '좋아요', 'emoji', '👍', '#2563eb', 'blue', '긍정 반응', 'active', 10, 1, NOW(), NOW()),
    ('sad', '슬퍼요', 'emoji', '😢', '#64748b', 'slate', '슬픔 또는 아쉬움 반응', 'active', 20, 1, NOW(), NOW()),
    ('fun', '재밌어요', 'emoji', '😄', '#f59e0b', 'amber', '재미 반응', 'active', 30, 1, NOW(), NOW()),
    ('empathy', '공감해요', 'emoji', '🤝', '#16a34a', 'green', '공감 반응', 'active', 40, 1, NOW(), NOW()),
    ('surprised', '놀랐어요', 'emoji', '😮', '#7c3aed', 'violet', '놀람 반응', 'active', 50, 1, NOW(), NOW()),
    ('useful', '유용해요', 'emoji', '💡', '#0f766e', 'teal', '유용함 반응', 'active', 60, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    icon_type = VALUES(icon_type),
    icon_value = VALUES(icon_value),
    color_hex = VALUES(color_hex),
    color_swatch = VALUES(color_swatch),
    description = VALUES(description),
    sort_order = VALUES(sort_order),
    is_seed = VALUES(is_seed),
    updated_at = VALUES(updated_at);

INSERT INTO sr_reaction_presets
    (preset_key, label, description, status, selection_policy, visible_key_limit, sort_order, created_at, updated_at)
VALUES
    ('emotions', '감정형 리액션', '기본 감정형 단일 선택 리액션 preset', 'active', 'single', 6, 10, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    status = VALUES(status),
    selection_policy = VALUES(selection_policy),
    visible_key_limit = VALUES(visible_key_limit),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

INSERT INTO sr_reaction_preset_items
    (preset_key, reaction_key, sort_order, is_public, created_at, updated_at)
VALUES
    ('emotions', 'like', 10, 1, NOW(), NOW()),
    ('emotions', 'sad', 20, 1, NOW(), NOW()),
    ('emotions', 'fun', 30, 1, NOW(), NOW()),
    ('emotions', 'empathy', 40, 1, NOW(), NOW()),
    ('emotions', 'surprised', 50, 1, NOW(), NOW()),
    ('emotions', 'useful', 60, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_public = VALUES(is_public),
    updated_at = VALUES(updated_at);
