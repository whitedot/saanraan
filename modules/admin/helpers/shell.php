<?php

declare(strict_types=1);

function sr_admin_shell_view(PDO $pdo, ?array $site, string $pageTitle, string $pageSubtitle = '', string $containerClass = ''): array
{
    $currentPath = sr_request_path();
    $navigationItems = sr_admin_shell_navigation_items($pdo, $currentPath);

    return [
        'site_title' => sr_admin_shell_site_title($site),
        'page_title' => $pageTitle !== '' ? $pageTitle : '관리자',
        'page_subtitle' => $pageSubtitle,
        'container_class' => sr_admin_shell_class_attr($containerClass),
        'dashboard_url' => sr_url('/admin'),
        'site_home_url' => sr_url('/'),
        'profile_url' => sr_url('/account'),
        'logout_url' => sr_url('/logout'),
        'navigation_items' => $navigationItems,
    ];
}

function sr_admin_shell_site_title(?array $site): string
{
    $siteName = is_array($site) ? trim((string) ($site['site_name'] ?? $site['name'] ?? '')) : '';

    return $siteName !== '' ? $siteName : '산란';
}

function sr_admin_shell_navigation_items(PDO $pdo, string $currentPath): array
{
    $sections = [];

    foreach (sr_admin_navigation_groups($pdo) as $group) {
        if (!is_array($group)) {
            continue;
        }

        $category = (string) ($group['category'] ?? 'other');
        $title = trim((string) ($group['label'] ?? ''));
        if ($title === '') {
            $title = sr_admin_default_menu_category_label($category);
        }

        $navGroups = sr_admin_shell_navigation_group_items($group, $currentPath);
        if ($navGroups === []) {
            continue;
        }

        $active = false;
        foreach ($navGroups as $navGroup) {
            if (!empty($navGroup['active'])) {
                $active = true;
                break;
            }
        }

        $sections[] = [
            'title' => $title,
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'section_class' => $active ? ' is-active' : '',
            'groups' => $navGroups,
        ];
    }

    if ($sections !== []) {
        $hasOpenItem = false;
        foreach ($sections as $section) {
            if (!empty($section['active'])) {
                $hasOpenItem = true;
                break;
            }
        }

        if (!$hasOpenItem) {
            $sections[0]['section_class'] = ' is-active';
            $sections[0]['groups'][0]['item_class'] = ' is-open';
            $sections[0]['groups'][0]['panel_class'] = '';
            $sections[0]['groups'][0]['aria_expanded'] = 'true';
        }
    }

    return $sections;
}

function sr_admin_shell_navigation_group_items(array $group, string $currentPath): array
{
    $navGroups = [];
    $moduleGroups = isset($group['module_groups']) && is_array($group['module_groups']) ? $group['module_groups'] : [];
    $category = (string) ($group['category'] ?? 'other');

    foreach ($moduleGroups as $moduleGroup) {
        if (!is_array($moduleGroup)) {
            continue;
        }

        $moduleLabel = trim((string) ($moduleGroup['label'] ?? ''));
        if ($moduleLabel === '') {
            $moduleLabel = (string) ($moduleGroup['module_key'] ?? '');
        }

        $rawItems = isset($moduleGroup['items']) && is_array($moduleGroup['items']) ? $moduleGroup['items'] : [];
        $subItems = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $label = trim((string) ($rawItem['label'] ?? ''));
            $path = trim((string) ($rawItem['path'] ?? ''));
            if ($label === '' || $path === '') {
                continue;
            }

            $active = sr_admin_shell_path_matches($currentPath, $path);
            $subItems[] = [
                'title' => $label,
                'path' => $path,
                'url' => sr_url($path),
                'active' => $active,
                'item_class' => $active ? ' is-current is-active' : '',
                'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower(trim($path, '/'))),
            ];
        }

        if ($subItems === []) {
            continue;
        }

        $active = false;
        foreach ($subItems as $subItem) {
            if (!empty($subItem['active'])) {
                $active = true;
                break;
            }
        }

        $navGroups[] = [
            'title' => $moduleLabel !== '' ? $moduleLabel : '메뉴',
            'icon_id' => sr_admin_shell_icon_id($category),
            'active' => $active,
            'item_class' => $active ? ' is-open is-active' : '',
            'panel_class' => $active ? '' : ' hidden',
            'aria_expanded' => $active ? 'true' : 'false',
            'menu_code' => preg_replace('/[^a-z0-9_-]+/', '-', strtolower((string) ($moduleGroup['module_key'] ?? $moduleLabel))),
            'sub_items' => $subItems,
        ];
    }

    return $navGroups;
}

