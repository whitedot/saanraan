<?php

return [
    'GET /community' => 'actions/home.php',
    'GET /community/board' => 'actions/list.php',
    'GET /community/post' => 'actions/view.php',
    'GET /community/write' => 'actions/write.php',
    'POST /community/write' => 'actions/write.php',
    'POST /community/comment' => 'actions/comment.php',
    'POST /community/report' => 'actions/report.php',
    'GET /community/scraps' => 'actions/scraps.php',
    'POST /community/scrap' => 'actions/scrap-toggle.php',
    'GET /community/messages' => 'actions/messages.php',
    'GET /community/message' => 'actions/message-view.php',
    'GET /community/message/write' => 'actions/message-write.php',
    'POST /community/message/write' => 'actions/message-write.php',
    'POST /community/message/delete' => 'actions/message-delete.php',
    'GET /admin/community/boards' => 'actions/admin-boards.php',
    'POST /admin/community/boards' => 'actions/admin-boards.php',
    'GET /admin/community/reports' => 'actions/admin-reports.php',
    'POST /admin/community/reports' => 'actions/admin-reports.php',
];
