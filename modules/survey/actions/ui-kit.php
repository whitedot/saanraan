<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/survey/helpers.php';

$settings = sr_survey_settings($pdo);

include SR_ROOT . '/modules/survey/views/ui-kit.php';
