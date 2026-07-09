#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/common.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/delivery-templates.php';
require_once $root . '/modules/notification/helpers.php';
require_once $root . '/modules/notification/helpers/event-template-admin.php';

if (!function_exists('sr_e')) {
    function sr_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$errors = [];

function sr_delivery_template_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_delivery_template_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_delivery_template_check_error($message);
    }
}

function sr_delivery_template_check_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'enabled\'
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_modules (module_key, status) VALUES
            ('admin', 'enabled'),
            ('member', 'enabled'),
            ('policy_documents', 'disabled'),
            ('notification', 'enabled'),
            ('point', 'disabled')"
    );
    $pdo->exec(
        'CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NULL,
            value_type TEXT NOT NULL DEFAULT \'string\'
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type)
         VALUES (4, 'email_channel_enabled', '0', 'bool')"
    );
    $pdo->exec(
        'CREATE TABLE sr_delivery_template_overrides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            template_key TEXT NOT NULL,
            owner_module TEXT NOT NULL,
            category TEXT NOT NULL,
            subject_template TEXT NOT NULL DEFAULT \'\',
            body_template TEXT NULL,
            link_template TEXT NOT NULL DEFAULT \'\',
            channels_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            updated_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(template_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_event_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            event_key TEXT NOT NULL,
            title_template TEXT NOT NULL,
            body_template TEXT NULL,
            link_template TEXT NOT NULL DEFAULT \'\',
            channels_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(module_key, event_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_channel_template_bindings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            event_key TEXT NOT NULL,
            channel TEXT NOT NULL,
            provider_template_code TEXT NOT NULL DEFAULT \'\',
            variables_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(module_key, event_key, channel)
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_notification_event_templates
            (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
         VALUES
            ('point', 'transaction.grant', '포인트 지급: {amount}', '{amount} 포인트가 지급되었습니다.', '/point/history', '[\"site\",\"email\"]', 'active', '2026-07-09 00:00:00', '2026-07-09 00:00:00'),
            ('point', 'transaction.hidden', '숨김', '본문', '', '[\"site\"]', 'inactive', '2026-07-09 00:00:00', '2026-07-09 00:00:00')"
    );

    return $pdo;
}

function sr_delivery_template_check_expected_notification_events(): array
{
    return [
        'point' => [
            'transaction.grant',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.expire',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
        'reward' => [
            'transaction.grant',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.expire',
            'transaction.withdraw',
            'transaction.reclaim',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
        'deposit' => [
            'transaction.deposit',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.withdraw',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
        'coupon' => [
            'issue.created',
            'redemption.redeemed',
            'redemption.refunded',
            'issue.refunded',
            'issue.status_updated',
            'issue.definition_disabled',
        ],
        'member' => [
            'security.email_verified',
            'security.password_changed',
            'security.password_reset_completed',
            'security.mfa_enabled',
            'security.mfa_recovery_rotated',
            'security.mfa_disabled',
            'security.oauth_linked',
            'security.oauth_unlinked',
        ],
        'content' => [
            'comment.created',
            'comment.mention',
            'followed_author.content_created',
        ],
        'community' => [
            'comment.created',
            'comment.mention',
            'followed_author.post_created',
            'attachment.publisher_reward.granted',
        ],
        'quiz' => [
            'comment.mention',
        ],
        'survey' => [
            'comment.mention',
        ],
        'reaction' => [
            'target.reacted',
        ],
        'notification' => [
            'member_push_endpoint.connected',
            'member_push_endpoint.disabled',
        ],
    ];
}

function sr_delivery_template_check_seed_notification_events(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "INSERT OR IGNORE INTO sr_notification_event_templates
            (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
         VALUES
            (:module_key, :event_key, :title_template, :body_template, :link_template, :channels_json, 'active', '2026-07-09 00:00:00', '2026-07-09 00:00:00')"
    );
    foreach (sr_delivery_template_check_expected_notification_events() as $moduleKey => $eventKeys) {
        foreach ($eventKeys as $eventKey) {
            $stmt->execute([
                'module_key' => $moduleKey,
                'event_key' => $eventKey,
                'title_template' => $moduleKey . ' ' . $eventKey . ' 제목',
                'body_template' => $moduleKey . ' ' . $eventKey . ' 본문',
                'link_template' => '',
                'channels_json' => '["site"]',
            ]);
        }
    }
}

$pdo = sr_delivery_template_check_pdo();
sr_delivery_template_check_seed_notification_events($pdo);
$contracts = sr_delivery_template_contracts($pdo);

sr_delivery_template_check_assert(isset($contracts['member.email_verification']), 'delivery template contracts must include the shared member.email_verification key.');
sr_delivery_template_check_assert(isset($contracts['member.password_reset']), 'delivery template contracts must include member.password_reset.');
sr_delivery_template_check_assert(isset($contracts['member.login_mfa_email_code']), 'delivery template contracts must include member.login_mfa_email_code.');
sr_delivery_template_check_assert(isset($contracts['policy_documents.version_notice']), 'delivery template contracts must collect installed-but-disabled policy_documents contracts.');
sr_delivery_template_check_assert(($contracts['policy_documents.version_notice']['module_enabled'] ?? true) === false, 'delivery template contracts must mark disabled installed modules without hiding them.');
sr_delivery_template_check_assert(!isset($contracts['point.transaction.grant']), 'delivery template contracts must not collect module notification event rows for a central editor.');
sr_delivery_template_check_assert(sr_delivery_template_variable_label('member_name') === '회원 이름', 'delivery template variable helper text must be operator-readable.');
sr_delivery_template_check_assert(sr_delivery_template_variable_label('link_url') === '연결 주소', 'delivery template link_url variable helper text must be operator-readable.');
sr_delivery_template_check_assert(sr_delivery_template_body_template_for_editor("본문\n\n/point/history", '/point/history') === "본문\n\n/point/history", 'admin editor body must not duplicate a link template that is already in the body.');
sr_delivery_template_check_assert(sr_delivery_template_body_template_for_editor('<code>본문</code>', '/point/history') === "본문\n\n/point/history", 'admin editor body must unwrap a code wrapper and append the link template into editable content.');
$eventAvailableChannels = sr_delivery_template_notification_event_available_channels(['site']);
sr_delivery_template_check_assert(in_array('email', $eventAvailableChannels, true), 'notification event templates must allow adding email delivery when the default channel is not email.');
$eventAdminChannels = sr_notification_event_template_admin_channels($pdo);
sr_delivery_template_check_assert(in_array('email', $eventAdminChannels, true), 'notification/mail admin event rows must show email even when the generic notification email setting is disabled.');
sr_delivery_template_check_assert(sr_notification_channel_template_code('KA01_hello-1.test') === 'KA01_hello-1.test', 'Alimtalk template code helper must allow Kakao provider template codes.');
sr_delivery_template_check_assert(sr_notification_channel_template_code('알림톡본문') === '', 'Alimtalk template code helper must reject editable Korean message bodies.');

sr_notification_event_template_admin_save_channel_binding($pdo, 'point', 'transaction.grant', 'alimtalk', 'KA01_POINT_GRANT');
$pointChannelBindings = sr_notification_event_template_admin_channel_bindings($pdo, 'point');
sr_delivery_template_check_assert(
    (string) ($pointChannelBindings['transaction.grant']['alimtalk']['provider_template_code'] ?? '') === 'KA01_POINT_GRANT',
    'notification/mail admin must store Alimtalk provider template code separately from editable title/body.'
);
sr_notification_event_template_admin_save($pdo, 'point', 'transaction.grant', [
    'title_template' => '포인트 지급: {amount}',
    'body_template' => '{amount} 포인트가 지급되었습니다.',
    'channels' => ['site', 'slack_webhook'],
    'enabled' => true,
]);
$pointGrantTemplate = sr_notification_event_template($pdo, 'point', 'transaction.grant');
sr_delivery_template_check_assert(
    in_array('slack_webhook', sr_notification_template_channels((string) ($pointGrantTemplate['channels_json'] ?? '')), true),
    'notification/mail admin save must preserve selected member external channels even when member external delivery is currently disabled.'
);

$memberDeliveryRows = sr_notification_event_template_admin_delivery_rows($pdo, 'member', [
    'member.email_verification',
    'member.password_reset',
    'member.login_mfa_email_code',
]);
$memberDeliveryRowKeys = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['template_key'] ?? ''), $memberDeliveryRows), true);
foreach (['member.email_verification', 'member.password_reset', 'member.login_mfa_email_code'] as $templateKey) {
    sr_delivery_template_check_assert(isset($memberDeliveryRowKeys[$templateKey]), 'member notification/mail admin rows must include delivery template: ' . $templateKey);
}
foreach ($memberDeliveryRows as $memberDeliveryRow) {
    sr_delivery_template_check_assert((array) ($memberDeliveryRow['available_channels'] ?? []) === ['email'], 'member transactional mail rows must not expose site or push channels: ' . (string) ($memberDeliveryRow['template_key'] ?? ''));
}

$notificationTemplateAdminActions = [
    'community' => 'modules/community/actions/admin-notification-templates.php',
    'content' => 'modules/content/actions/admin-notification-templates.php',
    'coupon' => 'modules/coupon/actions/admin-notification-templates.php',
    'deposit' => 'modules/deposit/actions/admin-notification-templates.php',
    'member' => 'modules/member/actions/admin-notification-templates.php',
    'message' => 'modules/message/actions/admin-notification-templates.php',
    'point' => 'modules/point/actions/admin-notification-templates.php',
    'quiz' => 'modules/quiz/actions/admin-notification-templates.php',
    'reaction' => 'modules/reaction/actions/admin-notification-templates.php',
    'reward' => 'modules/reward/actions/admin-notification-templates.php',
    'survey' => 'modules/survey/actions/admin-notification-templates.php',
];
foreach ($notificationTemplateAdminActions as $moduleKey => $actionPath) {
    $actionSource = is_file($root . '/' . $actionPath) ? file_get_contents($root . '/' . $actionPath) : false;
    sr_delivery_template_check_assert(is_string($actionSource), 'notification/mail admin action must exist: ' . $moduleKey);
    if (!is_string($actionSource)) {
        continue;
    }
    sr_delivery_template_check_assert(str_contains($actionSource, "sr_module_enabled(\$pdo, 'notification')"), 'notification/mail admin action must guard missing notification module: ' . $moduleKey);
    sr_delivery_template_check_assert(str_contains($actionSource, 'is_file($notificationTemplateAdminHelper)'), 'notification/mail admin action must guard missing notification helper file: ' . $moduleKey);
}

$notificationInstallSql = (string) file_get_contents($root . '/modules/notification/install.sql');
sr_delivery_template_check_assert(str_contains($notificationInstallSql, 'sr_notification_channel_template_bindings'), 'notification install.sql must include channel template binding table.');
foreach (sr_delivery_template_check_expected_notification_events() as $moduleKey => $eventKeys) {
    foreach ($eventKeys as $eventKey) {
        sr_delivery_template_check_assert(
            str_contains($notificationInstallSql, "('" . $moduleKey . "', '" . $eventKey . "'"),
            'notification install.sql must seed notification event template: ' . $moduleKey . '.' . $eventKey
        );
    }
}
$notificationEventTemplateView = (string) file_get_contents($root . '/modules/notification/views/admin-event-templates.php');
sr_delivery_template_check_assert(
    !str_contains($notificationEventTemplateView, '<small><?php echo sr_e($label); ?></small>'),
    'notification/mail admin list title cell must not append the event label next to the editable title.'
);
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '$showChannelSelector = !($rowType === \'delivery\' && $rowChannelOptions === [\'email\']);'), 'member notification/mail admin must hide channel selector for email-only transactional mail rows.');
sr_delivery_template_check_assert(!str_contains($notificationEventTemplateView, '상태/적용값'), 'member notification/mail admin status must mean enabled or disabled, not override application.');
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '$hasUnavailableEmailChannel'), 'notification/mail admin status cell must distinguish disabled email channel from active item status.');
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '이메일 중지'), 'notification/mail admin list must mark selected email channel disabled when the email channel is off.');
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '외부채널 중지'), 'notification/mail admin list must mark selected member external channels disabled when member delivery is off.');
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '상태 설명') && str_contains($notificationEventTemplateView, 'email_disabled') && str_contains($notificationEventTemplateView, 'member_external_disabled'), 'notification/mail admin list must include status descriptions for item status and disabled delivery channels.');
sr_delivery_template_check_assert(str_contains($notificationEventTemplateView, '채널 중지'), 'notification/mail admin editor must make disabled email channel state visible in channel choices.');
$deliveryTemplateView = (string) file_get_contents($root . '/modules/notification/views/admin-delivery-templates.php');
sr_delivery_template_check_assert(str_contains($deliveryTemplateView, '$showChannelSelector = $availableChannels !== [\'email\'];'), 'central delivery template admin must hide channel selector for email-only transactional mail rows.');
sr_delivery_template_check_assert(str_contains($deliveryTemplateView, "sr_admin_sort_header_html('수정값'") && str_contains($deliveryTemplateView, "sr_admin_sort_header_html('상태'"), 'central delivery template admin must label override storage separately from enabled status.');
$memberNotificationTemplateAction = (string) file_get_contents($root . '/modules/member/actions/admin-notification-templates.php');
sr_delivery_template_check_assert(str_contains($memberNotificationTemplateAction, "'title' => '회원 알림/메일 관리'"), 'member notification/mail admin page title must not be limited to security wording.');