function sr_admin_shell_path_matches(string $currentPath, string $itemPath): bool
{
    if ($currentPath === $itemPath) {
        return true;
    }

    if ($itemPath === '/admin') {
        return false;
    }

    return str_starts_with($currentPath, rtrim($itemPath, '/') . '/');
}

function sr_admin_shell_icon_id(string $category): string
{
    $icons = [
        'system' => 'settings',
        'member' => 'users',
        'site' => 'content',
        'content' => 'content',
        'operation' => 'stats',
        'other' => 'folder',
    ];

    return (string) ($icons[$category] ?? 'folder');
}

function sr_admin_shell_class_attr(string $class): string
{
    $tokens = [];
    foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $token) === 1) {
            $tokens[] = $token;
        }
    }

    return implode(' ', $tokens);
}

function sr_admin_stylesheet_tag(): string
{
    return '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . PHP_EOL
        . '<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>' . PHP_EOL
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>' . PHP_EOL
        . '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/assets/common.css')) . '">' . PHP_EOL
        . '<link rel="stylesheet" href="' . sr_e(sr_admin_asset_url('/modules/admin/assets/admin.css')) . '">';
}

function sr_admin_shell_script_tag(): string
{
    return '<script src="' . sr_e(sr_url('/modules/admin/assets/admin-shell.js')) . '" defer></script>';
}

function sr_admin_asset_url(string $path): string
{
    $url = sr_url($path);
    $file = SR_ROOT . $path;
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . rawurlencode((string) filemtime($file));
}

function sr_admin_begin_content_capture(): void
{
    ob_start();
}

function sr_admin_flush_content_capture(): void
{
    if (ob_get_level() < 1) {
        return;
    }

    $html = (string) ob_get_clean();
    echo sr_admin_normalize_content_html($html);
}

function sr_admin_normalize_content_html(string $html): string
{
    if (trim($html) === '' || !class_exists(DOMDocument::class)) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8"><!doctype html><html><body><div id="sr-admin-fragment">' . $html . '</div></body></html>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return $html;
    }

    $root = $document->getElementById('sr-admin-fragment');
    if (!$root instanceof DOMElement) {
        return $html;
    }

    sr_admin_normalize_form_controls($root);
    sr_admin_normalize_tables($document, $root);
    sr_admin_normalize_sections($document, $root);
    sr_admin_normalize_section_actions($root);
    sr_admin_normalize_top_level_forms($document, $root);
    sr_admin_normalize_top_level_tables($document, $root);
    sr_admin_normalize_form_layouts($document, $root);

    $output = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $output .= (string) $document->saveHTML($child);
    }

    return $output;
}

