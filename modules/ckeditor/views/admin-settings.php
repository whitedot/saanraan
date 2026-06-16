<?php

$adminPageTitle = 'CKEditor 설정';
$adminPageSubtitle = '본문 작성 화면에서 사용할 편집기 방식을 정합니다. 화면별 세부 적용은 각 모듈 설정을 따릅니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/ckeditor/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>편집기 설정</h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">

        <div class="form-row">
            <label class="form-label" for="ckeditor_admin_asset_mode">에셋 로딩 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('ckeditor_admin_asset_mode', 'asset_mode', $assetModeOptions, (string) $settings['asset_mode'], true); ?>
                <p class="form-help">직접 호스팅은 modules/ckeditor/vendor/ckeditor5/에 포함된 CKEditor 5 배포 파일을 사용합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="ckeditor_admin_cdn_version">CDN 버전 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="ckeditor_admin_cdn_version" type="text" name="cdn_version" class="form-control" maxlength="20" pattern="[0-9]+(\\.[0-9]+){1,2}" value="<?php echo sr_e((string) $settings['cdn_version']); ?>" required>
                <p class="form-help">CDN 방식을 쓸 때 불러올 CKEditor 5 버전입니다.</p>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="ckeditor_admin_license_key">라이선스 키 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="ckeditor_admin_license_key" type="text" name="license_key" class="form-control" maxlength="255" value="<?php echo sr_e((string) $settings['license_key']); ?>" required>
                <p class="form-help">GPL 조건으로 직접 호스팅할 때는 GPL을 입력합니다. CDN 방식은 해당 배포 채널에서 유효한 라이선스 키가 필요합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="ckeditor_admin_toolbar_preset">기본 툴바 구성 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="ckeditor_admin_toolbar_preset" name="toolbar_preset" class="form-select" required>
                    <?php foreach ($toolbarPresets as $presetKey => $preset) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) $settings['toolbar_preset'] === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($preset['label'] ?? $presetKey)); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">각 화면이나 모듈이 별도 툴바를 지정하지 않을 때 CKEditor가 사용하는 전역 기본값입니다. 기본 textarea 또는 다른 에디터를 사용할 때는 적용되지 않습니다.</p>
            </div>
        </div>

        <p class="form-help">콘텐츠, 커뮤니티, 관리자 화면 중 어디에 CKEditor를 적용할지는 각 모듈의 에디터 설정에서 결정합니다. 화면 소유 모듈이 명시 툴바를 지정하면 이 기본값보다 우선하며, 에셋 로딩에 실패하면 일반 textarea로 제출됩니다.</p>
    </section>

    <div class="form-sticky-actions form-actions">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
