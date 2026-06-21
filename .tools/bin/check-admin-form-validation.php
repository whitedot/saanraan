#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_check_admin_form_validation_php_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/modules', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        if (
            str_contains($path, '/views/admin')
            || str_contains($path, '/actions/admin')
            || str_contains($path, '/modules/admin/views/')
        ) {
            $files[] = $path;
        }
    }

    sort($files, SORT_STRING);
    return $files;
}

function sr_check_admin_form_validation_csrf_closures(string $content): array
{
    $closures = [];
    if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*static\s+function\b.*?\};/s', $content, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            if (str_contains((string) $match[0], 'sr_csrf_field(')) {
                $closures[] = (string) $match[1];
            }
        }
    }

    return $closures;
}

function sr_check_admin_form_validation_form_has_csrf(string $form, array $csrfClosures): bool
{
    if (str_contains($form, 'sr_csrf_field(')) {
        return true;
    }

    foreach ($csrfClosures as $closure) {
        if (str_contains($form, '$' . $closure . '(')) {
            return true;
        }
    }

    return false;
}

function sr_check_admin_form_validation_scan_csrf(string $root): void
{
    global $errors;

    foreach (sr_check_admin_form_validation_php_files($root) as $file) {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            $errors[] = 'Cannot read ' . $file . '.';
            continue;
        }

        $csrfClosures = sr_check_admin_form_validation_csrf_closures($content);
        if (preg_match_all('/<form\b(?=[^>]*\bmethod\s*=\s*([\'"]?)post\1)[^>]*>.*?<\/form>/is', $content, $matches, PREG_OFFSET_CAPTURE) === false) {
            continue;
        }

        foreach ($matches[0] as [$form, $offset]) {
            if (sr_check_admin_form_validation_form_has_csrf((string) $form, $csrfClosures)) {
                continue;
            }

            $line = substr_count(substr($content, 0, (int) $offset), "\n") + 1;
            $errors[] = 'Admin POST form must render sr_csrf_field(): ' . substr((string) $file, strlen($root) + 1) . ':' . (string) $line;
        }
    }
}

function sr_check_admin_form_validation_attr_value(string $tag, string $name): string
{
    if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*([\'"])(.*?)\1/is', $tag, $matches) !== 1) {
        return '';
    }

    return (string) $matches[2];
}

function sr_check_admin_form_validation_has_attr(string $tag, string $name): bool
{
    return preg_match('/\b' . preg_quote($name, '/') . '(?:\s*=|\s|>|\/>)/i', $tag) === 1;
}

function sr_check_admin_form_validation_is_admin_key_input(string $name): bool
{
    if (!str_ends_with($name, '_key') && !in_array($name, ['module_key', 'menu_key'], true)) {
        return false;
    }

    return !in_array($name, ['confirmation_key', 'license_key', 'version_key'], true);
}

function sr_check_admin_form_validation_scan_key_inputs(string $root): void
{
    global $errors;

    foreach (sr_check_admin_form_validation_php_files($root) as $file) {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            continue;
        }

        if (preg_match_all('/<input\b[^\n]*/i', $content, $matches, PREG_OFFSET_CAPTURE) === false) {
            continue;
        }

        foreach ($matches[0] as [$input, $offset]) {
            $input = (string) $input;
            $type = strtolower(sr_check_admin_form_validation_attr_value($input, 'type'));
            if ($type !== '' && $type !== 'text') {
                continue;
            }

            $name = sr_check_admin_form_validation_attr_value($input, 'name');
            if (!sr_check_admin_form_validation_is_admin_key_input($name)) {
                continue;
            }

            if (sr_check_admin_form_validation_has_attr($input, 'data-admin-key-input')) {
                continue;
            }

            $line = substr_count(substr($content, 0, (int) $offset), "\n") + 1;
            $errors[] = 'Admin key text input must use data-admin-key-input: ' . substr((string) $file, strlen($root) + 1) . ':' . (string) $line . ' name=' . $name;
        }
    }
}

