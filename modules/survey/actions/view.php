<?php

require_once __DIR__ . '/../helpers.php';
require_once SR_ROOT . '/modules/member/public-identity.php';
$settings = sr_survey_settings($pdo);
if (!empty($settings['reaction_enabled']) && sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/public-reaction.php')) {
    require_once SR_ROOT . '/modules/reaction/public-reaction.php';
}
if (sr_request_method() === 'POST') {
    sr_require_csrf();
}
$surveyReactionPublicAssets = function_exists('sr_reaction_public_assets') ? sr_reaction_public_assets() : [];

$surveyThemeFallbackViewFile = sr_survey_skin_view_file($settings, 'view');
include sr_survey_public_view_file($pdo, $settings, 'view');
