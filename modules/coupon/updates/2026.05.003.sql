UPDATE sr_modules
SET version = '2026.05.003',
    updated_at = NOW()
WHERE module_key = 'coupon';
