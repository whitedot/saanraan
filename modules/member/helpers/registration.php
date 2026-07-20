<?php

declare(strict_types=1);

function sr_member_registration_policy_document_specs(array $settings = []): array
{
    $termsDocumentKey = sr_member_registration_policy_document_clean_key((string) ($settings['registration_terms_document_key'] ?? 'member_terms'));
    $privacyDocumentKey = sr_member_registration_policy_document_clean_key((string) ($settings['registration_privacy_document_key'] ?? 'member_privacy_collection'));
    $marketingDocumentKey = sr_member_registration_policy_document_clean_key((string) ($settings['registration_marketing_document_key'] ?? 'member_marketing'));

    return [
        'terms' => [
            'document_key' => $termsDocumentKey !== '' ? $termsDocumentKey : 'member_terms',
            'required' => true,
            'post_key' => 'terms_consent',
        ],
        'privacy' => [
            'document_key' => $privacyDocumentKey !== '' ? $privacyDocumentKey : 'member_privacy_collection',
            'required' => true,
            'post_key' => 'privacy_consent',
        ],
        'marketing' => [
            'document_key' => $marketingDocumentKey,
            'required' => false,
            'post_key' => 'marketing_consent',
        ],
    ];
}

function sr_member_registration_policy_documents(PDO $pdo): array
{
    $errors = [];
    $documents = [];
    $settings = sr_member_settings($pdo);

    if (!sr_module_enabled($pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php')) {
        return [
            'documents' => [],
            'errors' => [sr_t('member::action.register.policy_documents_unavailable')],
        ];
    }

    require_once SR_ROOT . '/modules/policy_documents/helpers.php';
    if (!sr_policy_document_module_ready($pdo)) {
        return [
            'documents' => [],
            'errors' => [sr_t('member::action.register.policy_documents_unavailable')],
        ];
    }

    foreach (sr_member_registration_policy_document_specs($settings) as $consentKey => $spec) {
        $documentKey = (string) $spec['document_key'];
        if ($documentKey === '') {
            continue;
        }
        $renderData = sr_policy_document_public_render_data($pdo, $documentKey);
        if (!is_array($renderData)) {
            if (!empty($spec['required'])) {
                $errors[] = sr_t('member::action.register.policy_document_missing', ['key' => $documentKey]);
            }
            continue;
        }

        $documents[$consentKey] = [
            'consent_key' => $consentKey,
            'document_key' => $documentKey,
            'required' => (bool) $spec['required'],
            'post_key' => (string) $spec['post_key'],
            'version_id' => (int) ($renderData['version_id'] ?? 0),
            'title' => (string) $renderData['title'],
            'body_html' => (string) $renderData['body_html'],
            'body_hash' => (string) $renderData['body_hash'],
            'published_at' => (string) ($renderData['published_at'] ?? ''),
            'effective_from' => (string) ($renderData['effective_from'] ?? ''),
        ];
    }

    return [
        'documents' => $documents,
        'errors' => $errors,
    ];
}

function sr_member_registration_policy_document_options(PDO $pdo, string $currentKey = ''): array
{
    static $baseOptions = null;
    $currentKey = sr_member_registration_policy_document_clean_key($currentKey);

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

function sr_member_registration_policy_document_snapshot(PDO $pdo, string $documentKey): ?array
{
    $documentKey = sr_member_registration_policy_document_clean_key($documentKey);
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

function sr_member_registration_policy_consent_values_from_post(array $documents): array
{
    $values = [];
    foreach ($documents as $consentKey => $document) {
        if (!is_array($document)) {
            continue;
        }

        $postKey = (string) ($document['post_key'] ?? '');
        if ($postKey === '') {
            continue;
        }

        $values[(string) $consentKey] = (string) ($_POST[$postKey] ?? '') === '1';
    }

    return $values;
}

function sr_member_registration_policy_consent_validation_errors(array $documents, array $consentValues): array
{
    foreach ($documents as $consentKey => $document) {
        if (!is_array($document) || empty($document['required'])) {
            continue;
        }

        if (empty($consentValues[(string) $consentKey])) {
            return [sr_t('member::action.register.required_consents_missing')];
        }
    }

    return [];
}

function sr_member_registration_policy_consent_section_html(array $documents, array $consentValues = [], string $idSuffix = 'register'): string
{
    $orderedConsentKeys = ['terms', 'privacy', 'marketing'];
    $suffix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $idSuffix) ?? '';
    $html = '<fieldset class="member-skin-basic-policy-consent">';
    $html .= '<legend>' . sr_e(sr_t('member::ui.policy_consent.section_title')) . '</legend>';

    foreach ($orderedConsentKeys as $consentKey) {
        if (!is_array($documents[$consentKey] ?? null)) {
            continue;
        }

        $document = $documents[$consentKey];
        $postKey = (string) ($document['post_key'] ?? '');
        if ($postKey === '') {
            continue;
        }

        $inputId = 'modules_member_' . $suffix . '_' . $postKey;
        $required = !empty($document['required']);
        $checked = !empty($consentValues[$consentKey]);
        $html .= '<div class="member-skin-basic-policy-consent-item">';
        $html .= '<label class="member-skin-basic-choice-label" for="' . sr_e($inputId) . '">';
        $html .= '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($postKey) . '" value="1" class="form-checkbox member-skin-basic-choice-input"' . ($required ? ' required' : '') . ($checked ? ' checked' : '') . '>';
        $html .= '<span>' . sr_e((string) ($document['title'] ?? ''));
        if ($required) {
            $html .= ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>';
        }
        $html .= '</span>';
        $html .= '</label>';

        if ((string) ($document['body_html'] ?? '') !== '') {
            $html .= '<details class="member-skin-basic-policy">';
            $html .= '<summary>' . sr_e(sr_t('member::ui.policy_document.view')) . '</summary>';
            $html .= '<div>' . (string) $document['body_html'] . '</div>';
            $html .= '</details>';
        }

        $html .= '</div>';
    }

    $html .= '</fieldset>';

    return $html;
}

function sr_member_record_registration_policy_consents(PDO $pdo, int $accountId, array $documents, array $consentValues): int
{
    $recorded = 0;
    foreach (['terms', 'privacy', 'marketing'] as $consentKey) {
        if (!is_array($documents[$consentKey] ?? null)) {
            continue;
        }

        $document = $documents[$consentKey];
        sr_member_record_consent(
            $pdo,
            $accountId,
            $consentKey,
            (string) (int) ($document['version_id'] ?? 0),
            !empty($consentValues[$consentKey]),
            $document
        );
        $recorded++;
    }

    return $recorded;
}

function sr_member_registration_extension_helper_path(string $moduleKey, array $contract): string
{
    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_member_registration_extension_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'member-registration.php', ['member']) as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contract)) {
            continue;
        }

        $helperPath = sr_member_registration_extension_helper_path($moduleKey, $contract);
        if ($helperPath !== '') {
            require_once $helperPath;
        }

        $contracts[$moduleKey] = $contract;
    }

    return $contracts;
}

