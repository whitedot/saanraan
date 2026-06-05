ALTER TABLE sr_popup_layers
    ADD COLUMN body_format VARCHAR(20) NOT NULL DEFAULT 'plain' AFTER body_text;

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = NOW()
WHERE module_key = 'popup_layer';
