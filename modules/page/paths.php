<?php

return [
    'GET /pages/download' => 'actions/download.php',
    'GET /pages/group' => 'actions/group.php',
    'POST /pages/action' => 'actions/action.php',
    'GET /pages/*' => 'actions/view.php',
    'GET /admin/pages' => 'actions/admin-pages.php',
    'GET /admin/pages/new' => 'actions/admin-page-new.php',
    'GET /admin/pages/edit' => 'actions/admin-page-edit.php',
    'POST /admin/pages/save' => 'actions/admin-page-save.php',
    'POST /admin/pages/delete' => 'actions/admin-page-delete.php',
    'GET /admin/page-groups' => 'actions/admin-page-groups.php',
    'POST /admin/page-groups' => 'actions/admin-page-groups.php',
    'GET /admin/page-groups/new' => 'actions/admin-page-group-new.php',
    'GET /admin/page-groups/edit' => 'actions/admin-page-group-edit.php',
];