function sr_member_registration_extension_fields(PDO $pdo, array $contracts): array
{
    $fields = [];
    $reservedKeys = array_fill_keys([
        'email',
        'login_id',
        'display_name',
        'password',
        'password_confirm',
        'terms_consent',
        'privacy_consent',
        'marketing_consent',
        'birth_date',
        'profile_image_file',
    ], true);

    foreach ($contracts as $moduleKey => $contract) {
        $fieldDefinitions = [];
        $fieldsFunction = (string) ($contract['fields_function'] ?? '');
        if ($fieldsFunction !== '' && function_exists($fieldsFunction)) {
            $fieldDefinitions = $fieldsFunction($pdo);
        } elseif (is_array($contract['fields'] ?? null)) {
            $fieldDefinitions = $contract['fields'];
        }

        if (!is_array($fieldDefinitions)) {
            continue;
        }

        foreach ($fieldDefinitions as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $label = trim((string) ($field['label'] ?? ''));
            if (preg_match('/\A[a-z][a-z0-9_]{1,60}\z/', $key) !== 1 || $label === '') {
                continue;
            }

            if (isset($reservedKeys[$key]) || isset($fields[$key])) {
                error_log('[saanraan] member registration extension field conflict: module=' . $moduleKey . ' key=' . $key);
                continue;
            }

            $fields[$key] = [
                'module_key' => $moduleKey,
                'key' => $key,
                'type' => in_array((string) ($field['type'] ?? 'text'), ['text', 'checkbox'], true) ? (string) ($field['type'] ?? 'text') : 'text',
                'label' => $label,
                'help' => trim((string) ($field['help'] ?? '')),
                'maxlength' => max(1, min(255, (int) ($field['maxlength'] ?? 120))),
                'required' => !empty($field['required']),
                'default' => !empty($field['default']) ? '1' : '',
            ];
        }
    }

    return $fields;
}

function sr_member_registration_extension_account_fields(array $fields, array $contracts): array
{
    return array_filter(
        $fields,
        static function (array $field) use ($contracts): bool {
            $moduleKey = (string) ($field['module_key'] ?? '');
            $contract = is_array($contracts[$moduleKey] ?? null) ? $contracts[$moduleKey] : [];
            $valuesFunction = (string) ($contract['account_values_function'] ?? '');
            $saveFunction = (string) ($contract['account_save_function'] ?? '');

            return $valuesFunction !== ''
                && $saveFunction !== ''
                && function_exists($valuesFunction)
                && function_exists($saveFunction);
        }
    );
}

function sr_member_registration_extension_account_values(PDO $pdo, array $contracts, int $accountId): array
{
    $values = [];
    foreach ($contracts as $contract) {
        $valuesFunction = (string) ($contract['account_values_function'] ?? '');
        if ($valuesFunction === '' || !function_exists($valuesFunction)) {
            continue;
        }

        $result = $valuesFunction($pdo, $accountId);
        if (!is_array($result)) {
            continue;
        }
        foreach ($result as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }
    }

    return $values;
}

