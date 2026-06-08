<?php

return [
    'GET /quiz' => 'actions/home.php',
    'POST /quiz/comment' => 'actions/comment.php',
    'POST /quiz/comment/edit' => 'actions/comment-edit.php',
    'POST /quiz/comment/delete' => 'actions/comment-delete.php',
    'GET /quiz/*' => 'actions/view.php',
    'POST /quiz/*' => 'actions/view.php',
    'GET /admin/quiz' => 'actions/admin-quiz.php',
    'POST /admin/quiz' => 'actions/admin-quiz.php',
    'GET /admin/quiz/comments' => 'actions/admin-comments.php',
    'POST /admin/quiz/comments' => 'actions/admin-comments.php',
    'GET /admin/quiz/settings' => 'actions/admin-settings.php',
    'POST /admin/quiz/settings' => 'actions/admin-settings.php',
    'GET /admin/quiz/manual' => 'actions/admin-manual.php',
    'GET /admin/quiz/attempts' => 'actions/admin-attempts.php',
];