$emailContract = $contracts['member.email_verification'];
try {
    sr_delivery_template_save_override($pdo, $emailContract, '커스텀 인증', '본문만 있습니다.', '', ['email'], 'active', 9);
    sr_delivery_template_check_error('delivery template override save must reject missing required placeholders.');
} catch (InvalidArgumentException) {
}

sr_delivery_template_save_override(
    $pdo,
    $emailContract,
    '커스텀 인증',
    "아래 링크를 여세요.\n{verification_url}",
    '',
    ['site', 'email', 'telegram_bot'],
    'active',
    9
);
$emailEffective = sr_delivery_template_effective($pdo, 'member.email_verification');
sr_delivery_template_check_assert((array) ($emailEffective['channels'] ?? []) === ['email'], 'transactional email overrides must remain email-only even if site or push channels are posted.');
$rendered = sr_delivery_template_render($pdo, 'member.email_verification', ['verification_url' => 'https://example.test/verify']);
sr_delivery_template_check_assert((string) ($rendered['subject'] ?? '') === '커스텀 인증', 'active override must replace transactional email subject.');
sr_delivery_template_check_assert((string) ($rendered['source'] ?? '') === 'override', 'active valid override must report override source.');
sr_delivery_template_check_assert(str_contains((string) ($rendered['body'] ?? ''), 'https://example.test/verify'), 'active override must render metadata placeholders.');

