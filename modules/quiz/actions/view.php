<?php

require_once __DIR__ . '/../helpers.php';
require_once SR_ROOT . '/modules/member/public-identity.php';
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/public-reaction.php')) {
    require_once SR_ROOT . '/modules/reaction/public-reaction.php';
}
if (sr_request_method() === 'POST') {
    sr_require_csrf();
}
$quizReactionPublicAssets = function_exists('sr_reaction_public_assets') ? sr_reaction_public_assets() : [];

$quizSettings = sr_quiz_settings($pdo);
$quizThemeFallbackViewFile = sr_quiz_skin_view_file($quizSettings, 'view');
include sr_quiz_public_view_file($pdo, $quizSettings, 'view');
