<?php

require_once __DIR__ . '/../helpers.php';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$settings = sr_survey_settings($pdo);
$surveyThemeFallbackViewFile = sr_survey_skin_view_file($settings, 'view');
include sr_survey_public_view_file($pdo, $settings, 'view');
