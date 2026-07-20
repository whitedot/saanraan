<?php

$adminPageTitle = '알림 환경설정';
$adminPageSubtitle = '';
$emailTransportOptions = sr_notification_email_transport_options();
$emailEncryptionOptions = sr_notification_email_encryption_options();
$externalPushEnabled = !empty($settings['external_push_enabled']);
$slackOperationalEnabled = $externalPushEnabled && !empty($settings['slack_webhook_enabled']);
$discordOperationalEnabled = $externalPushEnabled && !empty($settings['discord_webhook_enabled']);
$telegramOperationalEnabled = $externalPushEnabled && !empty($settings['telegram_bot_enabled']);
$telegramTokenRequired = $externalPushEnabled && (!empty($settings['telegram_bot_enabled']) || !empty($settings['telegram_member_push_enabled']));
$slackWebhookStored = (string) ($settings['slack_webhook_url'] ?? '') !== '';
$discordWebhookStored = (string) ($settings['discord_webhook_url'] ?? '') !== '';
$telegramTokenStored = (string) ($settings['telegram_bot_token'] ?? '') !== '';
$notificationSettingsHelpOpenLabel = '도움말 보기';
$notificationSettingsHelpButtonHtml = static function (string $label, string $modalId) use ($notificationSettingsHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $notificationSettingsHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$notificationSettingsHelp = [
    'runner' => [
        'id' => 'notification-settings-help-runner',
        'title' => '발송 작업 처리 도움말',
        'body' => '<p>알림은 먼저 발송 대기열에 저장된 뒤 웹 자동 실행, 관리자 수동 실행 또는 서버 예약·명령 실행이 정해진 개수씩 처리합니다. 이 설정은 이메일과 외부 알림 채널의 모든 대기 작업에 함께 적용합니다.</p>'
            . '<p>웹 자동 실행은 정상적으로 끝난 사이트 요청 뒤에만 동작하며 설정한 간격이 지났을 때 웹 실행당 작업 수만큼 처리합니다. 방문 요청이 없으면 실행되지 않으므로 발송량이 많거나 일정한 처리가 필요하면 서버 예약 실행을 사용하세요.</p>'
            . '<p>웹·수동·서버 실행당 작업 수는 한 번에 가져올 최대 개수입니다. 값을 높이면 빨리 처리할 수 있지만 한 요청의 실행 시간과 외부 서비스 호출량도 늘어납니다.</p>',
    ],
    'attempts' => [
        'id' => 'notification-settings-help-attempts',
        'title' => '실패 작업 처리 도움말',
        'body' => '<p>최대 발송 시도 횟수는 첫 발송을 포함한 전체 시도 횟수입니다. 이 횟수까지 보내지 못하면 작업을 더 이상 자동 재시도하지 않는 실패 보관 상태로 바꿉니다.</p>'
            . '<p>처리 중 상태 만료 시간은 서버 중단 등으로 완료 표시를 남기지 못한 작업을 다른 실행이 다시 가져갈 수 있게 하는 기준입니다. 정상 발송에 필요한 시간보다 너무 짧게 설정하면 같은 작업을 중복 처리할 가능성이 커집니다.</p>',
    ],
    'email_method' => [
        'id' => 'notification-settings-help-email-method',
        'title' => '이메일 발송 방식 도움말',
        'body' => '<p>PHP mail()은 웹호스팅 서버의 기본 메일 기능을 사용합니다. 별도 계정 설정은 적지만 호스팅 환경에 따라 발송이 제한되거나 스팸 처리될 수 있습니다.</p>'
            . '<p>SMTP는 메일 서비스에서 받은 서버 주소, 포트, 암호화 방식과 계정 정보를 사용합니다. 메일 API는 외부 메일 서비스의 공개 HTTPS 전송 URL과 필요한 경우 인증 토큰을 사용합니다.</p>'
            . '<p>SMTP 또는 메일 API를 선택하고 이메일 채널을 켜면 발신 이메일이 필요합니다. 비밀번호와 인증 토큰은 이미 저장된 경우 비워 두면 기존 값을 유지합니다.</p>',
    ],
    'timeout' => [
        'id' => 'notification-settings-help-timeout',
        'title' => '외부 발송 응답 대기 시간 도움말',
        'body' => '<p>SMTP 서버, 메일 API와 Slack·Discord·Telegram 같은 외부 알림 서비스의 응답을 한 번에 얼마나 기다릴지 정합니다.</p>'
            . '<p>시간 안에 응답하지 않으면 해당 발송 시도는 실패로 처리됩니다. 너무 길게 설정하면 한 발송 작업이 웹 요청이나 예약 작업을 오래 점유할 수 있습니다.</p>',
    ],
    'external_channels' => [
        'id' => 'notification-settings-help-external-channels',
        'title' => '외부 알림 채널 도움말',
        'body' => '<p>사이트 운영 알림 발송은 이 화면에 저장한 공용 수신처로 운영자용 알림을 보냅니다. 회원 연결 허용은 회원이 계정 알림 화면에서 자신의 Slack·Discord 수신 URL이나 Telegram 대화방을 연결할 수 있게 합니다.</p>'
            . '<p>전체 외부 알림 채널을 켠 뒤 사용할 서비스의 운영 알림 발송 또는 회원 연결 허용을 하나 이상 켜야 합니다. Slack과 Discord의 운영 수신 URL, Telegram 봇 토큰은 비밀값으로 취급하며 비워 두면 기존 저장값을 유지합니다.</p>'
            . '<p>Telegram 봇 토큰은 사이트 운영 알림과 회원 Telegram 연결이 함께 사용합니다. 운영 알림에는 공용 대화방 ID가 추가로 필요하지만 회원 수신처는 각 회원이 별도로 연결합니다.</p>',
    ],
    'external_failure' => [
        'id' => 'notification-settings-help-external-failure',
        'title' => '외부 알림 실패 처리 도움말',
        'body' => '<p>재시도 후 실패 보관은 위의 최대 발송 시도 횟수까지 다시 보내고, 모두 실패하면 더 이상 자동 처리하지 않는 상태로 보관합니다.</p>'
            . '<p>즉시 실패 보관은 첫 실패 뒤 다시 보내지 않습니다. 잘못된 수신 URL처럼 재시도로 해결되지 않는 오류가 반복 호출되는 것을 막고 싶을 때 사용합니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$notificationSettingsSectionNavItems = [
    'notification-settings-section-runner' => '발송 작업 처리',
    'notification-settings-section-email' => '이메일',
    'notification-settings-section-external-push' => '외부 알림 채널',
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

    <section id="notification-settings-section-runner" class="card" data-admin-section-anchor>
        <h2>발송 작업 처리</h2>
        <p class="form-help">이메일과 외부 알림의 대기 작업을 언제, 몇 건씩 처리할지 정합니다.</p>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_web_runner_enabled', '웹 자동 실행', $notificationSettingsHelp['runner']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_delivery_web_runner_enabled', 'delivery_web_runner_enabled', '1', !empty($settings['delivery_web_runner_enabled']), '사용', '', ''); ?>
                <small class="form-help">정상적으로 끝난 사이트 요청 뒤에 대기 작업을 조금씩 처리합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_web_runner_interval_seconds', '웹 실행 간격', $notificationSettingsHelp['runner']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_web_runner_interval_seconds" type="number" name="delivery_web_runner_interval_seconds" value="<?php echo sr_e((string) $settings['delivery_web_runner_interval_seconds']); ?>" min="10" max="3600" class="form-input" required>
                    <span class="input-group-text">초</span>
                </div>
                <small class="form-help">10부터 3600까지 입력합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_web_runner_batch_size', '웹 실행당 발송 작업 수', $notificationSettingsHelp['runner']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_web_runner_batch_size" type="number" name="delivery_web_runner_batch_size" value="<?php echo sr_e((string) $settings['delivery_web_runner_batch_size']); ?>" min="1" max="5" class="form-input" required>
                    <span class="input-group-text">건</span>
                </div>
                <small class="form-help">한 번의 웹 요청 말미에서 처리할 최대 발송 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_manual_batch_size', '수동 실행당 발송 작업 수', $notificationSettingsHelp['runner']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_manual_batch_size" type="number" name="delivery_manual_batch_size" value="<?php echo sr_e((string) $settings['delivery_manual_batch_size']); ?>" min="1" max="50" class="form-input" required>
                    <span class="input-group-text">건</span>
                </div>
                <small class="form-help">관리자 발송 목록에서 수동 실행할 때 처리할 최대 발송 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_cli_batch_size', '서버 예약·명령 실행당 작업 수', $notificationSettingsHelp['runner']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_cli_batch_size" type="number" name="delivery_cli_batch_size" value="<?php echo sr_e((string) $settings['delivery_cli_batch_size']); ?>" min="1" max="100" class="form-input" required>
                    <span class="input-group-text">건</span>
                </div>
                <small class="form-help">서버 예약 작업이나 명령 실행 한 번에 처리할 최대 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_max_attempts', '최대 발송 시도 횟수', $notificationSettingsHelp['attempts']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_max_attempts" type="number" name="delivery_max_attempts" value="<?php echo sr_e((string) $settings['delivery_max_attempts']); ?>" min="1" max="20" class="form-input" required>
                    <span class="input-group-text">회</span>
                </div>
                <small class="form-help">첫 발송을 포함하며, 이 횟수까지 실패하면 자동 재시도를 멈춥니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_delivery_lock_timeout_seconds', '처리 중 상태 만료 시간', $notificationSettingsHelp['attempts']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_delivery_lock_timeout_seconds" type="number" name="delivery_lock_timeout_seconds" value="<?php echo sr_e((string) $settings['delivery_lock_timeout_seconds']); ?>" min="30" max="3600" class="form-input" required>
                    <span class="input-group-text">초</span>
                </div>
                <small class="form-help">완료되지 않은 작업이 이 시간을 넘기면 다음 실행에서 다시 처리할 수 있습니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-email" class="card" data-admin-section-anchor>
        <h2>
            <span>이메일</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="notification-smtp-test-email-modal" data-overlay="#notification-smtp-test-email-modal">
                테스트 메일
            </button>
        </h2>
        <div class="form-row">
            <span class="form-label">이메일 채널</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_email_channel_enabled', 'email_channel_enabled', '1', !empty($settings['email_channel_enabled']), '사용', '', ' data-notification-email-enabled'); ?>
                <small class="form-help">끄면 알림 등록 화면에서 이메일 채널을 선택할 수 없습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_email_transport', '이메일 발송 방식', $notificationSettingsHelp['email_method']['id'], $notificationSettingsHelpOpenLabel, true, true); ?>
            <div class="form-field">
                <select id="notification_admin_settings_email_transport" name="email_transport" class="form-select" required data-notification-email-transport>
                    <?php foreach ($emailTransportOptions as $transportValue => $transportLabel) { ?>
                        <option value="<?php echo sr_e((string) $transportValue); ?>"<?php echo (string) $settings['email_transport'] === (string) $transportValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $transportLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="form-help">웹호스팅 기본 메일, SMTP 서버 또는 외부 메일 API 중에서 선택합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_from_email">발신 이메일 <span class="sr-required-label" data-notification-email-from-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_from_email" type="email" name="email_from_email" value="<?php echo sr_e((string) $settings['email_from_email']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="email" data-notification-email-from>
                <small class="form-help">SMTP와 메일 API 발송 방식에서는 필수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_from_name">발신 이름</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_from_name" type="text" name="email_from_name" value="<?php echo sr_e((string) $settings['email_from_name']); ?>" maxlength="120" class="form-input form-control-full">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_email_timeout_seconds', '외부 발송 응답 대기 시간', $notificationSettingsHelp['timeout']['id'], $notificationSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="notification_admin_settings_email_timeout_seconds" type="number" name="email_timeout_seconds" value="<?php echo sr_e((string) $settings['email_timeout_seconds']); ?>" min="3" max="180" class="form-input" required>
                    <span class="input-group-text">초</span>
                </div>
                <small class="form-help">이메일과 외부 알림 서비스의 응답을 기다릴 시간이며 3~180초로 입력합니다.</small>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_host">SMTP 서버 주소 <span class="sr-required-label" data-notification-smtp-host-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_host" type="text" name="email_smtp_host" value="<?php echo sr_e((string) $settings['email_smtp_host']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off" data-notification-smtp-host>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_port">SMTP 포트 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_port" type="number" name="email_smtp_port" value="<?php echo sr_e((string) $settings['email_smtp_port']); ?>" min="1" max="65535" class="form-input" required>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_email_smtp_encryption', 'SMTP 보안 연결 방식', $notificationSettingsHelp['email_method']['id'], $notificationSettingsHelpOpenLabel, true); ?>
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
            <label class="form-label" for="notification_admin_settings_email_smtp_username">SMTP 사용자 이름</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_username" type="text" name="email_smtp_username" value="<?php echo sr_e((string) $settings['email_smtp_username']); ?>" maxlength="255" class="form-input form-control-full" autocomplete="off">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_email_smtp_password', 'SMTP 비밀번호', $notificationSettingsHelp['email_method']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="notification_admin_settings_email_smtp_password" type="password" name="email_smtp_password" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['email_smtp_password'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_http_api_endpoint">메일 API 전송 URL <span class="sr-required-label" data-notification-http-api-endpoint-required hidden>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_http_api_endpoint" type="url" name="email_http_api_endpoint" value="<?php echo sr_e((string) $settings['email_http_api_endpoint']); ?>" maxlength="255" class="form-input form-control-full" placeholder="https://api.example.com/mail/send" data-notification-http-api-endpoint>
                <small class="form-help">공개 HTTPS URL만 허용합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_email_http_api_bearer_token', '메일 API 인증 토큰', $notificationSettingsHelp['email_method']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="notification_admin_settings_email_http_api_bearer_token" type="password" name="email_http_api_bearer_token" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['email_http_api_bearer_token'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-external-push" class="card" data-admin-section-anchor>
        <h2>외부 알림 채널</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_external_push_enabled', '외부 알림 채널', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel, false, true); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_external_push_enabled', 'external_push_enabled', '1', !empty($settings['external_push_enabled']), '사용', '', ' data-notification-external-push-enabled'); ?>
                <small class="form-help">끄면 운영 알림 발송과 회원 연결을 모두 사용하지 않습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_slack_webhook_enabled', 'Slack 사이트 운영 알림', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_slack_webhook_enabled', 'slack_webhook_enabled', '1', !empty($settings['slack_webhook_enabled']), '사용', '', ' data-notification-operational-toggle="slack"'); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_slack_member_push_enabled', 'Slack 회원 연결 허용', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_slack_member_push_enabled', 'slack_member_push_enabled', '1', !empty($settings['slack_member_push_enabled']), '사용', '', ''); ?>
                <small class="form-help">회원이 계정 알림 화면에서 자신의 Slack 수신 URL을 연결할 수 있습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_slack_channel_label">Slack 채널 표시명 <span class="sr-required-label" data-notification-operational-required-label="slack"<?php echo $slackOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_slack_channel_label" type="text" name="slack_channel_label" value="<?php echo sr_e((string) $settings['slack_channel_label']); ?>" maxlength="80" class="form-input form-control-full"<?php echo $slackOperationalEnabled ? ' required' : ''; ?> data-notification-operational-required="slack">
                <small class="form-help">운영 알림 발송 목록의 수신자 칸에 저장할 식별용 표시명입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_slack_webhook_url">Slack 운영 수신 URL <span class="sr-required-label" data-notification-operational-required-label="slack"<?php echo $slackOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_slack_webhook_url" type="password" name="slack_webhook_url" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['slack_webhook_url'])); ?>" class="form-input form-control-full" autocomplete="new-password"<?php echo $slackOperationalEnabled && !$slackWebhookStored ? ' required' : ''; ?> data-notification-operational-secret="slack" data-notification-has-stored-secret="<?php echo $slackWebhookStored ? '1' : '0'; ?>">
                <small class="form-help">사이트 운영 알림을 받을 HTTPS URL입니다. 비워 두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_discord_webhook_enabled', 'Discord 사이트 운영 알림', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_discord_webhook_enabled', 'discord_webhook_enabled', '1', !empty($settings['discord_webhook_enabled']), '사용', '', ' data-notification-operational-toggle="discord"'); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_discord_member_push_enabled', 'Discord 회원 연결 허용', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_discord_member_push_enabled', 'discord_member_push_enabled', '1', !empty($settings['discord_member_push_enabled']), '사용', '', ''); ?>
                <small class="form-help">회원이 계정 알림 화면에서 자신의 Discord 수신 URL을 연결할 수 있습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_discord_channel_label">Discord 채널 표시명 <span class="sr-required-label" data-notification-operational-required-label="discord"<?php echo $discordOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_discord_channel_label" type="text" name="discord_channel_label" value="<?php echo sr_e((string) $settings['discord_channel_label']); ?>" maxlength="80" class="form-input form-control-full"<?php echo $discordOperationalEnabled ? ' required' : ''; ?> data-notification-operational-required="discord">
                <small class="form-help">운영 알림 발송 목록의 수신자 칸에 저장할 식별용 표시명입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_discord_webhook_url">Discord 운영 수신 URL <span class="sr-required-label" data-notification-operational-required-label="discord"<?php echo $discordOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_discord_webhook_url" type="password" name="discord_webhook_url" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['discord_webhook_url'])); ?>" class="form-input form-control-full" autocomplete="new-password"<?php echo $discordOperationalEnabled && !$discordWebhookStored ? ' required' : ''; ?> data-notification-operational-secret="discord" data-notification-has-stored-secret="<?php echo $discordWebhookStored ? '1' : '0'; ?>">
                <small class="form-help">사이트 운영 알림을 받을 HTTPS URL입니다. 비워 두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_telegram_bot_enabled', 'Telegram 사이트 운영 알림', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_telegram_bot_enabled', 'telegram_bot_enabled', '1', !empty($settings['telegram_bot_enabled']), '사용', '', ' data-notification-operational-toggle="telegram"'); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_telegram_member_push_enabled', 'Telegram 회원 연결 허용', $notificationSettingsHelp['external_channels']['id'], $notificationSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_telegram_member_push_enabled', 'telegram_member_push_enabled', '1', !empty($settings['telegram_member_push_enabled']), '사용', '', ' data-notification-member-toggle="telegram"'); ?>
                <small class="form-help">회원이 계정 알림 화면에서 자신의 Telegram 대화방을 연결할 수 있습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_channel_label">Telegram 채널 표시명 <span class="sr-required-label" data-notification-operational-required-label="telegram"<?php echo $telegramOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_channel_label" type="text" name="telegram_channel_label" value="<?php echo sr_e((string) $settings['telegram_channel_label']); ?>" maxlength="80" class="form-input form-control-full"<?php echo $telegramOperationalEnabled ? ' required' : ''; ?> data-notification-operational-required="telegram">
                <small class="form-help">운영 알림 발송 목록의 수신자 칸에 저장할 식별용 표시명입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_bot_token">Telegram 봇 토큰 <span class="sr-required-label" data-notification-telegram-token-required-label<?php echo $telegramTokenRequired ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_bot_token" type="password" name="telegram_bot_token" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['telegram_bot_token'])); ?>" class="form-input form-control-full" autocomplete="new-password"<?php echo $telegramTokenRequired && !$telegramTokenStored ? ' required' : ''; ?> data-notification-telegram-token data-notification-has-stored-secret="<?php echo $telegramTokenStored ? '1' : '0'; ?>">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_telegram_chat_id">Telegram 대화방 ID <span class="sr-required-label" data-notification-operational-required-label="telegram"<?php echo $telegramOperationalEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_telegram_chat_id" type="text" name="telegram_chat_id" value="<?php echo sr_e((string) $settings['telegram_chat_id']); ?>" maxlength="120" class="form-input form-control-full"<?php echo $telegramOperationalEnabled ? ' required' : ''; ?> data-notification-operational-required="telegram">
                <small class="form-help">사이트 운영 알림을 받을 곳이며 숫자 ID 또는 @channel 형식으로 입력합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('notification_admin_settings_external_push_failure_policy', '외부 알림 실패 처리', $notificationSettingsHelp['external_failure']['id'], $notificationSettingsHelpOpenLabel, true, true); ?>
            <div class="form-field">
                <select id="notification_admin_settings_external_push_failure_policy" name="external_push_failure_policy" class="form-select" required>
                    <option value="retry"<?php echo (string) $settings['external_push_failure_policy'] === 'retry' ? ' selected' : ''; ?>>재시도 후 실패 보관</option>
                    <option value="dead"<?php echo (string) $settings['external_push_failure_policy'] === 'dead' ? ' selected' : ''; ?>>즉시 실패 보관</option>
                </select>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/notifications')); ?>" class="btn btn-solid-light">알림 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($notificationSettingsHelp as $notificationSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $notificationSettingsHelpModal['id'], (string) $notificationSettingsHelpModal['title'], (string) $notificationSettingsHelpModal['body']); ?>