function sr_admin_normalize_form_controls(DOMElement $root): void
{
    foreach (sr_admin_dom_elements($root, 'button') as $button) {
        $isTableAction = sr_admin_dom_has_ancestor_tag($button, 'td');
        sr_admin_dom_add_class($button, 'btn');
        sr_admin_dom_add_class($button, $isTableAction ? 'btn-sm' : 'btn-solid-primary');
        if (preg_match('/삭제|탈퇴|폐기|해제|초기화|정리/u', trim($button->textContent)) === 1) {
            sr_admin_dom_remove_class($button, 'btn-solid-primary');
            sr_admin_dom_add_class($button, $isTableAction ? 'btn-outline-danger' : 'btn-solid-danger');
        }
    }

    foreach (sr_admin_dom_elements($root, 'input') as $input) {
        $type = strtolower($input->getAttribute('type') !== '' ? $input->getAttribute('type') : 'text');
        if ($type === 'hidden') {
            continue;
        }

        if ($type === 'submit' || $type === 'button') {
            $isTableAction = sr_admin_dom_has_ancestor_tag($input, 'td');
            sr_admin_dom_add_class($input, 'btn');
            sr_admin_dom_add_class($input, $isTableAction ? 'btn-sm' : 'btn-solid-primary');
            if (preg_match('/삭제|탈퇴|폐기|해제|초기화|정리/u', trim($input->getAttribute('value'))) === 1) {
                sr_admin_dom_remove_class($input, 'btn-solid-primary');
                sr_admin_dom_add_class($input, $isTableAction ? 'btn-outline-danger' : 'btn-solid-danger');
            }
            continue;
        }

        if ($type === 'checkbox') {
            sr_admin_dom_add_class($input, 'form-checkbox');
            continue;
        }

        if ($type === 'radio') {
            sr_admin_dom_add_class($input, 'form-radio');
            continue;
        }

        sr_admin_dom_add_class($input, 'form-input');
    }

    foreach (sr_admin_dom_elements($root, 'select') as $select) {
        sr_admin_dom_add_class($select, 'form-select');
    }

    foreach (sr_admin_dom_elements($root, 'textarea') as $textarea) {
        sr_admin_dom_add_class($textarea, 'form-textarea');
    }

    foreach (sr_admin_dom_elements($root, 'a') as $anchor) {
        if (!sr_admin_dom_has_ancestor_tag($anchor, 'td') || sr_admin_dom_has_class($anchor, 'btn')) {
            continue;
        }

        sr_admin_dom_add_class($anchor, 'btn');
        sr_admin_dom_add_class($anchor, 'btn-sm');
        if (preg_match('/삭제|탈퇴|폐기|해제|초기화|정리/u', trim($anchor->textContent)) === 1) {
            sr_admin_dom_add_class($anchor, 'btn-outline-danger');
            continue;
        }

        sr_admin_dom_add_class($anchor, 'btn-surface-default-soft');
    }
}

function sr_admin_normalize_tables(DOMDocument $document, DOMElement $root): void
{
    foreach (sr_admin_dom_elements($root, 'table') as $table) {
        sr_admin_dom_add_class($table, 'table');

        foreach (sr_admin_dom_child_elements($table, 'thead') as $thead) {
            sr_admin_dom_add_class($thead, 'ui-table-head');
        }

        $parent = $table->parentNode;
        if ($parent instanceof DOMElement && sr_admin_dom_has_class($parent, 'table-wrapper')) {
            continue;
        }

        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'table-wrapper');
        if ($parent !== null) {
            $parent->insertBefore($wrapper, $table);
            $wrapper->appendChild($table);
        }
    }
}

function sr_admin_normalize_sections(DOMDocument $document, DOMElement $root): void
{
    foreach (sr_admin_dom_elements($root, 'section') as $section) {
        sr_admin_dom_add_class($section, 'card');

        $heading = sr_admin_dom_first_child_heading($section);
        if ($heading instanceof DOMElement && !sr_admin_dom_has_class($heading->parentNode, 'card-header')) {
            sr_admin_dom_add_class($heading, 'card-title');

            $header = $document->createElement('div');
            $header->setAttribute('class', 'card-header');
            $section->insertBefore($header, $heading);
            $header->appendChild($heading);
        }

        sr_admin_wrap_card_body_nodes($document, $section);
    }
}

function sr_admin_normalize_section_actions(DOMElement $root): void
{
    foreach (sr_admin_dom_elements($root, 'section') as $section) {
        if (!sr_admin_dom_has_class($section, 'card')) {
            continue;
        }

        $header = null;
        $body = null;
        foreach (sr_admin_dom_child_elements($section, 'div') as $div) {
            if (sr_admin_dom_has_class($div, 'card-header')) {
                $header = $div;
            } elseif (sr_admin_dom_has_class($div, 'card-body')) {
                $body = $div;
            }
        }

        if (!$header instanceof DOMElement || !$body instanceof DOMElement) {
            continue;
        }

        $firstElement = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }
            if ($child instanceof DOMElement) {
                $firstElement = $child;
            }
            break;
        }

        if (!$firstElement instanceof DOMElement || strtolower($firstElement->tagName) !== 'p') {
            continue;
        }

        $action = sr_admin_paragraph_single_action_link($firstElement);
        if (!$action instanceof DOMElement) {
            continue;
        }

        sr_admin_dom_add_class($action, 'btn');
        sr_admin_dom_add_class($action, 'btn-sm');
        sr_admin_dom_add_class($action, 'btn-surface-default-soft');
        $header->appendChild($action);
        $body->removeChild($firstElement);
        sr_admin_remove_empty_card_body($body);
    }
}

