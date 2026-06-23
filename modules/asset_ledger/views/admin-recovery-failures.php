<?php

$adminPageTitle = '보상 운영';
$adminPageSubtitle = '지급 로그와 미회수 큐를 같은 운영 메뉴에서 확인합니다.';
$adminContainerClass = 'admin-page-asset-recovery admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/assets/recovery-failures');
$recoveryFailureFilters = isset($recoveryFailureFilters) && is_array($recoveryFailureFilters) ? $recoveryFailureFilters : [];
$recoveryFailures = isset($recoveryFailures) && is_array($recoveryFailures) ? $recoveryFailures : [];
$recoveryFailureTableReady = isset($recoveryFailureTableReady) ? (bool) $recoveryFailureTableReady : true;
$statusClass = static function (string $status): string {
    return match ($status) {
        'open' => 'is-warning',
        'recovered', 'manually_resolved' => 'is-normal',
        'cancelled' => 'is-left',
        default => 'is-blocked',
    };
};
$assetLabel = static function (string $assetModule): string {
    return match ($assetModule) {
        'point' => '포인트',
        'reward' => '적립금',
        'deposit' => '예치금',
        default => $assetModule,
    };
};
$assetUnit = static function (string $assetModule): string {
    return match ($assetModule) {
        'reward', 'deposit' => '원',
        default => '점',
    };
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/rewards/transactions')); ?>" class="btn btn-sm btn-solid-light">지급 로그</a>
        <a href="<?php echo sr_e(sr_url('/admin/assets/recovery-failures')); ?>" class="btn btn-sm btn-solid-primary">미회수 큐</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/assets/recovery-failures')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields admin-community-recovery-filter-grid">
        <label class="filtering-field" for="asset_recovery_status">
            <span class="filtering-label">상태</span>
            <select id="asset_recovery_status" name="status" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (sr_asset_recovery_statuses() as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($recoveryFailureFilters['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_asset_recovery_status_label($status)); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field" for="asset_recovery_source_module">
            <span class="filtering-label">출처</span>
            <select id="asset_recovery_source_module" name="source_module" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (['community' => '커뮤니티', 'content' => '콘텐츠', 'quiz' => '퀴즈', 'survey' => '설문'] as $sourceModule => $sourceLabel) { ?>
                    <option value="<?php echo sr_e($sourceModule); ?>"<?php echo (string) ($recoveryFailureFilters['source_module'] ?? '') === $sourceModule ? ' selected' : ''; ?>><?php echo sr_e($sourceLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field" for="asset_recovery_asset_module">
            <span class="filtering-label">자산</span>
            <select id="asset_recovery_asset_module" name="asset_module" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (['point' => '포인트', 'reward' => '적립금', 'deposit' => '예치금'] as $assetModule => $assetModuleLabel) { ?>
                    <option value="<?php echo sr_e($assetModule); ?>"<?php echo (string) ($recoveryFailureFilters['asset_module'] ?? '') === $assetModule ? ' selected' : ''; ?>><?php echo sr_e($assetModuleLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field" for="asset_recovery_subject_type">
            <span class="filtering-label">대상 유형</span>
            <input id="asset_recovery_subject_type" type="search" name="subject_type" value="<?php echo sr_e((string) ($recoveryFailureFilters['subject_type'] ?? '')); ?>" class="form-input filtering-input" maxlength="80">
        </label>
        <label class="filtering-field" for="asset_recovery_subject_id">
            <span class="filtering-label">대상 ID</span>
            <input id="asset_recovery_subject_id" type="search" name="subject_id" value="<?php echo sr_e((string) ((int) ($recoveryFailureFilters['subject_id'] ?? 0) ?: '')); ?>" class="form-input filtering-input" inputmode="numeric" pattern="[0-9]*">
        </label>
        <label class="filtering-field" for="asset_recovery_q">
            <span class="filtering-label">회원</span>
            <input id="asset_recovery_q" type="search" name="q" value="<?php echo sr_e((string) ($recoveryFailureFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120">
        </label>
        <label class="filtering-field" for="asset_recovery_created_from">
            <span class="filtering-label">생성 시작</span>
            <input id="asset_recovery_created_from" type="date" name="created_from" value="<?php echo sr_e((string) ($recoveryFailureFilters['created_from'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <label class="filtering-field" for="asset_recovery_created_to">
            <span class="filtering-label">생성 종료</span>
            <input id="asset_recovery_created_to" type="date" name="created_to" value="<?php echo sr_e((string) ($recoveryFailureFilters['created_to'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>

<?php if (!$recoveryFailureTableReady) { ?>
    <div class="alert alert-warning">
        보상 미회수 테이블이 아직 준비되지 않았습니다.
        <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>">DB 업데이트</a>를 먼저 적용하세요.
    </div>
<?php } ?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">미회수 큐</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($recoveryFailurePagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-recovery-table">
            <caption class="sr-only">보상 미회수 큐</caption>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>상태</th>
                    <th>출처</th>
                    <th>회원</th>
                    <th>대상</th>
                    <th>자산</th>
                    <th>금액</th>
                    <th>시도</th>
                    <th>일시</th>
                    <th>작업</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recoveryFailures === []) { ?>
                    <tr>
                        <td colspan="10" class="admin-empty-state">미회수 기록이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($recoveryFailures as $failure) { ?>
                    <?php
                    $failureId = (int) ($failure['id'] ?? 0);
                    $status = sr_asset_recovery_status_normalize((string) ($failure['status'] ?? ''));
                    $sourceModule = (string) ($failure['source_module'] ?? '');
                    $subjectLink = sr_asset_recovery_subject_link($failure);
                    $subjectId = (int) ($failure['subject_id'] ?? 0);
                    $moduleKey = (string) ($failure['asset_module'] ?? '');
                    $accountLabel = trim((string) (($failure['account_display_name'] ?? '') ?: ($failure['account_email'] ?? '')));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) $failureId); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass($status)); ?>"><?php echo sr_e(sr_asset_recovery_status_label($status)); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_asset_recovery_source_label($sourceModule)); ?></td>
                        <td class="admin-table-break">
                            <strong>#<?php echo sr_e((string) (int) ($failure['account_id'] ?? 0)); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e($accountLabel !== '' ? $accountLabel : '회원 정보 없음'); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <?php if ((string) ($subjectLink['url'] ?? '') !== '') { ?>
                                <a href="<?php echo sr_e(sr_url((string) $subjectLink['url'])); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e((string) $subjectLink['label']); ?></a>
                            <?php } else { ?>
                                <span class="badge badge-soft-secondary"><?php echo sr_e((string) $subjectLink['label']); ?></span>
                            <?php } ?>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) $subjectId); ?></small>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e($assetLabel($moduleKey)); ?></td>
                        <td class="admin-table-nowrap text-end">
                            <strong><?php echo sr_e(number_format((int) ($failure['unrecovered_amount'] ?? 0))); ?> <?php echo sr_e($assetUnit($moduleKey)); ?></strong>
                            <small class="admin-summary-meta">회수 <?php echo sr_e(number_format((int) ($failure['recovered_amount'] ?? 0))); ?> / 대상 <?php echo sr_e(number_format((int) ($failure['attempted_amount'] ?? 0))); ?></small>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($failure['attempt_count'] ?? 0))); ?>회</td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_admin_time_html((string) ($failure['updated_at'] ?? '')); ?>
                            <small class="admin-summary-meta">최근 <?php echo sr_admin_time_html((string) ($failure['last_attempted_at'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-actions">
                            <?php if ($status === 'open') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/assets/recovery-failures?' . (string) ($_SERVER['QUERY_STRING'] ?? ''))); ?>">
                                    <?php echo sr_csrf_input(); ?>
                                    <input type="hidden" name="intent" value="retry">
                                    <input type="hidden" name="failure_id" value="<?php echo sr_e((string) $failureId); ?>">
                                    <button type="submit" class="btn btn-sm btn-solid-primary">재회수</button>
                                </form>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/assets/recovery-failures?' . (string) ($_SERVER['QUERY_STRING'] ?? ''))); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_input(); ?>
                                    <input type="hidden" name="intent" value="manual_resolve">
                                    <input type="hidden" name="failure_id" value="<?php echo sr_e((string) $failureId); ?>">
                                    <input type="text" name="admin_reason" maxlength="500" class="form-input form-input-sm" placeholder="사유" required>
                                    <input type="text" name="confirm_text" maxlength="20" class="form-input form-input-sm" placeholder="해소" required>
                                    <button type="submit" class="btn btn-sm btn-outline-light">해소</button>
                                </form>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/assets/recovery-failures?' . (string) ($_SERVER['QUERY_STRING'] ?? ''))); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_input(); ?>
                                    <input type="hidden" name="intent" value="manual_cancel">
                                    <input type="hidden" name="failure_id" value="<?php echo sr_e((string) $failureId); ?>">
                                    <input type="text" name="admin_reason" maxlength="500" class="form-input form-input-sm" placeholder="사유" required>
                                    <input type="text" name="confirm_text" maxlength="20" class="form-input form-input-sm" placeholder="취소" required>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">취소</button>
                                </form>
                            <?php } else { ?>
                                <span class="admin-summary-meta">처리 완료</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('asset_recovery_status', array_combine(sr_asset_recovery_statuses(), array_map('sr_asset_recovery_status_label', sr_asset_recovery_statuses())) ?: []); ?>
</section>

<?php echo sr_admin_pagination_html($recoveryFailurePagination, '보상 미회수 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
