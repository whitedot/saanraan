<?php

return [
    'GET /oauth/start' => 'actions/start.php',
    'GET /oauth/callback' => 'actions/callback.php',
    'GET /oauth/complete' => 'actions/complete.php',
    'POST /oauth/complete' => 'actions/complete.php',
    'POST /account/oauth/unlink' => 'actions/unlink.php',
    'GET /admin/member-oauth' => 'actions/admin-settings.php',
    'POST /admin/member-oauth' => 'actions/admin-settings.php',
];