function sr_admin_remove_empty_card_body(DOMElement $body): void
{
    foreach ($body->childNodes as $child) {
        if ($child instanceof DOMText && trim($child->textContent) === '') {
            continue;
        }

        return;
    }

    if ($body->parentNode instanceof DOMNode) {
        $body->parentNode->removeChild($body);
    }
}

function sr_admin_paragraph_single_action_link(DOMElement $paragraph): ?DOMElement
{
    $anchor = null;
    foreach ($paragraph->childNodes as $child) {
        if ($child instanceof DOMText) {
            $text = trim(str_replace(['|', '/', '·'], '', $child->textContent));
            if ($text === '') {
                continue;
            }
            return null;
        }

        if (!$child instanceof DOMElement) {
            continue;
        }

        if (strtolower($child->tagName) !== 'a') {
            return null;
        }

        if ($anchor instanceof DOMElement) {
            return null;
        }

        $anchor = $child;
    }

    return $anchor;
}

function sr_admin_normalize_top_level_forms(DOMDocument $document, DOMElement $root): void
{
    foreach (sr_admin_dom_child_elements($root, 'form') as $form) {
        if (sr_admin_dom_has_class($form, 'admin-shell-normalized')) {
            continue;
        }

        sr_admin_dom_add_class($form, 'admin-shell-normalized');

        if (sr_admin_form_has_direct_sections($form)) {
            continue;
        }

        $card = $document->createElement('section');
        $card->setAttribute('class', 'card');
        $body = $document->createElement('div');
        $body->setAttribute('class', 'card-body');

        $root->insertBefore($card, $form);
        $card->appendChild($body);
        $body->appendChild($form);
    }
}

function sr_admin_form_has_direct_sections(DOMElement $form): bool
{
    foreach (sr_admin_dom_child_elements($form, 'section') as $section) {
        if ($section instanceof DOMElement) {
            return true;
        }
    }

    return false;
}

function sr_admin_normalize_top_level_tables(DOMDocument $document, DOMElement $root): void
{
    foreach (sr_admin_dom_child_elements($root, 'div') as $wrapper) {
        if (!sr_admin_dom_has_class($wrapper, 'table-wrapper')) {
            continue;
        }

        $card = $document->createElement('section');
        $card->setAttribute('class', 'card');
        $root->insertBefore($card, $wrapper);
        $card->appendChild($wrapper);
    }
}

function sr_admin_normalize_form_layouts(DOMDocument $document, DOMElement $root): void
{
    foreach (sr_admin_dom_elements($root, 'form') as $form) {
        if (sr_admin_form_should_use_filter_layout($form)) {
            sr_admin_normalize_filter_form($document, $form);
            continue;
        }

        if (!sr_admin_form_should_use_layout($form)) {
            continue;
        }

        sr_admin_dom_add_class($form, 'admin-form-layout');
        sr_admin_dom_add_class($form, 'ui-form-theme');
        sr_admin_dom_add_class($form, 'ui-form-showcase');

        sr_admin_normalize_form_sections($document, $form);
        sr_admin_normalize_form_anchor_tabs($document, $form);
        sr_admin_normalize_form_actions($document, $form);
    }
}

function sr_admin_form_should_use_filter_layout(DOMElement $form): bool
{
    if (
        strtolower($form->getAttribute('method')) !== 'get'
        || sr_admin_dom_has_ancestor_tag($form, 'td')
        || sr_admin_dom_has_ancestor_class($form, 'member-search-card')
        || sr_admin_dom_has_ancestor_class($form, 'admin-toolbar-menu')
    ) {
        return false;
    }

    foreach (sr_admin_dom_child_elements($form, 'p') as $paragraph) {
        if (sr_admin_form_paragraph_has_field($paragraph)) {
            return true;
        }
    }

    return false;
}

function sr_admin_normalize_filter_form(DOMDocument $document, DOMElement $form): void
{
    sr_admin_dom_add_class($form, 'admin-filter-form');

    $fields = null;
    foreach (iterator_to_array($form->childNodes) as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $isFilterField = strtolower($child->tagName) === 'p' && sr_admin_form_paragraph_has_field($child);
        $isAction = sr_admin_form_child_is_action($child);
        if (!$isFilterField && !$isAction) {
            continue;
        }

        if (!$fields instanceof DOMElement) {
            $fields = $document->createElement('div');
            $fields->setAttribute('class', 'admin-filter-fields');
            $form->insertBefore($fields, $child);
        }

        if ($isAction) {
            sr_admin_dom_add_class($child, 'admin-filter-submit');
            $fields->appendChild($child);
            continue;
        }

        $fields->appendChild(sr_admin_filter_paragraph_to_field($document, $child));
        $form->removeChild($child);
    }
}

