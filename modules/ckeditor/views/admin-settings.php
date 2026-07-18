<?php

$adminPageTitle = 'CKEditor 설정';
$adminPageSubtitle = '';
$ckeditorHelpOpenLabel = '도움말 보기';
$ckeditorHelp = [
    'asset_mode' => [
        'id' => 'ckeditor-admin-help-asset-mode',
        'title' => '편집기 파일 불러오기 도움말',
        'body' => '<p><strong>직접 호스팅</strong>은 사이트에 포함된 CKEditor 파일을 사용합니다. 외부 CDN 연결 없이 동작하며 저장소에 포함된 버전으로 유지됩니다.</p>'
            . '<p><strong>CDN</strong>은 입력한 버전의 스크립트와 스타일을 CKEditor 공식 CDN에서 불러옵니다. 방문자 브라우저가 외부 서버에 연결하므로 운영 환경의 외부 연결 정책과 라이선스 조건을 함께 확인하세요.</p>'
            . '<p>선택한 파일을 불러오지 못하면 CKEditor 대신 일반 긴 글 입력란이 표시되며, 작성한 내용은 그대로 제출할 수 있습니다.</p>',
    ],
    'license_key' => [
        'id' => 'ckeditor-admin-help-license-key',
        'title' => 'CKEditor 라이선스 키 도움말',
        'body' => '<p>직접 호스팅 방식에서 GPL 조건으로 사용하는 경우 <code>GPL</code>을 입력합니다. CDN 방식에서는 GPL 값을 사용할 수 없으며 해당 배포 채널에서 사용할 수 있는 라이선스 키가 필요합니다.</p>'
            . '<p>입력한 값은 CKEditor를 시작하기 위해 브라우저 설정에 포함됩니다. 비밀번호나 다른 서비스의 비밀 키를 입력하지 마세요.</p>',
    ],
    'toolbar' => [
        'id' => 'ckeditor-admin-help-toolbar',
        'title' => '기본 편집 도구 도움말',
        'body' => '<p>일반 편집 도구는 제목, 글자 크기와 색상, 기본 강조, 정렬, 링크, 이미지, 표, 구분선, 인용, 목록, 들여쓰기와 서식 제거를 제공합니다.</p>'
            . '<p>화면 폭에 모든 도구가 들어가지 않으면 툴바가 여러 줄로 접혀 모든 버튼을 계속 표시합니다.</p>'
            . '<p>이미지 삽입 버튼은 항상 표시되며, 업로드 경로가 있는 화면에서는 파일 업로드와 이미지 URL 삽입을 함께 제공합니다.</p>'
            . '<p>콘텐츠와 커뮤니티 등 화면을 소유한 모듈이 별도 구성을 지정하면 모듈 설정을 우선합니다. 기본 긴 글 입력란이나 다른 편집기를 사용하는 화면에는 적용되지 않습니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/ckeditor/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>편집기 설정</h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">

        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('ckeditor_admin_asset_mode', '편집기 파일 불러오기', $ckeditorHelp['asset_mode']['id'], $ckeditorHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('ckeditor_admin_asset_mode', 'asset_mode', $assetModeOptions, (string) $settings['asset_mode'], true); ?>
                <p class="form-help">직접 호스팅은 사이트에 포함된 파일을, CDN은 CKEditor 외부 서버의 파일을 사용합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('ckeditor_admin_cdn_version', 'CDN 버전', $ckeditorHelp['asset_mode']['id'], $ckeditorHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="ckeditor_admin_cdn_version" type="text" name="cdn_version" class="form-control" maxlength="20" pattern="[0-9]+(\\.[0-9]+){1,2}" value="<?php echo sr_e((string) $settings['cdn_version']); ?>" required>
                <p class="form-help">CDN 방식을 선택했을 때 불러올 CKEditor 버전입니다. 예: 48.3.0</p>
            </div>
        </div>

        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('ckeditor_admin_license_key', '라이선스 키', $ckeditorHelp['license_key']['id'], $ckeditorHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="ckeditor_admin_license_key" type="text" name="license_key" class="form-control" maxlength="255" value="<?php echo sr_e((string) $settings['license_key']); ?>" required>
                <p class="form-help">GPL 조건의 직접 호스팅은 <code>GPL</code>을, CDN 방식은 사용할 수 있는 라이선스 키를 입력합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('ckeditor_admin_toolbar_preset', '기본 편집 도구', $ckeditorHelp['toolbar']['id'], $ckeditorHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="ckeditor_admin_toolbar_preset" name="toolbar_preset" class="form-select" required>
                    <?php foreach ($toolbarPresets as $presetKey => $preset) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) $settings['toolbar_preset'] === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($preset['label'] ?? $presetKey)); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">화면별 구성이 없을 때 CKEditor 위에 표시할 편집 도구 묶음입니다.</p>
            </div>
        </div>

        <p class="form-help">CKEditor를 사용할 화면은 콘텐츠, 커뮤니티 등 각 모듈의 편집기 설정에서 선택합니다.</p>
    </section>

    <div class="form-sticky-actions form-actions">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($ckeditorHelp as $ckeditorHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $ckeditorHelpModal['id'], (string) $ckeditorHelpModal['title'], (string) $ckeditorHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
