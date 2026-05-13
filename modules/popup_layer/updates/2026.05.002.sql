ALTER TABLE sr_popup_layers
    ADD COLUMN skin_key VARCHAR(60) NOT NULL DEFAULT 'basic' AFTER status;

UPDATE sr_modules
SET version = '2026.05.002'
WHERE module_key = 'popup_layer';