function sr_admin_filter_paragraph_to_field(DOMDocument $document, DOMElement $paragraph): DOMElement
{
    $field = $document->createElement('div');
    $field->setAttribute('class', 'admin-filter-field');

    $firstControl = sr_admin_form_first_control($paragraph);
    $firstControlType = $firstControl instanceof DOMElement ? strtolower($firstControl->getAttribute('type') ?: $firstControl->tagName) : '';
    $labelText = sr_admin_form_paragraph_label_text($paragraph);

    if ($labelText !== '' && !in_array($firstControlType, ['checkbox', 'radio'], true)) {
        $label = $document->createElement('label');
        $label->setAttribute('class', 'form-label admin-filter-label');
        if ($firstControl instanceof DOMElement && $firstControl->getAttribute('id') !== '') {
            $label->setAttribute('for', $firstControl->getAttribute('id'));
        }
        $label->appendChild($document->createTextNode($labelText));
        $field->appendChild($label);
    }

    foreach (iterator_to_array($paragraph->childNodes) as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === 'label' && !in_array($firstControlType, ['checkbox', 'radio'], true)) {
            sr_admin_move_label_fields_to_container($child, $field);
            continue;
        }

        $field->appendChild($child);
    }

    sr_admin_normalize_inline_checks($field);

    return $field;
}

function sr_admin_form_should_use_layout(DOMElement $form): bool
{
    if (
        sr_admin_dom_has_ancestor_tag($form, 'td')
        || sr_admin_dom_has_ancestor_class($form, 'member-search-card')
        || sr_admin_dom_has_ancestor_class($form, 'member-manage')
        || sr_admin_dom_has_ancestor_class($form, 'admin-toolbar-menu')
    ) {
        return false;
    }

    foreach (sr_admin_dom_child_elements($form, 'section') as $section) {
        if (sr_admin_dom_has_class($section, 'card')) {
            return true;
        }
    }

    $fieldRows = 0;
    foreach (sr_admin_dom_child_elements($form, 'p') as $paragraph) {
        if (sr_admin_form_paragraph_has_field($paragraph)) {
            $fieldRows++;
        }
    }

    return $fieldRows >= 2;
}

function sr_admin_normalize_form_sections(DOMDocument $document, DOMElement $form): void
{
    foreach (sr_admin_dom_elements($form, 'div') as $container) {
        if (!sr_admin_dom_has_class($container, 'card-body')) {
            continue;
        }

        sr_admin_normalize_form_row_container($document, $container);
    }

    sr_admin_normalize_form_row_container($document, $form);
}

function sr_admin_normalize_form_row_container(DOMDocument $document, DOMElement $container): void
{
    $grid = null;
    foreach (iterator_to_array($container->childNodes) as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === 'p' && sr_admin_form_paragraph_has_field($child)) {
            if (sr_admin_form_paragraph_label_text($child) === '') {
                $grid = null;
                continue;
            }

            if (!$grid instanceof DOMElement) {
                $grid = $document->createElement('div');
                $grid->setAttribute('class', 'af-grid');
                $container->insertBefore($grid, $child);
            }

            $row = sr_admin_form_paragraph_to_row($document, $child);
            $grid->appendChild($row);
            $container->removeChild($child);
            continue;
        }

        if ($child instanceof DOMElement && sr_admin_dom_has_class($child, 'af-grid')) {
            $grid = $child;
            continue;
        }

        if ($child instanceof DOMText && trim($child->textContent) === '') {
            continue;
        }

        $grid = null;
    }
}

function sr_admin_form_paragraph_has_field(DOMElement $paragraph): bool
{
    foreach (['input', 'select', 'textarea'] as $tagName) {
        foreach ($paragraph->getElementsByTagName($tagName) as $field) {
            if ($field instanceof DOMElement && strtolower($field->getAttribute('type')) !== 'hidden') {
                return true;
            }
        }
    }

    return false;
}

