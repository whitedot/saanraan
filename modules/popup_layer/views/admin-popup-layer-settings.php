<?php

$adminPageTitle = '팝업레이어 설정';
$adminPageSubtitle = '팝업레이어 기본 스킨을 관리합니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>팝업레이어 설정</h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="admin-form-row">
            <label class="form-label" for="popup_layer_admin_popup_layer_settings_popup_layer_skin_key">팝업레이어 스킨</label>
            <div class="admin-form-field">
                <select id="popup_layer_admin_popup_layer_settings_popup_layer_skin_key" name="popup_layer_skin_key" class="form-select">
                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $popupLayerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light">팝업 목록</a>
        <button type="submit" class="btn btn-solid-primary">팝업레이어 설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