$files = [
    'modules/admin/assets/admin-shell.js' => [
        'data-sr-validate-form' => 'Admin shell must bind opt-in validation forms.',
        'checkValidity()' => 'Admin shell must use browser constraint validation as the client-side baseline.',
        'form-input-invalid' => 'Admin shell must apply the shared input invalid style.',
        'form-select-invalid' => 'Admin shell must apply the shared select invalid style.',
        'form-textarea-invalid' => 'Admin shell must apply the shared textarea invalid style.',
        'form-choice-invalid' => 'Admin shell must apply the shared choice invalid style.',
        'aria-invalid' => 'Admin shell must expose invalid state to assistive technology.',
        'aria-describedby' => 'Admin shell must connect controls with validation notes.',
        'validation-error-note' => 'Admin shell must render validation notes with the shared class.',
        'validateAdminRequiredSelections' => 'Admin shell must block missing required selection groups before admin POST submission.',
        'data-admin-required-selection-mode' => 'Admin shell must support explicit required selection group modes.',
        'fieldset' => 'Admin shell must detect required checkbox groups rendered as fieldsets.',
    ],
    'modules/site_menu/views/admin-site-menus.php' => [
        'data-sr-validate-form' => 'Site menu modals must opt in to admin form validation.',
        'data-validation-message' => 'Site menu required fields must provide field-specific validation messages.',
        'data-admin-key-input' => 'Site menu admin keys must keep normalized admin key input handling.',
    ],
    'modules/coupon/views/admin-coupons.php' => [
        'data-sr-validate-form' => 'Coupon modals must opt in to admin form validation.',
        'data-validation-message' => 'Coupon required fields must provide field-specific validation messages.',
        'data-coupon-issue-mode' => 'Coupon issue modal must keep conditional required target handling.',
    ],
    'modules/banner/views/admin-banners.php' => [
        'data-sr-validate-form' => 'Banner forms must opt in to admin form validation.',
        'data-validation-message' => 'Banner required fields must provide field-specific validation messages.',
        'data-admin-subject-form' => 'Banner form must keep conditional subject validation handling.',
        'subject && exact && scopeVisible && exact.checked' => 'Banner subject required state must be based on the subject control.',
        'data-admin-target-detail-required' => 'Banner target detail required label must stay conditional.',
    ],
    'modules/community/views/admin-settings.php' => [
        'data-admin-required-selection-mode="any"' => 'Community settings consent target row must require at least one document selection on the client.',
        'data-community-privacy-consent-required' => 'Community settings consent required label must stay conditional.',
    ],
    'modules/community/views/admin-boards.php' => [
        'data-admin-required-selection-mode="any"' => 'Community board consent target row must require at least one document selection on the client.',
        'data-community-privacy-consent-required' => 'Community board consent required label must stay conditional.',
    ],
    'modules/reaction/views/admin-reactions.php' => [
        '<legend>리액션 key <span class="sr-required-label">(필수)</span></legend>' => 'Reaction preset key checkbox groups must keep the required legend marker.',
        'name="reaction_keys[]"' => 'Reaction preset key checkbox groups must submit selected reaction keys.',
        'data-reaction-cleanup-form' => 'Reaction cleanup forms must keep modal validation hooks.',
        'data-reaction-cleanup-confirm' => 'Reaction cleanup confirmation input must keep modal validation hooks.',
        'setCustomValidity(validationMessage === message ? message : \'\')' => 'Reaction cleanup confirmation must use browser constraint validation.',
        'validation-error-note' => 'Reaction cleanup confirmation must render visible validation notes.',
    ],
];

foreach ($files as $relativePath => $markers) {
    $body = file_get_contents($root . '/' . $relativePath);
    if (!is_string($body)) {
        $errors[] = 'Cannot read ' . $relativePath . '.';
        continue;
    }

    foreach ($markers as $marker => $message) {
        if (strpos($body, $marker) === false) {
            $errors[] = $message;
        }
    }
}

sr_check_admin_form_validation_scan_csrf($root);
sr_check_admin_form_validation_scan_key_inputs($root);

if ($errors !== []) {
    fwrite(STDERR, "admin form validation checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin form validation checks completed.\n";
