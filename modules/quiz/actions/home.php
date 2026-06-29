<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizThemeFallbackViewFile = sr_quiz_skin_view_file($quizSettings, 'home');
include sr_quiz_public_view_file($pdo, $quizSettings, 'home');