sr_delivery_template_save_override(
    $pdo,
    $emailContract,
    '비활성 커스텀 인증',
    "비활성 본문\n{verification_url}",
    '',
    ['email'],
    'inactive',
    9
);
$inactiveRendered = sr_delivery_template_render($pdo, 'member.email_verification', ['verification_url' => 'https://example.test/inactive']);
sr_delivery_template_check_assert((string) ($inactiveRendered['source'] ?? '') === 'override', 'inactive valid override must still be the effective template source.');
sr_delivery_template_check_assert((string) ($inactiveRendered['status'] ?? '') === 'inactive', 'inactive delivery template override must keep disabled status.');
sr_delivery_template_check_assert(sr_delivery_template_send_mail($pdo, [], 'member.email_verification', 'member@example.test', ['verification_url' => 'https://example.test/inactive']) === false, 'inactive delivery template must not send mail.');

$pdo->exec("UPDATE sr_delivery_template_overrides SET body_template = '직접 DB 수정으로 필수 변수 제거' WHERE template_key = 'member.email_verification'");
$renderedFallback = sr_delivery_template_render($pdo, 'member.email_verification', ['verification_url' => 'https://example.test/fallback']);
sr_delivery_template_check_assert((string) ($renderedFallback['source'] ?? '') === 'default', 'runtime must fall back to contract default when an override loses required placeholders.');
sr_delivery_template_check_assert((string) ($renderedFallback['subject'] ?? '') === '이메일 인증 안내', 'runtime fallback must use the module contract default subject.');

