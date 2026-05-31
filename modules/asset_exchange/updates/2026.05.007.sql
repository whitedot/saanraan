ALTER TABLE sr_asset_exchange_logs
    ADD KEY idx_sr_asset_exchange_logs_reexchange (account_id, to_module_key, status, created_at);

UPDATE sr_modules
SET version = '2026.05.007',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'asset_exchange';