<?php } ?>

<div id="notification-smtp-test-email-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="notification-smtp-test-email-modal-title" aria-hidden="true" inert>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/notifications/settings')); ?>" class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="notification-smtp-test-email-modal-title" class="modal-title">테스트 메일 발송</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#notification-smtp-test-email-modal">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="test_email">
                <div class="form-row">
                    <label class="form-label" for="notification_smtp_test_email_recipient">수신 이메일 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="notification_smtp_test_email_recipient" type="email" name="test_email_recipient" maxlength="255" class="form-input form-control-full" autocomplete="email" required data-overlay-focus>
                        <small class="form-help">현재 저장된 SMTP 설정으로 발송합니다.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#notification-smtp-test-email-modal">취소</button>
                <button type="submit" class="btn btn-solid-primary modal-action">발송</button>
            </div>
        </form>
    </div>
</div>

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
    var externalPushEnabledInput = form.querySelector('[data-notification-external-push-enabled]');
    var operationalProviderKeys = ['slack', 'discord', 'telegram'];
    var telegramMemberInput = form.querySelector('[data-notification-member-toggle="telegram"]');
    var telegramTokenInput = form.querySelector('[data-notification-telegram-token]');
    var telegramTokenRequiredLabel = form.querySelector('[data-notification-telegram-token-required-label]');

    function setRequired(input, label, required) {
        if (input) {
            input.required = required;
        }
        if (label) {
            label.hidden = !required;
        }
    }

    function setRequiredGroup(selector, required, secretAware) {
        Array.prototype.slice.call(form.querySelectorAll(selector)).forEach(function (input) {
            var hasStoredSecret = input.getAttribute('data-notification-has-stored-secret') === '1';
            input.required = required && (!secretAware || !hasStoredSecret);
        });
    }

    function setLabelGroup(selector, required) {
        Array.prototype.slice.call(form.querySelectorAll(selector)).forEach(function (label) {
            label.hidden = !required;
        });
    }

    function syncOperationalRequiredState() {
        var externalEnabled = externalPushEnabledInput && externalPushEnabledInput.checked;

        operationalProviderKeys.forEach(function (providerKey) {
            var toggle = form.querySelector('[data-notification-operational-toggle="' + providerKey + '"]');
            var required = externalEnabled && toggle && toggle.checked;
            setLabelGroup('[data-notification-operational-required-label="' + providerKey + '"]', required);
            setRequiredGroup('[data-notification-operational-required="' + providerKey + '"]', required, false);
            setRequiredGroup('[data-notification-operational-secret="' + providerKey + '"]', required, true);
        });

        var telegramOperationalInput = form.querySelector('[data-notification-operational-toggle="telegram"]');
        var telegramTokenRequired = externalEnabled
            && ((telegramOperationalInput && telegramOperationalInput.checked) || (telegramMemberInput && telegramMemberInput.checked));
        setRequired(telegramTokenInput, telegramTokenRequiredLabel, telegramTokenRequired && telegramTokenInput && telegramTokenInput.getAttribute('data-notification-has-stored-secret') !== '1');
        if (telegramTokenRequiredLabel) {
            telegramTokenRequiredLabel.hidden = !telegramTokenRequired;
        }
    }

    function syncRequiredState() {
        var enabled = enabledInput && enabledInput.checked;
        var transport = transportInput ? transportInput.value : '';
        setRequired(fromInput, fromRequiredLabel, enabled && (transport === 'smtp' || transport === 'http_api'));
        setRequired(smtpHostInput, smtpHostRequiredLabel, enabled && transport === 'smtp');
        setRequired(httpApiEndpointInput, httpApiEndpointRequiredLabel, enabled && transport === 'http_api');
        syncOperationalRequiredState();
    }

    form.addEventListener('change', syncRequiredState);
    syncRequiredState();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
