<?php

$adminPageTitle = '회원관리';
$adminPageSubtitle = '회원 상태를 한눈에 확인하고, 조건 검색과 빠른 관리 동선을 자연스럽게 이어가세요.';
$adminContainerClass = 'admin-page-member-list admin-ui-scope';
$statusCounts = isset($statusCounts) && is_array($statusCounts) ? $statusCounts : [];
$totalMembers = (int) ($statusCounts['total'] ?? count($members));
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-soft-default">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총회원 <strong><?php echo sr_e((string) $totalMembers); ?>명</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=suspended')); ?>" class="admin-summary-meta">차단 <?php echo sr_e((string) ($statusCounts['suspended'] ?? 0)); ?>명</a>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=withdrawn')); ?>" class="admin-summary-meta">탈퇴 <?php echo sr_e((string) (($statusCounts['withdrawn'] ?? 0) + ($statusCounts['anonymized'] ?? 0))); ?>명</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-account-search-grid">
        <div class="admin-filter-field admin-member-filter-status">
            <label for="admin-status-filter" class="admin-filter-label">상태</label>
            <select name="status" id="admin-status-filter" class="form-select admin-filter-input">
                <option value="">전체</option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-member-filter-field">
            <label for="member-search-field" class="admin-filter-label">검색 조건</label>
            <select name="field" id="member-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'hash' => '해시 아이디', 'email' => '이메일', 'name' => '이름'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-member-filter-keyword">
            <label for="member-search-keyword" class="admin-filter-label">검색어</label>
            <input type="text" id="member-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="해시 아이디, 이메일, 이름">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<div class="admin-card admin-list-card card admin-list-form">
    <div class="table-wrapper">
        <table class="table admin-member-table">
            <caption class="sr-only">회원관리 목록</caption>
            <colgroup>
                <col class="admin-member-col-hash">
                <col class="admin-member-col-email">
                <col class="admin-member-col-name">
                <col class="admin-member-col-status">
                <col class="admin-member-col-date">
                <col class="admin-member-col-date">
                <col class="admin-member-col-session">
                <col class="admin-member-col-date">
                <col class="admin-member-col-actions">
            </colgroup>
            <thead class="ui-table-head">
                <tr>
                    <th scope="col">공개 해시</th>
                    <th scope="col">이메일</th>
                    <th scope="col">이름</th>
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
                        <td colspan="9" class="admin-empty-state">회원이 없습니다.</td>
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
                        <td class="admin-table-nowrap admin-table-id admin-member-hash-cell" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="admin-table-break admin-member-email-cell"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['email_verified_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['last_login_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-session-cell"><?php echo sr_e((string) $member['active_session_count']); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) $member['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <details class="admin-inline-edit-details">
                                    <summary class="btn btn-sm btn-soft-default">정보 수정</summary>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="admin-inline-edit-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="edit">
                                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                        <?php $memberFieldIdPrefix = 'modules_member_admin_members_' . (string) $member['id'] . '_'; ?>
                                        <label for="<?php echo sr_e($memberFieldIdPrefix); ?>email">
                                            <span>이메일</span>
                                            <input id="<?php echo sr_e($memberFieldIdPrefix); ?>email" type="email" name="email" value="<?php echo sr_e((string) $member['email']); ?>" class="form-input" required>
                                        </label>
                                        <label for="<?php echo sr_e($memberFieldIdPrefix); ?>display_name">
                                            <span>이름</span>
                                            <input id="<?php echo sr_e($memberFieldIdPrefix); ?>display_name" type="text" name="display_name" value="<?php echo sr_e((string) $member['display_name']); ?>" class="form-input" maxlength="120" required>
                                        </label>
                                        <label for="<?php echo sr_e($memberFieldIdPrefix); ?>locale">
                                            <span>Locale</span>
                                            <input id="<?php echo sr_e($memberFieldIdPrefix); ?>locale" type="text" name="locale" value="<?php echo sr_e((string) $member['locale']); ?>" class="form-input" maxlength="20" required>
                                        </label>
                                        <label for="<?php echo sr_e($memberFieldIdPrefix); ?>status">
                                            <span>상태</span>
                                            <select id="<?php echo sr_e($memberFieldIdPrefix); ?>status" name="status" class="form-select form-select-sm">
                                                <?php foreach ($allowedStatuses as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo $memberStatus === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-solid-primary">저장</button>
                                    </form>
                                </details>
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

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong>회원 관리 안내</strong>
        <p>상태 변경은 즉시 적용되며, 세션 폐기 시 해당 회원의 활성 로그인 세션이 모두 종료됩니다.</p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
