<?php

$adminPageTitle = '콘텐츠 회원 그룹별 금액 조정';
$adminPageSubtitle = '콘텐츠 유료 열람, 다운로드, 완료 버튼에서 회원 그룹별로 최종 금액을 조정하는 규칙입니다.';
$adminContainerClass = 'admin-content-asset-policy-sets admin-ui-scope';
$policySetPage = isset($policySetPage) ? (string) $policySetPage : 'list';
$policySetSort = isset($policySetSort) && is_array($policySetSort) ? $policySetSort : sr_content_asset_policy_set_default_sort();
$policySetCount = count($policySets ?? []);
$policySetPagination = ['total' => $policySetCount, 'start' => $policySetCount > 0 ? 1 : 0, 'end' => $policySetCount];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policySetPage === 'list') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">회원 그룹별 금액 조정 목록</h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/content/asset-policy-sets?mode=new')); ?>" class="btn btn-sm btn-outline-secondary">새 조정 규칙</a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($policySetSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_content_asset_policy_set_sort_options(), sr_content_asset_policy_set_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="콘텐츠 회원 그룹별 금액 조정 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($policySetPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <caption class="sr-only">콘텐츠 회원 그룹별 금액 조정 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th<?php echo sr_admin_sort_aria('title', $policySetSort); ?>><?php echo sr_admin_sort_header_html('이름', 'title', $policySetSort, sr_content_asset_policy_set_sort_options(), sr_content_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('set_key', $policySetSort); ?>><?php echo sr_admin_sort_header_html('Key', 'set_key', $policySetSort, sr_content_asset_policy_set_sort_options(), sr_content_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $policySetSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $policySetSort, sr_content_asset_policy_set_sort_options(), sr_content_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $policySetSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $policySetSort, sr_content_asset_policy_set_sort_options(), sr_content_asset_policy_set_default_sort()); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($policySets ?? []) === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state">등록된 회원 그룹별 금액 조정 규칙이 없습니다.</td>
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
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/content/asset-policy-sets?mode=edit&id=' . (string) (int) ($policySet['id'] ?? 0))); ?>" aria-label="회원 그룹별 금액 조정 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
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
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/asset-policy-sets')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="set_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
        <section class="admin-card card">
            <h2><?php echo $policySetPage === 'edit' ? '회원 그룹별 금액 조정 수정' : '회원 그룹별 금액 조정 생성'; ?></h2>
            <div class="admin-form-row">
                <label class="form-label" for="content_policy_set_key">Key <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="content_policy_set_key" type="text" name="set_key" value="<?php echo sr_e((string) ($values['set_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" required data-admin-key-input>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_policy_set_title">이름 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="content_policy_set_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_policy_set_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="content_policy_set_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_policy_set_status">상태 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="content_policy_set_status" name="status" class="form-select" required>
                        <?php foreach (sr_content_asset_policy_set_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </section>
        <?php
        $assetGroupPolicySectionTitle = '회원 그룹별 금액 조정 규칙';
        $assetGroupPolicyFieldName = 'policies';
        $assetGroupPolicyInputId = 'content_asset_policy_set_policies';
        $assetGroupPolicyRows = sr_content_asset_group_policies_from_value($values['policies_json'] ?? '');
        $assetGroupPolicyGroups = $memberGroups ?? [];
        $assetGroupPolicyAssetModules = $assetModuleOptions ?? [];
        $assetGroupPolicyShowMinLevel = false;
        $assetGroupPolicyHelpText = '이 규칙을 선택한 콘텐츠 포인트/금액 항목에서 회원 그룹별로 기본 금액을 조정합니다.';
        include SR_ROOT . '/modules/admin/views/asset-group-policy-editor.php';
        ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content/asset-policy-sets')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
