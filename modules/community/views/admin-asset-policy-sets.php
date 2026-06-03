<?php

$adminPageTitle = '커뮤니티 회원 그룹별 설정';
$adminPageSubtitle = '커뮤니티 적립, 차감, 유료 열람, 첨부 다운로드에서 회원 그룹별 최종 금액을 설정하는 규칙입니다.';
$adminContainerClass = 'admin-community-asset-policy-sets admin-ui-scope';
$policySetPage = isset($policySetPage) ? (string) $policySetPage : 'list';
$policySetSort = isset($policySetSort) && is_array($policySetSort) ? $policySetSort : sr_community_asset_policy_set_default_sort();
$policySetFilters = isset($policySetFilters) && is_array($policySetFilters) ? $policySetFilters : ['status' => [], 'field' => 'all', 'q' => ''];
$policySetCount = count($policySets ?? []);
$policySetPagination = ['total' => $policySetCount, 'start' => $policySetCount > 0 ? 1 : 0, 'end' => $policySetCount];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policySetPage === 'list') { ?>
    <?php $selectedPolicySetStatuses = is_array($policySetFilters['status'] ?? null) ? $policySetFilters['status'] : []; ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="admin-filter admin-community-asset-policy-set-filter ui-form-theme">
        <div class="admin-filter-grid admin-community-asset-policy-set-search-grid">
            <div class="admin-filter-field">
                <label class="admin-filter-label">상태</label>
                <div class="btn-group" role="group" aria-label="상태">
                    <?php $policySetStatuses = sr_community_asset_policy_set_statuses(); ?>
                    <?php foreach ($policySetStatuses as $index => $status) { ?>
                        <?php $groupClass = $index === 0 ? 'btn-group-start' : ($index === count($policySetStatuses) - 1 ? 'btn-group-end' : 'btn-group-middle'); ?>
                        <label class="btn btn-choice-light <?php echo sr_e($groupClass); ?>" for="community_policy_set_status_filter_<?php echo sr_e($status); ?>">
                            <input id="community_policy_set_status_filter_<?php echo sr_e($status); ?>" type="checkbox" name="status[]" value="<?php echo sr_e($status); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($status, $selectedPolicySetStatuses, true) ? ' checked' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </label>
                    <?php } ?>
                </div>
            </div>
            <div class="admin-filter-field">
                <label for="community_policy_set_filter_field" class="admin-filter-label">검색 대상</label>
                <select id="community_policy_set_filter_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'key' => 'Key', 'title' => '이름'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($policySetFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-community-asset-policy-set-filter-keyword">
                <label for="community_policy_set_filter_q" class="admin-filter-label">검색어</label>
                <input id="community_policy_set_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($policySetFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="Key 또는 이름">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">회원 그룹별 설정 목록</h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=new')); ?>" class="btn btn-sm btn-outline-secondary">새 설정</a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($policySetSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="커뮤니티 회원 그룹별 설정 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($policySetPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <caption class="sr-only">커뮤니티 회원 그룹별 설정 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th<?php echo sr_admin_sort_aria('title', $policySetSort); ?>><?php echo sr_admin_sort_header_html('이름', 'title', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('set_key', $policySetSort); ?>><?php echo sr_admin_sort_header_html('Key', 'set_key', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $policySetSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $policySetSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($policySets ?? []) === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state">등록된 회원 그룹별 설정이 없습니다.</td>
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
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=edit&id=' . (string) (int) ($policySet['id'] ?? 0))); ?>" aria-label="회원 그룹별 설정 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
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
            <h2><?php echo $policySetPage === 'edit' ? '회원 그룹별 설정 수정' : '회원 그룹별 설정 생성'; ?></h2>
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
        </section>
        <?php
        $assetGroupPolicySectionTitle = '회원 그룹별 설정 규칙';
        $assetGroupPolicyFieldName = 'policies';
        $assetGroupPolicyInputId = 'community_asset_policy_set_policies';
        $assetGroupPolicyRows = sr_community_asset_group_policies_from_value($values['policies_json'] ?? '');
        $assetGroupPolicyGroups = $memberGroups ?? [];
        $assetGroupPolicyAssetModules = $assetModuleOptions ?? [];
        $assetGroupPolicyShowMinLevel = true;
        $assetGroupPolicyHelpText = '이 규칙을 선택한 커뮤니티 포인트/금액 항목에서 회원 그룹별로 기본 금액을 조정합니다.';
        include SR_ROOT . '/modules/admin/views/asset-group-policy-editor.php';
        ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
