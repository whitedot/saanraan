<?php

require_once __DIR__ . '/../helpers.php';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$quizSettings = sr_quiz_settings($pdo);
$quizThemeFallbackViewFile = sr_quiz_skin_view_file($quizSettings, 'view');
include sr_quiz_public_view_file($pdo, $quizSettings, 'view');
