<?php

declare(strict_types=1);

function sr_community_privacy_consent_setting_keys(): array
{
    return [
        'privacy_consent_enabled',
        'privacy_consent_document_key',
        'privacy_consent_post_document_key',
        'privacy_consent_comment_document_key',
        'privacy_consent_attachment_upload_document_key',
        'privacy_consent_document_inherit_policy',
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

function sr_community_privacy_consent_document_setting_key(string $targetKey): string
{
    return 'privacy_consent_' . $targetKey . '_document_key';
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

function sr_community_privacy_consent_admin_label(string $targetKey): string
{
    $labels = [
        'post' => '게시글 동의',
        'comment' => '댓글 동의',
        'attachment_upload' => '업로드 동의',
    ];

    return (string) ($labels[$targetKey] ?? sr_community_privacy_consent_label($targetKey));
}

function sr_community_privacy_consent_clean_document_key(string $documentKey): string
{
    $documentKey = strtolower(trim($documentKey));

    return preg_match('/\A[a-z][a-z0-9_]{2,79}\z/', $documentKey) === 1 ? $documentKey : '';
}

function sr_community_privacy_consent_admin_document_key_from_settings(array $settings, string $targetKey): string
{
    if (!in_array($targetKey, sr_community_privacy_consent_target_keys(), true)) {
        return '';
    }

    $documentSettingKey = sr_community_privacy_consent_document_setting_key($targetKey);
    $targetDocumentKey = sr_community_privacy_consent_clean_document_key((string) ($settings[$documentSettingKey] ?? ''));
    if ($targetDocumentKey !== '') {
        return $targetDocumentKey;
    }

    $requiredValue = $settings['privacy_consent_require_' . $targetKey] ?? false;
    $required = is_bool($requiredValue)
        ? $requiredValue
        : sr_community_bool_from_setting((string) $requiredValue);
    if (!$required) {
        return '';
    }

    return sr_community_privacy_consent_clean_document_key((string) ($settings['privacy_consent_document_key'] ?? ''));
}

function sr_community_privacy_consent_policy_document_options(PDO $pdo, string $currentKey = ''): array
{
    static $baseOptions = null;
    $currentKey = sr_community_privacy_consent_clean_document_key($currentKey);

    if (is_array($baseOptions)) {
        $options = $baseOptions;
        if ($currentKey !== '' && !isset($options[$currentKey])) {
            $options[$currentKey] = [
                'title' => $currentKey,
            ];
        }

        return $options;
    }

    if (!sr_module_enabled($pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php')) {
        return $currentKey !== '' ? [$currentKey => ['title' => $currentKey]] : [];
    }

    require_once SR_ROOT . '/modules/policy_documents/helpers.php';
    $options = [];
    if (
        function_exists('sr_policy_document_enabled_choices')
        && function_exists('sr_policy_document_module_ready')
        && sr_policy_document_module_ready($pdo)
    ) {
        foreach (sr_policy_document_enabled_choices($pdo) as $policyDocumentChoice) {
            if ((int) ($policyDocumentChoice['published_version_id'] ?? 0) < 1) {
                continue;
            }

            $policyDocumentKey = (string) ($policyDocumentChoice['document_key'] ?? '');
            if ($policyDocumentKey === '') {
                continue;
            }

            $options[$policyDocumentKey] = [
                'title' => (string) ($policyDocumentChoice['title'] ?? $policyDocumentKey),
            ];
        }
    }
    $baseOptions = $options;

    if ($currentKey !== '' && !isset($options[$currentKey])) {
        $options[$currentKey] = [
            'title' => $currentKey,
        ];
    }

    return $options;
}

function sr_community_privacy_consent_policy_documents_available(PDO $pdo): bool
{
    if (!sr_module_enabled($pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/policy_documents/helpers.php';
    if (!function_exists('sr_policy_document_enabled_choices') || !function_exists('sr_policy_document_module_ready')) {
        return false;
    }

    try {
        if (!sr_policy_document_module_ready($pdo)) {
            return false;
        }

        foreach (sr_policy_document_enabled_choices($pdo) as $policyDocumentChoice) {
            if ((int) ($policyDocumentChoice['published_version_id'] ?? 0) > 0) {
                return true;
            }
        }
    } catch (Throwable) {
        return false;
    }

    return false;
}

function sr_community_bool_from_setting(string $value): bool
{
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sr_community_privacy_consent_policy_document_key(PDO $pdo, array $board, string $targetKey = ''): string
{
    if ($targetKey !== '' && in_array($targetKey, sr_community_privacy_consent_target_keys(), true)) {
        $targetDocumentKey = sr_community_privacy_consent_clean_document_key(
            sr_community_effective_board_setting($pdo, $board, sr_community_privacy_consent_document_setting_key($targetKey), '')
        );
        if ($targetDocumentKey !== '') {
            return $targetDocumentKey;
        }
    }

    return sr_community_privacy_consent_clean_document_key(
        sr_community_effective_board_setting($pdo, $board, 'privacy_consent_document_key', 'community_privacy_default')
    );
}

function sr_community_privacy_consent_policy_snapshot(PDO $pdo, string $documentKey): ?array
{
    if ($documentKey === '' || !sr_module_enabled($pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php')) {
        return null;
    }

    require_once SR_ROOT . '/modules/policy_documents/helpers.php';
    try {
        if (!sr_policy_document_module_ready($pdo)) {
            return null;
        }

        return sr_policy_document_snapshot($pdo, $documentKey);
    } catch (Throwable) {
        return null;
    }
}

function sr_community_effective_privacy_consent_config(PDO $pdo, array $board): array
{
    $legacyDocumentKey = sr_community_privacy_consent_policy_document_key($pdo, $board);
    $legacySnapshot = sr_community_privacy_consent_policy_snapshot($pdo, $legacyDocumentKey);
    $targets = [];
    $targetDocuments = [];
    $snapshots = [];
    foreach (sr_community_privacy_consent_target_keys() as $targetKey) {
        $settingKey = 'privacy_consent_require_' . $targetKey;
        $required = sr_community_bool_from_setting(sr_community_effective_board_setting($pdo, $board, $settingKey, '0'));
        if (!$required) {
            continue;
        }

        $targetDocumentKey = sr_community_privacy_consent_clean_document_key(
            sr_community_effective_board_setting($pdo, $board, sr_community_privacy_consent_document_setting_key($targetKey), '')
        );
        if ($targetDocumentKey === '') {
            $targetDocumentKey = $legacyDocumentKey;
        }
        if ($targetDocumentKey !== '') {
            $targetSnapshot = sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey);
            $targets[] = $targetKey;
            $targetDocuments[$targetKey] = $targetDocumentKey;
            $snapshots[$targetKey] = is_array($targetSnapshot) ? $targetSnapshot : [];
        }
    }

    $enabled = sr_community_bool_from_setting(sr_community_effective_board_setting($pdo, $board, 'privacy_consent_enabled', '0'));
    $policyReady = !$enabled;
    if ($enabled && $targets !== []) {
        $policyReady = true;
        foreach ($targets as $targetKey) {
            if (empty($snapshots[$targetKey])) {
                $policyReady = false;
                break;
            }
        }
    }

    return [
        'enabled' => $enabled,
        'document_key' => $legacyDocumentKey,
        'policy_ready' => $policyReady,
        'title' => is_array($legacySnapshot) ? (string) $legacySnapshot['title'] : '',
        'body' => '',
        'version' => is_array($legacySnapshot) ? (string) (int) ($legacySnapshot['version_id'] ?? 0) : '',
        'snapshot' => is_array($legacySnapshot) ? $legacySnapshot : [],
        'target_documents' => $targetDocuments,
        'snapshots' => $snapshots,
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
    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    if ($actions !== [] && empty($config['policy_ready'])) {
        return ['개인정보 수집 및 이용동의 정책 문서가 준비되지 않았습니다.'];
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
    if (empty($config['policy_ready'])) {
        return '<p class="community-privacy-consent is-error">' . sr_e('개인정보 수집 및 이용동의 정책 문서가 준비되지 않았습니다.') . '</p>';
    }
    $labels = array_map('sr_community_privacy_consent_label', $actions);
    $suffix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $idSuffix) ?? '';
    $id = 'modules_community_privacy_consent_' . substr(hash('sha256', implode('|', $actions)), 0, 12) . ($suffix !== '' ? '_' . $suffix : '');
    $html = '<fieldset class="community-privacy-consent">';
    $html .= '<legend>' . sr_e('개인정보 수집 및 이용동의') . ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span></legend>';
    $renderedDocuments = [];
    if (sr_module_enabled($pdo, 'policy_documents') && is_file(SR_ROOT . '/modules/policy_documents/helpers.php')) {
        require_once SR_ROOT . '/modules/policy_documents/helpers.php';
        $targetDocuments = is_array($config['target_documents'] ?? null) ? $config['target_documents'] : [];
        foreach ($actions as $actionKey) {
            $documentKey = (string) ($targetDocuments[$actionKey] ?? '');
            if ($documentKey === '' || isset($renderedDocuments[$documentKey])) {
                continue;
            }

            $renderData = sr_policy_document_public_render_data($pdo, $documentKey);
            if (is_array($renderData) && (string) ($renderData['body_html'] ?? '') !== '') {
                $html .= '<div class="community-privacy-consent-body">';
                $html .= '<h3>' . sr_e((string) ($renderData['title'] ?? $documentKey)) . '</h3>';
                $html .= (string) $renderData['body_html'];
                $html .= '</div>';
                $renderedDocuments[$documentKey] = true;
            }
        }
    }
    $html .= '<p><small>' . sr_e('적용 대상: ' . implode(', ', $labels)) . '</small></p>';
    $html .= '<label for="' . sr_e($id) . '">';
    $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="community_privacy_consent_accepted" value="1" class="form-checkbox"' . ($browserRequired ? ' required' : '') . '>';
    $html .= ' ' . sr_e('위 개인정보 수집 및 이용에 동의합니다.');
    $html .= '</label>';
    $html .= '</fieldset>';

    return $html;
}

function sr_community_privacy_consent_admin_summary_html(array $row): string
{
    $count = (int) ($row['privacy_consent_count'] ?? 0);
    if ($count < 1) {
        return '<span class="admin-summary-meta">-</span>';
    }

    $latestAt = (string) ($row['privacy_consent_latest_at'] ?? '');
    $latestHtml = $latestAt !== '' ? '<br><small>' . sr_community_time_html($latestAt) . '</small>' : '';

    return '<span class="badge-status is-success">동의 ' . sr_e((string) $count) . '</span>' . $latestHtml;
}

function sr_community_record_submission_consents(PDO $pdo, int $boardId, int $accountId, string $subjectType, int $subjectId, array $actionKeys, array $board): int
{
    if ($boardId < 1 || $subjectType === '' || $subjectId < 1 || $actionKeys === []) {
        return 0;
    }
    if (!sr_community_submission_consents_table_exists($pdo)) {
        return 0;
    }

    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    if (empty($config['enabled'])) {
        return 0;
    }
    if (empty($config['policy_ready']) || empty($config['snapshots']) || !is_array($config['snapshots'])) {
        throw new RuntimeException('Community privacy consent policy document is missing.');
    }
    $requiredActionKeys = sr_community_privacy_consent_required_actions($pdo, $board, $actionKeys);
    if ($requiredActionKeys === []) {
        return 0;
    }

    $snapshots = is_array($config['snapshots'] ?? null) ? $config['snapshots'] : [];
    $inserted = 0;
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_submission_consents
            (board_id, subject_type, subject_id, action_key, account_id, policy_document_key_snapshot,
             policy_version_key_snapshot, policy_document_version_id, consent_title_snapshot, consent_body_snapshot,
             consent_body_hash, consent_version_snapshot, consent_required, consent_accepted, ip_hash, user_agent_hash, created_at)
         VALUES
            (:board_id, :subject_type, :subject_id, :action_key, :account_id, :policy_document_key_snapshot,
             :policy_version_key_snapshot, :policy_document_version_id, :consent_title_snapshot, :consent_body_snapshot,
             :consent_body_hash, :consent_version_snapshot, :consent_required, :consent_accepted, :ip_hash, :user_agent_hash, :created_at)'
    );
    foreach ($requiredActionKeys as $actionKey) {
        if (!in_array($actionKey, sr_community_privacy_consent_target_keys(), true)) {
            continue;
        }
        $snapshot = is_array($snapshots[$actionKey] ?? null) ? $snapshots[$actionKey] : [];
        if ($snapshot === []) {
            continue;
        }
        $stmt->execute([
            'board_id' => $boardId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action_key' => $actionKey,
            'account_id' => $accountId > 0 ? $accountId : null,
            'policy_document_key_snapshot' => (string) $snapshot['document_key'],
            'policy_version_key_snapshot' => (string) (int) ($snapshot['version_id'] ?? 0),
            'policy_document_version_id' => (int) $snapshot['version_id'],
            'consent_title_snapshot' => (string) $snapshot['title'],
            'consent_body_snapshot' => '',
            'consent_body_hash' => (string) $snapshot['body_hash'],
            'consent_version_snapshot' => (string) (int) ($snapshot['version_id'] ?? 0),
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
    static $existsByPdo = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByPdo)) {
        return $existsByPdo[$cacheKey];
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_submission_consents LIMIT 1');
        $existsByPdo[$cacheKey] = $stmt !== false;
    } catch (Throwable $exception) {
        $existsByPdo[$cacheKey] = false;
    }

    return $existsByPdo[$cacheKey];
}
