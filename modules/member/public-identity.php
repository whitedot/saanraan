<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/settings.php';
require_once __DIR__ . '/helpers/sessions.php';
require_once __DIR__ . '/helpers/nicknames.php';
require_once __DIR__ . '/helpers/accounts.php';
require_once __DIR__ . '/helpers/follows.php';
require_once __DIR__ . '/helpers/profile.php';
require_once __DIR__ . '/helpers/public-identity.php';

return [
    'context_function' => 'sr_member_public_identity_context',
    'parts_function' => 'sr_member_public_identity_parts',
    'assets_function' => 'sr_member_public_identity_assets',
    'layout_account_function' => 'sr_member_public_layout_account_context',
];
