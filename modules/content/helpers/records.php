<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_content_input_values(?PDO $pdo = null): array
{
    $pageGroupScope = sr_content_group_apply_scope(sr_post_string('content_group_scope', 20));
    $pageGroupIdValue = trim(sr_post_string('content_group_id', 20));
    $pageGroupId = preg_match('/\A[1-9][0-9]*\z/', $pageGroupIdValue) === 1 ? (int) $pageGroupIdValue : 0;
    $pageGroupIdInvalid = $pageGroupIdValue !== '' && $pageGroupIdValue !== '0' && $pageGroupId === 0;
    $rawEditorKey = sr_post_string('editor_key', 40);
    $editorKey = sr_content_item_editor_key($rawEditorKey);
    $effectiveEditorKey = $pdo instanceof PDO ? sr_editor_effective_key($pdo, $editorKey) : 'textarea';
    $bodyFormat = $pdo instanceof PDO ? sr_content_body_format_for_editor($pdo, $effectiveEditorKey, sr_post_string('body_format', 20)) : 'plain';
    $bodyText = sr_post_string_without_truncation('body_text', 100000);
    if (!is_string($bodyText)) {
        $bodyText = '';
    }
    $bodyText = $bodyFormat === 'html'
        ? sr_sanitize_rich_text_html($bodyText)
        : sr_content_clean_text($bodyText, 100000);

    $coverImageDelete = sr_post_string('cover_image_delete', 1) === '1';
    $rawCoverImageUrl = sr_post_string('cover_image_url', 255);
    $assetAccessPolicySetIds = sr_content_asset_policy_set_ids_from_value($_POST['asset_access_policy_set_ids'] ?? []);
    $assetActionPolicySetIds = sr_content_asset_policy_set_ids_from_value($_POST['asset_action_policy_set_ids'] ?? []);
    $values = [
        'content_group_scope' => $pageGroupScope,
        'content_group_id' => $pageGroupId,
        'content_group_id_invalid' => $pageGroupIdInvalid ? 1 : 0,
        'source_status' => sr_content_normalize_setting_source(sr_post_string('source_status', 20)),
        'source_layout_key' => sr_content_normalize_setting_source(sr_post_string('source_layout_key', 20)),
        'title' => sr_content_clean_single_line(sr_post_string('title', 160), 160),
        'slug' => sr_content_clean_slug(sr_post_string('slug', 120)),
        'summary' => sr_content_clean_text(sr_post_string('summary', 1000), 1000),
        'cover_image_url' => $coverImageDelete ? '' : sr_content_clean_cover_image_url($rawCoverImageUrl),
        'raw_cover_image_url' => $coverImageDelete ? '' : $rawCoverImageUrl,
        'cover_image_delete' => $coverImageDelete ? 1 : 0,
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'editor_key' => $editorKey,
        'raw_editor_key' => $rawEditorKey,
        'status' => sr_post_string('status', 30),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'show_title' => sr_post_string('show_title', 1) === '1' ? 1 : 0,
        'asset_access_enabled' => sr_post_string('asset_access_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_module'] ?? '')),
        'asset_access_amount' => sr_admin_post_int_in_range('asset_access_amount', 0, 999999999) ?? 0,
        'asset_access_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_post('asset_access_amounts', sr_content_asset_module_keys_from_value($_POST['asset_module'] ?? ''), sr_admin_post_int_in_range('asset_access_amount', 0, 999999999) ?? 0)),
        'asset_access_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($assetAccessPolicySetIds),
        'asset_access_policy_set_id' => sr_content_asset_policy_set_first_id($assetAccessPolicySetIds),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('asset_charge_policy', 20)),
        'asset_action_enabled' => sr_post_string('asset_action_enabled', 1) === '1' ? 1 : 0,
        'asset_action_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['asset_action_module'] ?? '')),
        'asset_action_amount' => sr_admin_post_int_in_range('asset_action_amount', 0, 999999999) ?? 0,
        'asset_action_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_post('asset_action_amounts', sr_content_asset_module_keys_from_value($_POST['asset_action_module'] ?? ''), sr_admin_post_int_in_range('asset_action_amount', 0, 999999999) ?? 0)),
        'asset_action_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($assetActionPolicySetIds),
        'asset_action_policy_set_id' => sr_content_asset_policy_set_first_id($assetActionPolicySetIds),
        'asset_action_direction' => sr_content_clean_slug(sr_post_string('asset_action_direction', 20)),
        'asset_action_label' => sr_content_clean_single_line(sr_post_string('asset_action_label', 80), 80),
        'reaction_preset_key' => $pdo instanceof PDO && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, sr_post_string('reaction_preset_key', 80)) : '',
        'reaction_comment_preset_key' => $pdo instanceof PDO && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, sr_post_string('reaction_comment_preset_key', 80)) : '',
        'comment_extra_fields_json' => sr_post_string_without_truncation('comment_extra_fields_json', 20000) ?? '[]',
        'source_comment_extra_fields_json' => sr_content_normalize_setting_source(sr_post_string('source_comment_extra_fields_json', 20)),
        'seo_title' => sr_content_clean_single_line(sr_post_string('seo_title', 160), 160),
        'seo_description' => sr_content_clean_single_line(sr_post_string('seo_description', 255), 255),
    ];

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $rawValue = sr_post_string($settingKey, 20);
        $values[$settingKey] = preg_match('/\A[0-9]{1,9}\z/', $rawValue) === 1 ? (int) $rawValue : -1;
        $values['source_' . $settingKey] = sr_content_normalize_setting_source(sr_post_string('source_' . $settingKey, 20));
    }

    $legacyAssetSource = sr_content_normalize_setting_source(sr_post_string('asset_policy_source', 20));
    $legacyAccessSource = sr_content_normalize_setting_source(sr_post_string('asset_access_policy_source', 20));
    if (sr_post_string('asset_access_policy_source', 20) === '') {
        $legacyAccessSource = $legacyAssetSource;
    }
    $legacyActionSource = sr_content_normalize_setting_source(sr_post_string('asset_action_policy_source', 20));
    if (sr_post_string('asset_action_policy_source', 20) === '') {
        $legacyActionSource = $legacyAssetSource;
    }
    $legacyFileSource = sr_content_normalize_setting_source(sr_post_string('file_asset_policy_source', 20));
    foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyAccessSource;
    }
    foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyActionSource;
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $postedSource = sr_post_string('source_' . $settingKey, 20);
        $values['source_' . $settingKey] = $postedSource !== ''
            ? sr_content_normalize_setting_source($postedSource)
            : $legacyFileSource;
    }
    $values['source_asset_access_group_policies_json'] = $values['source_asset_access_policy_set_id'] ?? $legacyAccessSource;
    $values['source_asset_action_group_policies_json'] = $values['source_asset_action_policy_set_id'] ?? $legacyActionSource;
    $values['source_file_asset_download_group_policies_json'] = $values['source_file_asset_download_policy_set_id'] ?? $legacyFileSource;
    $values['source_asset_access_amounts_json'] = $values['source_asset_access_amount'] ?? $legacyAccessSource;
    $values['source_asset_action_amounts_json'] = $values['source_asset_action_amount'] ?? $legacyActionSource;
    $values['source_file_asset_download_amounts_json'] = $values['source_file_asset_download_amount'] ?? $legacyFileSource;

    return sr_content_normalize_asset_values($values, false);
}

