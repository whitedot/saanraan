<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/page/helpers.php';

$slug = sr_page_slug_from_request_path();
$page = $slug !== '' ? sr_page_published_by_slug($pdo, $slug) : null;
if (!is_array($page)) {
    sr_render_error(404, '요청한 페이지를 찾을 수 없습니다.');
}

include SR_ROOT . '/modules/page/views/page.php';
