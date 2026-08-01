<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizzes = sr_quiz_public_quizzes($pdo, 6, 0);
include sr_quiz_public_view_file($pdo, $quizSettings, 'home');
