<?php

$adminPageTitle = '알림 환경설정';
$adminPageSubtitle = '사이트 알림 발송에 사용할 메일과 운영자 알림 연결 정보를 설정합니다.';
$emailTransportOptions = sr_notification_email_transport_options();
$emailEncryptionOptions = sr_notification_email_encryption_options();
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$notificationSettingsSectionNavItems = [
    'notification-settings-section-email' => '메일 환경',
    'notification-settings-section-smtp' => 'SMTP',
    'notification-settings-section-http-api' => 'HTTP API',
    'notification-settings-section-external-push' => '외부 푸시',
    'notification-settings-section-runner' => '발송 실행',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="알림 설정 섹션">
    <?php $notificationSettingsSectionNavIndex = 0; ?>
    <?php foreach ($notificationSettingsSectionNavItems as $notificationSettingsSectionId => $notificationSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $notificationSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $notificationSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $notificationSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $notificationSettingsSectionLabel); ?>
        </a>
        <?php $notificationSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/settings')); ?>" class="admin-form ui-form-theme" data-notification-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section id="notification-settings-section-email" class="card" data-admin-section-anchor>
        <h2>메일 발송 환경</h2>
        <div class="form-row">
            <span class="form-label">이메일 채널</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_email_channel_enabled', 'email_channel_enabled', '1', !empty($settings['email_channel_enabled']), '알림 등록에서 이메일 채널 사용', '', ' data-notification-email-enabled'); ?>
                <small class="form-help">끄면 알림 등록 화면에서 이메일 채널을 선택할 수 없습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_transport">발송 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="notification_admin_settings_email_transport" name="email_transport" class="form-select" required data-notification-email-transport>
                    <?php foreach ($emailTransportOptions as $transportValue => $transportLabel) { ?>
                        <option value="<?php echo sr_e((string) $transportValue); ?>"<?php echo (string) $settings['email_transport'] === (string) $transportValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $transportLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="form-help">이 값은 이메일 발송 작업 처리에서 사용할 발송 방식입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_from_email">발신 이메일 <span class="sr-required-label" data-notification-email-from-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_from_email" type="email" name="email_from_email" value="<?php echo sr_e((string) $settings['email_from_email']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="email" data-notification-email-from>
                <small class="form-help">SMTP와 HTTP API 발송 방식에서는 필수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_from_name">발신 이름</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_from_name" type="text" name="email_from_name" value="<?php echo sr_e((string) $settings['email_from_name']); ?>" maxlength="120" class="form-input form-control-full">
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_timeout_seconds">타임아웃 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_timeout_seconds" type="number" name="email_timeout_seconds" value="<?php echo sr_e((string) $settings['email_timeout_seconds']); ?>" min="3" max="30" class="form-input" required>
                <small class="form-help">초 단위입니다. 3부터 30까지 입력합니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-smtp" class="card" data-admin-section-anchor>
        <h2>SMTP</h2>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_host">호스트 <span class="sr-required-label" data-notification-smtp-host-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_host" type="text" name="email_smtp_host" value="<?php echo sr_e((string) $settings['email_smtp_host']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off" data-notification-smtp-host>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_port">포트 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_port" type="number" name="email_smtp_port" value="<?php echo sr_e((string) $settings['email_smtp_port']); ?>" min="1" max="65535" class="form-input" required>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_encryption">암호화 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="notification_admin_settings_email_smtp_encryption" name="email_smtp_encryption" class="form-select" required>
                    <?php foreach ($emailEncryptionOptions as $encryptionValue => $encryptionLabel) { ?>
                        <option value="<?php echo sr_e((string) $encryptionValue); ?>"<?php echo (string) $settings['email_smtp_encryption'] === (string) $encryptionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $encryptionLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_username">사용자 이름</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_username" type="text" name="email_smtp_username" value="<?php echo sr_e((string) $settings['email_smtp_username']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off">
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_password">비밀번호</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_password" type="password" name="email_smtp_password" value="" maxlength="255" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-http-api" class="card" data-admin-section-anchor>
        <h2>메일 HTTP API</h2>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_http_api_endpoint">Endpoint <span class="sr-required-label" data-notification-http-api-endpoint-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_http_api_endpoint" type="url" name="email_http_api_endpoint" value="<?php echo sr_e((string) $settings['email_http_api_endpoint']); ?>" maxlength="255" class="form-input form-control-full" placeholder="https://api.example.com/mail/send" data-notification-http-api-endpoint>
                <small class="form-help">공개 HTTPS URL만 허용합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_http_api_bearer_token">Bearer token</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_http_api_bearer_token" type="password" name="email_http_api_bearer_token" value="" maxlength="255" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-external-push" class="card" data-admin-section-anchor>
        <h2>외부 운영 푸시</h2>
        <div class="form-row">
            <span class="form-label">외부 푸시 채널</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_external_push_enabled', 'external_push_enabled', '1', !empty($settings['external_push_enabled']), '관리자 운영 알림을 외부 provider로 발송', '', ''); ?>
                <small class="form-help">회원 대상 외부 푸시는 아직 사용하지 않습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Slack provider</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_slack_webhook_enabled', 'slack_webhook_enabled', '1', !empty($settings['slack_webhook_enabled']), 'Slack webhook 사용', '', ''); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_slack_channel_label">Slack 채널 표시명</label>
            <div class="form-field">
                <input id="notification_admin_settings_slack_channel_label" type="text" name="slack_channel_label" value="<?php echo sr_e((string) $settings['slack_channel_label']); ?>" maxlength="80" class="form-input form-control-full">
                <small class="form-help">발송 목록의 recipient 칸에 저장할 식별용 label입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_slack_webhook_url">Slack webhook URL</label>
            <div class="form-field">
                <input id="notification_admin_settings_slack_webhook_url" type="password" name="slack_webhook_url" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['slack_webhook_url'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">HTTPS URL만 허용합니다. 비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Discord provider</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_discord_webhook_enabled', 'discord_webhook_enabled', '1', !empty($settings['discord_webhook_enabled']), 'Discord webhook 사용', '', ''); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_discord_channel_label">Discord 채널 표시명</label>
            <div class="form-field">
                <input id="notification_admin_settings_discord_channel_label" type="text" name="discord_channel_label" value="<?php echo sr_e((string) $settings['discord_channel_label']); ?>" maxlength="80" class="form-input form-control-full">
                <small class="form-help">발송 목록의 recipient 칸에 저장할 식별용 label입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_discord_webhook_url">Discord webhook URL</label>
            <div class="form-field">
                <input id="notification_admin_settings_discord_webhook_url" type="password" name="discord_webhook_url" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['discord_webhook_url'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">HTTPS URL만 허용합니다. 비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Telegram provider</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_telegram_bot_enabled', 'telegram_bot_enabled', '1', !empty($settings['telegram_bot_enabled']), 'Telegram bot 사용', '', ''); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_channel_label">Telegram 채널 표시명</label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_channel_label" type="text" name="telegram_channel_label" value="<?php echo sr_e((string) $settings['telegram_channel_label']); ?>" maxlength="80" class="form-input form-control-full">
                <small class="form-help">발송 목록의 recipient 칸에 저장할 식별용 label입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_bot_token">Telegram bot token</label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_bot_token" type="password" name="telegram_bot_token" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['telegram_bot_token'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_chat_id">Telegram chat ID</label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_chat_id" type="text" name="telegram_chat_id" value="<?php echo sr_e((string) $settings['telegram_chat_id']); ?>" maxlength="120" class="form-input form-control-full">
                <small class="form-help">숫자 ID 또는 @channel 형식입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_external_push_failure_policy">외부 푸시 실패 정책 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="notification_admin_settings_external_push_failure_policy" name="external_push_failure_policy" class="form-select" required>
                    <option value="retry"<?php echo (string) $settings['external_push_failure_policy'] === 'retry' ? ' selected' : ''; ?>>재시도 후 dead-letter</option>
                    <option value="dead"<?php echo (string) $settings['external_push_failure_policy'] === 'dead' ? ' selected' : ''; ?>>즉시 dead-letter</option>
                </select>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-runner" class="card" data-admin-section-anchor>
        <h2>발송 실행</h2>
        <div class="form-row">
            <span class="form-label">웹 자동 실행</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_delivery_web_runner_enabled', 'delivery_web_runner_enabled', '1', !empty($settings['delivery_web_runner_enabled']), '공개/관리자 GET 요청 말미에 대기 발송 처리', '', ''); ?>
                <small class="form-help">공유호스팅 기본 실행 방식입니다. 요청 응답 뒤 작은 배치만 처리합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_web_runner_interval_seconds">웹 실행 간격 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_web_runner_interval_seconds" type="number" name="delivery_web_runner_interval_seconds" value="<?php echo sr_e((string) $settings['delivery_web_runner_interval_seconds']); ?>" min="10" max="3600" class="form-input" required>
                <small class="form-help">초 단위입니다. 10부터 3600까지 입력합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_web_runner_batch_size">웹 배치 크기 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_web_runner_batch_size" type="number" name="delivery_web_runner_batch_size" value="<?php echo sr_e((string) $settings['delivery_web_runner_batch_size']); ?>" min="1" max="5" class="form-input" required>
                <small class="form-help">한 번의 웹 요청 말미에서 처리할 최대 발송 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_manual_batch_size">수동 배치 크기 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_manual_batch_size" type="number" name="delivery_manual_batch_size" value="<?php echo sr_e((string) $settings['delivery_manual_batch_size']); ?>" min="1" max="50" class="form-input" required>
                <small class="form-help">관리자 발송 목록에서 수동 실행할 때 처리할 최대 발송 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_cli_batch_size">CLI 배치 크기 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_cli_batch_size" type="number" name="delivery_cli_batch_size" value="<?php echo sr_e((string) $settings['delivery_cli_batch_size']); ?>" min="1" max="100" class="form-input" required>
                <small class="form-help">cron 또는 수동 CLI runner에서 처리할 최대 발송 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_max_attempts">최대 재시도 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_max_attempts" type="number" name="delivery_max_attempts" value="<?php echo sr_e((string) $settings['delivery_max_attempts']); ?>" min="1" max="20" class="form-input" required>
                <small class="form-help">초과하면 dead-letter 상태로 전환합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_lock_timeout_seconds">Lock 만료 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_lock_timeout_seconds" type="number" name="delivery_lock_timeout_seconds" value="<?php echo sr_e((string) $settings['delivery_lock_timeout_seconds']); ?>" min="30" max="3600" class="form-input" required>
                <small class="form-help">처리 중인 작업이 이 시간을 넘기면 다음 runner가 다시 claim할 수 있습니다.</small>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
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
