<?php

$adminPageTitle = '관리자 권한';
$adminPageSubtitle = '회원 검색 결과 안에서만 관리자 역할을 부여하거나 회수합니다.';
$adminContainerClass = 'admin-page-role-list admin-ui-scope';
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$statusFilter = isset($statusFilter) ? (string) $statusFilter : '';
$roleFilter = isset($roleFilter) ? (string) $roleFilter : '';
$hasRoleFilters = !empty($hasRoleFilters);
$roleFormAction = sr_url(sr_admin_role_filter_url($statusFilter, $roleFilter, $searchFilter));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/roles')); ?>" class="admin-filter admin-role-filter ui-form-theme">
    <div class="admin-filter-grid admin-role-search-grid">
        <div class="admin-filter-field">
            <label for="admin-role-status-filter" class="admin-filter-label">계정 상태</label>
            <select name="status" id="admin-role-status-filter" class="form-select admin-filter-input">
                <option value="">전체</option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-filter" class="admin-filter-label">권한</label>
            <select name="role" id="admin-role-filter" class="form-select admin-filter-input">
                <option value="">전체</option>
                <option value="any"<?php echo $roleFilter === 'any' ? ' selected' : ''; ?>>권한 있음</option>
                <option value="none"<?php echo $roleFilter === 'none' ? ' selected' : ''; ?>>권한 없음</option>
                <?php foreach ($allowedRoles as $roleKey) { ?>
                    <option value="<?php echo sr_e($roleKey); ?>"<?php echo $roleFilter === $roleKey ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($roleKey, 'role')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-search-field" class="admin-filter-label">검색 조건</label>
            <select name="field" id="admin-role-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'hash' => '해시 아이디', 'email' => '이메일', 'login_id' => '로그인 아이디', 'name' => '이름'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-search-keyword" class="admin-filter-label">검색어</label>
            <input type="text" id="admin-role-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="해시 아이디, 이메일, 로그인 아이디, 이름">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<div class="admin-card admin-list-card card admin-list-form">
<div class="table-wrapper">
<table class="table">
    <thead class="ui-table-head">
        <tr>
            <th>공개 해시</th>
            <th>이메일</th>
            <th>표시명</th>
            <th>계정 상태</th>
            <th>현재 역할</th>
            <th class="text-end">변경</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$hasRoleFilters) { ?>
            <tr>
                <td colspan="6" class="admin-empty-state">필터를 선택하거나 검색어를 입력하면 회원이 표시됩니다.</td>
            </tr>
        <?php } elseif ($accounts === []) { ?>
            <tr>
                <td colspan="6" class="admin-empty-state">조건에 맞는 회원이 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($accounts as $adminAccount) { ?>
            <?php $roleModalId = 'admin-role-modal-' . (string) $adminAccount['id']; ?>
            <tr>
                <td><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></td>
                <td><?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $adminAccount['status'], 'member_status')); ?></td>
                <td><?php echo sr_e($adminAccount['roles'] === [] ? '없음' : implode(', ', array_map(static function (string $roleKey): string {
                    return sr_admin_code_label($roleKey, 'role');
                }, $adminAccount['roles']))); ?></td>
                <td class="admin-table-actions-cell">
                    <div class="admin-row-actions">
                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($roleModalId); ?>" data-overlay="#<?php echo sr_e($roleModalId); ?>">
                            권한 변경
                        </button>
                    </div>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<?php foreach ($accounts as $adminAccount) { ?>
    <?php $roleModalId = 'admin-role-modal-' . (string) $adminAccount['id']; ?>
    <div id="<?php echo sr_e($roleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($roleModalId); ?>-label">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo sr_e($roleFormAction); ?>" class="admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($roleModalId); ?>-label" class="modal-title">관리자 권한 변경</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($roleModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $adminAccount['id']); ?>">
                        <div class="admin-form-row">
                            <span class="form-label">회원</span>
                            <div class="admin-form-field">
                                <strong><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></strong><br>
                                <?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?> · <?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label">현재 역할</span>
                            <div class="admin-form-field">
                                <?php echo sr_e($adminAccount['roles'] === [] ? '없음' : implode(', ', array_map(static function (string $roleKey): string {
                                    return sr_admin_code_label($roleKey, 'role');
                                }, $adminAccount['roles']))); ?>
                            </div>
                        </div>
                        <input type="hidden" name="intent" value="sync_roles">
                        <div class="admin-form-row">
                            <span class="form-label">역할</span>
                            <div class="admin-form-field">
                                <fieldset class="admin-role-choice-list">
                                    <legend class="sr-only">관리자 역할</legend>
                                    <?php foreach ($allowedRoles as $roleKey) { ?>
                                        <?php $roleInputId = $roleModalId . '-role-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($roleKey)); ?>
                                        <label class="admin-role-choice admin-form-check form-label" for="<?php echo sr_e($roleInputId); ?>">
                                            <input id="<?php echo sr_e($roleInputId); ?>" type="checkbox" name="role_keys[]" value="<?php echo sr_e($roleKey); ?>" class="form-checkbox"<?php echo in_array($roleKey, $adminAccount['roles'], true) ? ' checked' : ''; ?><?php echo $roleKey === $allowedRoles[0] ? ' data-overlay-focus' : ''; ?>>
                                            <span><?php echo sr_e(sr_admin_code_label($roleKey, 'role')); ?></span>
                                        </label>
                                    <?php } ?>
                                </fieldset>
                                <p class="admin-form-help">체크된 역할이 저장 후 이 회원의 관리자 권한으로 적용됩니다.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($roleModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">권한 저장</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong>관리자 권한 안내</strong>
        <p>이 화면은 조건에 맞는 회원만 표시합니다. 권한 변경은 소유자만 실행할 수 있으며 마지막 소유자 권한은 회수할 수 없습니다.</p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
