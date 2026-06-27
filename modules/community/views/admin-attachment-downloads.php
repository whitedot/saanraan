<?php

$adminContainerClass = 'admin-community-attachment-downloads admin-ui-scope';
$downloadLogSortOptions = isset($downloadLogSortOptions) && is_array($downloadLogSortOptions) ? $downloadLogSortOptions : sr_community_admin_attachment_download_log_sort_options();
$downloadLogDefaultSort = isset($downloadLogDefaultSort) && is_array($downloadLogDefaultSort) ? $downloadLogDefaultSort : sr_community_admin_attachment_download_log_default_sort();
$downloadLogSort = isset($downloadLogSort) && is_array($downloadLogSort) ? $downloadLogSort : $downloadLogDefaultSort;
$selectedDownloadTypes = is_array($filters['download_type'] ?? null) ? $filters['download_type'] : [];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/attachment-downloads');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$detailFilterOpen = (int) ($filters['board_id'] ?? 0) > 0
    || (int) ($filters['post_id'] ?? 0) > 0
    || (int) ($filters['attachment_id'] ?? 0) > 0
    || (int) ($filters['account_id'] ?? 0) > 0
    || (string) ($filters['date_from'] ?? '') !== ''
    || (string) ($filters['date_to'] ?? '') !== '';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/attachment-downloads')); ?>" class="filtering-form admin-community-attachment-download-filter ui-form-theme">
    <div class="filtering-fields admin-content-filter-stack">
        <div class="filtering filtering-card<?php echo $detailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields filtering-fields-fit">
                <div class="filtering-field">
                    <span class="filtering-label">구분</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('community_attachment_download_filter_type', 'download_type', ['free' => '무료', 'paid' => '유료'], $selectedDownloadTypes, '전체'); ?>
                </div>
                <label class="filtering-field-fill filtering-field" for="community_attachment_download_filter_q">
                    <span class="filtering-label">검색</span>
                    <input id="community_attachment_download_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="게시판, 게시글, 첨부파일, 회원">
                </label>
            </div>
            <div id="community_attachment_download_detail_filters" class="filtering-body" data-filtering-body<?php echo $detailFilterOpen ? '' : ' hidden'; ?>>
                <label class="filtering-field" for="community_attachment_download_filter_board">
                    <span class="filtering-label">게시판</span>
                    <select id="community_attachment_download_filter_board" name="board_id" class="form-select filtering-input">
                        <option value="0"<?php echo (int) ($filters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체 게시판</option>
                        <?php foreach ($boards as $board) { ?>
                            <option value="<?php echo sr_e((string) (int) ($board['id'] ?? 0)); ?>"<?php echo (int) ($filters['board_id'] ?? 0) === (int) ($board['id'] ?? 0) ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($board['board_group_title'] ?? '') !== '' ? (string) $board['board_group_title'] . ' / ' . (string) ($board['title'] ?? '') : (string) ($board['title'] ?? '')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_post_id">
                    <span class="filtering-label">게시글 ID</span>
                    <input id="community_attachment_download_filter_post_id" type="number" min="1" name="post_id" value="<?php echo (int) ($filters['post_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['post_id']) : ''; ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_attachment_id">
                    <span class="filtering-label">첨부 ID</span>
                    <input id="community_attachment_download_filter_attachment_id" type="number" min="1" name="attachment_id" value="<?php echo (int) ($filters['attachment_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['attachment_id']) : ''; ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_account_id">
                    <span class="filtering-label">회원</span>
                    <input id="community_attachment_download_filter_account_id" type="text" name="account_id" value="<?php echo (int) ($filters['account_id'] ?? 0) > 0 ? sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $filters['account_id'])) : ''; ?>" class="form-input filtering-input" maxlength="80" autocomplete="off">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_date_from">
                    <span class="filtering-label">시작일</span>
                    <input id="community_attachment_download_filter_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_date_to">
                    <span class="filtering-label">종료일</span>
                    <input id="community_attachment_download_filter_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
                </label>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $detailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_attachment_download_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">다운로드 내역</h2>
            <p class="form-help">무료 다운로드는 로그인 회원만 회원 정보가 남고, 유료 다운로드는 차감 로그 ID를 함께 보존합니다.</p>
        </div>
        <a href="<?php echo sr_e(sr_url('/admin/community/attachments')); ?>" class="btn btn-sm btn-outline-secondary">첨부파일 관리</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($downloadLogSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($downloadLogSortOptions, $downloadLogDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="다운로드 내역 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($downloadLogPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-attachment-download-table">
            <caption class="sr-only">커뮤니티 첨부 다운로드 내역</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('created_at', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('다운로드 시각', 'created_at', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('board', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('게시판', 'board', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('post', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('게시글', 'post', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('attachment', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('첨부파일', 'attachment', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('회원', 'account_id', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('download_type', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('구분', 'download_type', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('amount', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('차감', 'amount', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($downloadLogs === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">다운로드 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($downloadLogs as $downloadLog) { ?>
                    <?php
                    $isPaid = (string) ($downloadLog['download_type'] ?? '') === 'paid';
                    $downloadAccountId = (int) ($downloadLog['account_id'] ?? 0);
                    $memberName = trim((string) ($downloadLog['display_name'] ?? ''));
                    $memberPublicHash = $downloadAccountId > 0 && function_exists('sr_admin_member_public_hash')
                        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $downloadAccountId)
                        : '';
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($downloadLog['created_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['board_title'] ?? '삭제된 게시판')); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e((string) ($downloadLog['board_key'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['post_title'] ?? '삭제된 게시글')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['post_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['post_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['attachment_name'] ?? '삭제된 첨부파일')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['attachment_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['attachment_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($downloadAccountId > 0) { ?>
                                <strong><?php echo sr_e($memberName !== '' ? $memberName : '회원'); ?></strong>
                                <?php if ($memberPublicHash !== '') { ?>
                                    <small class="admin-summary-meta"><?php echo sr_e($memberPublicHash); ?></small>
                                <?php } ?>
                            <?php } else { ?>
                                <?php echo sr_e('비회원'); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo $isPaid ? 'is-warning' : 'is-normal'; ?>"><?php echo $isPaid ? '유료' : '무료'; ?></span></td>
                        <td class="admin-table-break">
                            <?php if ($isPaid) { ?>
                                <?php echo sr_e(sr_community_asset_module_labels((string) ($downloadLog['asset_module'] ?? ''), $pdo)); ?>
                                <?php echo sr_e(number_format((int) ($downloadLog['amount'] ?? 0))); ?>
                                · <?php echo sr_e((string) (sr_community_asset_charge_policies()[(string) ($downloadLog['charge_policy'] ?? 'once')] ?? $downloadLog['charge_policy'] ?? '')); ?>
                            <?php } else { ?>
                                차감 없음
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('community_attachment_download_type', ['free' => '무료', 'paid' => '유료'], [], '다운로드 유형 설명'); ?>
</section>

<?php echo sr_admin_pagination_html($downloadLogPagination, '첨부 다운로드 내역 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
