<?php

$adminPageTitle = '커뮤니티 시리즈';
$adminPageSubtitle = '회원이 만든 시리즈의 상태와 공개 범위를 관리합니다.';
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
$seriesStatusClass = static function (string $status): string {
    return match ($status) {
        'active' => 'is-normal',
        'pending', 'hidden' => 'is-left',
        default => 'is-blocked',
    };
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/series')); ?>" class="btn btn-solid-light">전체</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">시리즈 <strong><?php echo sr_e((string) $totalSeries); ?>건</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=active')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('active')); ?> <?php echo sr_e((string) ($seriesStatusCounts['active'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=pending')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('pending')); ?> <?php echo sr_e((string) ($seriesStatusCounts['pending'] ?? 0)); ?>건</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/series?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_community_series_status_label('hidden')); ?> <?php echo sr_e((string) ($seriesStatusCounts['hidden'] ?? 0)); ?>건</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/series')); ?>" class="admin-filter admin-community-series-filter ui-form-theme">
    <div class="admin-filter-grid admin-community-series-search-grid">
            <div class="admin-filter-field admin-community-series-filter-status">
                <label for="community_series_filter_status" class="admin-filter-label">상태</label>
                <select id="community_series_filter_status" name="status" class="form-select admin-filter-input">
                    <option value="">전체</option>
                    <?php foreach (sr_community_series_statuses() as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo in_array($status, $selectedSeriesStatuses, true) ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_community_series_status_label($status)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-series-filter-visibility">
                <label for="community_series_filter_visibility" class="admin-filter-label">공개 범위</label>
                <select id="community_series_filter_visibility" name="visibility" class="form-select admin-filter-input">
                    <option value="">전체</option>
                    <?php foreach (sr_community_series_visibility_values() as $visibility) { ?>
                        <option value="<?php echo sr_e($visibility); ?>"<?php echo in_array($visibility, $selectedSeriesVisibilities, true) ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_community_series_visibility_label($visibility)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-series-filter-field">
            <label for="community_series_filter_field" class="admin-filter-label">검색 대상</label>
            <select id="community_series_filter_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'title' => '제목', 'board' => '게시판', 'owner' => '소유자', 'note' => '운영 메모'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($seriesFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
            </div>
            <div class="admin-filter-field admin-community-series-filter-keyword">
            <label for="community_series_filter_q" class="admin-filter-label">검색어</label>
            <input id="community_series_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($seriesFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, 게시판, 소유자">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
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
        <table class="table admin-community-series-table">
            <caption class="sr-only">커뮤니티 시리즈 목록</caption>
            <thead class="ui-table-head">
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
                            <form id="<?php echo sr_e($seriesUpdateFormId); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/community/series')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) $series['id']); ?>">
                            </form>
                        </td>
                        <td class="admin-table-break admin-community-series-board-cell"><?php echo sr_e((string) $series['board_title']); ?></td>
                        <td class="admin-table-break admin-community-series-owner-cell"><?php echo sr_e((string) ($series['owner_display_name'] ?? '')); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($seriesStatusClass((string) $series['status'])); ?>"><?php echo sr_e(sr_community_series_status_label((string) $series['status'])); ?></span>
                            <select form="<?php echo sr_e($seriesUpdateFormId); ?>" name="status" class="form-select" aria-label="상태">
                                <?php foreach (sr_community_series_statuses() as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) $series['status'] === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_community_series_status_label($status)); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td class="admin-table-nowrap">
                            <select form="<?php echo sr_e($seriesUpdateFormId); ?>" name="visibility" class="form-select" aria-label="공개 범위">
                                <?php foreach (sr_community_series_visibility_values() as $visibility) { ?>
                                    <option value="<?php echo sr_e($visibility); ?>"<?php echo (string) $series['visibility'] === $visibility ? ' selected' : ''; ?>><?php echo sr_e(sr_community_series_visibility_label($visibility)); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($series['active_item_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap admin-community-series-date-cell"><?php echo sr_e((string) ($series['updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions admin-community-series-actions">
                                <input form="<?php echo sr_e($seriesUpdateFormId); ?>" type="text" name="admin_note" maxlength="2000" value="<?php echo sr_e((string) ($series['admin_note'] ?? '')); ?>" class="form-input admin-community-series-note-input" aria-label="관리자 메모">
                                <button form="<?php echo sr_e($seriesUpdateFormId); ?>" type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="커뮤니티 시리즈 저장" title="저장"><?php echo sr_material_icon_html('save'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
<?php echo sr_admin_pagination_html($seriesPagination, '커뮤니티 시리즈 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