function sr_admin_form_paragraph_to_row(DOMDocument $document, DOMElement $paragraph): DOMElement
{
    $row = $document->createElement('div');
    $row->setAttribute('class', 'af-row');

    $labelCell = $document->createElement('div');
    $labelCell->setAttribute('class', 'af-label');
    $fieldCell = $document->createElement('div');
    $fieldCell->setAttribute('class', 'af-field');

    $firstControl = sr_admin_form_first_control($paragraph);
    $firstControlType = $firstControl instanceof DOMElement ? strtolower($firstControl->getAttribute('type') ?: $firstControl->tagName) : '';
    $labelText = sr_admin_form_paragraph_label_text($paragraph);
    $isChoiceControl = in_array($firstControlType, ['checkbox', 'radio'], true);

    if ($labelText !== '') {
        if ($isChoiceControl) {
            $label = $document->createElement('span');
            $label->setAttribute('class', 'form-label');
        } else {
            $label = $document->createElement('label');
            $label->setAttribute('class', 'form-label');
            if ($firstControl instanceof DOMElement && $firstControl->getAttribute('id') !== '') {
                $label->setAttribute('for', $firstControl->getAttribute('id'));
            }
        }
        $label->appendChild($document->createTextNode($isChoiceControl ? sr_admin_choice_label_text($labelText) : $labelText));
        $labelCell->appendChild($label);
    }

    foreach (iterator_to_array($paragraph->childNodes) as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === 'label' && !$isChoiceControl) {
            sr_admin_move_label_fields_to_container($child, $fieldCell);
            continue;
        }

        $fieldCell->appendChild($child);
    }

    sr_admin_normalize_inline_checks($fieldCell);
    $row->appendChild($labelCell);
    $row->appendChild($fieldCell);

    return $row;
}

function sr_admin_choice_label_text(string $labelText): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $labelText) ?? '');
    $suffixes = [
        '자동 재계산',
        '동의합니다.',
        '확인했습니다.',
        '허용',
        '사용',
        '확인',
        '숨김',
        '포함',
        '기록',
    ];

    foreach ($suffixes as $suffix) {
        if ($text === $suffix || str_ends_with($text, ' ' . $suffix)) {
            return match ($suffix) {
                '동의합니다.' => '동의',
                '확인했습니다.' => '확인',
                default => $suffix,
            };
        }
    }

    return $text;
}

function sr_admin_form_first_control(DOMElement $root): ?DOMElement
{
    foreach (['input', 'select', 'textarea'] as $tagName) {
        foreach ($root->getElementsByTagName($tagName) as $field) {
            if ($field instanceof DOMElement && strtolower($field->getAttribute('type')) !== 'hidden') {
                return $field;
            }
        }
    }

    return null;
}

function sr_admin_form_paragraph_label_text(DOMElement $paragraph): string
{
    foreach ($paragraph->getElementsByTagName('label') as $label) {
        if ($label instanceof DOMElement) {
            $text = trim(preg_replace('/\s+/u', ' ', sr_admin_dom_label_text($label)) ?? '');
            if ($text !== '') {
                return $text;
            }
        }
    }

    foreach ($paragraph->getElementsByTagName('strong') as $strong) {
        if ($strong instanceof DOMElement) {
            $text = trim(preg_replace('/\s+/u', ' ', $strong->textContent) ?? '');
            if ($text !== '') {
                return $text;
            }
        }
    }

    return '';
}

function sr_admin_dom_label_text(DOMElement $label): string
{
    $text = '';
    foreach ($label->childNodes as $child) {
        if ($child instanceof DOMText) {
            $text .= $child->textContent;
            continue;
        }

        if (!$child instanceof DOMElement) {
            continue;
        }

        $tagName = strtolower($child->tagName);
        if (in_array($tagName, ['input', 'select', 'textarea', 'button', 'span', 'small', 'code'], true)) {
            continue;
        }

        if ($tagName === 'br') {
            $text .= ' ';
            continue;
        }

        if ($tagName === 'strong' && (sr_admin_dom_has_class($child, 'sr-only') || sr_admin_dom_has_class($child, 'caption-sr-only'))) {
            continue;
        }

        $text .= sr_admin_dom_text_without_controls($child);
    }

    return $text;
}

