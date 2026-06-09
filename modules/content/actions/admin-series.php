<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/series', 'view');
$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$seriesSupported = sr_content_series_supported($pdo);
$seriesCreateModalOpen = false;
$seriesFormValues = [
    'series_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'active',
    'visibility' => 'public',
    'sort_order' => 0,
];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $seriesRedirectPath = '/admin/content/series' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');
    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/series', $intent === 'delete' ? 'delete' : 'edit');
    $seriesId = (int) sr_post_string('series_id', 20);
    if ($intent === 'delete') {
        if (!$seriesSupported) {
            $errors[] = '콘텐츠 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
        } else {
            $deleteResult = sr_content_delete_series($pdo, $seriesId);
            $errors = array_merge($errors, is_array($deleteResult['errors'] ?? null) ? $deleteResult['errors'] : []);
            $series = is_array($deleteResult['series'] ?? null) ? $deleteResult['series'] : null;
            if ($errors === [] && is_array($series)) {
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'content.series.deleted',
                    'target_type' => 'content_series',
                    'target_id' => (string) $seriesId,
                    'result' => 'success',
                    'message' => 'Content series deleted.',
                    'metadata' => [
                        'series_key' => (string) ($series['series_key'] ?? ''),
                        'title' => (string) ($series['title'] ?? ''),
                        'deleted_items' => (int) ($deleteResult['deleted_items'] ?? 0),
                    ],
                ]);
                $notice = '콘텐츠 시리즈를 삭제했습니다.';
            }
        }

        sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), $seriesRedirectPath);
    }

    $description = sr_post_string_without_truncation('description', 2000);
    $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
    $values = [
        'series_key' => strtolower(trim(sr_post_string('series_key', 60))),
        'title' => sr_post_string('title', 160),
        'description' => is_string($description) ? $description : '',
        'status' => sr_post_string('status', 30),
        'visibility' => sr_post_string('visibility', 30),
        'sort_order' => $sortOrder ?? 0,
    ];
    if ($intent === 'create') {
        $seriesFormValues = $values;
        $seriesCreateModalOpen = true;
    }
    if (!$seriesSupported) {
        $errors[] = '콘텐츠 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
    }
    if ($intent === 'create' && !sr_content_series_key_is_valid((string) $values['series_key'])) {
        $errors[] = '시리즈 key가 올바르지 않습니다.';
    }
    if (!is_string($description)) {
        $errors[] = '시리즈 설명이 너무 깁니다.';
    }
    if ($sortOrder === null) {
        $errors[] = '시리즈 정렬값이 올바르지 않습니다.';
    }
    if ((string) $values['title'] === '') {
        $errors[] = '시리즈 제목을 입력해 주세요.';
    }
    if (!in_array((string) $values['status'], sr_content_series_statuses(), true) || !in_array((string) $values['visibility'], sr_content_series_visibility_values(), true)) {
        $errors[] = '상태 또는 공개 범위가 올바르지 않습니다.';
    }
    if ($intent === 'create' && $errors === [] && sr_content_series_key_exists($pdo, (string) $values['series_key'])) {
        $errors[] = '이미 같은 key의 콘텐츠 시리즈가 있습니다.';
    }
    if ($errors === []) {
        if ($intent === 'create') {
            try {
                $seriesId = sr_content_create_series($pdo, $values, (int) $account['id']);
                $notice = '콘텐츠 시리즈를 만들었습니다.';
                $seriesCreateModalOpen = false;
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }
                $errors[] = '이미 같은 key의 콘텐츠 시리즈가 있습니다.';
            }
        } elseif ($intent === 'update') {
            $series = sr_content_series_by_id($pdo, $seriesId);
            if (!is_array($series)) {
                $errors[] = '시리즈를 찾을 수 없습니다.';
            } else {
                sr_content_update_series($pdo, $seriesId, $values, (int) $account['id']);
                $notice = '콘텐츠 시리즈를 수정했습니다.';
            }
        }
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), $seriesRedirectPath);
}

$seriesFilters = sr_content_admin_series_filters();
$seriesSortOptions = sr_content_admin_series_sort_options();
$seriesDefaultSort = sr_content_admin_series_default_sort();
$seriesSort = sr_admin_sort_from_request($seriesSortOptions, $seriesDefaultSort);
$seriesStatusCounts = sr_content_admin_series_status_counts($pdo);
$seriesPagination = sr_admin_pagination_from_total($pdo, sr_content_admin_series_count($pdo, $seriesFilters));
$seriesList = sr_content_admin_series_list($pdo, $seriesFilters, (int) $seriesPagination['per_page'], sr_admin_pagination_offset($seriesPagination), $seriesSort);
if (!$seriesSupported && sr_request_method() !== 'POST') {
    $errors[] = '콘텐츠 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
}
include SR_ROOT . '/modules/content/views/admin-series.php';
