<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/quiz/helpers.php';

$quizRawSettings = sr_module_settings($pdo, 'quiz');
$quizLayoutSettings = sr_quiz_settings($pdo);
$quizLayoutKey = sr_public_layout_normalize_key((string) ($quizRawSettings['layout_key'] ?? 'quiz.basic'));
$quizLayoutOptions = sr_public_layout_options($pdo, true);
if (isset($quizLayoutOptions[$quizLayoutKey])) {
    $quizLayoutSettings['layout_key'] = $quizLayoutKey;
}

include SR_ROOT . '/modules/quiz/views/ui-kit.php';