function sr_admin_dom_text_without_controls(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return $node->textContent;
    }

    if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['input', 'select', 'textarea', 'button'], true)) {
        return '';
    }

    $text = '';
    foreach ($node->childNodes as $child) {
        $text .= sr_admin_dom_text_without_controls($child);
    }

    return $text;
}

function sr_admin_move_label_fields_to_container(DOMElement $label, DOMElement $container): void
{
    foreach (iterator_to_array($label->childNodes) as $child) {
        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['input', 'select', 'textarea'], true)) {
            $container->appendChild($child);
        }
    }

    foreach (iterator_to_array($label->childNodes) as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
            $label->removeChild($child);
            continue;
        }

        if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['span', 'small', 'code'], true)) {
            $container->appendChild($child);
        }
    }
}

function sr_admin_normalize_inline_checks(DOMElement $container): void
{
    foreach (iterator_to_array($container->getElementsByTagName('label')) as $label) {
        if (!$label instanceof DOMElement) {
            continue;
        }

        $control = sr_admin_form_first_control($label);
        if (!$control instanceof DOMElement) {
            continue;
        }

        $type = strtolower($control->getAttribute('type'));
        if ($type === 'checkbox' || $type === 'radio') {
            sr_admin_dom_add_class($label, 'af-check');
            sr_admin_dom_add_class($label, 'form-label');
        }
    }
}

function sr_admin_normalize_form_anchor_tabs(DOMDocument $document, DOMElement $form): void
{
    $sections = [];
    foreach (sr_admin_dom_child_elements($form, 'section') as $section) {
        if (sr_admin_dom_has_class($section, 'card')) {
            $sections[] = $section;
        }
    }

    if (count($sections) < 2) {
        return;
    }

    $previous = $form->previousSibling;
    while ($previous instanceof DOMText && trim($previous->textContent) === '') {
        $previous = $previous->previousSibling;
    }
    if ($previous instanceof DOMElement && strtolower($previous->tagName) === 'nav') {
        sr_admin_normalize_existing_form_anchor_tabs($previous, $sections);
        return;
    }

    $nav = $document->createElement('nav');
    $nav->setAttribute('class', 'tab-nav-justified admin-anchor-tabs');
    $nav->setAttribute('aria-label', '관리자 등록/수정 탭');

    foreach ($sections as $index => $section) {
        if ($section->getAttribute('id') === '') {
            $section->setAttribute('id', 'admin_form_section_' . (string) ($index + 1));
        }

        $title = sr_admin_section_title($section);
        $link = $document->createElement('a');
        $link->setAttribute('class', 'tab-trigger-underline-justified' . ($index === 0 ? ' active' : ''));
        $link->setAttribute('href', '#' . $section->getAttribute('id'));
        $link->appendChild($document->createTextNode($title !== '' ? $title : '섹션 ' . (string) ($index + 1)));
        $nav->appendChild($link);
    }

    if ($form->parentNode !== null) {
        $form->parentNode->insertBefore($nav, $form);
    }
}

function sr_admin_normalize_existing_form_anchor_tabs(DOMElement $nav, array $sections): void
{
    sr_admin_dom_add_class($nav, 'tab-nav-justified');
    sr_admin_dom_add_class($nav, 'admin-anchor-tabs');
    if ($nav->getAttribute('aria-label') === '') {
        $nav->setAttribute('aria-label', '관리자 등록/수정 탭');
    }

    $sectionIds = [];
    foreach ($sections as $section) {
        if ($section instanceof DOMElement && $section->getAttribute('id') !== '') {
            $sectionIds[] = '#' . $section->getAttribute('id');
        }
    }

    $index = 0;
    foreach ($nav->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        if ($sectionIds !== [] && !in_array($link->getAttribute('href'), $sectionIds, true)) {
            continue;
        }

        sr_admin_dom_add_class($link, 'tab-trigger-underline-justified');
        if ($index === 0) {
            sr_admin_dom_add_class($link, 'active');
        }
        $index++;
    }
}

function sr_admin_section_title(DOMElement $section): string
{
    foreach ($section->getElementsByTagName('h2') as $heading) {
        if ($heading instanceof DOMElement) {
            return trim($heading->textContent);
        }
    }

    foreach ($section->getElementsByTagName('h3') as $heading) {
        if ($heading instanceof DOMElement) {
            return trim($heading->textContent);
        }
    }

    return '';
}

