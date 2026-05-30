UPDATE sr_asset_exchange_policies
SET fee_rate_denominator = 100
WHERE fee_rate_denominator <> 100;

UPDATE sr_modules
SET version = '2026.05.003',
    updated_at = NOW()
WHERE module_key = 'asset_exchange';
