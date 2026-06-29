<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/survey/helpers.php';

$settings = sr_survey_settings($pdo);

$surveyUiKitView = sr_survey_theme_view_file($settings, 'ui-kit') ?? SR_ROOT . '/modules/survey/theme/basic/ui-kit.php';
include $surveyUiKitView;
