<?php

declare(strict_types=1);

function toy_popup_layer_available_targets(PDO $pdo): array
{
    $targets = [];
    foreach (toy_enabled_module_contract_files($pdo, 'extension-points.php', ['popup_layer']) as $moduleKey => $file) {
        $modulePoints = toy_load_module_contract_file($moduleKey, $file);
        if (is_callable($modulePoints)) {
            $modulePoints = $modulePoints($pdo);
        }

        if (!is_array($modulePoints)) {
            continue;
        }

        foreach ($modulePoints as $point) {
            if (!is_array($point)) {
                continue;
            }

            $pointKey = (string) ($point['point_key'] ?? '');
            if (!toy_popup_layer_is_safe_key($pointKey, 120)) {
                continue;
            }

            if (($point['surface'] ?? 'public') !== 'public' || ($point['output'] ?? true) === false) {
                continue;
            }

            $pointLabel = toy_popup_layer_clean_single_line((string) ($point['label'] ?? $pointKey), 120);
            $slots = toy_popup_layer_normalize_slots($point['slots'] ?? []);
            foreach ($slots as $slot) {
                $targets[] = [
                    'module_key' => $moduleKey,
                    'module_label' => toy_popup_layer_module_label($moduleKey),
                    'point_key' => $pointKey,
                    'point_label' => $pointLabel,
                    'slot_key' => (string) $slot['slot_key'],
                    'slot_label' => (string) $slot['slot_label'],
                ];
            }
        }
    }

    return $targets;
}

function toy_popup_layer_normalize_slots(mixed $slots): array
{
    if (!is_array($slots) || $slots === []) {
        return [
            [
                'slot_key' => toy_popup_layer_default_slot_key(),
                'slot_label' => '화면',
                'slot_kind' => 'content',
            ],
        ];
    }

    $normalized = [];
    foreach ($slots as $slot) {
        if (!is_array($slot)) {
            continue;
        }

        $slotKey = (string) ($slot['slot_key'] ?? '');
        if (!toy_popup_layer_is_safe_key($slotKey, 80)) {
            continue;
        }

        $slotKind = toy_popup_layer_clean_slot_kind((string) ($slot['kind'] ?? 'content'));
        if ($slotKind !== 'content') {
            continue;
        }

        $slotLabel = toy_popup_layer_clean_single_line((string) ($slot['label'] ?? $slotKey), 80);
        $normalized[$slotKey] = [
            'slot_key' => $slotKey,
            'slot_label' => $slotLabel !== '' ? $slotLabel : $slotKey,
            'slot_kind' => $slotKind,
        ];
    }

    return array_values($normalized);
}

function toy_popup_layer_clean_slot_kind(string $value): string
{
    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower(trim($value)));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, 40);
}

function toy_popup_layer_module_label(string $moduleKey): string
{
    $metadata = toy_module_metadata($moduleKey);
    $name = (string) ($metadata['name'] ?? '');

    return $name !== '' ? $name : $moduleKey;
}

function toy_popup_layer_target_option_value(array $target): string
{
    return (string) $target['module_key'] . '|' . (string) $target['point_key'] . '|' . (string) $target['slot_key'];
}

function toy_popup_layer_public_target_option_value(): string
{
    return '__public__';
}

function toy_popup_layer_is_public_target_option(string $option): bool
{
    return $option === toy_popup_layer_public_target_option_value();
}

function toy_popup_layer_target_option_label(array $target): string
{
    return (string) $target['module_label'] . ' / ' . (string) $target['point_label'] . ' / ' . (string) $target['slot_label'];
}

function toy_popup_layer_find_target(array $targets, string $optionValue): ?array
{
    foreach ($targets as $target) {
        if (toy_popup_layer_target_option_value($target) === $optionValue) {
            return $target;
        }
    }

    return null;
}

