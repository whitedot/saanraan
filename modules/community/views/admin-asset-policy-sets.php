<?php

$adminPageTitle = '커뮤니티 회원 그룹별 금액 규칙';
$adminPageSubtitle = '회원 그룹에 따라 포인트·금액을 다르게 적용할 규칙 묶음을 관리합니다.';
$adminContainerClass = 'admin-community-asset-policy-sets admin-ui-scope';
$policySetPage = isset($policySetPage) ? (string) $policySetPage : 'list';
$policySetSort = isset($policySetSort) && is_array($policySetSort) ? $policySetSort : sr_community_asset_policy_set_default_sort();
$policySetFilters = isset($policySetFilters) && is_array($policySetFilters) ? $policySetFilters : ['status' => [], 'field' => 'all', 'q' => ''];
$policySetCount = count($policySets ?? []);
$policySetPagination = ['total' => $policySetCount, 'start' => $policySetCount > 0 ? 1 : 0, 'end' => $policySetCount];
$adminPageTitleUrl = sr_admin_page_title_reset_url($policySetPage === 'list', '/admin/community/asset-policy-sets');
$communityPolicySetHelp = [
    'id' => 'community-policy-set-help',
    'title' => '회원 그룹별 금액 규칙 도움말',
    'body' => '<p>이 화면에서는 여러 규칙을 하나의 묶음으로 만듭니다. 저장한 묶음은 커뮤니티 기본 설정이나 게시판 설정에서 포인트·금액 항목별로 선택해 사용합니다.</p>'
        . '<p>회원이 여러 규칙에 해당하면 최소 레벨이 높은 규칙을 우선합니다. 최소 레벨도 같으면 저장된 순서에서 먼저 나오는 규칙 하나만 적용합니다. 최소 레벨 0은 레벨을 확인하지 않는다는 뜻입니다.</p>'
        . '<p>일치하는 규칙이 없으면 기본 금액을 그대로 사용합니다. 묶음을 사용 중지하거나 보관하면 기존 선택과 기록은 남지만 새 금액 계산에는 적용되지 않습니다.</p>',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($policySetPage === 'list') { ?>
    <?php
    $selectedPolicySetStatuses = is_array($policySetFilters['status'] ?? null) ? $policySetFilters['status'] : [];
    $policySetDetailFilterOpen = $selectedPolicySetStatuses !== [];
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="filtering-form admin-community-asset-policy-set-filter ui-form-theme">
        <div class="filtering-fields admin-community-asset-policy-set-search-grid">
            <div class="filtering filtering-card<?php echo $policySetDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field">
                        <label for="community_policy_set_filter_field" class="filtering-label">검색조건</label>
                        <select id="community_policy_set_filter_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => '전체', 'key' => '식별값', 'title' => '이름'] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($policySetFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-community-asset-policy-set-filter-keyword">
                        <label for="community_policy_set_filter_q" class="filtering-label">검색어</label>
                        <input id="community_policy_set_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($policySetFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="식별값 또는 이름">
                    </div>
                </div>
                <div id="community_policy_set_detail_filters" class="filtering-body" data-filtering-body<?php echo $policySetDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field">
                        <span class="filtering-label">상태</span>
                        <?php echo sr_admin_filter_toggle_group_html('community_policy_set_status_filter', 'status', sr_admin_code_label_options(sr_community_asset_policy_set_statuses(), 'content_status'), $selectedPolicySetStatuses, '전체'); ?>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $policySetDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_policy_set_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
                </div>
            </div>
        </div>
    </form>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">회원 그룹별 금액 규칙 목록</h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=new')); ?>" class="btn btn-sm btn-outline-secondary">새 설정</a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($policySetSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="커뮤니티 회원 그룹별 금액 규칙 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($policySetPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <caption class="sr-only">커뮤니티 회원 그룹별 금액 규칙 목록</caption>
                <thead>
                    <tr>
                        <th<?php echo sr_admin_sort_aria('title', $policySetSort); ?>><?php echo sr_admin_sort_header_html('이름', 'title', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('set_key', $policySetSort); ?>><?php echo sr_admin_sort_header_html('식별값', 'set_key', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $policySetSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $policySetSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $policySetSort, sr_community_asset_policy_set_sort_options(), sr_community_asset_policy_set_default_sort()); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($policySets ?? []) === []) { ?>
                        <tr>
                            <td colspan="5" class="admin-empty-state">등록된 회원 그룹별 금액 규칙이 없습니다.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach (($policySets ?? []) as $policySet) { ?>
                            <?php
                            $policyStatus = (string) ($policySet['status'] ?? '');
                            $statusClass = match ($policyStatus) {
                                'enabled' => 'is-success',
                                'disabled' => 'is-warning',
                                default => 'is-danger',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-break"><?php echo sr_e((string) ($policySet['title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($policySet['set_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($policyStatus, 'content_status')); ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($policySet['updated_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets?mode=edit&id=' . (string) (int) ($policySet['id'] ?? 0))); ?>" aria-label="회원 그룹별 금액 규칙 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
        </div>
        <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="set_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
        <section class="card">
                <h2><?php echo $policySetPage === 'edit' ? '금액 규칙 묶음 수정' : '금액 규칙 묶음 생성'; ?></h2>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_policy_set_key', '규칙 묶음 식별값', $communityPolicySetHelp['id'], '도움말 보기', true); ?>
                <div class="form-field">
                    <input id="community_policy_set_key" type="text" name="set_key" value="<?php echo sr_e((string) ($values['set_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" required data-admin-key-input data-admin-key-suggest-source="#community_policy_set_title" data-admin-key-suggest-fallback="community_policy_set">
                    <p class="form-help">관리 화면과 연동에서 규칙 묶음을 구분합니다. 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 입력하세요.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_policy_set_title">이름 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="community_policy_set_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    <p class="form-help">커뮤니티·게시판 설정에서 운영자가 선택할 때 보이는 이름입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_policy_set_description">설명</label>
                <div class="form-field">
                    <textarea id="community_policy_set_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_policy_set_status', '상태', $communityPolicySetHelp['id'], '도움말 보기', true); ?>
                <div class="form-field">
                    <select id="community_policy_set_status" name="status" class="form-select" required>
                        <?php foreach (sr_community_asset_policy_set_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">사용 중지·보관 상태에서는 새 금액 계산에 이 규칙 묶음을 적용하지 않습니다.</p>
                </div>
            </div>
        </section>
        <?php
        $assetGroupPolicySectionTitle = '회원 그룹별 금액 규칙';
        $assetGroupPolicyFieldName = 'policies';
        $assetGroupPolicyInputId = 'community_asset_policy_set_policies';
        $assetGroupPolicyRows = sr_community_asset_group_policies_from_value($values['policies_json'] ?? '');
        $assetGroupPolicyGroups = $memberGroups ?? [];
        $assetGroupPolicyAssetModules = $assetModuleOptions ?? [];
        $assetGroupPolicyShowMinLevel = true;
        $assetGroupPolicyHelpText = '이 규칙을 선택한 커뮤니티 포인트/금액 항목에서 회원 그룹별로 기본 금액을 조정합니다.';
        include SR_ROOT . '/modules/admin/views/asset-group-policy-editor.php';
        ?>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/community/asset-policy-sets')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
    <?php echo sr_admin_help_modal_html($communityPolicySetHelp['id'], $communityPolicySetHelp['title'], $communityPolicySetHelp['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
