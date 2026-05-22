<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/page/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$pageId = (int) sr_post_string('page_id', 20);
$page = sr_page_by_id($pdo, $pageId);
if (is_array($page)) {
    $page = sr_page_with_effective_settings($pdo, $page);
}
if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('page::action.error.page_complete_not_found'));
}

$result = sr_page_run_asset_action($pdo, $page, (int) $account['id']);
if (!empty($result['completed'])) {
    $directionLabel = (string) ($result['direction'] ?? '') === 'use' ? '차감' : '지급';
    $_SESSION['sr_page_action_notice'] = (string) ($result['asset_label'] ?? '회원 자산') . ' '
        . number_format((int) ($result['amount'] ?? 0)) . ' ' . $directionLabel . ' 처리되었습니다.';
} elseif (!empty($result['already_completed'])) {
    $_SESSION['sr_page_action_notice'] = (string) ($result['message'] ?? '이미 완료 처리되었습니다.');
} else {
    $_SESSION['sr_page_action_errors'] = [(string) ($result['message'] ?? '완료 처리할 수 없습니다.')];
}

sr_redirect(sr_page_path((string) $page['slug']));
