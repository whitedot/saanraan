CREATE TABLE IF NOT EXISTS sr_content_link_refs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_id BIGINT UNSIGNED NOT NULL,
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
    UNIQUE KEY uq_sr_content_link_refs_unique (content_id, target_module, target_entity_type, target_entity_id, slot_key, variant, label),
    KEY idx_sr_content_link_refs_content (content_id, sort_order, id),
    KEY idx_sr_content_link_refs_target (target_module, target_entity_type, target_entity_id)
);
