#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

$mustContain = static function (string $file, array $markers) use (&$errors): void {
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, (string) $marker)) {
            $errors[] = $file . ' is missing marker: ' . (string) $marker;
        }
    }
};

$mustContain('modules/community/helpers/privacy-consents.php', [
    'sr_community_privacy_consent_setting_keys',
    'privacy_consent_require_attachment_upload',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
    'sr_community_submission_consents_table_exists',
    '$requiredActionKeys = sr_community_privacy_consent_required_actions($pdo, $board, $actionKeys)',
    'foreach ($requiredActionKeys as $actionKey)',
    '$idSuffix',
]);
$mustContain('modules/community/helpers/boards.php', [
    'privacy_consent_enabled',
    'privacy_consent_require_post',
    'privacy_consent_require_comment',
    'privacy_consent_require_attachment_upload',
]);
$mustContain('modules/community/actions/admin-boards.php', [
    'privacy_consent_enabled',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
    'privacy_consent_require_attachment_upload',
]);
$mustContain('modules/community/views/admin-boards.php', [
    'community-board-section-privacy-consent',
    'privacy_consent_title',
    'sr_community_privacy_consent_target_keys',
]);
$mustContain('modules/community/actions/write.php', [
    'sr_community_privacy_consent_post_targets_from_request',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/actions/edit.php', [
    "['post']",
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/actions/comment.php', [
    "['comment']",
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/skins/basic/form.php', [
    'sr_community_privacy_consent_field_html',
    'attachment_upload',
]);
$mustContain('modules/community/skins/basic/view.php', [
    'sr_community_privacy_consent_field_html',
    'comment_reply_',
    'comment_new',
]);
$mustContain('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_submission_consents',
    'consent_body_snapshot',
    'user_agent_hash',
]);
$mustContain('modules/community/updates/2026.06.019.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_submission_consents',
    "version = '2026.06.019'",
]);
$mustContain('modules/community/privacy-export.php', [
    'submission_consents',
    'sr_community_submission_consents',
    'consent_version_snapshot',
    'ip_hash',
    'user_agent_hash',
]);
$mustContain('modules/community/privacy-cleanup.php', [
    'sr_community_submission_consents',
    'community_submission_consent_anonymized_count',
]);

if ($errors !== []) {
    fwrite(STDERR, "community privacy consent checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community privacy consent checks completed.\n";
