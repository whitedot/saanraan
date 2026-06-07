<?php

return [
    'GET /survey' => 'actions/home.php',
    'GET /survey/*' => 'actions/view.php',
    'POST /survey/*' => 'actions/view.php',
    'GET /admin/surveys' => 'actions/admin-surveys.php',
    'POST /admin/surveys' => 'actions/admin-surveys.php',
];
