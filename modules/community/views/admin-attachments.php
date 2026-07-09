<?php

$adminContainerClass = 'admin-community-attachments admin-ui-scope';
$attachmentSortOptions = isset($attachmentSortOptions) && is_array($attachmentSortOptions) ? $attachmentSortOptions : sr_community_admin_attachment_sort_options();
$attachmentDefaultSort = isset($attachmentDefaultSort) && is_array($attachmentDefaultSort) ? $attachmentDefaultSort : sr_community_admin_attachment_default_sort();
$attachmentSort = isset($attachmentSort) && is_array($attachmentSort) ? $attachmentSort : $attachmentDefaultSort;
$selectedStatuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
$attachmentDetailFilterOpen = $selectedStatuses !== []
    || (int) ($filters['board_id'] ?? 0) > 0
    || (int) ($filters['post_id'] ?? 0) > 0;
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/attachments');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총첨부 <strong><?php echo sr_e((string) (int) ($attachmentStatusCounts['total'] ?? 0)); ?>개</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/attachments?status=active')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) (int) ($attachmentStatusCounts['active'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/attachments?status=hidden')); ?>" class="admin-summary-meta">숨김 <?php echo sr_e((string) (int) ($attachmentStatusCounts['hidden'] ?? 0)); ?>개</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/attachments')); ?>" class="filtering-form admin-community-attachment-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $attachmentDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields filtering-fields-fit">
            <label class="filtering-field filtering-field-fill" for="community_attachment_filter_q">
                <span class="filtering-label">검색</span>
                <input id="community_attachment_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="파일명, 게시글, 게시판">
            </label>
        </div>
        <div id="community_attachment_detail_filters" class="filtering-body" data-filtering-body<?php echo $attachmentDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_toggle_group_html('community_attachment_filter_status', 'status', ['active' => '사용', 'hidden' => '숨김'], $selectedStatuses, '전체'); ?>
            </div>
            <label class="filtering-field" for="community_attachment_filter_board">
                <span class="filtering-label">게시판</span>
                <select id="community_attachment_filter_board" name="board_id" class="form-select filtering-input">
                    <option value="0"<?php echo (int) ($filters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체 게시판</option>
                    <?php foreach ($boards as $board) { ?>
                        <option value="<?php echo sr_e((string) (int) ($board['id'] ?? 0)); ?>"<?php echo (int) ($filters['board_id'] ?? 0) === (int) ($board['id'] ?? 0) ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($board['board_group_title'] ?? '') !== '' ? (string) $board['board_group_title'] . ' / ' . (string) ($board['title'] ?? '') : (string) ($board['title'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
            <label class="filtering-field" for="community_attachment_filter_post_id">
                <span class="filtering-label">게시글 ID</span>
                <input id="community_attachment_filter_post_id" type="number" min="1" name="post_id" value="<?php echo (int) ($filters['post_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['post_id']) : ''; ?>" class="form-input filtering-input">
            </label>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $attachmentDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_attachment_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">첨부파일 목록</h2>
        </div>
        <a href="<?php echo sr_e(sr_url('/admin/community/attachment-downloads')); ?>" class="btn btn-sm btn-outline-secondary">다운로드 내역</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($attachmentSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($attachmentSortOptions, $attachmentDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="첨부파일 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <form id="community-attachment-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/attachments')); ?>" class="admin-row-actions">
            <?php echo sr_csrf_field(); ?>
            <button type="submit" name="target_status" value="active" class="btn btn-sm btn-outline-warning" data-community-attachment-bulk-submit disabled>사용</button>
            <button type="submit" name="target_status" value="hidden" class="btn btn-sm btn-outline-warning" data-community-attachment-bulk-submit disabled>숨김</button>
            <button type="button" class="btn btn-sm btn-outline-light" data-community-attachment-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-community-attachment-selected-count>0</span></button>
        </form>
        <?php echo sr_admin_pagination_summary_html($attachmentPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-attachment-table">
            <caption class="sr-only">커뮤니티 첨부파일 목록</caption>
            <thead>
                <tr>
                    <th class="admin-table-checkbox-cell">
                        <label class="sr-only" for="community_attachment_bulk_select_all">현재 페이지 첨부파일 전체 선택</label>
                        <input id="community_attachment_bulk_select_all" type="checkbox" class="form-checkbox" data-community-attachment-select-all<?php echo $attachments === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('original_name', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('파일명', 'original_name', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('board', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('게시판', 'board', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('post', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('게시글', 'post', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('size_bytes', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('크기', 'size_bytes', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('download_count', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('다운로드', 'download_count', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $attachmentSort); ?>><?php echo sr_admin_sort_header_html('등록일', 'created_at', $attachmentSort, $attachmentSortOptions, $attachmentDefaultSort); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attachments === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">첨부파일이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($attachments as $attachment) { ?>
                    <?php $status = (string) ($attachment['status'] ?? 'active'); ?>
                    <tr>
                        <td class="admin-table-checkbox-cell">
                            <label class="sr-only" for="community_attachment_bulk_select_<?php echo sr_e((string) (int) $attachment['id']); ?>"><?php echo sr_e((string) $attachment['original_name']); ?> 선택</label>
                            <input id="community_attachment_bulk_select_<?php echo sr_e((string) (int) $attachment['id']); ?>" type="checkbox" name="selected_attachment_ids[]" value="<?php echo sr_e((string) (int) $attachment['id']); ?>" class="form-checkbox" form="community-attachment-bulk-status-form" data-community-attachment-row-select>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($attachment['original_name'] ?? '')); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e((string) ($attachment['mime_type'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break"><?php echo sr_e((string) ($attachment['board_title'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($attachment['post_title'] ?? '')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($attachment['post_id'] ?? 0)); ?> <?php echo sr_e((string) ($attachment['post_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo $status === 'active' ? 'is-success' : 'is-danger'; ?>"><?php echo $status === 'active' ? '사용' : '숨김'; ?></span></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(sr_community_format_bytes((int) ($attachment['size_bytes'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($attachment['download_count'] ?? 0))); ?>회</td>
                        <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($attachment['created_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/community/attachment-downloads?attachment_id=' . rawurlencode((string) (int) $attachment['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="다운로드 내역" title="다운로드 내역"><?php echo sr_material_icon_html('history'); ?></a>
                                <a href="<?php echo sr_e(sr_url('/community/attachment?id=' . rawurlencode((string) (int) $attachment['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="첨부 확인" title="첨부 확인" target="_blank" rel="noopener"><?php echo sr_material_icon_html('download'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('history'); ?> 다운로드 내역</span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('download'); ?> 첨부 확인</span>
    </div>
    <?php echo sr_admin_status_description_list_html('community_attachment_status', ['active' => '사용', 'hidden' => '숨김']); ?>
</section>

<?php echo sr_admin_pagination_html($attachmentPagination, '첨부파일 목록 페이지'); ?>

<script>
(function () {
    var countNode = document.querySelector('[data-community-attachment-selected-count]');
    var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-attachment-bulk-submit]'));
    var clear = document.querySelector('[data-community-attachment-bulk-clear]');
    var selectAll = document.querySelector('[data-community-attachment-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-community-attachment-row-select]'));

    var checkedRows = function () {
        return rowChecks.filter(function (input) {
            return input.checked && !input.disabled;
        });
    };
    var sync = function () {
        var selectedCount = checkedRows().length;
        if (countNode) {
            countNode.textContent = String(selectedCount);
        }
        submitButtons.forEach(function (button) {
            button.disabled = selectedCount === 0;
        });
        if (clear) {
            clear.hidden = selectedCount === 0;
        }
        if (selectAll) {
            selectAll.checked = rowChecks.length > 0 && selectedCount === rowChecks.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
        }
    };

    rowChecks.forEach(function (input) {
        input.addEventListener('change', sync);
    });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (input) {
                input.checked = selectAll.checked;
            });
            sync();
        });
    }
    if (clear) {
        clear.addEventListener('click', function () {
            rowChecks.forEach(function (input) {
                input.checked = false;
            });
            sync();
        });
    }
    sync();
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
