<?php

declare(strict_types=1);

function sr_community_privacy_consent_setting_keys(): array
{
    return [
        'privacy_consent_enabled',
        'privacy_consent_title',
        'privacy_consent_body',
        'privacy_consent_version',
        'privacy_consent_require_post',
        'privacy_consent_require_comment',
        'privacy_consent_require_attachment_upload',
    ];
}

function sr_community_privacy_consent_target_keys(): array
{
    return ['post', 'comment', 'attachment_upload'];
}

function sr_community_privacy_consent_label(string $targetKey): string
{
    $labels = [
        'post' => '게시글 작성/수정',
        'comment' => '댓글 작성',
        'attachment_upload' => '첨부 업로드',
    ];

    return (string) ($labels[$targetKey] ?? $targetKey);
}

function sr_community_bool_from_setting(string $value): bool
{
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_privacy_consent_config(PDO $pdo, array $board): array
{
    $title = trim(sr_community_effective_board_setting($pdo, $board, 'privacy_consent_title', '개인정보 수집 및 이용동의'));
    $body = trim(sr_community_effective_board_setting($pdo, $board, 'privacy_consent_body', ''));
    $version = trim(sr_community_effective_board_setting($pdo, $board, 'privacy_consent_version', '1'));
    $targets = [];
    foreach (sr_community_privacy_consent_target_keys() as $targetKey) {
        $settingKey = 'privacy_consent_require_' . $targetKey;
        if (sr_community_bool_from_setting(sr_community_effective_board_setting($pdo, $board, $settingKey, '0'))) {
            $targets[] = $targetKey;
        }
    }

    return [
        'enabled' => sr_community_bool_from_setting(sr_community_effective_board_setting($pdo, $board, 'privacy_consent_enabled', '0')),
        'title' => $title !== '' ? $title : '개인정보 수집 및 이용동의',
        'body' => $body,
        'version' => $version !== '' ? $version : '1',
        'targets' => $targets,
    ];
}

function sr_community_privacy_consent_required_for(PDO $pdo, array $board, string $targetKey): bool
{
    $config = sr_community_effective_privacy_consent_config($pdo, $board);

    return !empty($config['enabled']) && in_array($targetKey, (array) ($config['targets'] ?? []), true);
}

function sr_community_privacy_consent_required_actions(PDO $pdo, array $board, array $targetKeys): array
{
    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    if (empty($config['enabled'])) {
        return [];
    }

    $actions = [];
    foreach ($targetKeys as $targetKey) {
        $targetKey = (string) $targetKey;
        if (in_array($targetKey, (array) ($config['targets'] ?? []), true)) {
            $actions[] = $targetKey;
        }
    }

    return array_values(array_unique($actions));
}

function sr_community_privacy_consent_accepted_from_post(): bool
{
    return (string) ($_POST['community_privacy_consent_accepted'] ?? '') === '1';
}

function sr_community_uploaded_file_present(mixed $file): bool
{
    if (!is_array($file)) {
        return false;
    }

    $errors = $file['error'] ?? null;
    if (is_array($errors)) {
        foreach ($errors as $error) {
            if ((int) $error !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        return false;
    }

    return isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function sr_community_privacy_consent_body_upload_targets_from_values(array $values): array
{
    if ((string) ($values['body_format'] ?? 'plain') !== 'html') {
        return [];
    }

    $bodyText = (string) ($values['body_text'] ?? '');
    foreach (sr_community_body_file_refs_from_html($bodyText) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'tmp') {
            return ['attachment_upload'];
        }
    }

    return [];
}

function sr_community_privacy_consent_post_targets_from_request(array $values = []): array
{
    $targets = ['post'];
    if (sr_community_uploaded_file_present($_FILES['image_attachment'] ?? null)
        || sr_community_uploaded_file_present($_FILES['file_attachments'] ?? null)) {
        $targets[] = 'attachment_upload';
    }

    return array_values(array_unique(array_merge($targets, sr_community_privacy_consent_body_upload_targets_from_values($values))));
}

function sr_community_privacy_consent_validation_errors(PDO $pdo, array $board, array $targetKeys): array
{
    $actions = sr_community_privacy_consent_required_actions($pdo, $board, $targetKeys);
    if ($actions !== [] && !sr_community_submission_consents_table_exists($pdo)) {
        return ['개인정보 수집 및 이용동의 스키마 업데이트가 아직 적용되지 않았습니다.'];
    }
    if ($actions === [] || sr_community_privacy_consent_accepted_from_post()) {
        return [];
    }

    return ['개인정보 수집 및 이용동의에 동의해 주세요.'];
}

function sr_community_privacy_consent_field_html(PDO $pdo, array $board, array $targetKeys, bool $browserRequired = true, string $idSuffix = ''): string
{
    $actions = sr_community_privacy_consent_required_actions($pdo, $board, $targetKeys);
    if ($actions === []) {
        return '';
    }

    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    $labels = array_map('sr_community_privacy_consent_label', $actions);
    $suffix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $idSuffix) ?? '';
    $id = 'modules_community_privacy_consent_' . substr(hash('sha256', implode('|', $actions)), 0, 12) . ($suffix !== '' ? '_' . $suffix : '');
    $html = '<fieldset class="community-privacy-consent">';
    $html .= '<legend>' . sr_e((string) $config['title']) . ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span></legend>';
    if ((string) $config['body'] !== '') {
        $html .= '<div class="community-privacy-consent-body">' . nl2br(sr_e((string) $config['body'])) . '</div>';
    }
    $html .= '<p><small>' . sr_e('적용 대상: ' . implode(', ', $labels) . ' / 버전: ' . (string) $config['version']) . '</small></p>';
    $html .= '<label for="' . sr_e($id) . '">';
    $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="community_privacy_consent_accepted" value="1"' . ($browserRequired ? ' required' : '') . '>';
    $html .= ' ' . sr_e('위 개인정보 수집 및 이용에 동의합니다.');
    $html .= '</label>';
    $html .= '</fieldset>';

    return $html;
}

function sr_community_record_submission_consents(PDO $pdo, int $boardId, int $accountId, string $subjectType, int $subjectId, array $actionKeys, array $board): int
{
    if ($boardId < 1 || $accountId < 1 || $subjectType === '' || $subjectId < 1 || $actionKeys === []) {
        return 0;
    }
    if (!sr_community_submission_consents_table_exists($pdo)) {
        return 0;
    }

    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    if (empty($config['enabled'])) {
        return 0;
    }
    $requiredActionKeys = sr_community_privacy_consent_required_actions($pdo, $board, $actionKeys);
    if ($requiredActionKeys === []) {
        return 0;
    }

    $inserted = 0;
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_submission_consents
            (board_id, subject_type, subject_id, action_key, account_id, consent_title_snapshot, consent_body_snapshot, consent_version_snapshot, consent_required, consent_accepted, ip_hash, user_agent_hash, created_at)
         VALUES
            (:board_id, :subject_type, :subject_id, :action_key, :account_id, :consent_title_snapshot, :consent_body_snapshot, :consent_version_snapshot, :consent_required, :consent_accepted, :ip_hash, :user_agent_hash, :created_at)'
    );
    foreach ($requiredActionKeys as $actionKey) {
        if (!in_array($actionKey, sr_community_privacy_consent_target_keys(), true)) {
            continue;
        }
        $stmt->execute([
            'board_id' => $boardId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action_key' => $actionKey,
            'account_id' => $accountId,
            'consent_title_snapshot' => (string) $config['title'],
            'consent_body_snapshot' => (string) $config['body'],
            'consent_version_snapshot' => (string) $config['version'],
            'consent_required' => 1,
            'consent_accepted' => 1,
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'created_at' => $now,
        ]);
        $inserted += $stmt->rowCount() > 0 ? 1 : 0;
    }

    return $inserted;
}

function sr_community_submission_consents_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_submission_consents LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}
