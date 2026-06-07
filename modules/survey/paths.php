<?php

return [
    'GET /survey' => 'actions/home.php',
    'GET /survey/*' => 'actions/view.php',
    'POST /survey/*' => 'actions/view.php',
    'GET /admin/surveys' => 'actions/admin-surveys.php',
    'POST /admin/surveys' => 'actions/admin-surveys.php',
    'GET /admin/surveys/responses' => 'actions/admin-responses.php',
    'POST /admin/surveys/responses' => 'actions/admin-responses.php',
    'GET /admin/surveys/statistics' => 'actions/admin-statistics.php',
    'GET /admin/surveys/export' => 'actions/admin-export.php',
];