function sr_content_validate_input(PDO $pdo, array $values, int $pageId = 0, array $publicBannerIds = [], array $publicPopupLayerIds = []): array
{
    $errors = [];
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '제목을 입력하세요.';
    }

    $pageGroupId = (int) ($values['content_group_id'] ?? 0);
    if (!empty($values['content_group_id_invalid'])) {
        $errors[] = '콘텐츠 그룹 값이 올바르지 않습니다.';
    } elseif ($pageGroupId < 0 || ($pageGroupId > 0 && !is_array(sr_content_group_by_id($pdo, $pageGroupId)))) {
        $errors[] = '콘텐츠 그룹 값이 올바르지 않습니다.';
    }
    if (sr_content_group_apply_scope((string) ($values['content_group_scope'] ?? 'here_only')) === 'group' && $pageGroupId < 1) {
        $errors[] = '그룹 적용을 선택하려면 콘텐츠 그룹을 선택하세요.';
    }

    $sourceLabels = [
        'source_status' => '상태',
        'source_layout_key' => '콘텐츠 레이아웃',
    ];
    foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
        $sourceLabels['source_' . $settingKey] = '유료 열람';
    }
    foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
        $sourceLabels['source_' . $settingKey] = '완료 버튼';
    }
    foreach ([
        'file_asset_download_enabled' => '파일 다운로드 사용',
        'file_asset_module' => '파일 다운로드 항목',
        'file_asset_download_amount' => '파일 다운로드 금액',
        'file_asset_download_amounts_json' => '파일 다운로드 항목별 금액',
        'file_asset_charge_policy' => '파일 다운로드 과금 방식',
        'asset_action_label' => '완료 버튼 문구',
    ] as $settingKey => $sourceLabel) {
        $sourceLabels['source_' . $settingKey] = $sourceLabel;
    }
    foreach ($sourceLabels as $sourceKey => $sourceLabel) {
        if (sr_content_normalize_setting_source((string) ($values[$sourceKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $sourceLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용을 할 수 있습니다.';
        }
    }
    if (sr_content_normalize_setting_source((string) ($values['source_comment_extra_fields_json'] ?? 'content')) === 'group' && $pageGroupId < 1) {
        $errors[] = '댓글 추가 입력 항목 설정은 콘텐츠 그룹이 있어야 그룹 적용을 할 수 있습니다.';
    }

    $slug = (string) ($values['slug'] ?? '');
    if (!sr_content_slug_is_valid($slug)) {
        $errors[] = 'slug는 3-120자의 소문자 영문, 숫자, 하이픈만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
    } elseif (sr_content_slug_exists($pdo, $slug, $pageId)) {
        $errors[] = '이미 사용 중인 slug입니다.';
    }

    if (!in_array((string) ($values['status'] ?? ''), sr_content_allowed_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }

    if ((string) ($values['status'] ?? '') === 'scheduled') {
        $scheduledPublishAt = (string) ($values['scheduled_publish_at'] ?? '');
        if ($scheduledPublishAt === '') {
            $errors[] = '예약 발행 시각을 입력하세요.';
        } elseif (strtotime($scheduledPublishAt) === false) {
            $errors[] = '예약 발행 시각 형식이 올바르지 않습니다.';
        } elseif (strtotime($scheduledPublishAt) <= time()) {
            $errors[] = '예약 발행 시각은 현재보다 미래여야 합니다.';
        }
    }

    if ((int) ($values['cover_image_upload_provided'] ?? 0) !== 1 && (string) ($values['raw_cover_image_url'] ?? '') !== '' && (string) ($values['cover_image_url'] ?? '') === '') {
        $errors[] = '커버 이미지 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
    }

    $layoutKey = (string) ($values['layout_key'] ?? '');
    if ($layoutKey !== '' && !sr_content_layout_disabled($layoutKey) && !isset(sr_public_layout_options($pdo)[$layoutKey])) {
        $errors[] = '콘텐츠 레이아웃 값이 올바르지 않습니다.';
    }

    if (!in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html', 'markdown'], true)) {
        $errors[] = '본문 형식이 올바르지 않습니다.';
    } elseif ((string) ($values['body_format'] ?? 'plain') === 'markdown' && !sr_markdown_renderer_available($pdo)) {
        $errors[] = 'Markdown 본문을 저장하려면 Markdown Editor 플러그인을 활성화하세요.';
    }
    $errors = array_merge($errors, sr_comment_extra_field_definition_errors($values['comment_extra_fields_json'] ?? '[]'));
    $rawEditorKey = strtolower(trim((string) ($values['raw_editor_key'] ?? $values['editor_key'] ?? '')));
    $editorKey = sr_content_item_editor_key((string) ($values['editor_key'] ?? ''));
    if ($rawEditorKey !== '' && $rawEditorKey !== $editorKey) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
    } elseif (!isset(sr_editor_options($pdo)[$editorKey])) {
        $errors[] = '본문 에디터 값이 올바르지 않습니다.';
    }

    if ((int) ($values['asset_access_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? '');
        if ($assetModules === []) {
            $errors[] = '유료 열람 항목이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 포인트/금액 항목이 모두 활성 상태일 때만 유료 열람 항목으로 사용할 수 있습니다.';
        } elseif (!sr_content_multi_asset_payment_enabled($pdo) && count($assetModules) > 1) {
            $errors[] = '유료 열람 항목은 포인트/금액 항목을 하나만 선택하세요.';
        }

        $amount = (int) ($values['asset_access_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '유료 열람 금액은 1부터 999999999 사이로 입력하세요.';
        }
        $amounts = sr_content_asset_amounts_from_value($values['asset_access_amounts_json'] ?? '', $assetModules);
        if (count($amounts) < count($assetModules)) {
            $errors[] = '유료 열람 항목별 금액은 선택한 항목마다 1 이상으로 입력하세요.';
        }

        if (!isset(sr_content_asset_view_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
            $errors[] = '유료 열람 과금 방식이 올바르지 않습니다.';
        }
        $assetAccessPolicySetIds = sr_content_asset_policy_set_ids_with_legacy($values['asset_access_group_policies_json'] ?? '', (int) ($values['asset_access_policy_set_id'] ?? 0));
        $errors = array_merge($errors, sr_content_asset_policy_set_ids_validation_errors($pdo, $assetAccessPolicySetIds, '유료 열람'));
        $errors = array_merge($errors, sr_content_asset_policy_set_asset_match_errors($pdo, $assetAccessPolicySetIds, $assetModules, '유료 열람'));
        $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($values['asset_access_group_policies_json'] ?? ''), '유료 열람'));
    }

    if ((int) ($values['asset_action_enabled'] ?? 0) === 1) {
        $assetModules = sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? '');
        $actionDirection = (string) ($values['asset_action_direction'] ?? '');
        if ($assetModules === []) {
            $errors[] = '완료 버튼 처리 항목이 올바르지 않습니다.';
        } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
            $errors[] = '선택한 금액 모듈이 모두 활성 상태일 때만 완료 버튼 처리 항목으로 사용할 수 있습니다.';
        } elseif ($actionDirection === 'grant' && count($assetModules) > 1) {
            $errors[] = '완료 버튼 지급은 처리 항목을 하나만 선택하세요.';
        }

        $amount = (int) ($values['asset_action_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '완료 버튼 금액은 1부터 999999999 사이로 입력하세요.';
        }
        if (!isset(sr_content_asset_action_directions()[$actionDirection])) {
            $errors[] = '완료 버튼 지급/차감 방향이 올바르지 않습니다.';
        }
        $amounts = sr_content_asset_amounts_from_value($values['asset_action_amounts_json'] ?? '', $assetModules);
        if (count($amounts) < count($assetModules)) {
            $errors[] = '완료 버튼 금액은 선택한 처리 항목마다 1 이상으로 입력하세요.';
        }

        if ((string) ($values['asset_action_label'] ?? '') === '') {
            $errors[] = '완료 버튼 문구를 입력하세요.';
        }
        $assetActionPolicySetIds = sr_content_asset_policy_set_ids_with_legacy($values['asset_action_group_policies_json'] ?? '', (int) ($values['asset_action_policy_set_id'] ?? 0));
        $errors = array_merge($errors, sr_content_asset_policy_set_ids_validation_errors($pdo, $assetActionPolicySetIds, '완료 버튼'));
        $errors = array_merge($errors, sr_content_asset_policy_set_asset_match_errors($pdo, $assetActionPolicySetIds, $assetModules, '완료 버튼'));
        $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($values['asset_action_group_policies_json'] ?? ''), '완료 버튼'));
    }

    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $displayId = (int) ($values[$settingKey] ?? 0);
        if (sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content')) === 'group' && $pageGroupId < 1) {
            $errors[] = $settingLabel . ' 설정은 콘텐츠 그룹이 있어야 그룹 적용을 할 수 있습니다.';
        }

        if ($displayId < 0) {
            $errors[] = $settingLabel . ' 값이 올바르지 않습니다.';
            continue;
        }

        if (isset(sr_content_public_banner_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicBannerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 배너 중에서 선택하세요.';
        }

        if (isset(sr_content_public_popup_layer_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicPopupLayerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 팝업레이어 중에서 선택하세요.';
        }
    }

    return $errors;
}

function sr_content_save(PDO $pdo, array $values, int $accountId, int $pageId = 0): int
{
    $values = sr_content_normalize_asset_values($values);

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $existing = $pageId > 0 ? sr_content_by_id($pdo, $pageId) : null;
        $publishedAt = null;
        if ((string) $values['status'] === 'published') {
            $publishedAt = is_array($existing) && (string) ($existing['status'] ?? '') === 'published' && !empty($existing['published_at']) ? (string) $existing['published_at'] : $now;
        } elseif ((string) $values['status'] === 'scheduled') {
            $publishedAt = (string) ($values['scheduled_publish_at'] ?? '');
        }
        $defaultSettlementCurrency = sr_site_default_currency($pdo);
        $assetAccessSettlementCurrency = is_array($existing)
            ? sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($existing['asset_access_settlement_currency'] ?? '')])
            : $defaultSettlementCurrency;
        $assetActionSettlementCurrency = is_array($existing)
            ? sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($existing['asset_action_settlement_currency'] ?? '')])
            : $defaultSettlementCurrency;
        $values['asset_access_settlement_currency'] = $assetAccessSettlementCurrency;
        $values['asset_action_settlement_currency'] = $assetActionSettlementCurrency;

        if (is_array($existing)) {
            $stmt = $pdo->prepare(
                'UPDATE sr_content_items
                 SET content_group_id = :content_group_id,
                     slug = :slug, title = :title, summary = :summary, cover_image_url = :cover_image_url, body_text = :body_text,
                     body_format = :body_format,
                     editor_key = :editor_key,
                     status = :status,
                     layout_key = :layout_key,
                     show_title = :show_title,
                     asset_access_enabled = :asset_access_enabled,
                     asset_module = :asset_module,
                     asset_access_amount = :asset_access_amount,
                     asset_access_settlement_currency = :asset_access_settlement_currency,
                     asset_access_amounts_json = :asset_access_amounts_json,
                     asset_access_group_policies_json = :asset_access_group_policies_json,
                     asset_access_policy_set_id = :asset_access_policy_set_id,
                     asset_charge_policy = :asset_charge_policy,
                     asset_action_enabled = :asset_action_enabled,
                     asset_action_module = :asset_action_module,
                     asset_action_amount = :asset_action_amount,
                     asset_action_settlement_currency = :asset_action_settlement_currency,
                     asset_action_amounts_json = :asset_action_amounts_json,
                     asset_action_group_policies_json = :asset_action_group_policies_json,
                     asset_action_policy_set_id = :asset_action_policy_set_id,
                     asset_action_direction = :asset_action_direction,
                     asset_action_label = :asset_action_label,
                     banner_before_content_id = :banner_before_content_id,
                     banner_after_content_id = :banner_after_content_id,
                     popup_layer_id = :popup_layer_id,
                     reaction_preset_key = :reaction_preset_key,
                     reaction_comment_preset_key = :reaction_comment_preset_key,
                     comment_extra_fields_json = :comment_extra_fields_json,
                     seo_title = :seo_title,
                     seo_description = :seo_description, updated_by = :updated_by,
                     published_at = :published_at, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'cover_image_url' => (string) ($values['cover_image_url'] ?? ''),
                'body_text' => (string) $values['body_text'],
                'body_format' => (string) ($values['body_format'] ?? 'plain'),
                'editor_key' => sr_content_item_editor_key((string) ($values['editor_key'] ?? 'textarea')),
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'show_title' => sr_content_layout_disabled((string) ($values['layout_key'] ?? '')) ? (int) ($values['show_title'] ?? 1) : 1,
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_access_settlement_currency' => $assetAccessSettlementCurrency,
                'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
                'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
                'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_settlement_currency' => $assetActionSettlementCurrency,
                'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
                'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
                'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'reaction_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($values['comment_extra_fields_json'] ?? '[]'),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'updated_at' => $now,
                'id' => $pageId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_content_items
                    (content_group_id, slug, title, summary, cover_image_url, body_text, body_format, editor_key, status, layout_key, show_title, asset_access_enabled, asset_module, asset_access_amount, asset_access_settlement_currency, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_settlement_currency, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, reaction_preset_key, reaction_comment_preset_key, comment_extra_fields_json, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
                 VALUES
                    (:content_group_id, :slug, :title, :summary, :cover_image_url, :body_text, :body_format, :editor_key, :status, :layout_key, :show_title, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_settlement_currency, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_settlement_currency, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :reaction_preset_key, :reaction_comment_preset_key, :comment_extra_fields_json, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'cover_image_url' => (string) ($values['cover_image_url'] ?? ''),
                'body_text' => (string) $values['body_text'],
                'body_format' => (string) ($values['body_format'] ?? 'plain'),
                'editor_key' => sr_content_item_editor_key((string) ($values['editor_key'] ?? 'textarea')),
                'status' => (string) $values['status'],
                'layout_key' => (string) ($values['layout_key'] ?? ''),
                'show_title' => sr_content_layout_disabled((string) ($values['layout_key'] ?? '')) ? (int) ($values['show_title'] ?? 1) : 1,
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? ''),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_access_settlement_currency' => $assetAccessSettlementCurrency,
                'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
                'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
                'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_settlement_currency' => $assetActionSettlementCurrency,
                'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
                'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
                'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'reaction_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key_or_disabled') ? sr_reaction_setting_preset_key_or_disabled($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($values['comment_extra_fields_json'] ?? '[]'),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'created_by' => $accountId,
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pageId = (int) $pdo->lastInsertId();
        }

        foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_basic_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
            sr_content_apply_setting_scope($pdo, $pageId, (int) ($values['content_group_id'] ?? 0), (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'), $values, $accountId, $now);
        }
        foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
            sr_content_set_setting_source($pdo, $pageId, (string) $settingKey, (string) ($values['source_' . $settingKey] ?? 'content'));
        }

        if ((string) ($values['body_format'] ?? 'plain') === 'html') {
            $finalBodyText = sr_content_finalize_body_files($pdo, $pageId, (string) ($values['body_text'] ?? ''), $accountId);
            if ($finalBodyText !== (string) ($values['body_text'] ?? '')) {
                $values['body_text'] = $finalBodyText;
                $stmt = $pdo->prepare('UPDATE sr_content_items SET body_text = :body_text, updated_at = :updated_at WHERE id = :id');
                $stmt->execute([
                    'body_text' => $finalBodyText,
                    'updated_at' => $now,
                    'id' => $pageId,
                ]);
            }
            sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $pageId, 'body', (string) ($values['body_text'] ?? ''), $accountId);
        } else {
            sr_content_cleanup_unreferenced_body_files($pdo, $pageId, '');
            sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $pageId, 'body', '', $accountId);
        }
        sr_content_record_revision($pdo, $pageId, $values, $accountId, $now);
        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $pageId;
}

