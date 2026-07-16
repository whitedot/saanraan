<?php

$adminPageTitle = '콘텐츠 시리즈';
$adminPageSubtitle = '독자가 순서대로 읽을 콘텐츠를 묶고 회차 목록과 이전·다음 이동을 관리합니다.';
$adminContainerClass = 'admin-content-series-list admin-ui-scope';
$seriesFilters = isset($seriesFilters) && is_array($seriesFilters) ? $seriesFilters : ['status' => '', 'visibility' => '', 'field' => 'all', 'q' => ''];
$seriesSortOptions = isset($seriesSortOptions) && is_array($seriesSortOptions) ? $seriesSortOptions : sr_content_admin_series_sort_options();
$seriesDefaultSort = isset($seriesDefaultSort) && is_array($seriesDefaultSort) ? $seriesDefaultSort : sr_content_admin_series_default_sort();
$seriesSort = isset($seriesSort) && is_array($seriesSort) ? $seriesSort : sr_admin_sort_from_request($seriesSortOptions, $seriesDefaultSort);
$seriesPagination = isset($seriesPagination) && is_array($seriesPagination) ? $seriesPagination : sr_admin_pagination_from_total($pdo, count($seriesList ?? []));
$seriesStatusCounts = isset($seriesStatusCounts) && is_array($seriesStatusCounts) ? $seriesStatusCounts : ['total' => count($seriesList ?? [])];
$seriesFormValues = isset($seriesFormValues) && is_array($seriesFormValues) ? $seriesFormValues : [
    'series_key' => '',
    'title' => '',
    'description' => '',
    'status' => 'active',
    'visibility' => 'public',
    'sort_order' => 0,
];
$seriesCreateModalOpen = !empty($seriesCreateModalOpen);
$seriesCreateModalId = 'content-series-create-modal';
$seriesCreateModalClass = 'modal-overlay modal-overlay-fade overlay';
$seriesCreateModalClass .= $seriesCreateModalOpen ? ' overlay-open open' : ' hidden pointer-events-none opacity-0';
$seriesCreateModalAriaHidden = $seriesCreateModalOpen ? 'false' : 'true';
$seriesCreateModalInert = $seriesCreateModalOpen ? '' : ' inert';
$seriesStatusClass = static function (string $status): string {
    return match ($status) {
        'active' => 'is-success',
        'pending', 'hidden' => 'is-danger',
        default => 'is-warning',
    };
};
$contentSeriesHelpOpenLabel = '도움말 보기';
$contentSeriesHelp = [
    'key' => [
        'id' => 'content-series-key-help',
        'title' => '시리즈 식별값 도움말',
        'body' => '<p>식별값은 시리즈를 다른 시리즈와 구분하고 콘텐츠 복사·연결 작업에서 대상을 확인할 때 사용합니다. 공개 주소로 직접 사용되지는 않습니다.</p>'
            . '<p>영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 입력할 수 있습니다. 시리즈를 만든 뒤에는 바꿀 수 없습니다.</p>',
    ],
    'status' => [
        'id' => 'content-series-status-help',
        'title' => '시리즈 상태 도움말',
        'body' => '<p>‘사용’ 상태에서만 공개 콘텐츠 화면에 시리즈 제목, 회차 목록, 이전·다음 이동이 표시됩니다. ‘대기’와 ‘숨김’은 공개 화면에서 시리즈를 숨기지만 회차 연결은 유지합니다.</p>'
            . '<p>‘보관’이나 ‘삭제’로 바꾸면 공개 화면에서 숨기고 모든 콘텐츠의 회차 연결을 해제합니다. 나중에 상태를 다시 ‘사용’으로 바꿔도 회차 연결은 자동으로 복원되지 않으므로 각 콘텐츠에서 다시 연결해야 합니다.</p>',
    ],
    'visibility' => [
        'id' => 'content-series-visibility-help',
        'title' => '공개 범위 도움말',
        'body' => '<p>공개 범위는 상태가 ‘사용’일 때 적용됩니다. ‘전체 공개’는 누구나, ‘회원 공개’는 로그인한 회원만 콘텐츠 화면에서 시리즈 정보를 볼 수 있습니다.</p>'
            . '<p>‘비공개’는 일반 방문자와 회원에게 모두 숨기고 운영자 미리보기에서만 표시합니다. 시리즈에 연결된 개별 콘텐츠의 공개·유료 접근 설정은 별도로 적용됩니다.</p>',
    ],
    'sort_order' => [
        'id' => 'content-series-sort-order-help',
        'title' => '시리즈 표시 순서 도움말',
        'body' => '<p>숫자가 작은 시리즈부터 관리자 목록과 콘텐츠 편집 화면의 시리즈 선택 항목에 먼저 표시됩니다.</p>'
            . '<p>이 값은 시리즈 안의 회차 순서를 바꾸지 않습니다. 회차 순서는 각 콘텐츠 편집 화면의 시리즈 회차 순서에서 정합니다.</p>',
    ],
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/series');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">시리즈 <strong><?php echo sr_e((string) ($seriesStatusCounts['total'] ?? 0)); ?>건</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/content/series?status=active')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_content_series_status_label('active')); ?> <?php echo sr_e((string) ($seriesStatusCounts['active'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/content/series?status=pending')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_content_series_status_label('pending')); ?> <?php echo sr_e((string) ($seriesStatusCounts['pending'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/content/series?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_content_series_status_label('hidden')); ?> <?php echo sr_e((string) ($seriesStatusCounts['hidden'] ?? 0)); ?>건</a>
    </div>
</div>

<?php
$selectedSeriesStatuses = is_array($seriesFilters['status'] ?? null) ? $seriesFilters['status'] : [];
$selectedSeriesVisibilities = is_array($seriesFilters['visibility'] ?? null) ? $seriesFilters['visibility'] : [];
$seriesDetailFilterOpen = $selectedSeriesStatuses !== [] || $selectedSeriesVisibilities !== [];
$seriesStatuses = sr_content_series_statuses();
$seriesStatusOptions = [];
foreach ($seriesStatuses as $status) {
    $seriesStatusOptions[$status] = sr_content_series_status_label($status);
}
$seriesVisibilities = sr_content_series_visibility_values();
$contentSeriesCurrentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$contentSeriesActionSuffix = $contentSeriesCurrentQuery !== '' ? '?' . $contentSeriesCurrentQuery : '';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="filtering-form admin-content-series-filter ui-form-theme">
    <div class="filtering-fields admin-content-series-search-grid admin-content-filter-stack">
        <div class="filtering filtering-card<?php echo $seriesDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields">
                <div class="filtering-field admin-content-series-filter-field">
                    <label for="content_series_filter_field" class="filtering-label">검색조건</label>
                    <select id="content_series_filter_field" name="field" class="form-select filtering-input">
                        <?php foreach (['all' => '전체', 'key' => '식별값', 'title' => '제목'] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($seriesFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field-fill filtering-field admin-content-series-filter-keyword">
                    <label for="content_series_filter_q" class="filtering-label">검색어</label>
                    <input id="content_series_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($seriesFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="식별값 또는 제목">
                </div>
            </div>
            <div id="content_series_detail_filters" class="filtering-body" data-filtering-body<?php echo $seriesDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field admin-content-series-filter-status">
                    <span class="filtering-label">상태</span>
                    <?php echo sr_admin_filter_toggle_group_html('content_series_filter_status', 'status', $seriesStatusOptions, $selectedSeriesStatuses, '전체'); ?>
                </div>
                <div class="filtering-field admin-content-series-filter-visibility">
                    <span class="filtering-label">공개 범위</span>
                    <?php
                    $contentSeriesVisibilityOptions = [];
                    foreach ($seriesVisibilities as $visibility) {
                        $visibilityLabel = sr_content_series_visibility_label((string) $visibility);
                        if ($visibilityLabel === '전체 공개') {
                            continue;
                        }
                        $contentSeriesVisibilityOptions[(string) $visibility] = $visibilityLabel;
                    }
                    echo sr_admin_filter_radio_toggle_group_html('content_series_filter_visibility', 'visibility', $contentSeriesVisibilityOptions, $selectedSeriesVisibilities, '전체');
                    ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $seriesDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="content_series_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">콘텐츠 시리즈 목록</h2>
            <p class="admin-dashboard-meta">콘텐츠 그룹과 별개로 공개 화면의 회차 목록과 이전/다음 이동만 담당합니다.</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $seriesCreateModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($seriesCreateModalId); ?>" data-overlay="#<?php echo sr_e($seriesCreateModalId); ?>">시리즈 등록</button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($seriesSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($seriesSortOptions, $seriesDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="콘텐츠 시리즈 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($seriesPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-content-series-table">
            <caption class="sr-only">콘텐츠 시리즈 목록</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('series_key', $seriesSort); ?>><?php echo sr_admin_sort_header_html('식별값', 'series_key', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $seriesSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $seriesSort); ?>>
                        <?php echo sr_admin_sort_header_html('상태', 'status', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?>
                        <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="시리즈 상태 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentSeriesHelp['status']['id']); ?>" data-overlay="#<?php echo sr_e($contentSeriesHelp['status']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    </th>
                    <th<?php echo sr_admin_sort_aria('visibility', $seriesSort); ?>>
                        <?php echo sr_admin_sort_header_html('공개 범위', 'visibility', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?>
                        <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="공개 범위 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentSeriesHelp['visibility']['id']); ?>" data-overlay="#<?php echo sr_e($contentSeriesHelp['visibility']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    </th>
                    <th<?php echo sr_admin_sort_aria('active_item_count', $seriesSort); ?>><?php echo sr_admin_sort_header_html('회차', 'active_item_count', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $seriesSort); ?>>
                        <?php echo sr_admin_sort_header_html('표시 순서', 'sort_order', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?>
                        <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="표시 순서 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentSeriesHelp['sort_order']['id']); ?>" data-overlay="#<?php echo sr_e($contentSeriesHelp['sort_order']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    </th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $seriesSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($seriesList === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">등록된 콘텐츠 시리즈가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($seriesList as $series) { ?>
                    <?php $seriesUpdateFormId = 'content_series_update_' . (string) (int) $series['id']; ?>
                    <tr>
                        <td class="admin-table-nowrap">
                            <form id="<?php echo sr_e($seriesUpdateFormId); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/content/series' . $contentSeriesActionSuffix)); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="update">
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                <input type="hidden" name="series_key" value="<?php echo sr_e((string) $series['series_key']); ?>">
                                <input type="hidden" name="status" value="<?php echo sr_e((string) $series['status']); ?>">
                                <input type="hidden" name="visibility" value="<?php echo sr_e((string) $series['visibility']); ?>">
                            </form>
                            <code><?php echo sr_e((string) $series['series_key']); ?></code>
                        </td>
                        <td class="admin-table-break admin-content-series-title-cell">
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="title" value="<?php echo sr_e((string) $series['title']); ?>" maxlength="160" required class="form-input form-control-full" aria-label="시리즈 제목" title="시리즈 제목">
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="description" value="<?php echo sr_e((string) ($series['description'] ?? '')); ?>" maxlength="2000" class="form-input form-control-full" aria-label="공개 화면에 표시할 시리즈 설명" title="공개 화면에 표시할 시리즈 설명">
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo sr_e($seriesStatusClass((string) $series['status'])); ?>"><?php echo sr_e(sr_content_series_status_label((string) $series['status'])); ?></span>
                            <div class="admin-row-actions">
                                <?php foreach (sr_content_series_statuses() as $status) { ?>
                                    <?php if ((string) $series['status'] === $status) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php
                                    $statusLabel = sr_content_series_status_label($status);
                                    $statusConfirmAttribute = sr_admin_row_action_confirm_attr($status, $statusLabel);
                                    if (in_array($status, ['archived', 'deleted'], true)) {
                                        $statusConfirmMessage = $statusLabel . ' 상태로 변경할까요? 모든 회차 연결이 해제되며 상태를 되돌려도 자동 복원되지 않습니다.';
                                        $statusConfirmAttribute = ' onclick="return confirm(\'' . sr_e($statusConfirmMessage) . '\');"';
                                    }
                                    ?>
                                    <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" name="status" value="<?php echo sr_e($status); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($status)); ?>"<?php echo $statusConfirmAttribute; ?>><?php echo sr_e($statusLabel); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo (string) $series['visibility'] === 'public' ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e(sr_content_series_visibility_label((string) $series['visibility'])); ?></span>
                            <div class="admin-row-actions">
                                <?php foreach (sr_content_series_visibility_values() as $visibility) { ?>
                                    <?php if ((string) $series['visibility'] === $visibility) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php $visibilityLabel = sr_content_series_visibility_label($visibility); ?>
                                    <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" name="visibility" value="<?php echo sr_e($visibility); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($visibility)); ?>"><?php echo sr_e($visibilityLabel); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($series['active_item_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap">
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="number" name="sort_order" value="<?php echo sr_e((string) $series['sort_order']); ?>" min="0" max="1000000" class="form-input admin-content-series-sort-input" aria-label="시리즈 표시 순서" title="숫자가 작을수록 먼저 표시됩니다. 회차 순서에는 영향을 주지 않습니다.">
                        </td>
                        <td class="admin-table-nowrap admin-content-series-date-cell"><?php echo sr_content_time_html((string) ($series['updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="콘텐츠 시리즈 저장" title="저장"><?php echo sr_material_icon_html('save'); ?></button>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="delete">
                                    <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="콘텐츠 시리즈 삭제" title="콘텐츠 시리즈 삭제" onclick="return confirm('이 콘텐츠 시리즈를 삭제할까요? 콘텐츠는 삭제되지 않지만 모든 회차 연결 기록은 삭제됩니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('save'); ?> 저장</span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 콘텐츠 시리즈 삭제</span>
    </div>
    <?php echo sr_admin_status_description_list_html('content_series_status', array_combine(sr_content_series_statuses(), array_map('sr_content_series_status_label', sr_content_series_statuses())) ?: [], [
        'pending' => '공개 화면에서는 숨기고 회차 연결은 유지합니다.',
        'active' => '선택한 공개 범위에 따라 콘텐츠 화면에 시리즈 정보를 표시합니다.',
        'hidden' => '공개 화면에서는 숨기고 회차 연결은 유지합니다.',
        'archived' => '공개 화면에서 숨기고 모든 회차 연결을 해제합니다.',
        'deleted' => '삭제 예정 상태로 표시하고 모든 회차 연결을 해제합니다.',
    ], '시리즈 상태 설명'); ?>
    <?php echo sr_admin_status_description_list_html('content_series_visibility', array_combine(sr_content_series_visibility_values(), array_map('sr_content_series_visibility_label', sr_content_series_visibility_values())) ?: [], [
        'public' => '누구나 콘텐츠 화면에서 시리즈 정보를 볼 수 있습니다.',
        'member' => '로그인한 회원만 콘텐츠 화면에서 시리즈 정보를 볼 수 있습니다.',
        'private' => '일반 방문자와 회원에게 숨기고 운영자 미리보기에서만 표시합니다.',
    ], '공개 범위 설명'); ?>
</section>
<?php echo sr_admin_pagination_html($seriesPagination, '콘텐츠 시리즈 목록 페이지'); ?>

<div id="<?php echo sr_e($seriesCreateModalId); ?>" class="<?php echo sr_e($seriesCreateModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($seriesCreateModalId); ?>_title" aria-hidden="<?php echo sr_e($seriesCreateModalAriaHidden); ?>"<?php echo $seriesCreateModalInert; ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="modal-content ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($seriesCreateModalId); ?>_title" class="modal-title">콘텐츠 시리즈 등록</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($seriesCreateModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('content_series_key_new', '식별값', $contentSeriesHelp['key']['id'], $contentSeriesHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="content_series_key_new" name="series_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" value="<?php echo sr_e((string) ($seriesFormValues['series_key'] ?? '')); ?>" required data-admin-key-input data-admin-key-suggest-source="#content_series_title_new" data-admin-key-suggest-fallback="series" data-overlay-focus class="form-input">
                        <small class="form-help">영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 입력하세요. 만든 뒤에는 바꿀 수 없습니다.</small>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="content_series_title_new">제목 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="content_series_title_new" name="title" maxlength="160" value="<?php echo sr_e((string) ($seriesFormValues['title'] ?? '')); ?>" required class="form-input form-control-full">
                        <small class="form-help">콘텐츠 화면의 시리즈 영역에 표시되는 제목입니다.</small>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="content_series_description_new">설명</label>
                    <div class="form-field">
                        <textarea id="content_series_description_new" name="description" rows="3" class="form-textarea"><?php echo sr_e((string) ($seriesFormValues['description'] ?? '')); ?></textarea>
                        <small class="form-help">콘텐츠 화면에서 시리즈 제목 아래에 표시됩니다.</small>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('content_series_status_new', '상태', $contentSeriesHelp['status']['id'], $contentSeriesHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="content_series_status_new" name="status" class="form-select" required>
                            <?php foreach (sr_content_series_statuses() as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($seriesFormValues['status'] ?? 'active') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_content_series_status_label($status)); ?></option>
                            <?php } ?>
                        </select>
                        <small class="form-help">보관·삭제로 바꾸면 회차 연결이 해제되고 자동으로 복원되지 않습니다.</small>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('content_series_visibility_new', '공개 범위', $contentSeriesHelp['visibility']['id'], $contentSeriesHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="content_series_visibility_new" name="visibility" class="form-select" required>
                            <?php foreach (sr_content_series_visibility_values() as $visibility) { ?>
                                <option value="<?php echo sr_e($visibility); ?>"<?php echo (string) ($seriesFormValues['visibility'] ?? 'public') === $visibility ? ' selected' : ''; ?>><?php echo sr_e(sr_content_series_visibility_label($visibility)); ?></option>
                            <?php } ?>
                        </select>
                        <small class="form-help">상태가 사용일 때 시리즈 정보를 볼 수 있는 대상을 정합니다.</small>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('content_series_sort_new', '표시 순서', $contentSeriesHelp['sort_order']['id'], $contentSeriesHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="content_series_sort_new" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($seriesFormValues['sort_order'] ?? 0)); ?>" class="form-input">
                        <small class="form-help">숫자가 작을수록 먼저 표시됩니다. 회차 순서에는 영향을 주지 않습니다.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($seriesCreateModalId); ?>">취소</button>
                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($contentSeriesHelp as $contentSeriesHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $contentSeriesHelpModal['id'], (string) $contentSeriesHelpModal['title'], (string) $contentSeriesHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
