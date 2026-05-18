<?php

return [
    'GET /pages/*' => 'actions/view.php',
    'GET /admin/pages' => 'actions/admin-pages.php',
    'GET /admin/pages/new' => 'actions/admin-page-new.php',
    'GET /admin/pages/edit' => 'actions/admin-page-edit.php',
    'POST /admin/pages/save' => 'actions/admin-page-save.php',
    'POST /admin/pages/delete' => 'actions/admin-page-delete.php',
];
