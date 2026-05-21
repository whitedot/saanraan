<?php

$adminPageTitle = '회원관리';
$adminPageSubtitle = '회원 상태를 한눈에 확인하고, 조건 검색과 빠른 관리 동선을 자연스럽게 이어가세요.';
$adminContainerClass = 'admin-page-member-list admin-ui-scope';
$memberAdminPage = isset($memberAdminPage) ? (string) $memberAdminPage : 'members';
if ($memberAdminPage === 'create_form') {
    $adminPageTitle = '회원 추가';
    $adminPageSubtitle = '운영자가 회원 계정을 직접 생성합니다.';
} elseif ($memberAdminPage === 'edit_form') {
    $adminPageTitle = '회원 정보 수정';
    $adminPageSubtitle = '회원 기본 정보와 상태를 수정합니다.';
}
$statusCounts = isset($statusCounts) && is_array($statusCounts) ? $statusCounts : [];
$totalMembers = (int) ($statusCounts['total'] ?? count($members));
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$memberCreateValues = isset($memberCreateValues) && is_array($memberCreateValues) ? $memberCreateValues : sr_admin_member_create_default_values($site ?? []);
$memberEditValues = isset($memberEditValues) && is_array($memberEditValues) ? $memberEditValues : [];
$memberSettings = isset($memberSettings) && is_array($memberSettings) ? $memberSettings : sr_member_settings($pdo);
$memberLoginIdRequired = sr_member_login_id_required($memberSettings);
$createStatuses = sr_admin_member_create_allowed_statuses();
$memberLocaleOptions = sr_supported_locales($site ?? null);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberAdminPage === 'create_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create">
        <section class="admin-card card">
            <h2>회원 추가</h2>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_email">이메일</label>
                <div class="admin-form-field">
                    <input id="member_admin_create_email" type="email" name="email" value="<?php echo sr_e((string) ($memberCreateValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_login_id">로그인 아이디</label>
                <div class="admin-form-field">
                    <input id="member_admin_create_login_id" type="text" name="login_id" value="<?php echo sr_e((string) ($memberCreateValues['login_id'] ?? '')); ?>" class="form-input" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username"<?php echo $memberLoginIdRequired ? ' required' : ''; ?>>
                    <small class="admin-form-help"><?php echo $memberLoginIdRequired ? '이 설정에서는 로그인 아이디로만 로그인할 수 있습니다.' : '비워두면 이메일로 로그인하고, 입력하면 이메일과 아이디를 모두 사용할 수 있습니다.'; ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_display_name">이름</label>
                <div class="admin-form-field">
                    <input id="member_admin_create_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberCreateValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_password">비밀번호</label>
                <div class="admin-form-field">
                    <input id="member_admin_create_password" type="password" name="password" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_password_confirm">비밀번호 확인</label>
                <div class="admin-form-field">
                    <input id="member_admin_create_password_confirm" type="password" name="password_confirm" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_locale">Locale</label>
                <div class="admin-form-field">
                    <select id="member_admin_create_locale" name="locale" class="form-select" required>
                        <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                            <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberCreateValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                <?php echo sr_e($localeOption); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_status">상태</label>
                <div class="admin-form-field">
                    <select id="member_admin_create_status" name="status" class="form-select">
                        <?php foreach ($createStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberCreateValues['status'] ?? 'active') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">이메일 인증</span>
                <div class="admin-form-field admin-form-check">
                    <input id="member_admin_create_email_verified" type="checkbox" name="email_verified" value="1" class="form-checkbox"<?php echo (string) ($memberCreateValues['email_verified'] ?? '1') === '1' ? ' checked' : ''; ?>>
                    <label for="member_admin_create_email_verified"><?php echo sr_admin_choice_label_html('인증 완료로 처리'); ?></label>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } elseif ($memberAdminPage === 'edit_form') { ?>
    <?php if (is_array($editMember)) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="edit">
            <input type="hidden" name="account_id" value="<?php echo sr_e((string) ($memberEditValues['id'] ?? $editMember['id'])); ?>">
            <section class="admin-card card">
                <h2>회원 정보 수정</h2>
                <div class="admin-form-row">
                    <span class="form-label">공개 해시</span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e(sr_admin_member_public_hash($runtimeConfig, (int) $editMember['id'])); ?></code>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_email">이메일</label>
                    <div class="admin-form-field">
                        <input id="member_admin_edit_email" type="email" name="email" value="<?php echo sr_e((string) ($memberEditValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_login_id">로그인 아이디</label>
                    <div class="admin-form-field">
                        <?php
                        $memberEditAccountIdentifierHash = (string) ($editMember['account_identifier_hash'] ?? '');
                        $memberEditEmailHash = (string) ($editMember['email_hash'] ?? '');
                        $memberEditHasLoginId = (string) ($editMember['login_id_hash'] ?? '') !== ''
                            || (
                                $memberEditAccountIdentifierHash !== ''
                                && $memberEditEmailHash !== ''
                                && !hash_equals($memberEditEmailHash, $memberEditAccountIdentifierHash)
                            );
                        ?>
                        <input id="member_admin_edit_login_id" type="text" name="login_id" value="<?php echo sr_e((string) ($memberEditValues['login_id'] ?? '')); ?>" class="form-input" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username" placeholder="새 로그인 아이디">
                        <small class="admin-form-help">현재 상태: <?php echo $memberEditHasLoginId ? '등록됨' : '없음'; ?>. 새 값을 입력하면 로그인 아이디가 변경되고, 비워두면 기존 상태를 유지합니다.</small>
                        <?php if (!$memberLoginIdRequired) { ?>
                            <label class="admin-form-check form-label" for="member_admin_edit_clear_login_id">
                                <input id="member_admin_edit_clear_login_id" type="checkbox" name="clear_login_id" value="1" class="form-checkbox"<?php echo (string) ($memberEditValues['clear_login_id'] ?? '0') === '1' ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('로그인 아이디 해제'); ?>
                            </label>
                        <?php } ?>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_display_name">이름</label>
                    <div class="admin-form-field">
                        <input id="member_admin_edit_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberEditValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_locale">Locale</label>
                    <div class="admin-form-field">
                        <select id="member_admin_edit_locale" name="locale" class="form-select" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberEditValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_status">상태</label>
                    <div class="admin-form-field">
                        <select id="member_admin_edit_status" name="status" class="form-select">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberEditValues['status'] ?? '') === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </section>
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
                <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light">목록</a>
                <button type="submit" class="btn btn-solid-primary">저장</button>
            </div>
        </form>
    <?php } else { ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light">목록</a>
        </div>
    <?php } ?>
