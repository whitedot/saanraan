UPDATE sr_asset_exchange_logs
SET deposit_amount = deposit_amount - fee_amount
WHERE status = 'completed'
  AND fee_amount > 0
  AND deposit_amount >= fee_amount;

UPDATE sr_modules
SET version = '2026.05.002',
    updated_at = NOW()
WHERE module_key = 'asset_exchange';
