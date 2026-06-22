UPDATE sr_logo_manager_logos
SET use_as_public_symbol = 0
WHERE position_key <> 'public.app_icon';

UPDATE sr_modules
SET version = '2026.06.004'
WHERE module_key = 'logo_manager'
  AND version < '2026.06.004';