function sr_content_record_revision(PDO $pdo, int $pageId, array $values, int $accountId, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_revisions
            (content_id, content_group_id, title, summary, cover_image_url, body_text, body_format, editor_key, status, layout_key, show_title, asset_access_enabled, asset_module, asset_access_amount, asset_access_settlement_currency, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_settlement_currency, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, created_by, created_at)
         VALUES
            (:content_id, :content_group_id, :title, :summary, :cover_image_url, :body_text, :body_format, :editor_key, :status, :layout_key, :show_title, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_settlement_currency, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_settlement_currency, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :created_by, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'content_group_id' => (int) ($values['content_group_id'] ?? 0) > 0 ? (int) $values['content_group_id'] : null,
        'title' => (string) $values['title'],
        'summary' => (string) $values['summary'],
        'cover_image_url' => (string) ($values['cover_image_url'] ?? ''),
        'body_text' => (string) $values['body_text'],
        'body_format' => (string) ($values['body_format'] ?? 'plain'),
        'editor_key' => sr_content_item_editor_key((string) ($values['editor_key'] ?? 'textarea')),
        'status' => (string) $values['status'],
        'layout_key' => (string) ($values['layout_key'] ?? ''),
        'show_title' => sr_content_layout_disabled((string) ($values['layout_key'] ?? '')) ? (int) ($values['show_title'] ?? 1) : 1,
        'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($values['asset_module'] ?? ''),
        'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
        'asset_access_settlement_currency' => (string) ($values['asset_access_settlement_currency'] ?? sr_site_default_currency($pdo)),
        'asset_access_amounts_json' => (string) ($values['asset_access_amounts_json'] ?? '{}'),
        'asset_access_group_policies_json' => (string) ($values['asset_access_group_policies_json'] ?? ''),
        'asset_access_policy_set_id' => (int) ($values['asset_access_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($values['asset_action_module'] ?? ''),
        'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
        'asset_action_settlement_currency' => (string) ($values['asset_action_settlement_currency'] ?? sr_site_default_currency($pdo)),
        'asset_action_amounts_json' => (string) ($values['asset_action_amounts_json'] ?? '{}'),
        'asset_action_group_policies_json' => (string) ($values['asset_action_group_policies_json'] ?? ''),
        'asset_action_policy_set_id' => (int) ($values['asset_action_policy_set_id'] ?? 0),
        'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
        'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
        'created_by' => $accountId,
        'created_at' => $now,
    ]);
}

function sr_content_copy_suggestion(array $content): array
{
    $title = sr_content_clean_single_line((string) ($content['title'] ?? '') . ' 복사본', 160);
    $slugBase = sr_content_clean_slug((string) ($content['slug'] ?? 'content') . '-copy');
    if (!sr_content_slug_is_valid($slugBase)) {
        $slugBase = 'content-copy';
    }

    return [
        'title' => $title,
        'slug' => $slugBase,
    ];
}

function sr_content_copy_series_suggestions(PDO $pdo, int $contentId): array
{
    if ($contentId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.series_key, s.title, si.episode_label, si.sort_order
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         ORDER BY s.id ASC'
    );
    $stmt->execute(['content_id' => $contentId]);

    $suggestions = [];
    foreach ($stmt->fetchAll() as $row) {
        $baseKey = sr_content_clean_slug((string) ($row['series_key'] ?? 'series') . '-copy');
        $baseKey = str_replace('-', '_', $baseKey);
        if (!sr_content_series_key_is_valid($baseKey)) {
            $baseKey = 'series_copy';
        }
        $candidate = $baseKey;
        $suffix = 2;
        while (sr_content_series_key_exists($pdo, $candidate)) {
            $candidate = substr($baseKey, 0, 54) . '_' . (string) $suffix;
            $suffix++;
        }

        $suggestions[] = [
            'series_id' => (int) $row['id'],
            'series_key' => $candidate,
            'title' => sr_content_clean_single_line((string) ($row['title'] ?? '') . ' 복사본', 160),
            'episode_label' => (string) ($row['episode_label'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    return $suggestions;
}

function sr_content_copy(PDO $pdo, int $sourceContentId, array $values, int $accountId): int
{
    $source = sr_content_by_id($pdo, $sourceContentId);
    if (!is_array($source)) {
        throw new RuntimeException('복사할 콘텐츠를 찾을 수 없습니다.');
    }
    if ((string) ($source['status'] ?? '') === 'deleted') {
        throw new InvalidArgumentException('삭제된 콘텐츠는 복사할 수 없습니다.');
    }

    $newTitle = sr_content_clean_single_line((string) ($values['title'] ?? ''), 160);
    $newSlug = sr_content_clean_slug((string) ($values['slug'] ?? ''));
    $errors = [];
    if ($newTitle === '') {
        $errors[] = '새 콘텐츠 제목을 입력하세요.';
    }
    if (!sr_content_slug_is_valid($newSlug)) {
        $errors[] = 'slug는 3-120자의 소문자 영문, 숫자, 하이픈만 사용할 수 있습니다.';
    } elseif (sr_content_slug_exists($pdo, $newSlug, 0)) {
        $errors[] = '이미 사용 중인 slug입니다.';
    }
    if ($errors !== []) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }
    if (!empty($values['copy_series'])) {
        $seriesErrors = sr_content_copy_series_validate_options($pdo, $sourceContentId, $values);
        if ($seriesErrors !== []) {
            throw new InvalidArgumentException(implode("\n", $seriesErrors));
        }
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $copy = $source;
        $copy['title'] = $newTitle;
        $copy['slug'] = $newSlug;
        $copy['status'] = 'draft';
        $copy['scheduled_publish_at'] = '';
        $copy['published_at'] = null;
        if ((string) ($copy['body_format'] ?? 'plain') === 'html') {
            $copy['body_text'] = sr_sanitize_rich_text_html((string) ($copy['body_text'] ?? ''));
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_items
                (content_group_id, slug, title, summary, cover_image_url, body_text, body_format, editor_key, status, layout_key, show_title, asset_access_enabled, asset_module, asset_access_amount, asset_access_settlement_currency, asset_access_amounts_json, asset_access_group_policies_json, asset_access_policy_set_id, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_settlement_currency, asset_action_amounts_json, asset_action_group_policies_json, asset_action_policy_set_id, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, comment_extra_fields_json, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
             VALUES
                (:content_group_id, :slug, :title, :summary, :cover_image_url, :body_text, :body_format, :editor_key, :status, :layout_key, :show_title, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_access_settlement_currency, :asset_access_amounts_json, :asset_access_group_policies_json, :asset_access_policy_set_id, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_settlement_currency, :asset_action_amounts_json, :asset_action_group_policies_json, :asset_action_policy_set_id, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :comment_extra_fields_json, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'content_group_id' => (int) ($copy['content_group_id'] ?? 0) > 0 ? (int) $copy['content_group_id'] : null,
            'slug' => $newSlug,
            'title' => $newTitle,
            'summary' => (string) ($copy['summary'] ?? ''),
            'cover_image_url' => (string) ($copy['cover_image_url'] ?? ''),
            'body_text' => (string) ($copy['body_text'] ?? ''),
            'body_format' => (string) ($copy['body_format'] ?? 'plain'),
            'editor_key' => sr_content_item_editor_key((string) ($copy['editor_key'] ?? 'textarea')),
            'status' => 'draft',
            'layout_key' => (string) ($copy['layout_key'] ?? ''),
            'show_title' => sr_content_layout_disabled((string) ($copy['layout_key'] ?? '')) ? (int) ($copy['show_title'] ?? 1) : 1,
            'asset_access_enabled' => (int) ($copy['asset_access_enabled'] ?? 0),
            'asset_module' => (string) ($copy['asset_module'] ?? ''),
            'asset_access_amount' => (int) ($copy['asset_access_amount'] ?? 0),
            'asset_access_settlement_currency' => sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($copy['asset_access_settlement_currency'] ?? '')]),
            'asset_access_amounts_json' => (string) ($copy['asset_access_amounts_json'] ?? '{}'),
            'asset_access_group_policies_json' => (string) ($copy['asset_access_group_policies_json'] ?? ''),
            'asset_access_policy_set_id' => (int) ($copy['asset_access_policy_set_id'] ?? 0),
            'asset_charge_policy' => (string) ($copy['asset_charge_policy'] ?? 'once'),
            'asset_action_enabled' => (int) ($copy['asset_action_enabled'] ?? 0),
            'asset_action_module' => (string) ($copy['asset_action_module'] ?? ''),
            'asset_action_amount' => (int) ($copy['asset_action_amount'] ?? 0),
            'asset_action_settlement_currency' => sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($copy['asset_action_settlement_currency'] ?? '')]),
            'asset_action_amounts_json' => (string) ($copy['asset_action_amounts_json'] ?? '{}'),
            'asset_action_group_policies_json' => (string) ($copy['asset_action_group_policies_json'] ?? ''),
            'asset_action_policy_set_id' => (int) ($copy['asset_action_policy_set_id'] ?? 0),
            'asset_action_direction' => (string) ($copy['asset_action_direction'] ?? 'grant'),
            'asset_action_label' => (string) ($copy['asset_action_label'] ?? '완료'),
            'banner_before_content_id' => (int) ($copy['banner_before_content_id'] ?? 0),
            'banner_after_content_id' => (int) ($copy['banner_after_content_id'] ?? 0),
            'popup_layer_id' => (int) ($copy['popup_layer_id'] ?? 0),
            'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($copy['comment_extra_fields_json'] ?? '[]'),
            'seo_title' => (string) ($copy['seo_title'] ?? ''),
            'seo_description' => (string) ($copy['seo_description'] ?? ''),
            'created_by' => $accountId,
            'updated_by' => $accountId,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newContentId = (int) $pdo->lastInsertId();
        if ((string) ($copy['body_format'] ?? 'plain') === 'html') {
            $rewrittenBodyText = sr_sanitize_rich_text_html((string) ($copy['body_text'] ?? ''));
            if ($rewrittenBodyText !== (string) ($copy['body_text'] ?? '')) {
                $copy['body_text'] = $rewrittenBodyText;
                $pdo->prepare('UPDATE sr_content_items SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $rewrittenBodyText,
                    'updated_at' => $now,
                    'id' => $newContentId,
                ]);
            }
            sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $newContentId, 'body', (string) ($copy['body_text'] ?? ''), $accountId);
        } else {
            sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $newContentId, 'body', '', $accountId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_setting_sources (content_id, setting_key, source, created_at, updated_at)
             SELECT :new_content_id, setting_key, source, :created_at, :updated_at
             FROM sr_content_setting_sources
             WHERE content_id = :source_content_id'
        );
        $stmt->execute([
            'new_content_id' => $newContentId,
            'created_at' => $now,
            'updated_at' => $now,
            'source_content_id' => $sourceContentId,
        ]);

        sr_content_copy_file_links($pdo, $sourceContentId, $newContentId, $now);

        sr_content_record_revision($pdo, $newContentId, $copy, $accountId, $now);
        if (!empty($values['copy_series'])) {
            sr_content_copy_series_for_content($pdo, $sourceContentId, $newContentId, $accountId, $now);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $newContentId;
}

function sr_content_copy_file_links(PDO $pdo, int $sourceContentId, int $newContentId, string $now): void
{
    if ($sourceContentId < 1 || $newContentId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT linked_files.file_id, linked_files.sort_order
         FROM (
            SELECT l.file_id, l.sort_order
            FROM sr_content_file_links l
            INNER JOIN sr_content_files f ON f.id = l.file_id AND f.status = \'active\'
            WHERE l.content_id = :source_link_content_id
              AND l.status = \'active\'
            UNION ALL
            SELECT f.id AS file_id, 0 AS sort_order
            FROM sr_content_files f
            WHERE f.content_id = :source_legacy_content_id
              AND f.status = \'active\'
              AND NOT EXISTS (
                  SELECT 1
                  FROM sr_content_file_links existing_link
                  WHERE existing_link.content_id = :source_existing_link_content_id
                    AND existing_link.file_id = f.id
                    AND existing_link.status = \'active\'
              )
         ) linked_files
         ORDER BY linked_files.sort_order ASC, linked_files.file_id ASC'
    );
    $stmt->execute([
        'source_link_content_id' => $sourceContentId,
        'source_legacy_content_id' => $sourceContentId,
        'source_existing_link_content_id' => $sourceContentId,
    ]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return;
    }

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driver = '';
    }
    $upsertSql = 'INSERT INTO sr_content_file_links
            (content_id, file_id, sort_order, status, created_at, updated_at)
         VALUES
            (:content_id, :file_id, :sort_order, \'active\', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            sort_order = VALUES(sort_order),
            status = \'active\',
            updated_at = VALUES(updated_at)';
    if ($driver === 'sqlite') {
        $upsertSql = 'INSERT INTO sr_content_file_links
                (content_id, file_id, sort_order, status, created_at, updated_at)
             VALUES
                (:content_id, :file_id, :sort_order, \'active\', :created_at, :updated_at)
             ON CONFLICT(content_id, file_id) DO UPDATE SET
                sort_order = excluded.sort_order,
                status = \'active\',
                updated_at = excluded.updated_at';
    }
    $upsert = $pdo->prepare($upsertSql);
    foreach ($rows as $row) {
        $fileId = (int) ($row['file_id'] ?? 0);
        if ($fileId < 1) {
            continue;
        }
        $upsert->execute([
            'content_id' => $newContentId,
            'file_id' => $fileId,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_content_copy_series_for_content(PDO $pdo, int $sourceContentId, int $newContentId, int $accountId, string $now): array
{
    if ($sourceContentId < 1 || $newContentId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, si.episode_label, si.item_status, si.sort_order AS item_sort_order, si.created_by AS item_created_by, si.created_at AS item_created_at, si.updated_at AS item_updated_at
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         ORDER BY s.id ASC'
    );
    $stmt->execute(['content_id' => $sourceContentId]);

    $created = [];
    $insertSeries = $pdo->prepare(
        'INSERT INTO sr_content_series
            (series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at)
         VALUES
            (:series_key, :title, :description, :status, :visibility, :sort_order, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $insertItem = $pdo->prepare(
        'INSERT INTO sr_content_series_items
            (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
         VALUES
            (:series_id, :content_id, :active_content_id, :episode_label, :item_status, :sort_order, :created_by, :created_at, :updated_at)'
    );

    foreach ($stmt->fetchAll() as $series) {
        $seriesKey = sr_content_copy_series_option_value($sourceContentId, (int) $series['id'], 'series_keys');
        $seriesTitle = sr_content_copy_series_option_value($sourceContentId, (int) $series['id'], 'series_titles');
        if ($seriesKey === '') {
            $baseKey = str_replace('-', '_', sr_content_clean_slug((string) ($series['series_key'] ?? 'series') . '-copy'));
            if (!sr_content_series_key_is_valid($baseKey)) {
                $baseKey = 'series_copy';
            }
            $seriesKey = $baseKey;
            $suffix = 2;
            while (sr_content_series_key_exists($pdo, $seriesKey)) {
                $seriesKey = substr($baseKey, 0, 54) . '_' . (string) $suffix;
                $suffix++;
            }
        }
        if ($seriesTitle === '') {
            $seriesTitle = sr_content_clean_single_line((string) ($series['title'] ?? '') . ' 복사본', 160);
        }

        $insertSeries->execute([
            'series_key' => $seriesKey,
            'title' => $seriesTitle,
            'description' => (string) ($series['description'] ?? ''),
            'status' => (string) ($series['status'] ?? 'active'),
            'visibility' => (string) ($series['visibility'] ?? 'public'),
            'sort_order' => (int) ($series['sort_order'] ?? 0),
            'created_by' => $accountId,
            'updated_by' => $accountId,
            'created_at' => (string) ($series['created_at'] ?? $now),
            'updated_at' => (string) ($series['updated_at'] ?? $now),
        ]);
        $newSeriesId = (int) $pdo->lastInsertId();
        $insertItem->execute([
            'series_id' => $newSeriesId,
            'content_id' => $newContentId,
            'active_content_id' => $newContentId,
            'episode_label' => (string) ($series['episode_label'] ?? ''),
            'item_status' => (string) ($series['item_status'] ?? 'active'),
            'sort_order' => (int) ($series['item_sort_order'] ?? 0),
            'created_by' => $series['item_created_by'] !== null ? (int) $series['item_created_by'] : null,
            'created_at' => (string) ($series['item_created_at'] ?? $now),
            'updated_at' => (string) ($series['item_updated_at'] ?? $now),
        ]);
        $created[] = ['source_series_id' => (int) $series['id'], 'new_series_id' => $newSeriesId];
    }

    return $created;
}

function sr_content_copy_series_option_value(int $sourceContentId, int $seriesId, string $key): string
{
    $sourceContentId = max(0, $sourceContentId);
    $seriesId = max(0, $seriesId);
    if ($sourceContentId < 1 || $seriesId < 1 || !isset($GLOBALS['sr_content_copy_series_options']) || !is_array($GLOBALS['sr_content_copy_series_options'])) {
        return '';
    }
    $options = $GLOBALS['sr_content_copy_series_options'][$sourceContentId] ?? null;
    if (!is_array($options)) {
        return '';
    }
    $values = $options[$key] ?? null;
    if (!is_array($values)) {
        return '';
    }

    return trim((string) ($values[(string) $seriesId] ?? $values[$seriesId] ?? ''));
}

function sr_content_copy_series_validate_options(PDO $pdo, int $sourceContentId, array $values): array
{
    $suggestions = sr_content_copy_series_suggestions($pdo, $sourceContentId);
    if ($suggestions === []) {
        return [];
    }

    $keys = is_array($values['series_keys'] ?? null) ? $values['series_keys'] : [];
    $titles = is_array($values['series_titles'] ?? null) ? $values['series_titles'] : [];
    $errors = [];
    $seen = [];
    foreach ($suggestions as $suggestion) {
        $seriesId = (int) $suggestion['series_id'];
        $seriesKey = strtolower(trim((string) ($keys[(string) $seriesId] ?? $keys[$seriesId] ?? $suggestion['series_key'])));
        $seriesTitle = sr_content_clean_single_line((string) ($titles[(string) $seriesId] ?? $titles[$seriesId] ?? $suggestion['title']), 160);
        if (!sr_content_series_key_is_valid($seriesKey)) {
            $errors[] = '시리즈 Key는 소문자 영문, 숫자, 밑줄만 사용할 수 있습니다.';
        } elseif (isset($seen[$seriesKey]) || sr_content_series_key_exists($pdo, $seriesKey)) {
            $errors[] = '이미 사용 중인 시리즈 Key입니다: ' . $seriesKey;
        }
        if ($seriesTitle === '') {
            $errors[] = '새 시리즈 제목을 입력하세요.';
        }
        $seen[$seriesKey] = true;
        $keys[(string) $seriesId] = $seriesKey;
        $titles[(string) $seriesId] = $seriesTitle;
    }

    $GLOBALS['sr_content_copy_series_options'][$sourceContentId] = [
        'series_keys' => $keys,
        'series_titles' => $titles,
    ];

    return $errors;
}

function sr_content_delete_redacted(PDO $pdo, int $pageId, int $accountId): array
{
    $page = sr_content_by_id($pdo, $pageId);
    if (!is_array($page) || (string) ($page['status'] ?? '') === 'deleted') {
        return [
            'deleted' => false,
            'body_files_deleted' => 0,
            'cover_image_deleted' => false,
            'cover_image_failed' => false,
            'files_deleted' => 0,
            'files_failed' => 0,
        ];
    }

    $now = sr_now();
    $deletedTitle = sr_t('content::redaction.deleted_content_title');
    $deletedBody = sr_t('content::redaction.deleted_content_body');
    $deletedFileName = sr_t('content::redaction.deleted_file_name');
    $coverImageCleanup = ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => ''];
    $deletedBodyFiles = 0;
    $deletedFiles = 0;
    $failedFiles = 0;

    $pdo->beginTransaction();
    try {
        $coverImageUrl = (string) ($page['cover_image_url'] ?? '');
        if ($coverImageUrl !== '') {
            $coverImageCleanup = sr_content_delete_cover_image_storage($pdo, $coverImageUrl, $pageId, 'content_delete_cover_image', $pageId);
        }

        $fileRows = sr_content_group_file_rows_for_delete($pdo, [$pageId]);

        foreach ($fileRows as $fileRow) {
            $driver = sr_content_file_storage_driver($fileRow);
            $key = sr_content_file_storage_key($fileRow);
            if ($key !== '' && !sr_storage_delete($driver, $key)) {
                $failedFiles++;
                sr_content_record_storage_cleanup_failure($pdo, 'content_delete_file', $pageId, $driver, $key, '콘텐츠 삭제 후 첨부 파일 저장소 정리에 실패했습니다.');
            } elseif ($key !== '') {
                sr_thumbnail_delete_variants([
                    'storage_driver' => $driver,
                    'storage_key' => $key,
                ]);
                $deletedFiles++;
            }
        }

        $fileIds = array_values(array_unique(array_filter(array_map(
            static fn (array $fileRow): int => (int) ($fileRow['id'] ?? 0),
            $fileRows
        ), static fn (int $fileId): bool => $fileId > 0)));
        if ($fileIds !== []) {
            $filePlaceholders = implode(',', array_fill(0, count($fileIds), '?'));
            $stmt = $pdo->prepare(
                "UPDATE sr_content_files
                 SET title = ?,
                     original_name = ?,
                     stored_name = '',
                     storage_path = '',
                     storage_key = '',
                     mime_type = 'application/octet-stream',
                     size_bytes = 0,
                     checksum_sha256 = ?,
                     status = 'deleted',
                     asset_download_enabled = 0,
                     asset_module = '',
                     asset_download_amount = 0,
                     asset_download_amounts_json = '{}',
                     asset_download_group_policies_json = '',
                     asset_download_policy_set_id = 0,
                     updated_at = ?
                 WHERE id IN (" . $filePlaceholders . ")"
            );
            $params = [$deletedFileName, $deletedFileName, str_repeat('0', 64), $now];
            foreach ($fileIds as $fileId) {
                $params[] = $fileId;
            }
            $stmt->execute($params);
        }

        $stmt = $pdo->prepare("UPDATE sr_content_file_links SET status = 'hidden', updated_at = :updated_at WHERE content_id = :content_id");
        $stmt->execute([
            'updated_at' => $now,
            'content_id' => $pageId,
        ]);

        $stmt = $pdo->prepare(
            "UPDATE sr_content_revisions
             SET title = :title,
                 summary = '',
                 cover_image_url = '',
                 body_text = :body_text,
                 body_format = 'plain',
                 editor_key = 'textarea',
                 status = 'deleted'
             WHERE content_id = :content_id"
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'body_text' => $deletedBody,
            'content_id' => $pageId,
        ]);

        $stmt = $pdo->prepare(
            "UPDATE sr_content_submissions
             SET title = :title,
                 summary = '',
                 body_text = :body_text,
                 body_format = 'plain',
                 review_note = ''
             WHERE content_id = :content_id"
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'body_text' => $deletedBody,
            'content_id' => $pageId,
        ]);

        $stmt = $pdo->prepare(
            "UPDATE sr_content_file_download_logs
             SET content_title_snapshot = :content_title_snapshot,
                 content_slug_snapshot = '',
                 file_title_snapshot = :file_title_snapshot,
                 file_original_name_snapshot = :file_original_name_snapshot
             WHERE content_id = :content_id"
        );
        $stmt->execute([
            'content_title_snapshot' => $deletedTitle,
            'file_title_snapshot' => $deletedFileName,
            'file_original_name_snapshot' => $deletedFileName,
            'content_id' => $pageId,
        ]);

        $stmt = $pdo->prepare('DELETE FROM sr_content_series_items WHERE content_id = ? OR active_content_id = ?');
        $stmt->execute([$pageId, $pageId]);

        sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $pageId, 'body', '', $accountId);
        $deletedBodyFiles = sr_content_cleanup_body_files_for_deleted_content($pdo, [$pageId]);

        $stmt = $pdo->prepare(
            "UPDATE sr_content_items
             SET title = :title,
                 summary = '',
                 cover_image_url = '',
                 body_text = :body_text,
                 body_format = 'plain',
                 editor_key = 'textarea',
                 status = 'deleted',
                 asset_access_enabled = 0,
                 asset_module = '',
                 asset_access_amount = 0,
                 asset_access_amounts_json = '{}',
                 asset_access_group_policies_json = '',
                 asset_access_policy_set_id = 0,
                 asset_action_enabled = 0,
                 asset_action_module = '',
                 asset_action_amount = 0,
                 asset_action_amounts_json = '{}',
                 asset_action_group_policies_json = '',
                 asset_action_policy_set_id = 0,
                 banner_before_content_id = 0,
                 banner_after_content_id = 0,
                 popup_layer_id = 0,
                 seo_title = '',
                 seo_description = '',
                 updated_by = :updated_by,
                 published_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> 'deleted'"
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'body_text' => $deletedBody,
            'updated_by' => $accountId,
            'updated_at' => $now,
            'id' => $pageId,
        ]);

        $pdo->commit();

        return [
            'deleted' => $stmt->rowCount() > 0,
            'body_files_deleted' => $deletedBodyFiles,
            'cover_image_deleted' => !empty($coverImageCleanup['deleted']),
            'cover_image_failed' => !empty($coverImageCleanup['failed']),
            'files_deleted' => $deletedFiles,
            'files_failed' => $failedFiles,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_content_permanently_delete(PDO $pdo, int $pageId, string $confirmationPhrase, int $accountId): array
{
    if ($pageId < 1) {
        return ['ok' => false, 'message' => '영구 삭제할 콘텐츠를 찾을 수 없습니다.'];
    }

    $driverName = '';
    try {
        $driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driverName = '';
    }
    $forUpdateSql = $driverName === 'sqlite' ? '' : ' FOR UPDATE';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, slug, title, status FROM sr_content_items WHERE id = :id AND status = 'deleted' LIMIT 1" . $forUpdateSql);
        $stmt->execute(['id' => $pageId]);
        $content = $stmt->fetch();
        if (!is_array($content)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '이미 영구 삭제되었거나 삭제 상태가 아닌 콘텐츠입니다.'];
        }

        $slug = (string) ($content['slug'] ?? '');
        $confirmationPhrase = trim($confirmationPhrase);
        if ($confirmationPhrase !== $slug && $confirmationPhrase !== (string) $pageId) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '확인 문구가 콘텐츠 ID 또는 주소 이름과 일치하지 않습니다.'];
        }

        $commentIdsStmt = $pdo->prepare('SELECT id FROM sr_content_comments WHERE content_id = :content_id');
        $commentIdsStmt->execute(['content_id' => $pageId]);
        $commentIds = array_values(array_filter(array_map('intval', array_column($commentIdsStmt->fetchAll(), 'id'))));
        $urlEmbedCacheDeleted = function_exists('sr_url_embed_delete_owner_or_target_url_cache')
            ? sr_url_embed_delete_owner_or_target_url_cache($pdo, 'content', 'content', $pageId)
            : 0;
        $reactionRecordsDeleted = 0;
        if (function_exists('sr_reaction_delete_target_records')) {
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'content', 'content', [$pageId]);
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'content', 'comment', $commentIds);
        }

        $pdo->prepare('DELETE FROM sr_content_setting_sources WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare('DELETE FROM sr_content_series_items WHERE content_id = :content_id OR active_content_id = :active_content_id')->execute([
            'content_id' => $pageId,
            'active_content_id' => $pageId,
        ]);
        $pdo->prepare('DELETE FROM sr_content_file_links WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare('DELETE FROM sr_content_access_entitlements WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare('DELETE FROM sr_content_revisions WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare('DELETE FROM sr_content_submissions WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare('DELETE FROM sr_content_comments WHERE content_id = :content_id')->execute(['content_id' => $pageId]);
        $pdo->prepare("DELETE FROM sr_content_files WHERE content_id = :content_id AND status = 'deleted'")->execute(['content_id' => $pageId]);
        $deleteStmt = $pdo->prepare("DELETE FROM sr_content_items WHERE id = :id AND status = 'deleted'");
        $deleteStmt->execute(['id' => $pageId]);
        if ($deleteStmt->rowCount() < 1) {
            throw new RuntimeException('Content permanent delete did not remove row.');
        }

        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'content.permanently_deleted',
            'target_type' => 'content',
            'target_id' => (string) $pageId,
            'result' => 'success',
            'message' => 'Deleted content body rows permanently removed.',
            'metadata' => [
                'slug' => $slug,
                'comments_deleted' => count($commentIds),
                'url_embed_cache_deleted' => $urlEmbedCacheDeleted,
                'reaction_records_deleted' => $reactionRecordsDeleted,
            ],
        ]);

        $pdo->commit();

        return ['ok' => true, 'message' => '콘텐츠를 영구 삭제했습니다. 이용 로그와 정산 이력은 보존됩니다.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
