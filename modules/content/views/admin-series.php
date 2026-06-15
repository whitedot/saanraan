<?php

$adminPageTitle = '콘텐츠 시리즈';
$adminPageSubtitle = '콘텐츠의 공개 회차 흐름을 관리합니다.';
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
        'active' => 'is-normal',
        'pending', 'hidden' => 'is-left',
        default => 'is-blocked',
    };
};
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
                        <?php foreach (['all' => '전체', 'key' => '관리용 키', 'title' => '제목'] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($seriesFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field-fill filtering-field admin-content-series-filter-keyword">
                    <label for="content_series_filter_q" class="filtering-label">검색어</label>
                    <input id="content_series_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($seriesFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="관리용 키 또는 제목">
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
                <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
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
        <table class="table admin-content-series-table">
            <caption class="sr-only">콘텐츠 시리즈 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('series_key', $seriesSort); ?>><?php echo sr_admin_sort_header_html('관리용 키', 'series_key', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $seriesSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $seriesSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('visibility', $seriesSort); ?>><?php echo sr_admin_sort_header_html('공개', 'visibility', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('active_item_count', $seriesSort); ?>><?php echo sr_admin_sort_header_html('회차', 'active_item_count', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $seriesSort); ?>><?php echo sr_admin_sort_header_html('정렬', 'sort_order', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
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
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="title" value="<?php echo sr_e((string) $series['title']); ?>" maxlength="160" required class="form-input form-control-full">
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="description" value="<?php echo sr_e((string) ($series['description'] ?? '')); ?>" maxlength="2000" class="form-input form-control-full" aria-label="설명">
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($seriesStatusClass((string) $series['status'])); ?>"><?php echo sr_e(sr_content_series_status_label((string) $series['status'])); ?></span>
                            <div class="admin-row-actions">
                                <?php foreach (sr_content_series_statuses() as $status) { ?>
                                    <?php if ((string) $series['status'] === $status) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php $statusLabel = sr_content_series_status_label($status); ?>
                                    <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" name="status" value="<?php echo sr_e($status); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($status)); ?>"<?php echo sr_admin_row_action_confirm_attr($status, $statusLabel); ?>><?php echo sr_e($statusLabel); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo (string) $series['visibility'] === 'public' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_content_series_visibility_label((string) $series['visibility'])); ?></span>
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
                            <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="number" name="sort_order" value="<?php echo sr_e((string) $series['sort_order']); ?>" min="0" max="1000000" class="form-input admin-content-series-sort-input">
                        </td>
                        <td class="admin-table-nowrap admin-content-series-date-cell"><?php echo sr_content_time_html((string) ($series['updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="콘텐츠 시리즈 저장" title="저장"><?php echo sr_material_icon_html('save'); ?></button>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="delete">
                                    <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="콘텐츠 시리즈 삭제" title="콘텐츠 시리즈 삭제" onclick="return confirm('이 콘텐츠 시리즈를 삭제할까요? 콘텐츠는 삭제되지 않고 시리즈 연결만 제거됩니다. 외부 참조가 있으면 삭제되지 않습니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
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
</section>
<?php echo sr_admin_pagination_html($seriesPagination, '콘텐츠 시리즈 목록 페이지'); ?>

<div id="<?php echo sr_e($seriesCreateModalId); ?>" class="<?php echo sr_e($seriesCreateModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($seriesCreateModalId); ?>_title" aria-hidden="<?php echo sr_e($seriesCreateModalAriaHidden); ?>"<?php echo $seriesCreateModalInert; ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/content/series')); ?>" class="modal-content ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($seriesCreateModalId); ?>_title" class="modal-title">콘텐츠 시리즈 등록</h3>
                <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($seriesCreateModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_key_new">key <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="content_series_key_new" name="series_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" value="<?php echo sr_e((string) ($seriesFormValues['series_key'] ?? '')); ?>" required data-admin-key-input data-admin-key-suggest-source="#content_series_title_new" data-admin-key-suggest-fallback="series" data-overlay-focus class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_title_new">제목 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="content_series_title_new" name="title" maxlength="160" value="<?php echo sr_e((string) ($seriesFormValues['title'] ?? '')); ?>" required class="form-input form-control-full">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_description_new">설명</label>
                    <div class="admin-form-field">
                        <textarea id="content_series_description_new" name="description" rows="3" class="form-textarea"><?php echo sr_e((string) ($seriesFormValues['description'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_status_new">상태 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <select id="content_series_status_new" name="status" class="form-select" required>
                            <?php foreach (sr_content_series_statuses() as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($seriesFormValues['status'] ?? 'active') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_content_series_status_label($status)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_visibility_new">공개 범위 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <select id="content_series_visibility_new" name="visibility" class="form-select" required>
                            <?php foreach (sr_content_series_visibility_values() as $visibility) { ?>
                                <option value="<?php echo sr_e($visibility); ?>"<?php echo (string) ($seriesFormValues['visibility'] ?? 'public') === $visibility ? ' selected' : ''; ?>><?php echo sr_e(sr_content_series_visibility_label($visibility)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="content_series_sort_new">정렬</label>
                    <div class="admin-form-field">
                        <input id="content_series_sort_new" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($seriesFormValues['sort_order'] ?? 0)); ?>" class="form-input">
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

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
