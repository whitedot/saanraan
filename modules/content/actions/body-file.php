<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$fileIdValue = sr_get_string('id', 20);
$fileId = preg_match('/\A[1-9][0-9]*\z/', $fileIdValue) === 1 ? (int) $fileIdValue : 0;
sr_content_send_body_file($pdo, $fileId);