function sr_member_registration_extension_account_validation_errors(PDO $pdo, array $contracts, array $values, array $context = []): array
{
    $errors = [];
    foreach ($contracts as $contract) {
        $validateFunction = (string) ($contract['account_validate_function'] ?? '');
        if ($validateFunction === '' || !function_exists($validateFunction)) {
            continue;
        }

        $result = $validateFunction($pdo, $values, $context);
        if (!is_array($result)) {
            continue;
        }
        foreach ($result as $error) {
            $error = trim((string) $error);
            if ($error !== '') {
                $errors[] = $error;
            }
        }
    }

    return $errors;
}

function sr_member_registration_extension_account_save(PDO $pdo, array $contracts, int $accountId, array $values, array $context = []): array
{
    $metadata = [];
    foreach ($contracts as $moduleKey => $contract) {
        $saveFunction = (string) ($contract['account_save_function'] ?? '');
        if ($saveFunction === '' || !function_exists($saveFunction)) {
            continue;
        }

        $result = $saveFunction($pdo, $accountId, $values, $context);
        if (is_array($result)) {
            $metadata[(string) $moduleKey] = $result;
        }
    }

    return $metadata;
}

function sr_member_registration_extension_empty_values(array $fields): array
{
    $values = [];
    foreach ($fields as $key => $field) {
        $values[(string) $key] = (string) ($field['type'] ?? 'text') === 'checkbox'
            ? (!empty($field['default']) ? '1' : '0')
            : '';
    }

    return $values;
}

function sr_member_registration_extension_values_from_post(array $fields, array &$errors): array
{
    $values = [];
    $extensionPost = $_POST['registration_extensions'] ?? [];
    if (!is_array($extensionPost)) {
        $extensionPost = [];
    }

    foreach ($fields as $key => $field) {
        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'checkbox') {
            $rawValue = $extensionPost[(string) $key] ?? '';
            if (is_array($rawValue)) {
                $errors[] = (string) ($field['label'] ?? $key) . ' 값을 확인해 주세요.';
                $values[(string) $key] = '0';
                continue;
            }

            $values[(string) $key] = (string) $rawValue === '1' ? '1' : '0';
            if (!empty($field['required']) && $values[(string) $key] !== '1') {
                $errors[] = (string) ($field['label'] ?? $key) . '을(를) 선택해 주세요.';
            }
            continue;
        }

        $maxlength = (int) ($field['maxlength'] ?? 120);
        $rawValue = $extensionPost[(string) $key] ?? '';
        if (is_array($rawValue)) {
            $value = null;
        } else {
            $value = trim((string) $rawValue);
            if (strlen($value) > $maxlength) {
                $value = null;
            }
        }

        if ($value === null) {
            $errors[] = (string) ($field['label'] ?? $key) . '은(는) ' . (string) $maxlength . '자 이하로 입력하세요.';
            $value = '';
        }

        $values[(string) $key] = trim($value);
        if (!empty($field['required']) && $values[(string) $key] === '') {
            $errors[] = (string) ($field['label'] ?? $key) . '을(를) 입력해 주세요.';
        }
    }

    return $values;
}

function sr_member_registration_extension_validation_errors(PDO $pdo, array $contracts, array $values, array $context = []): array
{
    $errors = [];
    foreach ($contracts as $contract) {
        $validateFunction = (string) ($contract['validate_function'] ?? '');
        if ($validateFunction === '' || !function_exists($validateFunction)) {
            continue;
        }

        $result = $validateFunction($pdo, $values, $context);
        if (!is_array($result)) {
            continue;
        }

        foreach ($result as $error) {
            $error = trim((string) $error);
            if ($error !== '') {
                $errors[] = $error;
            }
        }
    }

    return $errors;
}

function sr_member_registration_extension_save(PDO $pdo, array $contracts, int $accountId, array $values, array $context = []): array
{
    $metadata = [];
    foreach ($contracts as $moduleKey => $contract) {
        $saveFunction = (string) ($contract['save_function'] ?? '');
        if ($saveFunction === '' || !function_exists($saveFunction)) {
            continue;
        }

        $result = $saveFunction($pdo, $accountId, $values, $context);
        if (is_array($result)) {
            $metadata[(string) $moduleKey] = $result;
        }
    }

    return $metadata;
}

function sr_member_registration_extension_exception_message(array $contracts, Throwable $exception): string
{
    $messageKey = $exception instanceof RuntimeException ? $exception->getMessage() : '';
    if ($messageKey === '') {
        return '';
    }

    foreach ($contracts as $contract) {
        $messages = is_array($contract['exception_messages'] ?? null) ? $contract['exception_messages'] : [];
        $message = trim((string) ($messages[$messageKey] ?? ''));
        if ($message !== '') {
            return $message;
        }
    }

    return '';
}
