<?php

declare(strict_types=1);

function sr_layout_extra_menu_clean_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_layout_extra_menu_clean_area_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})\z/', $value) === 1 ? $value : '';
}

function sr_layout_extra_menu_clean_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 80) : substr($value, 0, 80);
}

function sr_layout_extra_menu_hash_key(string $ownerKey, array $usedKeys = [], string $seed = ''): string
{
    $used = array_fill_keys(array_map('strval', $usedKeys), true);
    if ($seed !== '') {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $key = substr(hash('sha256', $ownerKey . '.layout_extra_menu|' . $seed . '|' . (string) $attempt), 0, 12);
            if (!isset($used[$key])) {
                return $key;
            }
        }
    }
    do {
        $key = bin2hex(random_bytes(6));
    } while (isset($used[$key]));

    return $key;
}

function sr_layout_extra_menu_items_from_value(mixed $value, string $ownerKey): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach (array_values($value) as $itemIndex => $item) {
        $areaKey = '';
        $menuKey = '';
        if (is_array($item)) {
            $areaKey = sr_layout_extra_menu_clean_area_key((string) ($item['area_key'] ?? ($item['key'] ?? ($item['slot_key'] ?? ''))));
            $label = sr_layout_extra_menu_clean_label((string) ($item['label'] ?? ($item['name'] ?? '')));
            $menuKey = sr_layout_extra_menu_clean_menu_key((string) ($item['menu_key'] ?? ''));
        } else {
            $label = '';
            $menuKey = sr_layout_extra_menu_clean_menu_key((string) $item);
        }
        if ($menuKey === '') {
            continue;
        }
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_layout_extra_menu_hash_key($ownerKey, array_keys($items), (string) $itemIndex . '|' . $menuKey);
        }
        $items[$areaKey] = [
            'area_key' => $areaKey,
            'label' => $label,
            'menu_key' => $menuKey,
        ];
    }

    return array_values($items);
}

function sr_layout_extra_menu_items_from_pair_values(
    mixed $areaKeys,
    mixed $labels,
    mixed $menuKeys,
    string $ownerKey
): array {
    $areaKeys = is_array($areaKeys) ? array_values($areaKeys) : [];
    $labels = is_array($labels) ? array_values($labels) : [];
    $menuKeys = is_array($menuKeys) ? array_values($menuKeys) : [];
    $items = [];
    foreach ($menuKeys as $index => $menuKeyValue) {
        $menuKey = sr_layout_extra_menu_clean_menu_key((string) $menuKeyValue);
        if ($menuKey === '') {
            continue;
        }
        $areaKey = sr_layout_extra_menu_clean_area_key((string) ($areaKeys[$index] ?? ''));
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_layout_extra_menu_hash_key($ownerKey, array_keys($items));
        }
        $label = sr_layout_extra_menu_clean_label((string) ($labels[$index] ?? ''));
        if (!isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => $label,
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_layout_extra_menu_keys_from_value(mixed $value, string $ownerKey): array
{
    return array_values(array_map(
        static fn (array $item): string => (string) $item['menu_key'],
        sr_layout_extra_menu_items_from_value($value, $ownerKey)
    ));
}

function sr_layout_extra_menu_items_from_settings(array $settings, string $ownerKey): array
{
    $items = [];
    foreach (sr_layout_extra_menu_items_from_value($settings['layout_extra_menu_keys_json'] ?? [], $ownerKey) as $item) {
        $items[(string) $item['area_key']] = $item;
    }
    foreach (['layout_secondary_menu_key', 'layout_tertiary_menu_key', 'layout_quaternary_menu_key', 'layout_quinary_menu_key'] as $legacySettingKey) {
        $menuKey = sr_layout_extra_menu_clean_menu_key((string) ($settings[$legacySettingKey] ?? ''));
        $areaKey = str_replace('layout_', '', str_replace('_menu_key', '', $legacySettingKey));
        if ($menuKey !== '' && !isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => '',
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_layout_extra_menu_keys_from_settings(array $settings, string $ownerKey): array
{
    return array_values(array_map(
        static fn (array $item): string => (string) $item['menu_key'],
        sr_layout_extra_menu_items_from_settings($settings, $ownerKey)
    ));
}

function sr_layout_extra_menu_keys_json(mixed $value, string $ownerKey): string
{
    $json = json_encode(sr_layout_extra_menu_items_from_value($value, $ownerKey), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}