sr_delivery_template_delete_override($pdo, 'member.email_verification');
$restored = sr_delivery_template_effective($pdo, 'member.email_verification');
sr_delivery_template_check_assert(is_array($restored) && empty($restored['has_override']), 'restore default must delete the explicit override row.');

$policyRendered = sr_delivery_template_render($pdo, 'policy_documents.version_notice', [
    'site_name' => '산란',
    'document_title' => '개인정보 처리방침',
    'effective_date' => '2026-07-09',
]);
sr_delivery_template_check_assert((string) ($policyRendered['subject'] ?? '') === '정책 문서 변경 안내', 'policy document notice must render a subject-only contract.');
sr_delivery_template_check_assert((string) ($policyRendered['body'] ?? '') === '', 'policy document notice body must stay with the module builder in the first release.');

$escapedPreview = sr_e((string) sr_delivery_template_render($pdo, 'member.password_reset', [
    'reset_url' => '<script>alert(1)</script>',
])['body']);
sr_delivery_template_check_assert(str_contains($escapedPreview, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'admin preview output must be escaped before displaying rendered text.');

$notificationRow = [
    'title' => '저장된 제목',
    'source_module_key' => 'point',
    'event_key' => 'transaction.grant',
    'metadata_json' => json_encode(['amount' => '999'], JSON_UNESCAPED_UNICODE),
];
$pdo->exec("UPDATE sr_notification_event_templates SET title_template = '수정된 제목: {amount}' WHERE module_key = 'point' AND event_key = 'transaction.grant'");
sr_delivery_template_check_assert(sr_notification_title_from_row($pdo, $notificationRow) === '저장된 제목', 'notification display must keep the stored title snapshot instead of rerendering current templates.');

if ($errors !== []) {
    fwrite(STDERR, "delivery template checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "delivery template checks completed.\n";
