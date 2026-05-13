<?php

$adminPageTitle = '회원관리';
$adminPageSubtitle = '회원 상태를 한눈에 확인하고, 조건 검색과 빠른 관리 동선을 자연스럽게 이어가세요.';
$adminContainerClass = 'admin-page-member-list';
$totalMembers = count($members);
$statusCounts = array_fill_keys($allowedStatuses, 0);
foreach ($members as $member) {
    $statusKey = (string) ($member['status'] ?? '');
    if (array_key_exists($statusKey, $statusCounts)) {
        $statusCounts[$statusKey]++;
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p class="member-notice"><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul class="member-notice">
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<div class="member-summary">
    <div class="member-summary-links">
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-surface-default-soft">전체 보기</a>
    </div>
    <div class="member-summary-stats">
        <span class="member-summary-meta">총회원 <strong><?php echo sr_e((string) $totalMembers); ?>명</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=suspended')); ?>" class="member-summary-meta">차단 <?php echo sr_e((string) ($statusCounts['suspended'] ?? 0)); ?>명</a>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=withdrawn')); ?>" class="member-summary-meta">탈퇴 <?php echo sr_e((string) (($statusCounts['withdrawn'] ?? 0) + ($statusCounts['anonymized'] ?? 0))); ?>명</a>
    </div>
</div>

<div class="member-search-card">
    <form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
        <div class="member-search-fields">
            <div class="member-field">
                <label for="member-status-filter" class="member-field-label">상태</label>
                <select name="status" id="member-status-filter" class="form-select member-field-input">
                    <option value="">전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn btn-solid-primary member-search-submit">검색</button>
        </div>
    </form>
</div>

<div class="member-table-card admin-member-list-form">
    <div class="table-wrapper">
        <table class="table">
            <caption>회원관리 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th scope="col">공개 해시</th>
                    <th scope="col">이메일</th>
                    <th scope="col">표시명</th>
                    <th scope="col">상태</th>
                    <th scope="col">이메일 인증</th>
                    <th scope="col">최근 로그인</th>
                    <th scope="col">활성 세션</th>
                    <th scope="col">생성일</th>
                    <th scope="col" class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-dashboard-empty">회원이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($members as $member) { ?>
                    <?php
                    $memberStatus = (string) $member['status'];
                    $statusClass = match ($memberStatus) {
                        'active' => 'is-normal',
                        'suspended', 'pending' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="member-cell-fixed member-cell-id"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="member-cell-email"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="member-cell-fixed"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <td class="member-cell-fixed"><span class="member-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td><?php echo sr_e((string) ($member['email_verified_at'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) ($member['last_login_at'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) $member['active_session_count']); ?></td>
                        <td><?php echo sr_e((string) $member['created_at']); ?></td>
                        <td class="member-cell-manage">
                            <div class="member-manage">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="status">
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach ($allowedStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $memberStatus === $status ? ' selected' : ''; ?>>
                                                <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-surface-default-soft">저장</button>
                                </form>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="revoke_sessions">
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">세션 폐기</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="member-notice">
    <span class="member-notice-icon" aria-hidden="true">i</span>
    <div class="member-notice-copy">
        <strong>회원 관리 안내</strong>
        <p>상태 변경은 즉시 적용되며, 세션 폐기 시 해당 회원의 활성 로그인 세션이 모두 종료됩니다.</p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
