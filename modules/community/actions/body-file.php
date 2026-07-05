<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$postIdValue = sr_get_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$tmpToken = sr_get_string('tmp', 64);
$fileName = sr_get_string('file', 180);
$driver = sr_get_string('d', 20);
$thumbnail = sr_get_string('thumb', 10) === '1';
sr_community_send_body_file($pdo, $postId, $fileName, $tmpToken, $driver, $thumbnail);
