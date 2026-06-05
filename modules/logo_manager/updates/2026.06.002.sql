ALTER TABLE sr_logo_manager_logos
    ADD COLUMN use_as_public_symbol TINYINT(1) NOT NULL DEFAULT 0 AFTER link_url;

UPDATE sr_logo_manager_logos
SET use_as_public_symbol = 0
WHERE position_key <> 'public.favicon';

UPDATE sr_modules
SET version = '2026.06.002'
WHERE module_key = 'logo_manager'
  AND version < '2026.06.002';
