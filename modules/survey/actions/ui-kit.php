<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/survey/helpers.php';

$surveyRawSettings = sr_module_settings($pdo, 'survey');
$settings = sr_survey_settings($pdo);
$surveyLayoutKey = sr_public_layout_normalize_key((string) ($surveyRawSettings['layout_key'] ?? 'survey.basic'));
$surveyLayoutOptions = sr_survey_layout_options($pdo, true);
if (isset($surveyLayoutOptions[$surveyLayoutKey])) {
    $settings['layout_key'] = $surveyLayoutKey;
}

$surveyUiKitView = sr_survey_theme_view_file($settings, 'ui-kit') ?? SR_ROOT . '/modules/survey/theme/basic/ui-kit.php';
include $surveyUiKitView;
