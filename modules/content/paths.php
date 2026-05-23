<?php

return [
    'GET /content/download' => 'actions/download.php',
    'GET /content/group' => 'actions/group.php',
    'POST /content/action' => 'actions/action.php',
    'GET /content/*' => 'actions/view.php',
    'GET /admin/content' => 'actions/admin-contents.php',
    'GET /admin/content/new' => 'actions/admin-content-new.php',
    'GET /admin/content/edit' => 'actions/admin-content-edit.php',
    'POST /admin/content/save' => 'actions/admin-content-save.php',
    'POST /admin/content/delete' => 'actions/admin-content-delete.php',
    'GET /admin/content-groups' => 'actions/admin-content-groups.php',
    'POST /admin/content-groups' => 'actions/admin-content-groups.php',
    'GET /admin/content-groups/new' => 'actions/admin-content-group-new.php',
    'GET /admin/content-groups/edit' => 'actions/admin-content-group-edit.php',
];
