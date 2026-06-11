<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
sr_quiz_render_skin($pdo, $quizSettings, 'home');
