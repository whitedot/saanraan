<?php

return [
    'GET /community' => 'actions/home.php',
    'GET /community/board' => 'actions/list.php',
    'GET /community/post' => 'actions/view.php',
    'GET /community/write' => 'actions/write.php',
    'POST /community/write' => 'actions/write.php',
    'POST /community/comment' => 'actions/comment.php',
    'GET /community/scraps' => 'actions/scraps.php',
    'POST /community/scrap' => 'actions/scrap-toggle.php',
    'GET /admin/community/boards' => 'actions/admin-boards.php',
    'POST /admin/community/boards' => 'actions/admin-boards.php',
];
