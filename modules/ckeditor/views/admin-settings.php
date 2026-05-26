<?php

$adminPageTitle = 'CKEditor 설정';
$adminPageSubtitle = '콘텐츠, 커뮤니티, 관리자 본문 입력 화면의 CKEditor 연결 방식을 관리합니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/ckeditor/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>편집기 설정</h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">

        <div class="admin-form-row">
            <label class="form-label" for="ckeditor_admin_asset_mode">에셋 로딩 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="ckeditor_admin_asset_mode" name="asset_mode" class="form-select">
                    <?php foreach ($assetModeOptions as $assetMode => $assetModeLabel) { ?>
                        <option value="<?php echo sr_e((string) $assetMode); ?>"<?php echo (string) $settings['asset_mode'] === (string) $assetMode ? ' selected' : ''; ?>><?php echo sr_e((string) $assetModeLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">직접 호스팅은 modules/ckeditor/vendor/ckeditor5/에 포함된 CKEditor 5 배포 파일을 사용합니다.</p>
            </div>
        </div>

        <div class="admin-form-row">
            <label class="form-label" for="ckeditor_admin_cdn_version">CDN 버전 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="ckeditor_admin_cdn_version" type="text" name="cdn_version" class="form-control" maxlength="20" pattern="[0-9]+(\\.[0-9]+){1,2}" value="<?php echo sr_e((string) $settings['cdn_version']); ?>" required>
                <p class="admin-form-help">CDN 방식을 쓸 때 불러올 CKEditor 5 버전입니다.</p>
            </div>
        </div>

        <div class="admin-form-row">
            <label class="form-label" for="ckeditor_admin_license_key">라이선스 키 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="ckeditor_admin_license_key" type="text" name="license_key" class="form-control" maxlength="255" value="<?php echo sr_e((string) $settings['license_key']); ?>" required>
                <p class="admin-form-help">GPL 조건으로 직접 호스팅할 때는 GPL을 입력합니다. CDN 방식은 해당 배포 채널에서 유효한 라이선스 키가 필요합니다.</p>
            </div>
        </div>

        <div class="admin-form-row">
            <label class="form-label" for="ckeditor_admin_toolbar_preset">툴바 구성 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="ckeditor_admin_toolbar_preset" name="toolbar_preset" class="form-select">
                    <?php foreach ($toolbarPresets as $presetKey => $preset) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) $settings['toolbar_preset'] === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($preset['label'] ?? $presetKey)); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <p class="admin-form-help">콘텐츠, 커뮤니티, 관리자 화면 중 어디에 CKEditor를 적용할지는 각 모듈의 에디터 설정에서 결정합니다. 에셋 로딩에 실패하면 일반 textarea로 제출됩니다.</p>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="btn btn-solid-light">모듈 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
