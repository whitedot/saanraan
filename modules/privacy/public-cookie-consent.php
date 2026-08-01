<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/public-cookie-consent.php';

return [
    'render_function' => 'sr_privacy_cookie_consent_public_html',
    'assets_function' => 'sr_privacy_cookie_consent_public_assets',
];
