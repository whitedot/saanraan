<?php

$sessionErrors = $_SESSION['sr_page_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_page_admin_values'] ?? [];
unset($_SESSION['sr_page_admin_errors'], $_SESSION['sr_page_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}
if (is_array($sessionValues)) {
    $values = $sessionValues;
}
$editing = is_array($editPage);
if ($values === []) {
    $values = $editing ? $editPage : [
        'title' => '',
        'page_group_id' => 0,
        'slug' => '',
        'summary' => '',
        'body_text' => '',
        'status' => 'draft',
        'layout_key' => sr_public_layout_key($site ?? null, $pdo ?? null),
        'asset_access_enabled' => 0,
        'asset_module' => 'point',
        'asset_access_amount' => 0,
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => 0,
        'asset_action_module' => 'point',
        'asset_action_amount' => 0,
        'asset_action_direction' => 'grant',
        'asset_action_label' => '완료',
        'banner_before_content_id' => 0,
        'banner_after_content_id' => 0,
        'popup_layer_id' => 0,
        'seo_title' => '',
        'seo_description' => '',
    ];
}

$adminPageTitle = $pageAdminPage === 'form' ? ($editing ? '페이지 수정' : '페이지 추가') : '페이지';
$adminPageSubtitle = $pageAdminPage === 'form' ? '공개 페이지의 본문, 노출 상태, 자산 정책을 관리합니다.' : '페이지 상태를 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = $pageAdminPage === 'form' ? 'admin-page-content-form admin-ui-scope' : 'admin-page-content-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'page_group_id' => 0, 'field' => 'all', 'q' => ''];
$pageStatusCounts = isset($pageStatusCounts) && is_array($pageStatusCounts) ? $pageStatusCounts : [];
$pageGroups = isset($pageGroups) && is_array($pageGroups) ? $pageGroups : [];
$publicLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : sr_public_layout_options($pdo ?? null);
$values['layout_key'] = sr_public_layout_normalize_key((string) ($values['layout_key'] ?? ''));
if ($values['layout_key'] === '' || !isset($publicLayoutOptions[$values['layout_key']])) {
    $values['layout_key'] = sr_public_layout_key($site ?? null, $pdo ?? null);
}
$totalPages = (int) ($pageStatusCounts['total'] ?? count($pages ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($pageAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/pages/save')); ?>" class="admin-form ui-form-theme" enctype="multipart/form-data">
        <section class="admin-card card">
            <h2><?php echo $editing ? '페이지 수정' : '페이지 추가'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="page_id" value="<?php echo $editing ? sr_e((string) $editPage['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_title">제목</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_slug">Slug</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_slug" type="text" name="slug" value="<?php echo sr_e((string) ($values['slug'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    <br>
                                        <small>공개 URL은 /pages/slug 형식입니다. 소문자 영문, 숫자, 하이픈만 사용할 수 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_page_group_id">페이지 그룹</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_page_group_id" name="page_group_id" class="form-select">
                        <option value="0"<?php echo (int) ($values['page_group_id'] ?? 0) === 0 ? ' selected' : ''; ?>>그룹 없음</option>
                        <?php foreach ($pageGroups as $pageGroup) { ?>
                            <option value="<?php echo sr_e((string) $pageGroup['id']); ?>"<?php echo (int) ($values['page_group_id'] ?? 0) === (int) $pageGroup['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($pageGroup['title'] ?? $pageGroup['group_key'])); ?>
                                <?php if ((string) ($pageGroup['status'] ?? '') !== 'enabled') { ?>
                                    (<?php echo sr_e(sr_admin_code_label((string) $pageGroup['status'], 'content_status')); ?>)
                                <?php } ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help">그룹을 선택하면 그룹별 공개 목록과 사이트 메뉴 연결 자산에서 함께 묶입니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_summary">요약</label>
                <div class="admin-form-field">
                    <textarea id="page_admin_pages_summary" name="summary" maxlength="1000" class="form-textarea"><?php echo sr_e((string) ($values['summary'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_body_text">본문</label>
                <div class="admin-form-field">
                    <textarea id="page_admin_pages_body_text" name="body_text" rows="14" class="form-textarea"><?php echo sr_e((string) ($values['body_text'] ?? '')); ?></textarea>
                    <br>
                                        <small>1차 페이지 본문은 plain text로 저장하고 출력 시 escape합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_status">상태</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_status" name="status" class="form-select">
                                                <?php foreach (sr_page_allowed_statuses() as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'draft') === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_layout_key">페이지 레이아웃</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_layout_key" name="layout_key" class="form-select">
                                                <?php foreach ($publicLayoutOptions as $layoutKey => $layoutOption) { ?>
                                                    <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($values['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <p class="admin-form-help">이 공개 페이지에 적용할 문서 레이아웃입니다.</p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>유료 열람</h2>
            <div class="admin-form-row">
                <span class="form-label">유료 열람 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_page_admin_pages_asset_access_enabled">
                                            <input id="modules_page_admin_pages_asset_access_enabled" type="checkbox" name="asset_access_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['asset_access_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('유료 열람 사용'); ?>
                                        </label>
                                        <p class="admin-form-help">선택한 회원 자산을 차감한 뒤 페이지 본문을 보여줍니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_module">차감 자산</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_asset_module" name="asset_module" class="form-select">
                                                <?php if ($assetModuleOptions === []) { ?>
                                                    <option value="">활성 자산 모듈 없음</option>
                                                <?php } ?>
                                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                    <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) ($values['asset_module'] ?? 'point') === (string) $assetModule ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $assetOption['label']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <p class="admin-form-help">포인트, 적립금, 예치금 모듈 중 활성화된 자산만 사용할 수 있습니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_access_amount">차감 금액</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_asset_access_amount" type="number" name="asset_access_amount" value="<?php echo sr_e((string) (int) ($values['asset_access_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_charge_policy">과금 방식</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_asset_charge_policy" name="asset_charge_policy" class="form-select">
                                                <?php foreach (sr_page_asset_view_charge_policies() as $policyKey => $policyLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($values['asset_charge_policy'] ?? 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $policyLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>완료 액션</h2>
            <div class="admin-form-row">
                <span class="form-label">액션 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_page_admin_pages_asset_action_enabled">
                                            <input id="modules_page_admin_pages_asset_action_enabled" type="checkbox" name="asset_action_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['asset_action_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('완료 액션 사용'); ?>
                                        </label>
                                        <p class="admin-form-help">회원이 공개 페이지에서 버튼을 누르면 선택한 자산을 1회 지급하거나 차감합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_action_label">버튼 문구</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_asset_action_label" type="text" name="asset_action_label" value="<?php echo sr_e((string) ($values['asset_action_label'] ?? '완료')); ?>" class="form-input" maxlength="80">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_action_direction">처리 방향</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_asset_action_direction" name="asset_action_direction" class="form-select">
                                                <?php foreach (sr_page_asset_action_directions() as $directionKey => $directionLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $directionKey); ?>"<?php echo (string) ($values['asset_action_direction'] ?? 'grant') === (string) $directionKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $directionLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_action_module">대상 자산</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_asset_action_module" name="asset_action_module" class="form-select">
                                                <?php if ($assetModuleOptions === []) { ?>
                                                    <option value="">활성 자산 모듈 없음</option>
                                                <?php } ?>
                                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                    <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) ($values['asset_action_module'] ?? 'point') === (string) $assetModule ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $assetOption['label']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_asset_action_amount">금액</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_asset_action_amount" type="number" name="asset_action_amount" value="<?php echo sr_e((string) (int) ($values['asset_action_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>
                <span>공개 표시</span>
                <span class="admin-form-actions">
                    <?php if (sr_module_enabled($pdo, 'banner')) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-sm btn-solid-light">배너 관리</a>
                    <?php } ?>
                    <?php if (sr_module_enabled($pdo, 'popup_layer')) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-sm btn-solid-light">팝업레이어 관리</a>
                    <?php } ?>
                </span>
            </h2>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_banner_before_content_id">본문 상단 배너</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_banner_before_content_id" name="banner_before_content_id" class="form-select form-control-full">
                                                <option value="0"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                                                <?php foreach ($publicBanners as $banner) { ?>
                                                    <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $banner['title']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_banner_after_content_id">본문 하단 배너</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_banner_after_content_id" name="banner_after_content_id" class="form-select form-control-full">
                                                <option value="0"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                                                <?php foreach ($publicBanners as $banner) { ?>
                                                    <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $banner['title']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <br>
                                        <small>공용 배너만 직접 선택할 수 있습니다. 세부 출력 규칙은 배너 모듈의 출력 위치에서 설정할 수도 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_popup_layer_id">팝업레이어</label>
                <div class="admin-form-field">
                    <select id="page_admin_pages_popup_layer_id" name="popup_layer_id" class="form-select form-control-full">
                                                <option value="0"<?php echo (int) ($values['popup_layer_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                                                <?php foreach ($publicPopupLayers as $popupLayer) { ?>
                                                    <option value="<?php echo sr_e((string) $popupLayer['id']); ?>"<?php echo (int) ($values['popup_layer_id'] ?? 0) === (int) $popupLayer['id'] ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $popupLayer['title']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <br>
                                        <small>공용 팝업레이어만 직접 선택할 수 있습니다. 페이지 전체 규칙은 팝업레이어 모듈의 출력 위치에서 설정할 수도 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_seo_title">SEO 제목</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_seo_title" type="text" name="seo_title" value="<?php echo sr_e((string) ($values['seo_title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_seo_description">SEO 설명</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_seo_description" type="text" name="seo_description" value="<?php echo sr_e((string) ($values['seo_description'] ?? '')); ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <?php if ($editing) { ?>
                <div class="admin-form-row">
                    <span class="form-label">공개 URL</span>
                    <div class="admin-form-field">
                        <a href="<?php echo sr_e(sr_url(sr_page_path((string) $editPage['slug']))); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_page_path((string) $editPage['slug'])); ?></a>
                    </div>
                </div>
            <?php } ?>
        </section>
        <section class="admin-card card">
            <h2>다운로드 파일</h2>
            <?php if ($editing && $pageFiles !== []) { ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead class="ui-table-head">
                            <tr>
                                <th>파일</th>
                                <th>다운로드 과금</th>
                                <th>삭제</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageFiles as $pageFile) { ?>
                                <?php $fileId = (int) $pageFile['id']; ?>
                                <?php $pageFileTitleId = 'page_file_title_' . (string) $fileId; ?>
                                <?php $pageFileChargeEnabledId = 'page_file_asset_download_enabled_' . (string) $fileId; ?>
                                <?php $pageFileAssetModuleId = 'page_file_asset_module_' . (string) $fileId; ?>
                                <?php $pageFileAmountId = 'page_file_asset_download_amount_' . (string) $fileId; ?>
                                <?php $pageFileChargePolicyId = 'page_file_asset_charge_policy_' . (string) $fileId; ?>
                                <?php $pageFileDeleteId = 'page_file_delete_' . (string) $fileId; ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="page_file_ids[]" value="<?php echo sr_e((string) $fileId); ?>">
                                        <label for="<?php echo sr_e($pageFileTitleId); ?>">
                                            <span class="sr-only">파일 제목</span>
                                            <input id="<?php echo sr_e($pageFileTitleId); ?>" type="text" name="page_file_title[<?php echo sr_e((string) $fileId); ?>]" value="<?php echo sr_e((string) $pageFile['title']); ?>" class="form-input form-control-full" maxlength="160">
                                        </label>
                                        <br>
                                        <small><?php echo sr_e((string) $pageFile['original_name']); ?> · <?php echo sr_e(sr_page_format_bytes((int) $pageFile['size_bytes'])); ?></small>
                                    </td>
                                    <td>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($pageFileChargeEnabledId); ?>">
                                            <input id="<?php echo sr_e($pageFileChargeEnabledId); ?>" type="checkbox" name="page_file_asset_download_enabled[<?php echo sr_e((string) $fileId); ?>]" value="1" class="form-checkbox"<?php echo (int) ($pageFile['asset_download_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('과금'); ?>
                                        </label>
                                        <label for="<?php echo sr_e($pageFileAssetModuleId); ?>">
                                            <span class="sr-only">파일 차감 자산</span>
                                            <select id="<?php echo sr_e($pageFileAssetModuleId); ?>" name="page_file_asset_module[<?php echo sr_e((string) $fileId); ?>]" class="form-select">
                                                <?php if ($assetModuleOptions === []) { ?>
                                                    <option value="">활성 자산 모듈 없음</option>
                                                <?php } ?>
                                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                    <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) ($pageFile['asset_module'] ?? 'point') === (string) $assetModule ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $assetOption['label']); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                        <label for="<?php echo sr_e($pageFileAmountId); ?>">
                                            <span class="sr-only">파일 차감 금액</span>
                                            <input id="<?php echo sr_e($pageFileAmountId); ?>" type="number" name="page_file_asset_download_amount[<?php echo sr_e((string) $fileId); ?>]" value="<?php echo sr_e((string) (int) ($pageFile['asset_download_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                                        </label>
                                        <label for="<?php echo sr_e($pageFileChargePolicyId); ?>">
                                            <span class="sr-only">파일 과금 방식</span>
                                            <select id="<?php echo sr_e($pageFileChargePolicyId); ?>" name="page_file_asset_charge_policy[<?php echo sr_e((string) $fileId); ?>]" class="form-select">
                                                <?php foreach (sr_page_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($pageFile['asset_charge_policy'] ?? 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $policyLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($pageFileDeleteId); ?>">
                                            <input id="<?php echo sr_e($pageFileDeleteId); ?>" type="checkbox" name="page_file_delete[<?php echo sr_e((string) $fileId); ?>]" value="1" class="form-checkbox">
                                            <?php echo sr_admin_choice_label_html('삭제'); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } elseif ($editing) { ?>
                <p>등록된 다운로드 파일이 없습니다.</p>
            <?php } else { ?>
                <p>새 페이지 저장과 함께 파일을 추가할 수 있습니다.</p>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_page_file_upload">새 파일</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_page_file_upload" type="file" name="page_file_upload" class="form-input">
                    <br>
                                        <small>PDF, 문서, 표, 압축 파일, 이미지 / 최대 <?php echo sr_e(sr_page_format_bytes(sr_page_file_upload_max_bytes())); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_pages_new_page_file_title">새 파일 제목</label>
                <div class="admin-form-field">
                    <input id="page_admin_pages_new_page_file_title" type="text" name="new_page_file_title" value="" class="form-input form-control-full" maxlength="160">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">새 파일 과금</span>
                <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="modules_page_admin_pages_new_page_file_asset_download_enabled">
                                <input id="modules_page_admin_pages_new_page_file_asset_download_enabled" type="checkbox" name="new_page_file_asset_download_enabled" value="1" class="form-checkbox">
                                <?php echo sr_admin_choice_label_html('다운로드 과금'); ?>
                            </label>
                            <select name="new_page_file_asset_module" class="form-select" aria-label="새 파일 차감 자산">
                                <?php if ($assetModuleOptions === []) { ?>
                                    <option value="">활성 자산 모듈 없음</option>
                                <?php } ?>
                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                    <option value="<?php echo sr_e((string) $assetModule); ?>">
                                        <?php echo sr_e((string) $assetOption['label']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <input type="number" name="new_page_file_asset_download_amount" value="0" class="form-input" min="0" max="999999999" step="1" aria-label="새 파일 차감 금액">
                            <select name="new_page_file_asset_charge_policy" class="form-select" aria-label="새 파일 과금 방식">
                                <?php foreach (sr_page_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
                                    <option value="<?php echo sr_e((string) $policyKey); ?>">
                                        <?php echo sr_e((string) $policyLabel); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/pages')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/pages')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총페이지 <strong><?php echo sr_e((string) $totalPages); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/pages?status=published')); ?>" class="admin-summary-meta">공개 <?php echo sr_e((string) ($pageStatusCounts['published'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/pages?status=draft')); ?>" class="admin-summary-meta">초안 <?php echo sr_e((string) ($pageStatusCounts['draft'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/pages?status=hidden')); ?>" class="admin-summary-meta">숨김 <?php echo sr_e((string) ($pageStatusCounts['hidden'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/pages')); ?>" class="admin-filter admin-page-filter ui-form-theme">
        <div class="admin-filter-grid admin-page-search-grid">
            <div class="admin-filter-field admin-page-filter-status">
                <label for="modules_page_admin_pages_status" class="admin-filter-label">상태</label>
                <select id="modules_page_admin_pages_status" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach (sr_page_allowed_statuses() as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-page-filter-group">
                <label for="modules_page_admin_pages_page_group_id" class="admin-filter-label">그룹</label>
                <select id="modules_page_admin_pages_page_group_id" name="page_group_id" class="form-select admin-filter-input">
                    <option value="0"<?php echo (int) ($filters['page_group_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($pageGroups as $pageGroup) { ?>
                        <option value="<?php echo sr_e((string) $pageGroup['id']); ?>"<?php echo (int) ($filters['page_group_id'] ?? 0) === (int) $pageGroup['id'] ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($pageGroup['title'] ?? $pageGroup['group_key'])); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-page-filter-field">
                <label for="modules_page_admin_pages_field" class="admin-filter-label">검색 조건</label>
                <select id="modules_page_admin_pages_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'slug' => 'Slug'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-page-filter-keyword">
                <label for="modules_page_admin_pages_q" class="admin-filter-label">검색어</label>
                <input id="modules_page_admin_pages_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, Slug">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">페이지 목록</h2>
                <p class="admin-dashboard-meta">공개 상태인 페이지는 /pages/slug URL로 노출됩니다.</p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/pages/new')); ?>" class="btn btn-sm btn-solid-light">새 페이지 추가</a>
        </div>
        <div class="table-wrapper">
            <table class="table admin-page-table">
                <caption class="sr-only">페이지 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th>제목</th>
                        <th>그룹</th>
                        <th>Slug</th>
                        <th>상태</th>
                        <th>유료 열람</th>
                        <th>작성자</th>
                        <th>수정일</th>
                        <th>공개일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pages === []) { ?>
                        <tr>
                            <td colspan="9" class="admin-empty-state">등록된 페이지가 없습니다.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pages as $page) { ?>
                            <?php
                            $pageStatus = (string) $page['status'];
                            $statusClass = match ($pageStatus) {
                                'published' => 'is-normal',
                                'draft' => 'is-blocked',
                                default => 'is-left',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-break admin-page-title-cell"><?php echo sr_e((string) $page['title']); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($page['page_group_title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap admin-page-slug-cell"><code><?php echo sr_e((string) $page['slug']); ?></code></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($pageStatus, 'content_status')); ?></span></td>
                                <td>
                                    <?php if ((int) ($page['asset_access_enabled'] ?? 0) === 1) { ?>
                                        <?php echo sr_e(sr_page_asset_module_label((string) ($page['asset_module'] ?? ''))); ?>
                                        <?php echo sr_e(number_format((int) ($page['asset_access_amount'] ?? 0))); ?>
                                        · <?php echo sr_e(sr_page_asset_charge_policies()[(string) ($page['asset_charge_policy'] ?? 'once')] ?? ''); ?>
                                    <?php } else { ?>
                                        무료
                                    <?php } ?>
                                </td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($page['created_by_name'] ?? '')); ?></td>
                                <td class="admin-table-nowrap admin-page-date-cell"><?php echo sr_e((string) $page['updated_at']); ?></td>
                                <td class="admin-table-nowrap admin-page-date-cell"><?php echo sr_e((string) ($page['published_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ((string) $page['status'] === 'published') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_page_path((string) $page['slug']))); ?>" class="btn btn-sm btn-solid-light" target="_blank" rel="noopener noreferrer">보기</a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/pages/edit?id=' . rawurlencode((string) $page['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                                        <?php if ((string) $page['status'] !== 'hidden') { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/pages/delete')); ?>" class="admin-inline-form">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="page_id" value="<?php echo sr_e((string) $page['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-soft-danger">숨김</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
