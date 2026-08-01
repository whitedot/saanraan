<?php

require_once __DIR__ . '/../helpers.php';

$settings = sr_survey_settings($pdo);
$surveys = sr_survey_public_forms($pdo, 6, 0);
include sr_survey_public_view_file($pdo, $settings, 'home');
