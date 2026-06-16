<?php

$adminPageTitle = '커뮤니티 시리즈';
$adminPageSubtitle = '회원이 만든 시리즈의 공개 상태와 노출 범위를 관리합니다.';
$adminContainerClass = 'admin-page-community-series-list admin-ui-scope';
$seriesFilters = isset($seriesFilters) && is_array($seriesFilters) ? $seriesFilters : ['status' => [], 'visibility' => [], 'field' => 'all', 'q' => ''];
$seriesSortOptions = isset($seriesSortOptions) && is_array($seriesSortOptions) ? $seriesSortOptions : sr_community_admin_series_sort_options();
$seriesDefaultSort = isset($seriesDefaultSort) && is_array($seriesDefaultSort) ? $seriesDefaultSort : sr_community_admin_series_default_sort();
$seriesSort = isset($seriesSort) && is_array($seriesSort) ? $seriesSort : sr_admin_sort_from_request($seriesSortOptions, $seriesDefaultSort);
$seriesPagination = isset($seriesPagination) && is_array($seriesPagination) ? $seriesPagination : sr_admin_pagination_from_total($pdo, count($seriesList ?? []));
$seriesStatusCounts = isset($seriesStatusCounts) && is_array($seriesStatusCounts) ? $seriesStatusCounts : ['total' => count($seriesList ?? [])];
$totalSeries = (int) ($seriesStatusCounts['total'] ?? count($seriesList ?? []));
$selectedSeriesStatuses = is_array($seriesFilters['status'] ?? null) ? $seriesFilters['status'] : [];
$selectedSeriesVisibilities = is_array($seriesFilters['visibility'] ?? null) ? $seriesFilters['visibility'] : [];
$communitySeriesCurrentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$communitySeriesActionSuffix = $communitySeriesCurrentQuery !== '' ? '?' . $communitySeriesCurrentQuery : '';
$seriesStatusClass = static function (string $status): string {
    return match ($status) {
        'active' => 'is-normal',
        'pending', 'hidden' => 'is-left',
        default => 'is-blocked',
    };
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/series');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">시리즈 <strong><?php echo sr_e((string) $totalSeries); ?>건</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=active')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('active')); ?> <?php echo sr_e((string) ($seriesStatusCounts['active'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=pending')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('pending')); ?> <?php echo sr_e((string) ($seriesStatusCounts['pending'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('hidden')); ?> <?php echo sr_e((string) ($seriesStatusCounts['hidden'] ?? 0)); ?>건</a>
    </div>
</div>

<?php $communitySeriesDetailFilterOpen = $selectedSeriesStatuses !== [] || $selectedSeriesVisibilities !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/series')); ?>" class="filtering-form admin-community-series-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $communitySeriesDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-community-series-search-grid">
            <div class="filtering-field admin-community-series-filter-field">
                <label for="community_series_filter_field" class="filtering-label">검색조건</label>
                <select id="community_series_filter_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'board' => '게시판', 'owner' => '소유자', 'note' => '운영 메모'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($seriesFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-community-series-filter-keyword">
                <label for="community_series_filter_q" class="filtering-label">검색어</label>
                <input id="community_series_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($seriesFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="제목, 게시판, 소유자">
            </div>
        </div>
        <div id="community_series_detail_filters" class="filtering-body" data-filtering-body<?php echo $communitySeriesDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-community-series-filter-status">
                <span class="filtering-label">상태</span>
                <?php
                $communitySeriesStatusOptions = [];
                foreach (sr_community_series_statuses() as $status) {
                    $communitySeriesStatusOptions[$status] = sr_community_series_status_label($status);
                }
                echo sr_admin_filter_toggle_group_html('community_series_filter_status', 'status', $communitySeriesStatusOptions, $selectedSeriesStatuses, '전체');
                ?>
            </div>
            <div class="filtering-field admin-community-series-filter-visibility">
                <span class="filtering-label">공개 범위</span>
                <?php
                $communitySeriesVisibilityOptions = [];
                foreach (sr_community_series_visibility_values() as $visibility) {
                    $visibilityLabel = sr_community_series_visibility_label((string) $visibility);
                    if ($visibilityLabel === '전체 공개') {
                        continue;
                    }
                    $communitySeriesVisibilityOptions[(string) $visibility] = $visibilityLabel;
                }
                echo sr_admin_filter_radio_toggle_group_html('community_series_filter_visibility', 'visibility', $communitySeriesVisibilityOptions, $selectedSeriesVisibilities, '전체');
                ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $communitySeriesDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_series_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">커뮤니티 시리즈 목록</h2>
            <p class="admin-dashboard-meta">작성자 공개 수정은 활성 시리즈와 글쓰기 가능한 게시판으로 제한하고, 관리자 메모는 조치 기록으로만 관리합니다.</p>
        </div>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($seriesSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($seriesSortOptions, $seriesDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="커뮤니티 시리즈 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($seriesPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-series-table">
            <caption class="sr-only">커뮤니티 시리즈 목록</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('title', $seriesSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('board_title', $seriesSort); ?>><?php echo sr_admin_sort_header_html('게시판', 'board_title', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('owner_display_name', $seriesSort); ?>><?php echo sr_admin_sort_header_html('소유자', 'owner_display_name', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $seriesSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('visibility', $seriesSort); ?>><?php echo sr_admin_sort_header_html('공개', 'visibility', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('active_item_count', $seriesSort); ?>><?php echo sr_admin_sort_header_html('글', 'active_item_count', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $seriesSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $seriesSort, $seriesSortOptions, $seriesDefaultSort); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($seriesList === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">등록된 커뮤니티 시리즈가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($seriesList as $series) { ?>
                    <?php $seriesUpdateFormId = 'community_series_update_' . (string) (int) $series['id']; ?>
                    <tr>
                        <td class="admin-table-break admin-community-series-title-cell">
                            <?php echo sr_e((string) $series['title']); ?>
                            <form id="<?php echo sr_e($seriesUpdateFormId); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/community/series' . $communitySeriesActionSuffix)); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                                <input type="hidden" name="status" value="<?php echo sr_e((string) $series['status']); ?>">
                                <input type="hidden" name="visibility" value="<?php echo sr_e((string) $series['visibility']); ?>">
                            </form>
                        </td>
                        <td class="admin-table-break admin-community-series-board-cell"><?php echo sr_e((string) $series['board_title']); ?></td>
                        <td class="admin-table-break admin-community-series-owner-cell"><?php echo sr_e((string) ($series['owner_display_name'] ?? '')); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($seriesStatusClass((string) $series['status'])); ?>"><?php echo sr_e(sr_community_series_status_label((string) $series['status'])); ?></span>
                            <div class="admin-row-actions">
                                <?php foreach (sr_community_series_statuses() as $status) { ?>
                                    <?php if ((string) $series['status'] === $status) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php $statusLabel = sr_community_series_status_label($status); ?>
                                    <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" name="status" value="<?php echo sr_e($status); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($status)); ?>"<?php echo sr_admin_row_action_confirm_attr($status, $statusLabel); ?>><?php echo sr_e($statusLabel); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo (string) $series['visibility'] === 'public' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_community_series_visibility_label((string) $series['visibility'])); ?></span>
                            <div class="admin-row-actions">
                                <?php foreach (sr_community_series_visibility_values() as $visibility) { ?>
                                    <?php if ((string) $series['visibility'] === $visibility) { ?>
                                        <?php continue; ?>
                                    <?php } ?>
                                    <?php $visibilityLabel = sr_community_series_visibility_label($visibility); ?>
                                    <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" name="visibility" value="<?php echo sr_e($visibility); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($visibility)); ?>"><?php echo sr_e($visibilityLabel); ?></button>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($series['active_item_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap admin-community-series-date-cell"><?php echo sr_community_time_html((string) ($series['updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions admin-community-series-actions">
                                <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="admin_note" maxlength="2000" value="<?php echo sr_e((string) ($series['admin_note'] ?? '')); ?>" class="form-input admin-community-series-note-input" aria-label="관리자 메모">
                                <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" class="btn btn-sm btn-solid-light" aria-label="커뮤니티 시리즈 메모 저장" title="메모 저장">메모 저장</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('community_series_status', array_combine(sr_community_series_statuses(), array_map('sr_community_series_status_label', sr_community_series_statuses())) ?: [], [], '시리즈 상태 설명'); ?>
    <?php echo sr_admin_status_description_list_html('community_series_visibility', array_combine(sr_community_series_visibility_values(), array_map('sr_community_series_visibility_label', sr_community_series_visibility_values())) ?: [], [], '공개 범위 설명'); ?>
</section>
<?php echo sr_admin_pagination_html($seriesPagination, '커뮤니티 시리즈 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
