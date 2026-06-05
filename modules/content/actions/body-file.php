<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$contentIdValue = sr_get_string('content_id', 20);
$contentId = preg_match('/\A[1-9][0-9]*\z/', $contentIdValue) === 1 ? (int) $contentIdValue : 0;
$tmpToken = sr_get_string('tmp', 64);
$fileName = sr_get_string('file', 180);
sr_content_send_body_file($pdo, $contentId, $fileName, $tmpToken);
