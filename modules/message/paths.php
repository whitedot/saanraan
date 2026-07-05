<?php

return [
    'GET /messages' => 'actions/messages.php',
    'GET /message' => 'actions/message-view.php',
    'GET /message/write' => 'actions/message-write.php',
    'POST /message/write' => 'actions/message-write.php',
    'POST /message/delete' => 'actions/message-delete.php',
    'GET /admin/message/settings' => 'actions/admin-settings.php',
    'POST /admin/message/settings' => 'actions/admin-settings.php',
];
