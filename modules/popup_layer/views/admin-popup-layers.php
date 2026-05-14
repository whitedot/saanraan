<?php

$popupLayerAdminPage = isset($popupLayerAdminPage) ? (string) $popupLayerAdminPage : 'list';
$editing = is_array($editPopup);
$adminPageTitle = $popupLayerAdminPage === 'form' ? ($editing ? '팝업 수정' : '팝업 추가') : '팝업레이어';
$selectedTargetOption = sr_popup_layer_public_target_option_value();
if ($editing && (string) ($editPopup['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editPopup['module_key'] ?? '') . '|' . (string) ($editPopup['point_key'] ?? '') . '|' . (string) ($editPopup['slot_key'] ?? '');
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<div class="member-summary">
    <div class="member-summary-links">
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-surface-default-soft">팝업 목록</a>
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers/new')); ?>" class="btn btn-surface-default-soft">팝업 추가</a>
    </div>
</div>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/save')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
        <section class="card">
            <h2><?php echo $editing ? '팝업 수정' : '팝업 추가'; ?></h2>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="popup_id" value="<?php echo $editing ? sr_e((string) $editPopup['id']) : '0'; ?>">

                <div class="af-row">
                    <div class="af-label"><span class="form-label">제목</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">제목</span>
                        <input type="text" name="title" value="<?php echo $editing ? sr_e((string) $editPopup['title']) : ''; ?>" maxlength="120" required>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">내용</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">내용</span>
                        <textarea name="body_text" maxlength="5000"><?php echo $editing ? sr_e((string) $editPopup['body_text']) : ''; ?></textarea>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">상태</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">상태</span>
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <?php $currentStatus = $editing ? (string) $editPopup['status'] : 'draft'; ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">팝업 스킨</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">팝업 스킨</span>
                        <select name="skin_key">
                            <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                <?php $currentSkinKey = $editing ? (string) ($editPopup['skin_key'] ?? $popupLayerSkinKey) : $popupLayerSkinKey; ?>
                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">노출 대상</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">노출 대상</span>
                        <select name="target_option">
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
                        </label>
                    <br>
                    <small>공용 팝업레이어는 자동 출력되지 않고, 게시판 같은 모듈의 개별 설정에서 선택해 사용합니다.</small>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">매칭 방식</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">매칭 방식</span>
                        <select name="match_type">
                            <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                <?php $currentMatchType = $editing ? (string) ($editPopup['match_type'] ?? 'all') : 'all'; ?>
                                <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">특정 subject ID</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">특정 subject ID</span>
                        <input type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editPopup['subject_id'] ?? '')) : ''; ?>" maxlength="80">
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">시작 시각</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">시작 시각</span>
                        <input type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['starts_at'] ?? null)) : ''; ?>">
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">종료 시각</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">종료 시각</span>
                        <input type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['ends_at'] ?? null)) : ''; ?>">
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">닫기 유지일</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">닫기 유지일</span>
                        <input type="number" name="dismiss_cookie_days" value="<?php echo $editing ? sr_e((string) $editPopup['dismiss_cookie_days']) : '1'; ?>" min="0" max="365">
                        </label>
                    </div>
                </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
        <section class="card">
            <h2>팝업레이어 설정</h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_settings">
            <div class="af-row">
                <div class="af-label"><span class="form-label">팝업레이어 스킨</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">팝업레이어 스킨</span>
                    <select name="popup_layer_skin_key">
                        <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $popupLayerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary">팝업레이어 설정 저장</button>
        </div>
    </form>

    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">팝업 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers/new')); ?>" class="btn btn-sm btn-surface-default-soft">새 팝업 추가</a>
        </div>
        <?php if ($popups === []) { ?>
            <p>등록된 팝업이 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
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
                    <?php foreach ($popups as $popup) { ?>
                        <?php
                        if ((string) ($popup['module_key'] ?? '') === '') {
                            $popupTargetLabel = '공용 팝업레이어';
                        } else {
                            $popupTargetLabel = (string) $popup['module_key'] . ' / ' . (string) $popup['point_key'] . ' / ' . (string) $popup['slot_key'];
                        }
                        ?>
                        <tr>
                            <td><?php echo sr_e((string) $popup['title']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $popup['status'], 'content_status')); ?></td>
                            <td><?php echo sr_e(sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($popup['skin_key'] ?? 'basic')])); ?></td>
                            <td>
                                <?php echo sr_e($popupTargetLabel); ?><br>
                                <?php echo sr_e((string) $popup['match_type'] . ((string) ($popup['subject_id'] ?? '') !== '' ? ': ' . (string) $popup['subject_id'] : '')); ?>
                            </td>
                            <td>
                                <?php echo sr_e((string) ($popup['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($popup['ends_at'] ?? '-')); ?>
                            </td>
                            <td><?php echo sr_e((string) $popup['dismiss_cookie_days']); ?></td>
                            <td><?php echo sr_e((string) $popup['updated_at']); ?></td>
                            <td class="member-cell-manage">
                                <div class="member-manage">
                                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers/edit?id=' . rawurlencode((string) $popup['id']))); ?>" class="btn btn-sm btn-surface-default-soft">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="popup_id" value="<?php echo sr_e((string) $popup['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
