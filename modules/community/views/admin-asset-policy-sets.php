<?php

$adminPageTitle = '커뮤니티 회원 그룹/레벨 혜택';
$adminPageSubtitle = '커뮤니티 적립, 차감, 유료 열람, 첨부 다운로드에서 사용할 회원 그룹/레벨별 혜택입니다.';
$adminContainerClass = 'admin-community-asset-policy-sets admin-ui-scope';
$policySetPage = isset($policySetPage) ? (string) $policySetPage : 'list';
$policySetCount = count($policySets ?? []);
$policySetPagination = ['total' => $policySetCount, 'start' => $policySetCount > 0 ? 1 : 0, 'end' => $policySetCount];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policySetPage === 'list') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">회원 그룹/레벨 혜택 목록</h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=new')); ?>" class="btn btn-sm btn-outline-secondary">새 혜택</a>
        </div>
        <div class="admin-list-summary-row">
            <?php echo sr_admin_pagination_summary_html($policySetPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <caption class="sr-only">커뮤니티 회원 그룹/레벨 혜택 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th>이름</th>
                        <th>Key</th>
                        <th>상태</th>
                        <th>수정일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($policySets ?? []) === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state">등록된 회원 그룹/레벨 혜택이 없습니다.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach (($policySets ?? []) as $policySet) { ?>
                            <?php
                            $policyStatus = (string) ($policySet['status'] ?? '');
                            $statusClass = match ($policyStatus) {
                                'enabled' => 'is-normal',
                                'disabled' => 'is-blocked',
                                default => 'is-left',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-break"><?php echo sr_e((string) ($policySet['title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($policySet['set_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($policyStatus, 'content_status')); ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($policySet['updated_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=edit&id=' . (string) (int) ($policySet['id'] ?? 0))); ?>" aria-label="회원 그룹/레벨 혜택 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="set_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
        <section class="admin-card card">
            <h2><?php echo $policySetPage === 'edit' ? '회원 그룹/레벨 혜택 수정' : '회원 그룹/레벨 혜택 생성'; ?></h2>
            <div class="admin-form-row">
                <label class="form-label" for="community_policy_set_key">Key <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="community_policy_set_key" type="text" name="set_key" value="<?php echo sr_e((string) ($values['set_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" required data-admin-key-input>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_policy_set_title">이름 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="community_policy_set_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_policy_set_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="community_policy_set_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="community_policy_set_status">상태 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="community_policy_set_status" name="status" class="form-select" required>
                        <?php foreach (sr_community_asset_policy_set_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">회원 그룹/레벨 혜택</span>
                <div class="admin-form-field">
                    <?php
                    $assetGroupPolicyFieldName = 'policies';
                    $assetGroupPolicyInputId = 'community_asset_policy_set_policies';
                    $assetGroupPolicyRows = sr_community_asset_group_policies_from_value($values['policies_json'] ?? '');
                    $assetGroupPolicyGroups = $memberGroups ?? [];
                    $assetGroupPolicyLevelEnabled = true;
                    $assetGroupPolicyMaxLevel = isset($maxLevel) ? (int) $maxLevel : sr_community_max_level_value();
                    $assetGroupPolicyHelpText = '이 혜택을 선택한 커뮤니티 자산 항목에 적용할 회원 그룹/레벨별 금액 혜택입니다.';
                    include SR_ROOT . '/modules/admin/views/asset-group-policy-editor.php';
                    ?>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
