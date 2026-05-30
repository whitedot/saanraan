UPDATE sr_coupon_issues
SET status = 'expired',
    updated_at = NOW()
WHERE status = 'active'
  AND expires_at IS NOT NULL
  AND expires_at < NOW();

UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'coupon';
