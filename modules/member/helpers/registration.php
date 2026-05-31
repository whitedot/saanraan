<?php

declare(strict_types=1);

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
        'phone',
        'birth_date',
        'profile_text',
        'avatar_file',
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
                'type' => in_array((string) ($field['type'] ?? 'text'), ['text'], true) ? (string) ($field['type'] ?? 'text') : 'text',
                'label' => $label,
                'help' => trim((string) ($field['help'] ?? '')),
                'maxlength' => max(1, min(255, (int) ($field['maxlength'] ?? 120))),
                'required' => !empty($field['required']),
            ];
        }
    }

    return $fields;
}

function sr_member_registration_extension_empty_values(array $fields): array
{
    $values = [];
    foreach ($fields as $key => $_field) {
        $values[(string) $key] = '';
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