<?php } else { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총회원 <strong><?php echo sr_e((string) $totalMembers); ?>명</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=suspended')); ?>" class="admin-summary-meta">차단 <?php echo sr_e((string) ($statusCounts['suspended'] ?? 0)); ?>명</a>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=withdrawn')); ?>" class="admin-summary-meta">탈퇴 <?php echo sr_e((string) (($statusCounts['withdrawn'] ?? 0) + ($statusCounts['anonymized'] ?? 0))); ?>명</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-member-search-grid">
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
                <?php foreach (['all' => '전체', 'hash' => '해시 아이디', 'email' => '이메일', 'login_id' => '로그인 아이디', 'name' => '이름'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-member-filter-keyword">
            <label for="member-search-keyword" class="admin-filter-label">검색어</label>
            <input type="text" id="member-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="해시 아이디, 이메일, 로그인 아이디, 이름">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">회원 목록</h2>
        <a href="<?php echo sr_e(sr_url('/admin/members/new')); ?>" class="btn btn-sm btn-solid-light">새 회원 추가</a>
    </div>
    <div class="table-wrapper">
        <table class="table admin-member-table">
            <caption class="sr-only">회원관리 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>공개 해시</th>
                    <th>이메일</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>이메일 인증</th>
                    <th>최근 로그인</th>
                    <th>활성 세션</th>
                    <th>생성일</th>
                    <th class="text-end">관리</th>
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
                                <a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $member['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
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
</section>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong>회원 관리 안내</strong>
        <p>상태 변경은 즉시 적용되며, 세션 폐기 시 해당 회원의 활성 로그인 세션이 모두 종료됩니다.</p>
    </div>
</div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
