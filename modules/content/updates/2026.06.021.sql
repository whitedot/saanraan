ALTER TABLE sr_content_items
    ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER popup_layer_id,
    ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER reaction_preset_key;