function toy_popup_layer_public_layers(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.status, p.starts_at, p.ends_at, p.dismiss_cookie_days, p.updated_at
         FROM toy_popup_layers p
         WHERE NOT EXISTS (
             SELECT 1
             FROM toy_popup_layer_targets t
             WHERE t.popup_layer_id = p.id
         )
         ORDER BY p.id DESC"
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_popup_layer_settings(PDO $pdo): array
{
    $metadata = toy_module_metadata('popup_layer');
    $defaults = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return array_merge(['popup_layer_skin_key' => 'basic'], $defaults, toy_module_settings($pdo, 'popup_layer'));
}

function toy_popup_layer_skin_options(): array
{
    return [
        'basic' => [
            'label' => '기본',
            'views' => [
                'layer' => TOY_ROOT . '/modules/popup_layer/skins/basic/layer.php',
            ],
        ],
    ];
}

function toy_popup_layer_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['popup_layer_skin_key'] ?? 'basic');

    return isset(toy_popup_layer_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function toy_popup_layer_skin_view(string $skinKey, string $viewKey): string
{
    $options = toy_popup_layer_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    return is_file($view) ? $view : (string) ($options['basic']['views'][$viewKey] ?? '');
}

function toy_popup_layer_save_skin_key(PDO $pdo, string $skinKey): void
{
    $skinKey = toy_popup_layer_skin_key(['popup_layer_skin_key' => $skinKey]);
    $stmt = $pdo->prepare("SELECT id FROM toy_modules WHERE module_key = 'popup_layer' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('팝업레이어 모듈이 등록되어 있지 않습니다.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO toy_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = toy_now();
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'popup_layer_skin_key',
        'setting_value' => $skinKey,
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    toy_clear_module_settings_cache('popup_layer');
}

function toy_popup_layer_render_stack(array $popups, string $skinKey = 'basic'): string
{
    if ($popups === []) {
        return '';
    }

    $skinKey = toy_popup_layer_skin_key(['popup_layer_skin_key' => $skinKey]);
    $view = toy_popup_layer_skin_view($skinKey, 'layer');
    if ($view === '') {
        return '';
    }

    ob_start();
    include $view;
    return (string) ob_get_clean();
}

function toy_popup_layer_render_basic_stack(array $popups): string
{
    $html = ['<div class="toy-popup-layer-stack" data-toy-popup-layer-stack>'];
    foreach ($popups as $popup) {
        $cookieDays = max(0, min(365, (int) $popup['dismiss_cookie_days']));
        $html[] = '<section class="toy-popup-layer" data-toy-popup-layer data-popup-id="' . toy_e((string) $popup['id']) . '" data-cookie-days="' . toy_e((string) $cookieDays) . '">';
        $html[] = '<button class="toy-popup-layer-close" type="button" data-toy-popup-layer-close aria-label="닫기">x</button>';
        $html[] = '<h2>' . toy_e((string) $popup['title']) . '</h2>';
        $html[] = '<div class="toy-popup-layer-body">' . nl2br(toy_e((string) $popup['body_text'])) . '</div>';
        $html[] = '</section>';
    }
    $html[] = '</div>';
    $html[] = toy_popup_layer_close_script();
    return implode("\n", $html);
}

function toy_popup_layer_render_public_layer(PDO $pdo, int $popupLayerId): string
{
    if ($popupLayerId <= 0) {
        return '';
    }

    $now = toy_now();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.body_text, p.skin_key, p.dismiss_cookie_days
         FROM toy_popup_layers p
         WHERE p.id = :id
           AND p.status = 'enabled'
           AND (p.starts_at IS NULL OR p.starts_at <= :now_start)
           AND (p.ends_at IS NULL OR p.ends_at >= :now_end)
           AND NOT EXISTS (
               SELECT 1
               FROM toy_popup_layer_targets t
               WHERE t.popup_layer_id = p.id
           )
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $popupLayerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $row = $stmt->fetch();
    if (!is_array($row)) {
        return '';
    }

    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0 || isset($_COOKIE[toy_popup_layer_cookie_name($id)])) {
        return '';
    }

    $skinKey = toy_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($row['skin_key'] ?? 'basic')]);
    return toy_popup_layer_render_stack([
        [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'skin_key' => $skinKey,
            'dismiss_cookie_days' => (int) ($row['dismiss_cookie_days'] ?? 1),
        ],
    ], $skinKey);
}

function toy_popup_layer_render(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? toy_popup_layer_default_slot_key());
    $subjectId = toy_popup_layer_clean_subject_id((string) ($context['subject_id'] ?? ''));

    if (
        !toy_is_safe_module_key($moduleKey)
        || !toy_popup_layer_is_safe_key($pointKey, 120)
        || !toy_popup_layer_is_safe_key($slotKey, 80)
    ) {
        return '';
    }

    $now = toy_now();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.body_text, p.skin_key, p.dismiss_cookie_days
         FROM toy_popup_layers p
         INNER JOIN toy_popup_layer_targets t ON t.popup_layer_id = p.id
         WHERE p.status = 'enabled'
           AND (p.starts_at IS NULL OR p.starts_at <= :now_start)
           AND (p.ends_at IS NULL OR p.ends_at >= :now_end)
           AND t.module_key = :module_key
           AND t.point_key = :point_key
           AND t.slot_key = :slot_key
           AND (
                t.match_type = 'all'
                OR (t.match_type = 'exact' AND t.subject_id = :subject_id)
           )
         ORDER BY p.id DESC"
    );
    $stmt->execute([
        'now_start' => $now,
        'now_end' => $now,
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'subject_id' => $subjectId,
    ]);

    $popups = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $cookieName = toy_popup_layer_cookie_name($id);
        if (isset($_COOKIE[$cookieName])) {
            continue;
        }

        $popups[] = [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'skin_key' => toy_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($row['skin_key'] ?? 'basic')]),
            'dismiss_cookie_days' => (int) ($row['dismiss_cookie_days'] ?? 1),
        ];
    }

    $html = '';
    $popupsBySkin = [];
    foreach ($popups as $popup) {
        $popupsBySkin[(string) $popup['skin_key']][] = $popup;
    }
    foreach ($popupsBySkin as $skinKey => $skinPopups) {
        $html .= toy_popup_layer_render_stack($skinPopups, $skinKey);
    }

    return $html;
}

function toy_popup_layer_cookie_name(int $popupId): string
{
    return 'toy_popup_layer_' . $popupId . '_dismissed';
}

function toy_popup_layer_default_slot_key(): string
{
    return 'overlay';
}

function toy_popup_layer_close_script(): string
{
    static $printed = false;
    if ($printed) {
        return '';
    }

    $printed = true;

    return '<script src="' . toy_e(toy_url('/modules/popup_layer/assets/toycore-popup-layer.js')) . '" defer></script>';
}

function toy_popup_layer_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r", "\n"], ' ', $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function toy_popup_layer_clean_text(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function toy_popup_layer_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value) !== 1) {
        return null;
    }

    return str_replace('T', ' ', $value) . ':00';
}

function toy_popup_layer_admin_datetime_value(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/', $value, $matches) !== 1) {
        return '';
    }

    return $matches[1] . 'T' . $matches[2];
}

function toy_popup_layer_clean_subject_id(string $value): string
{
    $value = toy_popup_layer_clean_single_line($value, 80);
    if ($value === '') {
        return '';
    }

    return preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $value) === 1 ? $value : '';
}

function toy_popup_layer_is_safe_key(string $value, int $maxLength): bool
{
    if ($value === '' || strlen($value) > $maxLength) {
        return false;
    }

    return preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/', $value) === 1;
}
