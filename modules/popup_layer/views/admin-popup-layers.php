<?php

$popupLayerAdminPage = isset($popupLayerAdminPage) ? (string) $popupLayerAdminPage : 'list';
$editing = is_array($editPopup);
$adminPageTitle = $popupLayerAdminPage === 'form' ? ($editing ? '팝업 수정' : '팝업 추가') : '팝업레이어';
$adminPageSubtitle = $popupLayerAdminPage === 'form' ? '팝업 내용, 노출 대상, 기간과 닫기 정책을 관리합니다.' : '팝업 상태를 확인하고 조건 검색과 관리 작업을 이어가세요.';
$adminContainerClass = $popupLayerAdminPage === 'form' ? 'admin-page-popup-layer-form admin-ui-scope' : 'admin-page-popup-layer-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$popupStatusCounts = isset($popupStatusCounts) && is_array($popupStatusCounts) ? $popupStatusCounts : [];
$totalPopups = (int) ($popupStatusCounts['total'] ?? count($popups ?? []));
$targetLabels = [];
foreach ($availableTargets as $target) {
    $targetLabels[sr_popup_layer_target_option_value($target)] = sr_popup_layer_target_option_label($target);
}
$selectedTargetOption = sr_popup_layer_public_target_option_value();
if ($editing && (string) ($editPopup['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editPopup['module_key'] ?? '') . '|' . (string) ($editPopup['point_key'] ?? '') . '|' . (string) ($editPopup['slot_key'] ?? '');
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? '팝업 수정' : '팝업 추가'; ?></h2>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="popup_id" value="<?php echo $editing ? sr_e((string) $editPopup['id']) : '0'; ?>">

                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_title">제목</label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editPopup['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_body_text">내용</label>
                    <div class="admin-form-field">
                        <textarea id="popup_layer_admin_popup_layers_body_text" name="body_text" maxlength="5000" class="form-textarea"><?php echo $editing ? sr_e((string) $editPopup['body_text']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_status">상태</label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_status" name="status" class="form-select">
                                                    <?php foreach ($allowedStatuses as $status) { ?>
                                                        <?php $currentStatus = $editing ? (string) $editPopup['status'] : 'draft'; ?>
                                                        <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_skin_key">팝업 스킨</label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_skin_key" name="skin_key" class="form-select">
                                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                                        <?php $currentSkinKey = $editing ? (string) ($editPopup['skin_key'] ?? $popupLayerSkinKey) : $popupLayerSkinKey; ?>
                                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_target_option">노출 대상</label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_target_option" name="target_option" class="form-select">
                                                    <option value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>"<?php echo $selectedTargetOption === sr_popup_layer_public_target_option_value() ? ' selected' : ''; ?>>
                                                        공용 팝업레이어
                                                    </option>
                                                    <?php foreach ($availableTargets as $target) { ?>
                                                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                                                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_popup_layer_target_option_label($target)); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                        <br>
                                            <small>공용 팝업레이어는 자동 출력되지 않고, 게시판 같은 모듈의 개별 설정에서 선택해 사용합니다.</small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_match_type">매칭 방식</label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_match_type" name="match_type" class="form-select">
                                                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                        <?php $currentMatchType = $editing ? (string) ($editPopup['match_type'] ?? 'all') : 'all'; ?>
                                                        <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_subject_id">특정 subject ID</label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editPopup['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_starts_at">시작 시각</label>
                    <div class="admin-form-field">
                        <input type="datetime-local" name="starts_at" id="popup_starts_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['starts_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick="now" data-datetime-target="popup_starts_at">지금</button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_starts_at">+1일</button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_starts_at">+3일</button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_starts_at">+7일</button>
                                                </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_ends_at">종료 시각</label>
                    <div class="admin-form-field">
                        <input type="datetime-local" name="ends_at" id="popup_ends_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['ends_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_ends_at">+1일</button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_ends_at">+3일</button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_ends_at">+7일</button>
                                                </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_dismiss_cookie_days">닫기 유지일</label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_dismiss_cookie_days" type="number" name="dismiss_cookie_days" value="<?php echo $editing ? sr_e((string) $editPopup['dismiss_cookie_days']) : '1'; ?>" class="form-input" min="0" max="365">
                    </div>
                </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총팝업 <strong><?php echo sr_e((string) $totalPopups); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($popupStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=draft')); ?>" class="admin-summary-meta">초안 <?php echo sr_e((string) ($popupStatusCounts['draft'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=disabled')); ?>" class="admin-summary-meta">중지 <?php echo sr_e((string) ($popupStatusCounts['disabled'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="admin-filter admin-popup-layer-filter ui-form-theme">
        <div class="admin-filter-grid admin-popup-layer-search-grid">
            <div class="admin-filter-field admin-popup-layer-filter-status">
                <label for="modules_popup_layer_admin_popup_layers_status_filter" class="admin-filter-label">상태</label>
                <select id="modules_popup_layer_admin_popup_layers_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-target">
                <label for="modules_popup_layer_admin_popup_layers_target_filter" class="admin-filter-label">노출 대상</label>
                <select id="modules_popup_layer_admin_popup_layers_target_filter" name="target" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['target'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <option value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>"<?php echo (string) ($filters['target'] ?? '') === sr_popup_layer_public_target_option_value() ? ' selected' : ''; ?>>공용 팝업레이어</option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo (string) ($filters['target'] ?? '') === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_popup_layer_target_option_label($target)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-field">
                <label for="modules_popup_layer_admin_popup_layers_field" class="admin-filter-label">검색 조건</label>
                <select id="modules_popup_layer_admin_popup_layers_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'subject' => 'Subject ID'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-keyword">
                <label for="modules_popup_layer_admin_popup_layers_q" class="admin-filter-label">검색어</label>
                <input id="modules_popup_layer_admin_popup_layers_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="제목, Subject ID">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">팝업 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers/new')); ?>" class="btn btn-sm btn-solid-light">새 팝업 추가</a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-popup-layer-table">
            <caption class="sr-only">팝업 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>제목</th>
                    <th>상태</th>
                    <th>스킨</th>
                    <th>대상</th>
                    <th>기간</th>
                    <th>닫기 유지일</th>
                    <th>수정일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($popups === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">등록된 팝업이 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($popups as $popup) { ?>
                        <?php
                        if ((string) ($popup['module_key'] ?? '') === '') {
                            $popupTargetLabel = '공용 팝업레이어';
                        } else {
                            $popupTargetOption = (string) $popup['module_key'] . '|' . (string) $popup['point_key'] . '|' . (string) $popup['slot_key'];
                            $popupTargetLabel = (string) ($targetLabels[$popupTargetOption] ?? ((string) $popup['module_key'] . ' / ' . (string) $popup['point_key'] . ' / ' . (string) $popup['slot_key']));
                        }
                        $popupStatus = (string) $popup['status'];
                        $statusClass = match ($popupStatus) {
                            'enabled' => 'is-normal',
                            'draft' => 'is-blocked',
                            default => 'is-left',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-break admin-popup-layer-title-cell"><?php echo sr_e((string) $popup['title']); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($popupStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($popup['skin_key'] ?? 'basic')])); ?></td>
                            <td class="admin-table-break admin-popup-layer-target-cell">
                                <?php echo sr_e($popupTargetLabel); ?><br>
                                <?php echo sr_e((string) $popup['match_type'] . ((string) ($popup['subject_id'] ?? '') !== '' ? ': ' . (string) $popup['subject_id'] : '')); ?>
                            </td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell">
                                <?php echo sr_e((string) ($popup['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($popup['ends_at'] ?? '-')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $popup['dismiss_cookie_days']); ?></td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell"><?php echo sr_e((string) $popup['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers/edit?id=' . rawurlencode((string) $popup['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="popup_id" value="<?php echo sr_e((string) $popup['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
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
