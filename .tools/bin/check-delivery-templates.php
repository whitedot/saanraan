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
sr_delivery_template_check_assert(isset($contracts['point.transaction.grant']), 'delivery template contracts must expose legacy notification event rows until explicit module contracts replace them.');
sr_delivery_template_check_assert((string) ($contracts['point.transaction.grant']['link_template'] ?? '') === '/point/history', 'legacy notification event contracts must use link_template schema field.');
sr_delivery_template_check_assert(in_array('email', (array) ($contracts['point.transaction.grant']['channels'] ?? []), true), 'legacy notification event contracts must read channels_json schema field.');
sr_delivery_template_check_assert(in_array('slack_webhook', (array) ($contracts['point.transaction.grant']['available_channels'] ?? []), true), 'notification event template options must include current member external channels.');
sr_delivery_template_check_assert(in_array('discord_webhook', (array) ($contracts['point.transaction.grant']['available_channels'] ?? []), true), 'notification event template options must include Discord as an external channel.');
sr_delivery_template_check_assert(in_array('telegram_bot', (array) ($contracts['point.transaction.grant']['available_channels'] ?? []), true), 'notification event template options must include Telegram as an external channel.');
sr_delivery_template_check_assert(sr_delivery_template_variable_label('member_name') === '회원 이름', 'delivery template variable helper text must be operator-readable.');
sr_delivery_template_check_assert(sr_delivery_template_variable_label('link_url') === '연결 주소', 'delivery template link_url variable helper text must be operator-readable.');
sr_delivery_template_check_assert(sr_delivery_template_body_template_for_editor("본문\n\n/point/history", '/point/history') === "본문\n\n/point/history", 'admin editor body must not duplicate a link template that is already in the body.');
sr_delivery_template_check_assert(sr_delivery_template_body_template_for_editor('<code>본문</code>', '/point/history') === "본문\n\n/point/history", 'admin editor body must unwrap a code wrapper and append the link template into editable content.');
$pointRendered = sr_delivery_template_render($pdo, 'point.transaction.grant', ['amount' => '100']);
sr_delivery_template_check_assert(str_contains((string) ($pointRendered['body'] ?? ''), '/point/history'), 'notification event render must treat link_template as editable body content.');

$notificationInstallSql = (string) file_get_contents($root . '/modules/notification/install.sql');
foreach (sr_delivery_template_check_expected_notification_events() as $moduleKey => $eventKeys) {
    foreach ($eventKeys as $eventKey) {
        $templateKey = $moduleKey . '.' . $eventKey;
        sr_delivery_template_check_assert(isset($contracts[$templateKey]), 'delivery template list must include notification event template: ' . $templateKey);
        sr_delivery_template_check_assert(
            str_contains($notificationInstallSql, "('" . $moduleKey . "', '" . $eventKey . "'"),
            'notification install.sql must seed notification event template: ' . $templateKey
        );
    }
}

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
    ['email'],
    'active',
    9
);
$rendered = sr_delivery_template_render($pdo, 'member.email_verification', ['verification_url' => 'https://example.test/verify']);
sr_delivery_template_check_assert((string) ($rendered['subject'] ?? '') === '커스텀 인증', 'active override must replace transactional email subject.');
sr_delivery_template_check_assert((string) ($rendered['source'] ?? '') === 'override', 'active valid override must report override source.');
sr_delivery_template_check_assert(str_contains((string) ($rendered['body'] ?? ''), 'https://example.test/verify'), 'active override must render metadata placeholders.');

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
