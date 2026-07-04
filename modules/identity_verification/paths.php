<?php

return [
    'GET /identity/verify/start' => 'actions/start.php',
    'POST /identity/verify/start' => 'actions/start.php',
    'GET /identity/verify/return' => 'actions/return.php',
    'POST /identity/verify/return' => 'actions/return.php',
    'POST /identity/verify/callback' => 'actions/callback.php',
    'GET /admin/identity-verifications' => 'actions/admin-verifications.php',
    'GET /admin/identity-verifications/*' => 'actions/admin-verifications.php',
    'GET /admin/identity-providers' => 'actions/admin-providers.php',
    'POST /admin/identity-providers' => 'actions/admin-providers.php',
];
