UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'asset_exchange';
