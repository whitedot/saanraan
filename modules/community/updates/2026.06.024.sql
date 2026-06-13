CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_board_field_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    field_type VARCHAR(30) NOT NULL DEFAULT 'text',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    show_on_view TINYINT(1) NOT NULL DEFAULT 1,
    show_in_admin TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    validation_json TEXT NULL,
    privacy_purpose VARCHAR(255) NOT NULL DEFAULT '',
    export_policy VARCHAR(30) NOT NULL DEFAULT 'include',
    cleanup_policy VARCHAR(30) NOT NULL DEFAULT 'anonymize',
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_field_key (board_id, field_key),
    KEY idx_sr_community_board_field_board_status_sort (board_id, status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_post_field_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(60) NOT NULL,
    label_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    field_type_snapshot VARCHAR(30) NOT NULL DEFAULT 'text',
    visibility_snapshot VARCHAR(30) NOT NULL DEFAULT 'public',
    show_on_view_snapshot TINYINT(1) NOT NULL DEFAULT 1,
    show_in_admin_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    privacy_purpose_snapshot VARCHAR(255) NOT NULL DEFAULT '',
    export_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'include',
    cleanup_policy_snapshot VARCHAR(30) NOT NULL DEFAULT 'anonymize',
    value_text TEXT NULL,
    value_json TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_post_field_value_key (post_id, field_key),
    KEY idx_sr_community_post_field_values_post (post_id),
    KEY idx_sr_community_post_field_values_key (field_key)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.024',
    updated_at = NOW()
WHERE module_key = 'community';