function sr_admin_normalize_form_actions(DOMDocument $document, DOMElement $form): void
{
    $actions = [];
    foreach (iterator_to_array($form->childNodes) as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        if (sr_admin_form_child_is_action($child)) {
            $actions[] = $child;
        }
    }

    if ($actions === []) {
        return;
    }

    $wrapper = $document->createElement('div');
    $wrapper->setAttribute('class', 'admin-form-sticky-actions admin-form-actions admin-form-actions-split');
    $form->insertBefore($wrapper, $actions[0]);

    foreach ($actions as $action) {
        $wrapper->appendChild($action);
    }
}

function sr_admin_form_child_is_action(DOMElement $child): bool
{
    $tagName = strtolower($child->tagName);

    return $tagName === 'button'
        || ($tagName === 'input' && in_array(strtolower($child->getAttribute('type')), ['submit', 'button'], true))
        || ($tagName === 'a' && sr_admin_dom_has_class($child, 'btn'));
}

function sr_admin_wrap_card_body_nodes(DOMDocument $document, DOMElement $section): void
{
    $nodes = [];
    foreach (iterator_to_array($section->childNodes) as $node) {
        if (!$node instanceof DOMElement) {
            if (trim($node->textContent) !== '') {
                $nodes[] = $node;
            }
            continue;
        }

        if (sr_admin_dom_has_class($node, 'card-header') || sr_admin_dom_has_class($node, 'card-body') || sr_admin_dom_has_class($node, 'table-wrapper')) {
            continue;
        }

        $nodes[] = $node;
    }

    if ($nodes === []) {
        return;
    }

    $body = $document->createElement('div');
    $body->setAttribute('class', 'card-body');
    $section->insertBefore($body, $nodes[0]);

    foreach ($nodes as $node) {
        $body->appendChild($node);
    }
}

/**
 * @return list<DOMElement>
 */
function sr_admin_dom_elements(DOMElement $root, string $tagName): array
{
    $elements = [];
    foreach ($root->getElementsByTagName($tagName) as $element) {
        if ($element instanceof DOMElement) {
            $elements[] = $element;
        }
    }

    return $elements;
}

/**
 * @return list<DOMElement>
 */
function sr_admin_dom_child_elements(DOMElement $root, string $tagName): array
{
    $elements = [];
    foreach (iterator_to_array($root->childNodes) as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === strtolower($tagName)) {
            $elements[] = $child;
        }
    }

    return $elements;
}

function sr_admin_dom_first_child_heading(DOMElement $root): ?DOMElement
{
    foreach (iterator_to_array($root->childNodes) as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tagName = strtolower($child->tagName);
        if ($tagName === 'h2' || $tagName === 'h3') {
            return $child;
        }
    }

    return null;
}

function sr_admin_dom_has_class(?DOMNode $node, string $class): bool
{
    if (!$node instanceof DOMElement) {
        return false;
    }

    return in_array($class, preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [], true);
}

function sr_admin_dom_has_ancestor_tag(DOMNode $node, string $tagName): bool
{
    $tagName = strtolower($tagName);
    $parent = $node->parentNode;
    while ($parent instanceof DOMNode) {
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === $tagName) {
            return true;
        }

        $parent = $parent->parentNode;
    }

    return false;
}

function sr_admin_dom_has_ancestor_class(DOMNode $node, string $class): bool
{
    $parent = $node->parentNode;
    while ($parent instanceof DOMNode) {
        if (sr_admin_dom_has_class($parent, $class)) {
            return true;
        }

        $parent = $parent->parentNode;
    }

    return false;
}

function sr_admin_dom_add_class(DOMElement $element, string $class): void
{
    $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    $classes = array_values(array_filter($classes, static fn (string $value): bool => $value !== ''));
    if (!in_array($class, $classes, true)) {
        $classes[] = $class;
    }

    $element->setAttribute('class', implode(' ', $classes));
}

function sr_admin_dom_remove_class(DOMElement $element, string $class): void
{
    $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    $classes = array_values(array_filter($classes, static fn (string $value): bool => $value !== '' && $value !== $class));
    if ($classes === []) {
        $element->removeAttribute('class');
        return;
    }

    $element->setAttribute('class', implode(' ', $classes));
}
