<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/page/helpers.php';

$groupKey = sr_page_clean_slug(sr_get_string('key', 60));
$pageGroup = sr_page_enabled_group_by_key($pdo, $groupKey);
if (!is_array($pageGroup)) {
    sr_render_error(404, sr_t('page::action.error.page_group_not_found'));
}

$groupPages = sr_page_published_pages_for_group($pdo, (int) $pageGroup['id']);
$pageTitle = (string) ($pageGroup['title'] ?? '페이지 그룹');
$pageDescription = (string) ($pageGroup['description'] ?? '');

include SR_ROOT . '/modules/page/views/group.php';
