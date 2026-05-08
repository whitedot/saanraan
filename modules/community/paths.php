<?php

return [
    'GET /community' => 'actions/home.php',
    'GET /community/board' => 'actions/list.php',
    'GET /community/post' => 'actions/view.php',
    'GET /admin/community/boards' => 'actions/admin-boards.php',
    'POST /admin/community/boards' => 'actions/admin-boards.php',
];
