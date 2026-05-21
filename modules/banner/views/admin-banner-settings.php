<?php

$adminPageTitle = '배너 설정';
$adminPageSubtitle = '배너 기본 출력 스킨을 관리합니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/banners/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>배너 설정</h2>
        <p>배너 스킨은 기본 출력 템플릿입니다. 개별 배너에서 다른 스킨을 선택하면 개별 설정이 우선합니다.</p>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="admin-form-row">
            <label class="form-label" for="banner_admin_banner_settings_banner_skin_key">배너 스킨</label>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_banner_skin_key" name="banner_skin_key" class="form-select">
                                    <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $bannerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                            (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light">배너 목록</a>
        <button type="submit" class="btn btn-solid-primary">배너 설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
