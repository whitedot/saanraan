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
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$notificationSettingsSectionNavItems = [
    'notification-settings-section-runner' => '기본환경',
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
        <h2>기본환경</h2>
        <p class="form-help">이 설정은 이메일뿐 아니라 외부 발송 대기열에 쌓이는 모든 발송 작업의 처리 정책에 적용됩니다.</p>
        <div class="form-row">
            <span class="form-label">웹 자동 실행</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_delivery_web_runner_enabled', 'delivery_web_runner_enabled', '1', !empty($settings['delivery_web_runner_enabled']), '사용', '', ''); ?>
                <small class="form-help">공유호스팅 기본 실행 방식입니다. 요청 응답 뒤 적은 수의 발송 작업만 처리합니다.</small>
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
            <label class="form-label" for="notification_admin_settings_delivery_web_runner_batch_size">웹 실행당 발송 작업 수 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_web_runner_batch_size" type="number" name="delivery_web_runner_batch_size" value="<?php echo sr_e((string) $settings['delivery_web_runner_batch_size']); ?>" min="1" max="5" class="form-input" required>
                <small class="form-help">한 번의 웹 요청 말미에서 처리할 최대 발송 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_manual_batch_size">수동 실행당 발송 작업 수 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_manual_batch_size" type="number" name="delivery_manual_batch_size" value="<?php echo sr_e((string) $settings['delivery_manual_batch_size']); ?>" min="1" max="50" class="form-input" required>
                <small class="form-help">관리자 발송 목록에서 수동 실행할 때 처리할 최대 발송 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_cli_batch_size">명령줄 실행당 발송 작업 수 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_cli_batch_size" type="number" name="delivery_cli_batch_size" value="<?php echo sr_e((string) $settings['delivery_cli_batch_size']); ?>" min="1" max="100" class="form-input" required>
                <small class="form-help">cron 또는 명령줄 수동 실행에서 처리할 최대 발송 작업 수입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_max_attempts">최대 재시도 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_max_attempts" type="number" name="delivery_max_attempts" value="<?php echo sr_e((string) $settings['delivery_max_attempts']); ?>" min="1" max="20" class="form-input" required>
                <small class="form-help">초과하면 실패 보관 상태로 전환합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_delivery_lock_timeout_seconds">처리 점유 만료 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_delivery_lock_timeout_seconds" type="number" name="delivery_lock_timeout_seconds" value="<?php echo sr_e((string) $settings['delivery_lock_timeout_seconds']); ?>" min="30" max="3600" class="form-input" required>
                <small class="form-help">처리 중인 작업이 이 시간을 넘기면 다음 실행에서 다시 이어받을 수 있습니다.</small>
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
            <label class="form-label" for="notification_admin_settings_email_transport">발송 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="notification_admin_settings_email_transport" name="email_transport" class="form-select" required data-notification-email-transport>
                    <?php foreach ($emailTransportOptions as $transportValue => $transportLabel) { ?>
                        <option value="<?php echo sr_e((string) $transportValue); ?>"<?php echo (string) $settings['email_transport'] === (string) $transportValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $transportLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <small class="form-help">이 값은 이메일 채널 발송에서 사용할 방식입니다.</small>
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
            <label class="form-label" for="notification_admin_settings_email_timeout_seconds">타임아웃 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="notification_admin_settings_email_timeout_seconds" type="number" name="email_timeout_seconds" value="<?php echo sr_e((string) $settings['email_timeout_seconds']); ?>" min="3" max="180" class="form-input" required>
                <small class="form-help">초 단위입니다. 3부터 180까지 입력합니다.</small>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_email_smtp_host">SMTP 호스트 <span class="sr-required-label" data-notification-smtp-host-required hidden>(필수)</span></label>
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
            <label class="form-label" for="notification_admin_settings_email_smtp_encryption">SMTP 암호화 <span class="sr-required-label">(필수)</span></label>
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
            <label class="form-label" for="notification_admin_settings_email_smtp_password">SMTP 비밀번호</label>
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
            <label class="form-label" for="notification_admin_settings_email_http_api_bearer_token">메일 API 인증 토큰</label>
            <div class="form-field">
                <input id="notification_admin_settings_email_http_api_bearer_token" type="password" name="email_http_api_bearer_token" value="" maxlength="255" placeholder="<?php echo sr_e(sr_notification_secret_display((string) $settings['email_http_api_bearer_token'])); ?>" class="form-input form-control-full" autocomplete="new-password">
                <small class="form-help">비워두면 기존 저장값을 유지합니다.</small>
            </div>
        </div>
    </section>

    <section id="notification-settings-section-external-push" class="card" data-admin-section-anchor>
        <h2>외부 알림 채널</h2>
        <div class="form-row">
            <span class="form-label">외부 알림 채널</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_external_push_enabled', 'external_push_enabled', '1', !empty($settings['external_push_enabled']), '사용', '', ' data-notification-external-push-enabled'); ?>
                <small class="form-help">끄면 운영 알림 발송과 회원 연결을 모두 사용하지 않습니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Slack 운영 알림 발송</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_slack_webhook_enabled', 'slack_webhook_enabled', '1', !empty($settings['slack_webhook_enabled']), '사용', '', ' data-notification-operational-toggle="slack"'); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Slack 회원 허용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_slack_member_push_enabled', 'slack_member_push_enabled', '1', !empty($settings['slack_member_push_enabled']), '사용', '', ''); ?>
                <small class="form-help">회원이 계정 알림 화면에서 개인 Slack 수신 URL을 연결할 수 있습니다.</small>
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
                <small class="form-help">운영 알림에 사용할 HTTPS 수신 URL입니다. 회원 개인 수신 URL은 회원 화면에서 별도로 암호화 저장합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Discord 운영 알림 발송</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_discord_webhook_enabled', 'discord_webhook_enabled', '1', !empty($settings['discord_webhook_enabled']), '사용', '', ' data-notification-operational-toggle="discord"'); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Discord 회원 허용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_discord_member_push_enabled', 'discord_member_push_enabled', '1', !empty($settings['discord_member_push_enabled']), '사용', '', ''); ?>
                <small class="form-help">회원이 계정 알림 화면에서 개인 Discord 수신 URL을 연결할 수 있습니다.</small>
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
                <small class="form-help">운영 알림에 사용할 HTTPS 수신 URL입니다. 회원 개인 수신 URL은 회원 화면에서 별도로 암호화 저장합니다.</small>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Telegram 운영 알림 발송</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_telegram_bot_enabled', 'telegram_bot_enabled', '1', !empty($settings['telegram_bot_enabled']), '사용', '', ' data-notification-operational-toggle="telegram"'); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">Telegram 회원 허용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('notification_admin_settings_telegram_member_push_enabled', 'telegram_member_push_enabled', '1', !empty($settings['telegram_member_push_enabled']), '사용', '', ' data-notification-member-toggle="telegram"'); ?>
                <small class="form-help">회원이 계정 알림 화면에서 개인 Telegram 대화방 ID를 연결할 수 있습니다.</small>
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
                <small class="form-help">운영 알림 수신처입니다. 회원 개인 Telegram 수신처는 회원 화면에서 따로 연결합니다. 숫자 ID 또는 @channel 형식입니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="notification_admin_settings_external_push_failure_policy">외부 푸시 실패 정책 <span class="sr-required-label">(필수)</span></label>
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
