ALTER TABLE sr_popup_layers
    ADD COLUMN coupon_claim_campaign_key VARCHAR(60) NOT NULL DEFAULT '' AFTER body_format;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'popup_layer';
