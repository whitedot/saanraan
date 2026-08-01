#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

function sr_t(string $key): string
{
    return [
        'community::ui.text.919bd592' => '쪽지',
        'community::report.reason.spam' => '스팸',
        'community::report.reason.abuse' => '욕설·괴롭힘',
        'community::report.reason.personal_info' => '개인정보 노출',
        'community::report.reason.illegal' => '불법 정보',
        'community::report.reason.other' => '기타',
        'community::ui.text.162e66be' => '신고 사유',
        'community::ui.required.1f227c67' => '(필수)',
        'community::ui.text.c8a14bcd' => '상세 내용',
        'community::ui.text.bbb56c63' => '신고하기',
    ][$key] ?? $key;
}

function sr_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sr_url(string $path): string
{
    return $path;
}

function sr_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="fixture">';
}

function sr_public_layout_begin(mixed ...$arguments): void
{
}

function sr_public_layout_end(): void
{
}

function sr_message_account_label(?string $displayName, int $accountId, bool $canViewIdentifiers, array $config, ?string $status): string
{
    return $displayName !== null && $displayName !== '' ? $displayName : '회원';
}

function sr_message_time_html(string $value, string $empty = ''): string
{
    return $value !== '' ? sr_e($value) : sr_e($empty);
}

function sr_message_plain_text_html(string $value): string
{
    return nl2br(sr_e($value));
}

function sr_public_feedback_toasts(string $namespace, string $notice, array $errors): string
{
    return '<div data-feedback="' . sr_e($namespace) . '">' . sr_e($notice . implode(' ', $errors)) . '</div>';
}

require_once SR_ROOT . '/modules/community/public-report.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$context = sr_community_public_report_context('message', 17);
$formHtml = sr_community_public_report_form_html($context);
$assert(
    ($context['title'] ?? '') === '쪽지 신고'
        && str_contains($formHtml, 'action="/community/report"')
        && str_contains($formHtml, 'name="csrf_token"')
        && str_contains($formHtml, 'name="target_type" value="message"')
        && str_contains($formHtml, 'name="target_id" value="17"')
        && str_contains($formHtml, 'name="reason_key"'),
    'public report contract must render the provider-owned message report form.'
);
$assert(sr_community_public_report_context('message', 0) === [], 'public report context must reject an invalid target id.');

$_SESSION['sr_community_report_errors'] = ['검증 오류'];
$_SESSION['sr_community_report_notice'] = '접수 완료';
$feedback = sr_community_public_report_pop_feedback();
$secondFeedback = sr_community_public_report_pop_feedback();
$assert(
    $feedback === ['errors' => ['검증 오류'], 'notice' => '접수 완료']
        && $secondFeedback === ['errors' => [], 'notice' => ''],
    'public report feedback must be consumed exactly once after PRG.'
);

$pdo = null;
$site = null;
$config = [];
$message = [
    'id' => 17,
    'sender_account_id' => 2,
    'recipient_account_id' => 9,
    'sender_display_name' => '보낸 사람',
    'recipient_display_name' => '받는 사람',
    'sender_account_status' => 'active',
    'recipient_account_status' => 'active',
    'created_at' => '2026-08-01 10:00:00',
    'read_at' => '',
    'body_text' => 'fixture message',
];
$messageBox = 'inbox';
$canViewMemberIdentifiers = false;
$replyAccountHash = '';
$messageReportContext = $context;
$messageReportFeedback = $feedback;

$messageReportAvailable = true;
ob_start();
include SR_ROOT . '/modules/message/views/message-view.php';
$availableHtml = (string) ob_get_clean();
$assert(
    str_contains($availableHtml, '쪽지 신고')
        && str_contains($availableHtml, 'data-feedback="message-report"')
        && str_contains($availableHtml, 'action="/community/report"'),
    'message view must render provider feedback and form when the optional contract is available.'
);

$messageReportAvailable = false;
ob_start();
include SR_ROOT . '/modules/message/views/message-view.php';
$unavailableHtml = (string) ob_get_clean();
$assert(
    !str_contains($unavailableHtml, '쪽지 신고')
        && !str_contains($unavailableHtml, 'data-feedback="message-report"')
        && !str_contains($unavailableHtml, 'action="/community/report"'),
    'message view must omit the report surface when the optional contract is unavailable.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public report render checks completed.\n");
