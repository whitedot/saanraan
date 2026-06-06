<?php

$adminPageTitle = '알림 환경설정';
$adminPageSubtitle = '메일 발송 환경을 관리합니다.';
$emailTransportOptions = sr_notification_email_transport_options();
$emailEncryptionOptions = sr_notification_email_encryption_options();
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/settings')); ?>" class="admin-form ui-form-theme" data-notification-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="admin-card card">
        <h2>메일 발송 환경</h2>
        <div class="admin-form-row">
            <span class="form-label">이메일 채널</span>
            <div class="admin-form-field">
                <label class="admin-form-check form-label" for="notification_admin_settings_email_channel_enabled">
                    <input id="notification_admin_settings_email_channel_enabled" type="checkbox" name="email_channel_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['email_channel_enabled']) ? ' checked' : ''; ?> data-notification-email-enabled>
                    <?php echo sr_admin_choice_label_html('알림 등록에서 이메일 채널 사용'); ?>
                </label>
                <small class="admin-form-help">끄면 알림 등록 화면에서 이메일 채널을 선택할 수 없습니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_transport">발송 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="notification_admin_settings_email_transport" name="email_transport" class="form-select" required data-notification-email-transport>
                    <?php foreach ($emailTransportOptions as $transportValue => $transportLabel) { ?>
                        <option value="<?php echo sr_e((string) $transportValue); ?>"<?php echo (string) $settings['email_transport'] === (string) $transportValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $transportLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="admin-form-help">이 값은 이메일 발송 작업 처리에서 사용할 발송 방식입니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_from_email">발신 이메일 <span class="sr-required-label" data-notification-email-from-required hidden>(필수)</span></label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_from_email" type="email" name="email_from_email" value="<?php echo sr_e((string) $settings['email_from_email']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="email" data-notification-email-from>
                <small class="admin-form-help">SMTP와 HTTP API 발송 방식에서는 필수입니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_from_name">발신 이름</label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_from_name" type="text" name="email_from_name" value="<?php echo sr_e((string) $settings['email_from_name']); ?>" maxlength="120" class="form-input form-control-full">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_timeout_seconds">타임아웃 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_timeout_seconds" type="number" name="email_timeout_seconds" value="<?php echo sr_e((string) $settings['email_timeout_seconds']); ?>" min="3" max="30" class="form-input" required>
                <small class="admin-form-help">초 단위입니다. 3부터 30까지 입력합니다.</small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>SMTP</h2>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_host">호스트 <span class="sr-required-label" data-notification-smtp-host-required hidden>(필수)</span></label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_smtp_host" type="text" name="email_smtp_host" value="<?php echo sr_e((string) $settings['email_smtp_host']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off" data-notification-smtp-host>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_port">포트 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_smtp_port" type="number" name="email_smtp_port" value="<?php echo sr_e((string) $settings['email_smtp_port']); ?>" min="1" max="65535" class="form-input" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_encryption">암호화 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="notification_admin_settings_email_smtp_encryption" name="email_smtp_encryption" class="form-select" required>
                    <?php foreach ($emailEncryptionOptions as $encryptionValue => $encryptionLabel) { ?>
                        <option value="<?php echo sr_e((string) $encryptionValue); ?>"<?php echo (string) $settings['email_smtp_encryption'] === (string) $encryptionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $encryptionLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_username">사용자 이름</label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_smtp_username" type="text" name="email_smtp_username" value="<?php echo sr_e((string) $settings['email_smtp_username']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_password">비밀번호</label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_smtp_password" type="password" name="email_smtp_password" value="" maxlength="255" class="form-input form-control-full" autocomplete="new-password">
                <small class="admin-form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>메일 HTTP API</h2>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_http_api_endpoint">Endpoint <span class="sr-required-label" data-notification-http-api-endpoint-required hidden>(필수)</span></label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_http_api_endpoint" type="url" name="email_http_api_endpoint" value="<?php echo sr_e((string) $settings['email_http_api_endpoint']); ?>" maxlength="255" class="form-input form-control-full" placeholder="https://api.example.com/mail/send" data-notification-http-api-endpoint>
                <small class="admin-form-help">공개 HTTPS URL만 허용합니다.</small>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="notification_admin_settings_email_http_api_bearer_token">Bearer token</label>
            <div class="admin-form-field">
                <input id="notification_admin_settings_email_http_api_bearer_token" type="password" name="email_http_api_bearer_token" value="" maxlength="255" class="form-input form-control-full" autocomplete="new-password">
                <small class="admin-form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light">알림 목록</a>
        <button type="submit" class="btn btn-solid-primary">환경설정 저장</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-notification-settings-form]');
    if (!form) {
        return;
    }

    var enabledInput = form.querySelector('[data-notification-email-enabled]');
    var transportInput = form.querySelector('[data-notification-email-transport]');
    var fromInput = form.querySelector('[data-notification-email-from]');
    var smtpHostInput = form.querySelector('[data-notification-smtp-host]');
    var httpApiEndpointInput = form.querySelector('[data-notification-http-api-endpoint]');
    var fromRequiredLabel = form.querySelector('[data-notification-email-from-required]');
    var smtpHostRequiredLabel = form.querySelector('[data-notification-smtp-host-required]');
    var httpApiEndpointRequiredLabel = form.querySelector('[data-notification-http-api-endpoint-required]');

    function setRequired(input, label, required) {
        if (input) {
            input.required = required;
        }
        if (label) {
            label.hidden = !required;
        }
    }

    function syncRequiredState() {
        var enabled = enabledInput && enabledInput.checked;
        var transport = transportInput ? transportInput.value : '';
        setRequired(fromInput, fromRequiredLabel, enabled && (transport === 'smtp' || transport === 'http_api'));
        setRequired(smtpHostInput, smtpHostRequiredLabel, enabled && transport === 'smtp');
        setRequired(httpApiEndpointInput, httpApiEndpointRequiredLabel, enabled && transport === 'http_api');
    }

    form.addEventListener('change', syncRequiredState);
    syncRequiredState();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
