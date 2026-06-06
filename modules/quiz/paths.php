<?php

return [
    'GET /quiz' => 'actions/home.php',
    'GET /quiz/*' => 'actions/view.php',
    'POST /quiz/*' => 'actions/view.php',
    'GET /admin/quiz' => 'actions/admin-quiz.php',
    'POST /admin/quiz' => 'actions/admin-quiz.php',
    'GET /admin/quiz/attempts' => 'actions/admin-attempts.php',
];
