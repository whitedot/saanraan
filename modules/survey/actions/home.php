<?php

require_once __DIR__ . '/../helpers.php';

$settings = sr_survey_settings($pdo);
sr_survey_render_skin($pdo, $settings, 'home');
