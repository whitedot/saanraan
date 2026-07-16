<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? sr_t('community::ui.community.c1f4d427') : sr_t('community::ui.community.settings.af4e5ebd');
$communityPostBodyLengthMax = sr_community_post_body_setting_max_length();
$communitySiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$communityBoardSidebarSiteMenuAvailable = isset($communityBoardSidebarSiteMenuAvailable)
    ? (bool) $communityBoardSidebarSiteMenuAvailable
    : sr_community_board_sidebar_site_menu_available($pdo);
$communitySettingsModuleReferenceListHtml = static function (array $references): string {
    $items = [];
    foreach ($references as $reference) {
        if (!is_array($reference)) {
            continue;
        }
        $label = trim((string) ($reference['label'] ?? ''));
        $path = trim((string) ($reference['path'] ?? ''));
        if ($label === '' || $path === '' || isset($items[$path])) {
            continue;
        }
        $items[$path] = '<li><a href="' . sr_e(sr_url($path)) . '" target="_blank" rel="noopener noreferrer">'
            . sr_material_icon_html('extension', 'form-help-reference-icon')
            . '<span class="form-help-reference-label">' . sr_e($label) . '</span></a></li>';
    }
    if ($items === []) {
        return '';
    }

    return '<ul class="form-help form-help-info form-help-reference-list" aria-label="참조 모듈">'
        . implode('', $items)
        . '</ul>';
};
$communitySettingsModuleAdminReferenceIndex = sr_admin_module_menu_reference_index($pdo);
$communitySettingsModuleAdminReference = static function (string $moduleKey, string $path) use ($communitySettingsModuleAdminReferenceIndex): array {
    $reference = $communitySettingsModuleAdminReferenceIndex[$moduleKey][$path] ?? [];
    return is_array($reference) ? $reference : [];
};
$communitySettingsModuleAdminPaths = [
    'site_menu' => '/admin/site-menus',
    'content_layout' => '/admin/content/settings',
    'quiz_layout' => '/admin/quiz/settings',
    'survey_layout' => '/admin/surveys/settings',
    'ckeditor' => '/admin/ckeditor/settings',
    'markdown_editor' => '/admin/markdown-editor/settings',
    'content_embed' => '/admin/content',
    'coupon_embed' => '/admin/coupons/campaigns',
    'quiz_embed' => '/admin/quiz',
    'survey_embed' => '/admin/surveys',
];
$communityLayoutModuleReferences = [];
foreach ($communityLayoutOptions as $communityLayoutOption) {
    $providerModuleKey = is_array($communityLayoutOption) ? (string) ($communityLayoutOption['provider_module_key'] ?? '') : '';
    $referenceKey = $providerModuleKey . '_layout';
    $referencePath = (string) ($communitySettingsModuleAdminPaths[$referenceKey] ?? '');
    $reference = $communitySettingsModuleAdminReference($providerModuleKey, $referencePath);
    if ($providerModuleKey !== 'community' && $reference !== []) {
        $communityLayoutModuleReferences[$providerModuleKey] = $reference;
    }
}
$communitySiteMenuModuleReferences = $communityBoardSidebarSiteMenuAvailable
    ? array_filter([$communitySettingsModuleAdminReference('site_menu', $communitySettingsModuleAdminPaths['site_menu'])])
    : [];
$communityEditorModuleReferences = [];
foreach (sr_editor_contracts($pdo) as $communityEditorContract) {
    $editorModuleKey = is_array($communityEditorContract) ? (string) ($communityEditorContract['module_key'] ?? '') : '';
    $referencePath = (string) ($communitySettingsModuleAdminPaths[$editorModuleKey] ?? '');
    $reference = $communitySettingsModuleAdminReference($editorModuleKey, $referencePath);
    if ($reference !== []) {
        $communityEditorModuleReferences[$editorModuleKey] = $reference;
    }
}
$communityToolbarModuleReferences = isset($communityEditorModuleReferences['ckeditor'])
    ? [$communityEditorModuleReferences['ckeditor']]
    : [];
$communityInternalEmbedModuleReferences = [];
foreach (array_keys(sr_enabled_module_contract_files($pdo, 'url-embed-targets.php', ['community'])) as $embedModuleKey) {
    $referenceKey = (string) $embedModuleKey . '_embed';
    $referencePath = (string) ($communitySettingsModuleAdminPaths[$referenceKey] ?? '');
    $reference = $communitySettingsModuleAdminReference((string) $embedModuleKey, $referencePath);
    if ($reference !== []) {
        $communityInternalEmbedModuleReferences[(string) $embedModuleKey] = $reference;
    }
}
$communityBoardSidebarMenuTypeOptions = sr_community_board_sidebar_menu_type_options($communityBoardSidebarSiteMenuAvailable);
$communityBoardSidebarMenuTypeValue = sr_community_board_sidebar_menu_type((string) ($settings['board_sidebar_menu_type'] ?? 'all_boards'));
if (!isset($communityBoardSidebarMenuTypeOptions[$communityBoardSidebarMenuTypeValue])) {
    $communityBoardSidebarMenuTypeValue = 'none';
}
$communityIdentityRestrictedBoardAvailable = isset($communityIdentityRestrictedBoardAvailable)
    ? (bool) $communityIdentityRestrictedBoardAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'community.restricted_board'));
$communityIdentityVerificationInputAttributes = $communityIdentityRestrictedBoardAvailable
    ? ''
    : ' disabled aria-describedby="community-settings-identity-unavailable"';
$communityReactionAvailable = isset($communityReactionAvailable)
    ? (bool) $communityReactionAvailable
    : (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php'));
$communityReactionInputAttributes = $communityReactionAvailable
    ? ''
    : ' disabled aria-describedby="community-settings-reaction-unavailable"';
$communityPrivacyConsentPolicyDocumentsAvailable = isset($communityPrivacyConsentPolicyDocumentsAvailable)
    ? (bool) $communityPrivacyConsentPolicyDocumentsAvailable
    : sr_community_privacy_consent_policy_documents_available($pdo);
$communityPrivacyConsentInputAttributes = $communityPrivacyConsentPolicyDocumentsAvailable
    ? ''
    : ' disabled aria-describedby="community-settings-privacy-consent-unavailable"';
$communitySiteMenuSelectOptions = static function (string $selectedMenuKey) use ($communitySiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach (sr_community_layout_menu_builtin_options() as $menuKey => $menuLabel) { ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e((string) $menuLabel); ?>
        </option>
    <?php } ?>
    <?php foreach ($communitySiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$communityBoardSidebarSiteMenuSelectOptions = static function (string $selectedMenuKey) use ($communitySiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>선택 안 함</option>
    <?php foreach ($communitySiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$communityLayoutExtraMenuItems = function_exists('sr_community_layout_extra_menu_items_from_settings') ? sr_community_layout_extra_menu_items_from_settings($settings) : [];
if (is_array($adminFormDraft ?? null)) {
    $communityLayoutExtraMenuItems = sr_admin_form_draft_parallel_rows((array) $adminFormDraft['payload'], [
        'area_key' => 'layout_extra_menu_area_keys',
        'label' => 'layout_extra_menu_labels',
        'menu_key' => 'layout_extra_menu_keys',
    ]);
}
$communityLayoutExtraMenuRows = static function (array $menuItems, bool $template = false) use ($communitySiteMenuSelectOptions): void {
    foreach ($template ? [['area_key' => '', 'label' => '', 'menu_key' => '']] : $menuItems as $menuItem) {
        $areaKey = is_array($menuItem) ? (string) ($menuItem['area_key'] ?? '') : '';
        $menuLabel = is_array($menuItem) ? (string) ($menuItem['label'] ?? '') : '';
        $selectedMenuKey = is_array($menuItem) ? (string) ($menuItem['menu_key'] ?? '') : (string) $menuItem;
        ?>
        <div class="admin-layout-menu-row"<?php echo $template ? ' hidden data-admin-layout-menu-template' : ''; ?> data-admin-layout-menu-row>
            <input type="text" name="layout_extra_menu_area_keys[]" value="<?php echo sr_e($areaKey); ?>" class="form-input admin-layout-menu-key-input" maxlength="60" pattern="(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="자동 식별값" aria-label="추가 메뉴 자동 식별값" readonly data-admin-layout-menu-key data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <input type="text" name="layout_extra_menu_labels[]" value="<?php echo sr_e($menuLabel); ?>" class="form-input" maxlength="80" placeholder="이름" aria-label="추가 메뉴 이름" data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <select name="layout_extra_menu_keys[]" class="form-select" data-admin-layout-menu-select data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
                <?php $communitySiteMenuSelectOptions((string) $selectedMenuKey); ?>
            </select>
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger admin-layout-menu-remove" data-admin-layout-menu-remove aria-label="추가 메뉴 제거" title="제거"><?php echo sr_material_icon_html('delete'); ?></button>
        </div>
        <?php
    }
};
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_community_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? sr_t('community::ui.text.706623d8') . implode(', ', $assetDeductionPriorityLabels)
    : sr_t('community::ui.text.3e195cdd');
$communityAssetAuditUrl = sr_admin_asset_settings_audit_url('community.settings.asset_settings.updated', 'module', 'community');
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
$privacyConsentDocumentOptions = isset($privacyConsentDocumentOptions) && is_array($privacyConsentDocumentOptions) ? $privacyConsentDocumentOptions : [];
$privacyConsentDocumentSelectOptionsHtml = static function (string $selectedDocumentKey) use ($privacyConsentDocumentOptions): string {
    $html = '<option value="">' . sr_e('선택 안 함') . '</option>';
    foreach ($privacyConsentDocumentOptions as $privacyConsentDocumentKey => $privacyConsentDocumentOption) {
        $privacyConsentDocumentTitle = is_array($privacyConsentDocumentOption)
            ? (string) ($privacyConsentDocumentOption['title'] ?? $privacyConsentDocumentKey)
            : (string) $privacyConsentDocumentOption;
        $html .= '<option value="' . sr_e((string) $privacyConsentDocumentKey) . '"' . ($selectedDocumentKey === (string) $privacyConsentDocumentKey ? ' selected' : '') . '>'
            . sr_e($privacyConsentDocumentTitle)
            . '</option>';
    }

    return $html;
};
$thumbnailCriterionValue = sr_community_thumbnail_criterion((string) ($settings['thumbnail_criterion'] ?? 'width'));
$canViewCommunityThumbnailFileCache = !empty($canViewCommunityThumbnailFileCache);
$canViewCommunityEmbedManager = !empty($canViewCommunityEmbedManager);
$levelScoreHelpModalId = 'community-level-score-help-modal';
$levelScoreHelpBodyHtml = '<p>' . sr_e(sr_t('community::ui.level_score_help_global_default')) . '</p>'
    . '<p>' . sr_e(sr_t('community::ui.level_score_help_formula')) . '</p>'
    . '<ul>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_priority')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_group_initial')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_board_initial')) . '</li>'
    . '</ul>';
$communitySettingsHelpOpenLabel = sr_t('community::help.open');
$communitySettingsHelpButtonHtml = static function (string $label, string $modalId) use ($communitySettingsHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $communitySettingsHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$communitySettingsHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$communitySettingsHelp = [
    'level_feature' => [
        'id' => 'community_settings_help_level_feature',
        'title' => sr_t('community::help.level_feature.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_feature.body.1', 'community::help.level_feature.body.2']),
    ],
    'level_auto_recalculate' => [
        'id' => 'community_settings_help_level_auto_recalculate',
        'title' => sr_t('community::help.level_auto_recalculate.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_auto_recalculate.body.1', 'community::help.level_auto_recalculate.body.2']),
    ],
    'asset_settings' => [
        'id' => 'community_settings_help_asset_settings',
        'title' => sr_t('community::help.asset_settings.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.asset_settings.body.1', 'community::help.asset_settings.body.2', 'community::help.asset_settings.body.3'])
            . '<p>회원 그룹별 적용을 선택하면 회원의 그룹, 레벨과 이용 대상에 맞는 규칙으로 실제 지급·차감 금액을 계산합니다. 규칙의 계산 방식과 조정값은 커뮤니티 회원 그룹별 설정 화면에서 관리합니다.</p>',
    ],
    'once_history_policy' => [
        'id' => 'community_settings_help_once_history_policy',
        'title' => sr_t('community::ui.once_history_policy.label'),
        'body' => '<p>' . sr_e('유료 열람이나 첨부 다운로드를 최초 1회 결제로 운영할 때, 예전에 이용한 회원을 다시 결제시킬지 정합니다.') . '</p>'
            . '<ul>'
            . '<li><strong>' . sr_e('결제/쿠폰 이력') . '</strong>: ' . sr_e('포인트, 예치금, 적립금 결제나 쿠폰 이용 이력이 있으면 다시 결제하지 않습니다.') . '</li>'
            . '<li><strong>' . sr_e('결제 이력만') . '</strong>: ' . sr_e('포인트, 예치금, 적립금으로 결제한 이력만 인정하고 쿠폰 이용자는 다시 결제합니다.') . '</li>'
            . '<li><strong>' . sr_e('현재 결제수단 이력만') . '</strong>: ' . sr_e('지금 선택한 결제수단으로 최초 1회 결제한 이력만 인정합니다. 예를 들어 지금 포인트만 받으면 예전에 포인트로 결제한 회원만 다시 결제하지 않습니다.') . '</li>'
            . '</ul>'
            . '<p>' . sr_e('이 설정은 앞으로의 재결제 여부만 바꾸며, 기존 원장 거래와 쿠폰 사용 로그를 환불하거나 추가 차감하지 않습니다.') . '</p>',
    ],
    'layout' => [
        'id' => 'community_settings_help_layout',
        'title' => sr_t('community::help.layout.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.layout.body.1', 'community::help.layout.body.2']),
    ],
    'theme' => [
        'id' => 'community_settings_help_theme',
        'title' => sr_t('community::help.theme.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.theme.body.1', 'community::help.theme.body.2']),
    ],
    'level_min_score' => [
        'id' => 'community_settings_help_level_min_score',
        'title' => sr_t('community::help.level_min_score.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_min_score.body.1', 'community::help.level_min_score.body.2']),
    ],
    'report_auto_action' => [
        'id' => 'community_settings_help_report_auto_action',
        'title' => '신고 자동 임시 조치 도움말',
        'body' => '<p>게시글이나 댓글에 정해진 수 이상의 서로 다른 회원이 신고하면 시스템이 먼저 공개 화면에서 숨기고, 운영자가 신고 관리 화면에서 확정·해제·기각을 판단하게 합니다. 같은 회원의 여러 신고는 한 명으로 계산하고 기각된 신고는 제외합니다.</p>'
            . '<p>집계 기간이 0일이면 마지막 운영자 처리 뒤의 전체 신고를, 1일 이상이면 그 기간 안의 신고만 계산합니다.</p>'
            . '<p>현재는 공개 처리 방식에서 어느 값을 선택해도 자동 숨김 대상을 목록에서 제외합니다. 대체 문구 선택은 이후 표시 방식을 위한 기준값만 저장합니다.</p>',
    ],
    'publication_hold' => [
        'id' => 'community_settings_help_publication_hold',
        'title' => '반복 신고 작성자 게시 보류 도움말',
        'body' => '<p>같은 작성자의 서로 다른 게시글 여러 개가 동시에 자동 임시 조치되면, 정해진 기간 동안 그 회원의 새 게시글을 바로 공개하지 않고 검토 대기로 저장합니다. 댓글 자동 임시 조치는 이 기준에 포함하지 않습니다.</p>'
            . '<p>각 게시글의 신고자가 설정한 비율 이상 서로 겹치면 조직적인 중복 신고 가능성이 있다고 보고 자동 게시 보류 대신 운영자 검토 대상으로 남깁니다.</p>'
            . '<p>보류 기간이 끝나도 이미 검토 대기로 저장된 글은 자동 공개하지 않습니다. 운영자가 직접 검토해야 합니다.</p>',
    ],
    'confirmed_hold' => [
        'id' => 'community_settings_help_confirmed_hold',
        'title' => '확정 조치 반복 게시 보류 도움말',
        'body' => '<p>설정한 집계 기간 안에 운영자가 확정한 신고 자동조치가 기준 건수 이상 쌓이면 해당 작성자의 새 게시글을 일정 시간 검토 대기로 보냅니다. 확정된 게시글과 댓글 조치를 함께 계산합니다.</p>'
            . '<p>운영자가 해제하거나 기각한 조치는 계산하지 않습니다. 이 자동 조치는 회원 계정을 정지하지 않고 커뮤니티의 새 게시글 공개만 보류합니다.</p>',
    ],
    'multi_asset' => [
        'id' => 'community_settings_help_multi_asset',
        'title' => '여러 포인트·금액 함께 결제 도움말',
        'body' => '<p>유료 게시글 열람이나 첨부 다운로드 한 건을 결제할 때 포인트, 예치금, 적립금처럼 서로 다른 포인트·금액 항목을 함께 사용할 수 있게 합니다.</p>'
            . '<p>끄면 한 건의 결제에는 한 종류만 사용할 수 있습니다. 쿠폰으로 일부 할인한 뒤 남은 금액을 결제할 때도 같은 제한을 적용합니다.</p>',
    ],
    'drafts' => [
        'id' => 'community_settings_help_drafts',
        'title' => '게시글 자동 임시저장 도움말',
        'body' => '<p>로그인 회원이 게시글을 작성하거나 수정할 때 제목, 본문, 카테고리와 선택 항목을 서버에 임시저장합니다. 비회원 게시글, 댓글, 첨부파일 선택값은 저장하지 않습니다.</p>'
            . '<p>내용이 바뀌었을 때만 설정한 간격으로 저장합니다. 서버 저장에 실패하면 같은 브라우저 탭에 복구용 내용을 남기며, 여러 탭에서 같은 글을 편집하면 마지막으로 저장된 내용이 우선합니다.</p>'
            . '<p>보존 기간이 지난 임시저장본과 계정별 최대 개수를 넘은 오래된 임시저장본은 작성 화면을 열거나 자동저장에 성공할 때 일부씩 정리합니다. 정해진 시각에 한꺼번에 삭제하는 방식은 아닙니다.</p>',
    ],
    'reaction' => [
        'id' => 'community_settings_help_reaction',
        'title' => '기본 반응 구성 도움말',
        'body' => '<p>반응 구성은 좋아요 같은 반응 버튼의 종류와 표시 방식을 묶어 둔 설정이며, 리액션 모듈에서는 프리셋이라고 부릅니다.</p>'
            . '<p>게시판 그룹이나 게시판에서 별도 구성을 선택하지 않았을 때 이 값을 사용합니다. 더 구체적인 게시판 설정이 전체 기본값보다 우선합니다.</p>',
    ],
    'thumbnail' => [
        'id' => 'community_settings_help_thumbnail',
        'title' => '첨부 이미지 썸네일 도움말',
        'body' => '<p>이 설정은 게시글 읽기 화면의 첨부 이미지 미리보기를 작은 캐시 이미지로 만들지 정합니다. 게시판에서 별도 값을 정하면 게시판 설정이 우선합니다.</p>'
            . '<p>게시글 목록 이미지는 공개 첨부 이미지가 있으면 이 설정과 관계없이 캐시 썸네일을 우선 사용합니다. 너비 또는 파일 용량 중 선택한 기준 하나만 읽기 화면 미리보기에 적용합니다.</p>'
            . '<p>캐시 파일을 정리해도 원본 첨부파일과 게시글은 삭제되지 않으며 필요할 때 다시 생성할 수 있습니다.</p>',
    ],
    'menus' => [
        'id' => 'community_settings_help_menus',
        'title' => '공개 화면 메뉴 도움말',
        'body' => '<p>주 메뉴는 화면 틀이 기본 메뉴 위치에 표시할 메뉴입니다. 추가 메뉴는 화면 틀이 여러 메뉴 위치를 지원할 때 함께 전달할 메뉴입니다.</p>'
            . '<p>추가 메뉴의 이름은 화면 틀이 각 메뉴 영역을 구분할 때 쓰는 값이며, 자동 식별값은 저장 항목을 구분하므로 직접 수정하지 않습니다. 실제 표시 위치와 지원 개수는 선택한 화면 틀에 따라 달라집니다.</p>',
    ],
    'editor_default' => [
        'id' => 'community_settings_help_editor_default',
        'title' => '게시글 입력 방식 기본값 도움말',
        'body' => '<p>새 게시판을 만들 때 기본으로 선택할 게시글 입력 방식입니다. 기존 게시판에 따로 저장된 값은 자동으로 바꾸지 않습니다.</p>'
            . '<p>다만 게시글은 작성 당시의 입력 방식을 별도로 저장하지 않습니다. 이 전체 기본값을 그대로 따르는 기존 게시판은 설정을 바꾸면 예전에 작성한 게시글의 공개 표시 방식도 달라질 수 있으므로 변경 전에 확인하세요.</p>',
    ],
    'body_length' => [
        'id' => 'community_settings_help_body_length',
        'title' => '게시글 본문 길이 기본값 도움말',
        'body' => '<p>새 게시판을 만들 때 사용할 본문 최소·최대 길이 기본값입니다. 게시판에서 별도 값을 정하면 해당 게시판 설정이 우선하며 기존 게시판 값은 자동으로 바뀌지 않습니다.</p>'
            . '<p>0은 해당 길이를 검사하지 않는다는 뜻입니다. 최대 길이를 0으로 두어도 데이터베이스 용량과 웹 서버의 요청 크기 제한을 넘는 본문은 저장할 수 없습니다.</p>',
    ],
    'embed' => [
        'id' => 'community_settings_help_embed',
        'title' => '주소 자동 표시 도움말',
        'body' => '<p>임베드는 게시글에 입력한 주소를 동영상 플레이어나 미리보기 카드처럼 바로 볼 수 있는 형태로 바꾸어 표시하는 기능입니다.</p>'
            . '<p>외부 서비스 자동 표시는 지원하는 YouTube, X, Instagram 주소에 적용합니다. 사이트 콘텐츠 자동 표시는 다른 모듈 주소를 미리보기로 바꾸고, 커뮤니티 게시글 주소도 다른 본문에서 미리보기로 사용할 수 있게 합니다.</p>'
            . '<p>설정을 꺼도 저장된 게시글 주소는 삭제되지 않으며 공개 화면에서 자동 표시만 하지 않습니다.</p>',
    ],
    'auto_link' => [
        'id' => 'community_settings_help_auto_link',
        'title' => '일반 텍스트 URL 링크 도움말',
        'body' => '<p>일반 텍스트 입력 방식으로 저장한 게시글에서 URL을 찾아 클릭할 수 있는 링크로 바꿉니다. 새 탭을 함께 켜면 이렇게 자동으로 만든 링크만 새 탭에서 엽니다.</p>'
            . '<p>Markdown이나 HTML 방식의 게시글에 작성자가 직접 만든 링크에는 적용하지 않습니다. 설정을 바꿔도 저장된 게시글 내용은 변경되지 않습니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communitySettingsPage === 'settings') { ?>
<?php
$communitySettingsSectionNavItems = [
    'community-settings-section-level' => '레벨 기본값',
    'community-settings-section-identity' => '본인확인',
    'community-settings-section-report-auto-action' => '신고 자동조치',
    'community-settings-section-privacy-consent' => '개인정보 동의',
    'community-settings-section-assets' => '포인트·금액/결제',
    'community-settings-section-series' => '시리즈',
    'community-settings-section-drafts' => '임시저장',
    'community-settings-section-reaction' => '리액션',
    'community-settings-section-thumbnail' => '썸네일',
    'community-settings-section-display' => '공개 화면',
    'community-post-extra-fields-json-section' => '게시글 추가 입력',
    'community-comment-extra-fields-json-section' => '댓글 추가 입력',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="커뮤니티 설정 섹션">
    <?php $communitySettingsSectionNavIndex = 0; ?>
    <?php foreach ($communitySettingsSectionNavItems as $communitySettingsSectionId => $communitySettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $communitySettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $communitySettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $communitySettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $communitySettingsSectionLabel); ?>
        </a>
        <?php $communitySettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form id="community-settings-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="admin-form ui-form-theme" data-community-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">
    <?php echo sr_admin_form_draft_status_html($adminFormDraft ?? null, 'community-settings-form'); ?>
    <input type="hidden" name="level_max_change_confirmed" value="0" data-community-settings-level-max-confirmed>
    <input type="hidden" name="level_max_change_confirm_text" value="" data-community-settings-level-max-confirm-text>

    <section id="community-settings-section-level" class="card" data-admin-section-anchor>
        <h2>레벨 기본값</h2>
        <div class="form-grid">
            <div class="form-row">
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.7d97b5a5'), $communitySettingsHelp['level_feature']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></span></div>
                <div class="form-field">
                    <label class="form-check form-label" for="modules_community_admin_settings_level_enabled">
                        <input id="modules_community_admin_settings_level_enabled" type="checkbox" name="level_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?> data-community-level-enabled>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_display_name">레벨 표시명 <span class="sr-required-label" data-community-level-required-label<?php echo !empty($settings['level_enabled']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_display_name" type="text" name="level_display_name" maxlength="40" value="<?php echo sr_e((string) ($settings['level_display_name'] ?? '레벨')); ?>"<?php echo !empty($settings['level_enabled']) ? ' required' : ''; ?> class="form-input" data-community-level-required-field>
                    <p class="form-help">회원 화면과 관리자 선택지에서 레벨을 부를 이름입니다.</p>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_short_label">레벨 약칭</label>
                <div class="form-field">
                    <input id="community_admin_settings_level_short_label" type="text" name="level_short_label" maxlength="20" value="<?php echo sr_e((string) ($settings['level_short_label'] ?? 'Lv.')); ?>" class="form-input">
                    <p class="form-help">아바타 아래나 회원 드롭다운처럼 좁은 곳에서 쓰는 짧은 이름입니다. 비워 두면 표시명을 사용합니다.</p>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.f9447e05'), $communitySettingsHelp['level_auto_recalculate']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.f9447e05')); ?></span></div>
                <div class="form-field">
                    <label class="form-check form-label" for="modules_community_admin_settings_level_auto_recalculate">
                        <input id="modules_community_admin_settings_level_auto_recalculate" type="checkbox" name="level_auto_recalculate" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?> data-community-level-auto-toggle>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                </div>
            </div>
            <div class="form-row" data-community-level-dependent-field>
                <label class="form-label" for="community_admin_settings_level_max_value"><?php echo sr_e(sr_t('community::ui.level_max_value')); ?> <span class="sr-required-label" data-community-level-required-label<?php echo !empty($settings['level_enabled']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_max_value" type="number" name="level_max_value" min="1" max="100" value="<?php echo sr_e((string) $settings['level_max_value']); ?>"<?php echo !empty($settings['level_enabled']) ? ' required' : ''; ?> class="form-input" data-community-settings-level-max-value data-community-settings-level-max-initial="<?php echo sr_e((string) $settings['level_max_value']); ?>" data-community-level-required-field>
                    <p class="form-help"><?php echo sr_e(sr_t('community::ui.level_max_value_help')); ?></p>
                </div>
            </div>
            <div class="form-row"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?> data-community-level-auto-field>
                <label class="form-label" for="community_admin_settings_level_post_score"><?php echo sr_e(sr_t('community::ui.text.99092cba')); ?> <span class="sr-required-label" data-community-level-auto-required-label<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? ' required' : ''; ?> class="form-input" data-community-level-auto-required-field>
                </div>
            </div>
            <div class="form-row"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?> data-community-level-auto-field>
                <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.96af1f5c'), $levelScoreHelpModalId); ?><span><?php echo sr_e(sr_t('community::ui.text.96af1f5c')); ?> <span class="sr-required-label" data-community-level-auto-required-label<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? '' : ' hidden'; ?>>(필수)</span></span></div>
                <div class="form-field">
                    <input id="community_admin_settings_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>"<?php echo !empty($settings['level_enabled']) && !empty($settings['level_auto_recalculate']) ? ' required' : ''; ?> class="form-input" data-community-level-auto-required-field>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-identity" class="card" data-admin-section-anchor>
        <h2>본인확인</h2>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_identity_restricted_board_required">제한 게시판 본인확인</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_identity_restricted_board_required', 'identity_restricted_board_required', '1', $communityIdentityRestrictedBoardAvailable && !empty($settings['identity_restricted_board_required']), '사용', '', $communityIdentityVerificationInputAttributes); ?>
                <p class="form-help">읽기 정책이 회원/그룹이거나 읽기 레벨/그룹 제한이 있는 게시판은 본인확인을 마친 회원만 볼 수 있게 합니다.</p>
                <?php if (!$communityIdentityRestrictedBoardAvailable) { ?>
                    <p id="community-settings-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 제한 게시판 목적을 지원하는 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
    </section>

    <section id="community-settings-section-report-auto-action" class="card" data-admin-section-anchor>
        <h2>신고 자동조치</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_report_auto_action_enabled', '자동 임시 조치', $communitySettingsHelp['report_auto_action']['id'], $communitySettingsHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('community_admin_settings_report_auto_action_enabled', 'report_auto_action_enabled', '1', !empty($settings['report_auto_action_enabled']), '사용'); ?>
                    <p class="form-help">신고자가 기준 수에 도달하면 먼저 숨기고 운영자 검토 대상으로 보냅니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_report_auto_action_threshold">임계 신고자 수 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_report_auto_action_threshold" type="number" name="report_auto_action_threshold" min="2" max="100" value="<?php echo sr_e((string) (int) ($settings['report_auto_action_threshold'] ?? 5)); ?>" required class="form-input">
                    <p class="form-help">서로 다른 신고자 수를 기준으로 계산합니다. 기각된 신고는 임계치 집계에서 제외합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_report_auto_action_window_days">집계 기간 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_report_auto_action_window_days" type="number" name="report_auto_action_window_days" min="0" max="365" value="<?php echo sr_e((string) (int) ($settings['report_auto_action_window_days'] ?? 0)); ?>" required class="form-input">
                    <p class="form-help">0이면 전체 신고 이력을 기준으로 계산합니다. 1 이상이면 최근 N일 신고만 집계합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml('공개 처리 방식', $communitySettingsHelp['report_auto_action']['id']); ?><span>공개 처리 방식 <span class="sr-required-label">(필수)</span></span></span>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('community_admin_settings_report_auto_action_public_mode', 'report_auto_action_public_mode', ['exclude' => '목록 제외', 'placeholder' => '대체 문구'], (string) ($settings['report_auto_action_public_mode'] ?? 'exclude'), true); ?>
                    <p class="form-help">현재는 두 선택 모두 자동 숨김 대상을 목록에서 제외합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_account_guard_publication_hold_enabled', '반복 신고 작성자 게시 보류', $communitySettingsHelp['publication_hold']['id'], $communitySettingsHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('community_admin_settings_account_guard_publication_hold_enabled', 'account_guard_publication_hold_enabled', '1', !empty($settings['account_guard_publication_hold_enabled']), '사용'); ?>
                    <p class="form-help">여러 게시글이 동시에 자동 숨김되면 작성자의 새 게시글을 검토 대기로 보냅니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_account_guard_publication_hold_threshold">자동조치 게시글 수 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_account_guard_publication_hold_threshold" type="number" name="account_guard_publication_hold_threshold" min="2" max="20" value="<?php echo sr_e((string) (int) ($settings['account_guard_publication_hold_threshold'] ?? 3)); ?>" required class="form-input">
                    <p class="form-help">서로 다른 게시글이 자동 임시 조치된 건수를 기준으로 합니다. 한 게시글만으로는 작성자 게시 보류를 만들지 않습니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_account_guard_publication_hold_duration_minutes">게시 보류 기간 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <div class="input-group">
                        <input id="community_admin_settings_account_guard_publication_hold_duration_minutes" type="number" name="account_guard_publication_hold_duration_minutes" min="10" max="10080" value="<?php echo sr_e((string) (int) ($settings['account_guard_publication_hold_duration_minutes'] ?? 120)); ?>" required class="form-input">
                        <span class="input-group-text">분</span>
                    </div>
                    <p class="form-help">기간이 끝나도 보류 중 작성된 검토 대기 게시글은 자동 공개하지 않고 관리자 검토 대상으로 남깁니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_account_guard_publication_hold_overlap_review_percent', '신고자 중복률 검토 기준', $communitySettingsHelp['publication_hold']['id'], $communitySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group">
                        <input id="community_admin_settings_account_guard_publication_hold_overlap_review_percent" type="number" name="account_guard_publication_hold_overlap_review_percent" min="0" max="100" value="<?php echo sr_e((string) (int) ($settings['account_guard_publication_hold_overlap_review_percent'] ?? 80)); ?>" required class="form-input">
                        <span class="input-group-text">%</span>
                    </div>
                    <p class="form-help">신고자가 이 비율 이상 겹치면 자동 게시 보류 대신 운영자 검토 대상으로 남깁니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_account_guard_confirmed_hold_enabled', '확정 조치 반복 게시 보류', $communitySettingsHelp['confirmed_hold']['id'], $communitySettingsHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('community_admin_settings_account_guard_confirmed_hold_enabled', 'account_guard_confirmed_hold_enabled', '1', !empty($settings['account_guard_confirmed_hold_enabled']), '사용'); ?>
                    <p class="form-help">운영자가 확정한 반복 조치만 계산해 새 게시글을 검토 대기로 보냅니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_account_guard_confirmed_hold_threshold">확정 조치 건수 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="community_admin_settings_account_guard_confirmed_hold_threshold" type="number" name="account_guard_confirmed_hold_threshold" min="2" max="20" value="<?php echo sr_e((string) (int) ($settings['account_guard_confirmed_hold_threshold'] ?? 3)); ?>" required class="form-input">
                    <p class="form-help">해제했거나 기각한 신고/자동조치는 작성자 불이익 집계에 포함하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_account_guard_confirmed_hold_window_days">확정 조치 집계 기간 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <div class="input-group">
                        <input id="community_admin_settings_account_guard_confirmed_hold_window_days" type="number" name="account_guard_confirmed_hold_window_days" min="1" max="365" value="<?php echo sr_e((string) (int) ($settings['account_guard_confirmed_hold_window_days'] ?? 30)); ?>" required class="form-input">
                        <span class="input-group-text">일</span>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_account_guard_confirmed_hold_duration_minutes">반복 보류 기간 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <div class="input-group">
                        <input id="community_admin_settings_account_guard_confirmed_hold_duration_minutes" type="number" name="account_guard_confirmed_hold_duration_minutes" min="10" max="10080" value="<?php echo sr_e((string) (int) ($settings['account_guard_confirmed_hold_duration_minutes'] ?? 1440)); ?>" required class="form-input">
                        <span class="input-group-text">분</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-privacy-consent" class="card" data-admin-section-anchor>
        <h2>개인정보 수집 및 이용동의 기본값</h2>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="community_admin_settings_privacy_consent_enabled">동의 사용</label>
                <div class="form-field">
                    <label class="form-check form-label" for="community_admin_settings_privacy_consent_enabled">
                        <input id="community_admin_settings_privacy_consent_enabled" type="checkbox" name="privacy_consent_enabled" value="1" class="form-switch form-switch-light"<?php echo $communityPrivacyConsentPolicyDocumentsAvailable && !empty($settings['privacy_consent_enabled']) ? ' checked' : ''; ?><?php echo $communityPrivacyConsentInputAttributes; ?> data-community-privacy-consent-enabled>
                        <?php echo sr_admin_choice_label_html('사용'); ?>
                    </label>
                    <p class="form-help">게시판 개별 설정에서 다른 값으로 재정의할 수 있습니다.</p>
                    <?php if (!$communityPrivacyConsentPolicyDocumentsAvailable) { ?>
                        <p id="community-settings-privacy-consent-unavailable" class="form-help form-help-warning">
                            <a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">약관/방침 관리 모듈</a>이 설치되어 있지 않거나 활성화되어 있지 않고, <a href="<?php echo sr_e(sr_url('/admin/policy-documents')); ?>" target="_blank" rel="noopener noreferrer">게시된 정책 문서</a>가 없어 개인정보 수집 및 이용동의 설정을 사용할 수 없습니다.
                        </p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row" data-admin-required-selection-mode="any">
                <span class="form-label">동의 적용 대상 <span class="sr-required-label" data-community-privacy-consent-required<?php echo $communityPrivacyConsentPolicyDocumentsAvailable && !empty($settings['privacy_consent_enabled']) ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                <div class="form-field" data-community-privacy-consent-controls>
                    <div class="community-privacy-consent-document-list">
                        <?php foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) { ?>
                            <?php $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey); ?>
                            <label class="community-privacy-consent-document-row" for="<?php echo sr_e('community_admin_settings_' . $privacyConsentDocumentSettingKey); ?>">
                                <span><?php echo sr_e(sr_community_privacy_consent_admin_label($privacyConsentTargetKey)); ?></span>
                                <select id="<?php echo sr_e('community_admin_settings_' . $privacyConsentDocumentSettingKey); ?>" name="<?php echo sr_e($privacyConsentDocumentSettingKey); ?>" class="form-select"<?php echo $communityPrivacyConsentInputAttributes; ?> data-community-privacy-consent-document="<?php echo sr_e($privacyConsentTargetKey); ?>">
                                    <?php echo $privacyConsentDocumentSelectOptionsHtml(sr_community_privacy_consent_admin_document_key_from_settings($settings, $privacyConsentTargetKey)); ?>
                                </select>
                            </label>
                        <?php } ?>
                    </div>
                    <p class="form-help">동의 사용 시 3가지 중 하나 이상 정책 문서를 선택해야 하며, 선택 안 함인 대상에는 동의를 적용하지 않습니다.</p>
                    <input type="hidden" name="privacy_consent_title" value="">
                    <input type="hidden" name="privacy_consent_version" value="">
                    <input type="hidden" name="privacy_consent_body" value="">
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-assets" class="card" data-admin-section-anchor>
        <h2>
            <span>포인트·금액/결제</span>
            <span class="form-actions">
                <a href="<?php echo sr_e($communityAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 설정 변경 이력'); ?></a>
            </span>
        </h2>
        <div class="form-grid">
            <?php foreach ([
                'post_reward' => sr_t('community::ui.text.a3cc976c'),
                'comment_reward' => sr_t('community::ui.text.bb39df0e'),
                'write_charge' => sr_t('community::ui.text.ce1392a2'),
                'comment_charge' => sr_t('community::ui.text.629c5136'),
                'paid_read' => sr_t('community::ui.text.c9b3e6f0'),
                'paid_attachment_download' => sr_t('community::ui.text.5b864b9e'),
            ] as $assetPrefix => $assetLabel) { ?>
                <?php $assetEnabledId = 'modules_community_admin_settings_' . (string) $assetPrefix . '_enabled'; ?>
                <?php $assetSourceId = 'community_admin_settings_' . (string) $assetPrefix . '_asset_source'; ?>
                <?php $isRewardAsset = in_array((string) $assetPrefix, ['post_reward', 'comment_reward'], true); ?>
                <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($settings[$assetPrefix . '_asset_module'] ?? '', true); ?>
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml($assetLabel, $communitySettingsHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?> 사용</span></div>
                    <div class="form-field">
                        <div class="admin-asset-setting-line">
                            <div class="admin-asset-setting-control">
                                <div class="admin-asset-setting-primary">
                                    <label class="form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                        <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings[$assetPrefix . '_enabled']) ? ' checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html('사용'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($isRewardAsset) { ?>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e($assetLabel . ' 회수'); ?></span>
                        <div class="form-field">
                            <label class="form-check form-label" for="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>">
                                <input id="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_reversal_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings[$assetPrefix . '_reversal_enabled']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('회수'); ?>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($assetPrefix === 'paid_read') { ?>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_read_charge_policy"><?php echo sr_e(sr_t('community::ui.text.05ead7ab')); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_paid_read_charge_policy', 'paid_read_charge_policy', ['once' => sr_t('community::ui.text.6eb4fe4e'), 'every_view' => sr_t('community::ui.text.53e8d077')], (string) ($settings['paid_read_charge_policy'] ?? 'once')); ?>
                        </div>
                    </div>
                <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_attachment_download_charge_policy"><?php echo sr_e(sr_t('community::ui.text.978f8b2e')); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_paid_attachment_download_charge_policy', 'paid_attachment_download_charge_policy', ['once' => sr_t('community::ui.text.6eb4fe4e'), 'every_download' => sr_t('community::ui.text.e9d14df2')], (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once')); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <span class="form-label">게시자 보상</span>
                        <div class="form-field">
                            <label class="form-check form-label" for="modules_community_admin_settings_paid_attachment_download_publisher_reward_enabled">
                                <input id="modules_community_admin_settings_paid_attachment_download_publisher_reward_enabled" type="checkbox" name="paid_attachment_download_publisher_reward_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['paid_attachment_download_publisher_reward_enabled']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('지급'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="modules_community_admin_settings_paid_attachment_download_publisher_reward_rate">게시자 보상 지급률</label>
                        <div class="form-field">
                            <div class="input-group admin-asset-single-amount-group admin-community-publisher-reward-rate-group">
                                <input id="modules_community_admin_settings_paid_attachment_download_publisher_reward_rate" type="number" min="0" max="100" name="paid_attachment_download_publisher_reward_rate" value="<?php echo sr_e((string) (int) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0)); ?>" class="form-input admin-community-publisher-reward-rate-input">
                                <span class="input-group-text">%</span>
                            </div>
                            <p class="form-help">실제로 차감한 것과 같은 포인트·금액 항목으로 게시글 작성자에게 지급합니다. 본인 다운로드, 무료 이용, 최초 1회 방식에서 이미 결제한 다운로드는 지급하지 않습니다.</p>
                        </div>
                    </div>
                <?php } ?>
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e($assetLabel . ' 포인트·금액'); ?></span>
                    <div class="form-field">
                        <?php if ($isRewardAsset) { ?>
                            <div class="admin-asset-setting-target admin-asset-single-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                <select id="<?php echo sr_e($assetSourceId); ?>" name="<?php echo sr_e((string) $assetPrefix); ?>_asset_module" class="form-select" data-admin-asset-unit-select>
                                    <option value=""><?php echo sr_e($assetModuleOptions === [] ? sr_t('community::ui.text.3e195cdd') : sr_t('community::ui.text.asset_none')); ?></option>
                                    <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                        <option value="<?php echo sr_e((string) $assetModule); ?>" data-admin-asset-unit="<?php echo sr_e((string) ($assetOption['unit_label'] ?? '')); ?>"<?php echo (string) ($settings[$assetPrefix . '_asset_module'] ?? '') === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                    <?php } ?>
                                </select>
                                <?php echo sr_community_asset_single_amount_input_group_html((string) $assetPrefix . '_amount', (int) ($settings[$assetPrefix . '_amount'] ?? 0), $assetModuleOptions, (string) ($settings[$assetPrefix . '_asset_module'] ?? ''), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel])); ?>
                            </div>
                        <?php } else { ?>
                            <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                <?php echo sr_community_asset_grouped_amount_inputs_html($assetSourceId, (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $settings[$assetPrefix . '_amounts_json'] ?? '', (int) ($settings[$assetPrefix . '_amount'] ?? 0), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                            </div>
                            <input type="hidden" name="<?php echo sr_e((string) $assetPrefix); ?>_amount" value="<?php echo sr_e((string) ($settings[$assetPrefix . '_amount'] ?? 0)); ?>">
                            <p class="form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $communitySettingsHelpButtonHtml($assetLabel . ' 회원 그룹별 적용', $communitySettingsHelp['asset_settings']['id']); ?><label for="<?php echo sr_e('community_settings_' . (string) $assetPrefix . '_policy_set_ids'); ?>"><?php echo sr_e('회원 그룹별 적용'); ?></label></div>
                    <div class="form-field admin-policy-set-field">
                        <?php echo sr_community_asset_policy_set_checkboxes_html('community_settings_' . (string) $assetPrefix . '_policy_set_ids', (string) $assetPrefix . '_policy_set_ids', $assetPolicySets ?? [], sr_community_asset_policy_set_ids_with_legacy($settings[$assetPrefix . '_group_policies_json'] ?? '', (int) ($settings[$assetPrefix . '_policy_set_id'] ?? 0)), $isRewardAsset ? 'grant' : 'use', '#' . $assetSourceId, $pdo); ?>
                        <p class="form-help">회원의 그룹과 레벨에 맞는 규칙으로 실제 지급·차감 금액을 계산합니다.</p>
                    </div>
                </div>
            <?php } ?>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_multi_asset_payment_enabled', '여러 포인트·금액 함께 결제', $communitySettingsHelp['multi_asset']['id'], $communitySettingsHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('community_admin_settings_multi_asset_payment_enabled', 'multi_asset_payment_enabled', '1', !empty($settings['multi_asset_payment_enabled']), '허용'); ?>
                    <p class="form-help">한 건의 유료 열람이나 다운로드에 여러 포인트·금액 항목을 함께 쓸 수 있게 합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('modules_community_admin_settings_once_history_policy', sr_t('community::ui.once_history_policy.label'), $communitySettingsHelp['once_history_policy']['id'], $communitySettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('modules_community_admin_settings_once_history_policy', 'once_history_policy', sr_community_once_history_policy_values(), (string) ($settings['once_history_policy'] ?? 'all_access'), true); ?>
                    <p class="form-help"><?php echo sr_e(sr_t('community::ui.once_history_policy.help')); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-series" class="card" data-admin-section-anchor>
        <h2>시리즈</h2>
        <div class="form-row">
            <span class="form-label">시리즈 기능</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_series_enabled', 'series_enabled', '1', !empty($settings['series_enabled']), '사용'); ?>
                <p class="form-help">꺼져 있으면 시리즈 생성, 연결, 관리, 스크랩, 공개 내비게이션과 커뮤니티 메인 시리즈 섹션을 사용하지 않습니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-drafts" class="card" data-admin-section-anchor>
        <h2>임시저장</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_draft_autosave_enabled', '자동 임시저장', $communitySettingsHelp['drafts']['id'], $communitySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('community_admin_settings_draft_autosave_enabled', 'draft_autosave_enabled', '1', !empty($settings['draft_autosave_enabled']), '사용'); ?>
                    <p class="form-help">로그인 회원이 작성·수정 중인 게시글 내용을 서버에 임시로 저장합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_draft_autosave_interval_seconds', '저장 간격', $communitySettingsHelp['drafts']['id'], $communitySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group admin-input-unit">
                        <input id="community_admin_settings_draft_autosave_interval_seconds" type="number" name="draft_autosave_interval_seconds" min="30" max="600" value="<?php echo sr_e((string) (int) ($settings['draft_autosave_interval_seconds'] ?? 60)); ?>" required class="form-input">
                        <span class="input-group-text">초</span>
                    </div>
                    <p class="form-help">내용이 바뀌었을 때만 이 간격으로 저장합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_draft_retention_days', '보존 기간', $communitySettingsHelp['drafts']['id'], $communitySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group admin-input-unit">
                        <input id="community_admin_settings_draft_retention_days" type="number" name="draft_retention_days" min="1" max="30" value="<?php echo sr_e((string) (int) ($settings['draft_retention_days'] ?? 7)); ?>" required class="form-input">
                        <span class="input-group-text">일</span>
                    </div>
                    <p class="form-help">이 기간이 지난 임시저장본은 작성 화면 이용 중에 일부씩 정리합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('community_admin_settings_draft_max_count_per_account', '회원당 최대 개수', $communitySettingsHelp['drafts']['id'], $communitySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="community_admin_settings_draft_max_count_per_account" type="number" name="draft_max_count_per_account" min="1" max="100" value="<?php echo sr_e((string) (int) ($settings['draft_max_count_per_account'] ?? 20)); ?>" required class="form-input">
                    <p class="form-help">최대 개수를 넘으면 오래된 임시저장본부터 정리합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="community-settings-section-reaction" class="card" data-admin-section-anchor>
        <h2>리액션</h2>
        <div class="form-row">
            <span class="form-label">리액션 사용</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_reaction_enabled', 'reaction_enabled', '1', $communityReactionAvailable && !empty($settings['reaction_enabled']), '사용', '', $communityReactionInputAttributes); ?>
                <p class="form-help">꺼져 있으면 커뮤니티 게시글과 댓글의 리액션 위젯을 표시하지 않고, 게시판 목록에도 반응 수를 표시하지 않습니다.</p>
                <?php if (!$communityReactionAvailable) { ?>
                    <p id="community-settings-reaction-unavailable" class="form-help form-help-warning"><a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">리액션 모듈</a>을 설치하고 활성화하면 리액션 설정을 사용할 수 있습니다.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_reaction_post_preset_key', '게시글 기본 반응 구성', $communitySettingsHelp['reaction']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="community_admin_settings_reaction_post_preset_key" name="reaction_post_preset_key" class="form-select"<?php echo $communityReactionInputAttributes; ?>>
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_post_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_reaction_comment_preset_key', '댓글 기본 반응 구성', $communitySettingsHelp['reaction']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="community_admin_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select"<?php echo $communityReactionInputAttributes; ?>>
                    <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">게시판 그룹과 게시판에서 따로 정하지 않았을 때 사용하는 반응 버튼 구성입니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-thumbnail" class="card" data-admin-section-anchor>
        <h2>썸네일</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_thumbnail_enabled', '첨부 이미지 썸네일', $communitySettingsHelp['thumbnail']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_thumbnail_enabled', 'thumbnail_enabled', '1', !empty($settings['thumbnail_enabled']), '사용'); ?>
                <p class="form-help">게시글 읽기 화면의 첨부 이미지 미리보기에 작은 캐시 이미지를 사용합니다.</p>
                <?php if ($canViewCommunityThumbnailFileCache) { ?>
                    <p class="form-help">생성된 공개 썸네일 파일은 <a href="<?php echo sr_e(sr_url('/admin/storage-cache?module_key=community')); ?>">썸네일 파일 캐시</a>에서 확인하고 정리할 수 있습니다. 정리해도 원본 파일과 게시글은 삭제되지 않습니다.</p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">생성 기준 <span class="sr-required-label">(필수)</span></span>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('community_admin_settings_thumbnail_criterion', 'thumbnail_criterion', ['width' => '너비 기준', 'bytes' => '용량 기준'], $thumbnailCriterionValue, true, ' data-community-thumbnail-criterion'); ?>
                <p class="form-help">원본 너비와 파일 용량 중 썸네일을 만들 기준 하나를 선택합니다.</p>
            </div>
        </div>
        <div class="form-row" data-community-thumbnail-rule="width"<?php echo $thumbnailCriterionValue === 'width' ? '' : ' hidden'; ?>>
            <label class="form-label" for="community_admin_settings_thumbnail_min_width">생성 기준 너비 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="community_admin_settings_thumbnail_min_width" type="number" name="thumbnail_min_width" min="1" max="4000" value="<?php echo sr_e((string) ($settings['thumbnail_min_width'] ?? 320)); ?>"<?php echo $thumbnailCriterionValue === 'width' ? ' required' : ''; ?> data-admin-required-when-visible class="form-input">
                    <span class="input-group-text">px</span>
                </div>
                <p class="form-help">너비 기준을 선택했을 때 읽기 화면 첨부 이미지의 원본 너비가 이 값보다 작으면 캐시 썸네일을 만들지 않습니다.</p>
            </div>
        </div>
        <div class="form-row" data-community-thumbnail-rule="bytes"<?php echo $thumbnailCriterionValue === 'bytes' ? '' : ' hidden'; ?>>
            <label class="form-label" for="community_admin_settings_thumbnail_min_bytes">생성 기준 용량 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="admin-community-thumbnail-bytes-line">
                    <div class="input-group admin-input-unit admin-community-thumbnail-bytes-group">
                        <input id="community_admin_settings_thumbnail_min_bytes" type="number" name="thumbnail_min_bytes" min="0" max="20971520" value="<?php echo sr_e((string) ($settings['thumbnail_min_bytes'] ?? 102400)); ?>"<?php echo $thumbnailCriterionValue === 'bytes' ? ' required' : ''; ?> data-admin-required-when-visible data-community-thumbnail-bytes-input class="form-input">
                        <span class="input-group-text">bytes</span>
                    </div>
                    <span class="form-help admin-community-thumbnail-bytes-label" data-community-thumbnail-bytes-label></span>
                </div>
                <p class="form-help">용량 기준을 선택했을 때 읽기 화면 첨부 이미지의 원본 파일 크기가 이 값보다 작으면 캐시 썸네일을 만들지 않습니다. 0이면 모든 용량에서 생성합니다.</p>
            </div>
        </div>
    </section>

    <section id="community-settings-section-display" class="card" data-admin-section-anchor>
        <h2>공개 화면 구성</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_theme_key', '기본 화면 스타일', $communitySettingsHelp['theme']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="community_admin_settings_theme_key" name="theme_key" class="form-select" required>
                    <?php foreach ($communityThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">커뮤니티 본문 안쪽의 색상과 구성 요소 모양을 정합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_layout_key', '커뮤니티 화면 틀', $communitySettingsHelp['layout']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="community_admin_settings_layout_key" name="layout_key" class="form-select">
                    <?php foreach ($communityLayoutOptions as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) $settings['layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">커뮤니티 화면의 헤더, 푸터와 메뉴 배치를 정합니다.</p>
                <?php echo $communitySettingsModuleReferenceListHtml($communityLayoutModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_layout_primary_menu_key', '주 메뉴', $communitySettingsHelp['menus']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <select id="community_admin_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                    <?php $communitySiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                </select>
                <?php echo $communitySettingsModuleReferenceListHtml($communitySiteMenuModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_board_sidebar_menu_type">게시판 사이드 메뉴</label>
            <div class="form-field">
                <select id="community_admin_settings_board_sidebar_menu_type" name="board_sidebar_menu_type" class="form-select" required data-community-board-sidebar-menu-type>
                    <?php foreach ($communityBoardSidebarMenuTypeOptions as $menuType => $menuTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $menuType); ?>"<?php echo $communityBoardSidebarMenuTypeValue === (string) $menuType ? ' selected' : ''; ?>><?php echo sr_e((string) $menuTypeLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">게시판 목록·읽기·쓰기 화면에서 인기글 위에 표시할 기본 메뉴 범위를 정합니다. 게시판 관리에서 개별값으로 바꿀 수 있습니다.</p>
            </div>
        </div>
        <?php if ($communityBoardSidebarSiteMenuAvailable) { ?>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_board_sidebar_site_menu_key">표시할 사이트 메뉴 <span class="sr-required-label"<?php echo $communityBoardSidebarMenuTypeValue === 'site_menu' ? '' : ' hidden'; ?> data-community-board-sidebar-site-menu-required>(필수)</span></label>
            <div class="form-field">
                <select id="community_admin_settings_board_sidebar_site_menu_key" name="board_sidebar_site_menu_key" class="form-select"<?php echo $communityBoardSidebarMenuTypeValue === 'site_menu' ? ' required' : ' disabled'; ?> data-community-board-sidebar-site-menu>
                    <?php $communityBoardSidebarSiteMenuSelectOptions((string) ($settings['board_sidebar_site_menu_key'] ?? '')); ?>
                </select>
                <p class="form-help">게시판 사이드 메뉴에서 사이트 메뉴의 특정값을 선택한 경우에만 사용합니다.</p>
                <?php echo $communitySettingsModuleReferenceListHtml($communitySiteMenuModuleReferences); ?>
            </div>
        </div>
        <?php } ?>
        <div class="form-row">
            <span class="form-label">추가 메뉴</span>
            <div class="form-field">
                <div class="admin-layout-menu-list" data-admin-layout-menu-list>
                    <div class="admin-layout-menu-header"<?php echo $communityLayoutExtraMenuItems === [] ? ' hidden' : ''; ?> aria-hidden="true" data-admin-layout-menu-header>
                        <span>자동 식별값</span>
                        <span>이름</span>
                        <span>메뉴</span>
                        <span>동작</span>
                    </div>
                    <?php $communityLayoutExtraMenuRows($communityLayoutExtraMenuItems); ?>
                    <?php $communityLayoutExtraMenuRows([], true); ?>
                    <div class="admin-layout-menu-actions" data-admin-layout-menu-actions>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-layout-menu-add><?php echo sr_material_icon_html('add'); ?> 추가 메뉴 추가</button>
                    </div>
                </div>
                <?php echo $communitySettingsModuleReferenceListHtml($communitySiteMenuModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">사업자정보</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_business_info_visible', 'business_info_visible', '1', !empty($settings['business_info_visible']), '노출'); ?>
                <p class="form-help">사이트 설정에 저장된 사업자 정보 중 값이 있는 항목을 커뮤니티 공개 레이아웃 푸터에 표시합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_post_editor', '게시글 입력 방식', $communitySettingsHelp['editor_default']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('community_admin_settings_post_editor', 'post_editor', $editorOptions, (string) ($settings['post_editor'] ?? 'textarea'), true); ?>
                <p class="form-help">새 게시판과 별도 설정이 없는 기존 게시판에 적용할 기본 입력 방식입니다.</p>
                <?php echo $communitySettingsModuleReferenceListHtml($communityEditorModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_post_toolbar_preset">툴바 구성 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="community_admin_settings_post_toolbar_preset" name="post_toolbar_preset" class="form-select" required>
                    <?php foreach ($toolbarPresetOptions as $presetKey => $presetLabel) { ?>
                        <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['post_toolbar_preset'] ?? 'community_post_basic') === (string) $presetKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $presetLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">CKEditor를 사용할 때 커뮤니티 게시글 작성/수정 화면에 적용할 툴바입니다.</p>
                <?php echo $communitySettingsModuleReferenceListHtml($communityToolbarModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_post_body_min_length', '게시글 본문 최소 길이', $communitySettingsHelp['body_length']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="community_admin_settings_post_body_min_length" type="number" name="post_body_min_length" min="0" max="<?php echo sr_e((string) $communityPostBodyLengthMax); ?>" value="<?php echo sr_e((string) ($settings['post_body_min_length'] ?? 0)); ?>" required class="form-input">
                <p class="form-help">새 게시판의 기본값이며 0이면 최소 길이를 검사하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_post_body_max_length', '게시글 본문 최대 길이', $communitySettingsHelp['body_length']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="community_admin_settings_post_body_max_length" type="number" name="post_body_max_length" min="0" max="<?php echo sr_e((string) $communityPostBodyLengthMax); ?>" value="<?php echo sr_e((string) ($settings['post_body_max_length'] ?? 0)); ?>" required class="form-input">
                <p class="form-help">새 게시판의 기본값이며 0이면 커뮤니티의 최대 길이 검사를 하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_admin_settings_comments_per_page">댓글 페이지당 수 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="community_admin_settings_comments_per_page" type="number" name="comments_per_page" min="1" max="100" value="<?php echo sr_e((string) ($settings['comments_per_page'] ?? 20)); ?>" required class="form-input">
                <p class="form-help">게시글 읽기 화면의 댓글 한 페이지에 표시할 기본 수입니다. 게시판 관리에서 1~100 값을 지정하면 해당 게시판 값이 우선합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_external_embed_enabled', '외부 서비스 자동 표시', $communitySettingsHelp['embed']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_external_embed_enabled', 'external_embed_enabled', '1', !empty($settings['external_embed_enabled']), '사용'); ?>
                <p class="form-help">본문에 단독으로 붙여 넣은 YouTube, X, Instagram 주소를 바로 볼 수 있게 표시합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_internal_embed_enabled', '사이트 콘텐츠 자동 표시', $communitySettingsHelp['embed']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_internal_embed_enabled', 'internal_embed_enabled', '1', !empty($settings['internal_embed_enabled']), '사용'); ?>
                <p class="form-help">사이트 안의 콘텐츠 주소를 게시글에서 미리보기 형태로 표시합니다.</p>
                <?php echo $communitySettingsModuleReferenceListHtml($communityInternalEmbedModuleReferences); ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">일반 텍스트 URL 링크</span>
            <div class="form-field">
                <div class="admin-community-url-link-switches">
                    <?php echo sr_admin_switch_html('community_admin_settings_plain_text_auto_link_urls', 'plain_text_auto_link_urls', '1', !empty($settings['plain_text_auto_link_urls']), '사용'); ?>
                    <?php echo sr_admin_switch_html('community_admin_settings_plain_text_auto_link_new_tab', 'plain_text_auto_link_new_tab', '1', !empty($settings['plain_text_auto_link_new_tab']), '새 탭'); ?>
                </div>
                <p class="form-help">일반 텍스트 게시글의 URL을 링크로 바꾸고, 필요하면 새 탭에서 엽니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">비밀글</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_secret_posts_enabled', 'secret_posts_enabled', '1', !empty($settings['secret_posts_enabled']), '사용'); ?>
                <p class="form-help">게시판 설정에서 별도로 재정의할 수 있습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">비밀 댓글</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('community_admin_settings_secret_comments_enabled', 'secret_comments_enabled', '1', !empty($settings['secret_comments_enabled']), '사용'); ?>
                <p class="form-help">게시판 설정에서 별도로 재정의할 수 있습니다.</p>
            </div>
        </div>
    </section>

    <?php echo sr_community_admin_post_extra_fields_editor_html(
        'community_post_extra_fields_json',
        'extra_fields_json',
        $settings['extra_fields_json'] ?? '[]',
        '새 게시판 게시글 추가 입력 기본값',
        '새 게시판 등록 화면을 열 때 미리 채워지는 항목입니다. 기존 게시판에는 반영되지 않으며, 등록 화면에서 수정한 최종 값이 해당 게시판에 저장됩니다.'
    ); ?>

    <?php echo sr_admin_comment_extra_fields_editor_html(
        'community_comment_extra_fields_json',
        'comment_extra_fields_json',
        $settings['comment_extra_fields_json'] ?? '[]',
        '새 게시판 댓글 추가 입력 기본값',
        '새 게시판 등록 화면을 열 때 미리 채워지는 항목입니다. 기존 게시판에는 반영되지 않으며, 등록 화면에서 수정한 최종 값이 해당 게시판에 저장됩니다.'
    ); ?>

    <script src="<?php echo sr_e(sr_admin_asset_url('/modules/community/assets/admin-post-extra-fields.js')); ?>" defer></script>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary admin-form-final-save"><?php echo sr_e(sr_t('community::ui.settings.save.59aa86cd')); ?></button>
        <button type="submit" name="admin_form_action" value="save_draft" class="btn btn-solid-light admin-form-draft-save" formnovalidate>설정 임시저장</button>
        <?php if (is_array($adminFormDraft ?? null)) { ?>
            <button type="submit" name="admin_form_action" value="discard_draft" class="btn btn-outline-danger admin-form-draft-delete" formnovalidate>임시저장 삭제</button>
        <?php } ?>
    </div>
</form>
<?php echo sr_admin_form_draft_restore_script($adminFormDraft ?? null, 'community-settings-form'); ?>

<button type="button" class="btn btn-solid-light" hidden data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-open><?php echo sr_e(sr_t('community::ui.level_max_value')); ?></button>
<div id="community-settings-level-max-confirm-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-settings-level-max-confirm-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog-center">
        <div class="modal-content admin-form ui-form-theme">
            <div class="modal-header">
                <h3 id="community-settings-level-max-confirm-modal-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.level_max_value')); ?></h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div data-community-settings-level-max-step="notice">
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_notice_1')); ?></p>
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_notice_2')); ?></p>
                </div>
                <div data-community-settings-level-max-step="text" hidden>
                    <p><?php echo sr_e(sr_t('community::ui.level_max_change_modal_text_help', ['text' => sr_t('community::ui.level_max_change_confirmation_text')])); ?></p>
                    <label class="sr-only" for="community-settings-level-max-confirm-input"><?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_label')); ?></label>
                    <input id="community-settings-level-max-confirm-input" type="text" class="form-input" autocomplete="off" placeholder="<?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_text')); ?>" aria-describedby="community-settings-level-max-confirm-error" data-overlay-focus data-community-settings-level-max-confirm-input>
                    <p id="community-settings-level-max-confirm-error" class="community-confirm-validation-message type-caption" data-community-settings-level-max-confirm-error hidden><?php echo sr_e(sr_t('community::ui.level_max_change_confirmation_error')); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-settings-level-max-confirm-modal" data-community-settings-level-max-cancel><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-settings-level-max-next><?php echo sr_e(sr_t('community::ui.level_max_change_modal_next')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-settings-level-max-submit hidden><?php echo sr_e(sr_t('community::ui.level_max_change_modal_execute')); ?></button>
            </div>
        </div>
    </div>
</div>

<?php echo sr_admin_help_modal_html($levelScoreHelpModalId, sr_t('community::ui.level_score_help_title'), $levelScoreHelpBodyHtml); ?>
<?php foreach ($communitySettingsHelp as $communitySettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $communitySettingsHelpModal['id'], (string) $communitySettingsHelpModal['title'], (string) $communitySettingsHelpModal['body']); ?>
<?php } ?>
<script>
(function () {
    var enabled = document.querySelector('[data-community-privacy-consent-enabled]');
    var controls = document.querySelector('[data-community-privacy-consent-controls]');
    if (!enabled || !controls) {
        return;
    }

    function syncPrivacyConsentControls() {
        var requiredLabel = controls.parentNode ? controls.parentNode.querySelector('[data-community-privacy-consent-required]') : null;
        if (requiredLabel) {
            requiredLabel.hidden = !enabled.checked;
        }
        Array.prototype.slice.call(controls.querySelectorAll('[data-community-privacy-consent-document]')).forEach(function (select) {
            select.disabled = !enabled.checked;
            select.required = false;
        });
    }

    enabled.addEventListener('change', syncPrivacyConsentControls);
    syncPrivacyConsentControls();
})();

(function () {
    var levelEnabled = document.querySelector('[data-community-level-enabled]');
    var toggle = document.querySelector('[data-community-level-auto-toggle]');
    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-field]'));
    var dependentFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-dependent-field]'));
    var requiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-required-field]'));
    var requiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-required-label]'));
    var autoRequiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-required-field]'));
    var autoRequiredLabels = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-auto-required-label]'));
    if (!levelEnabled || !toggle || fields.length < 1) {
        return;
    }

    function syncAutoFields() {
        var enabled = levelEnabled.checked;
        var autoEnabled = enabled && toggle.checked;
        dependentFields.forEach(function (field) {
            Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function (control) {
                if (control === levelEnabled) {
                    return;
                }
                control.disabled = !enabled;
            });
        });
        requiredFields.forEach(function (control) {
            control.required = enabled;
        });
        requiredLabels.forEach(function (label) {
            label.hidden = !enabled;
        });
        fields.forEach(function (field) {
            field.hidden = !autoEnabled;
        });
        autoRequiredFields.forEach(function (control) {
            control.disabled = !autoEnabled;
            control.required = autoEnabled;
        });
        autoRequiredLabels.forEach(function (label) {
            label.hidden = !autoEnabled;
        });
    }

    levelEnabled.addEventListener('change', syncAutoFields);
    toggle.addEventListener('change', syncAutoFields);
    syncAutoFields();
})();

(function () {
    var form = document.querySelector('[data-community-settings-form]');
    var maxInput = document.querySelector('[data-community-settings-level-max-value]');
    var openButton = document.querySelector('[data-community-settings-level-max-open]');
    var confirmedInput = document.querySelector('[data-community-settings-level-max-confirmed]');
    var confirmTextHidden = document.querySelector('[data-community-settings-level-max-confirm-text]');
    var confirmTextInput = document.querySelector('[data-community-settings-level-max-confirm-input]');
    var confirmError = document.querySelector('[data-community-settings-level-max-confirm-error]');
    var stepNotice = document.querySelector('[data-community-settings-level-max-step="notice"]');
    var stepText = document.querySelector('[data-community-settings-level-max-step="text"]');
    var nextButton = document.querySelector('[data-community-settings-level-max-next]');
    var submitButton = document.querySelector('[data-community-settings-level-max-submit]');
    var closeButton = document.querySelector('[data-community-settings-level-max-close]');
    var cancelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-settings-level-max-cancel]'));
    var requiredConfirmationText = <?php echo sr_js_json_encode(sr_t('community::ui.level_max_change_confirmation_text')); ?>;

    if (!form || !maxInput || !openButton || !confirmedInput || !confirmTextHidden) {
        return;
    }

    function maxChanged() {
        return String(maxInput.value).trim() !== String(maxInput.getAttribute('data-community-settings-level-max-initial') || '').trim();
    }

    function resetModal() {
        if (stepNotice) {
            stepNotice.hidden = false;
        }
        if (stepText) {
            stepText.hidden = true;
        }
        if (nextButton) {
            nextButton.hidden = false;
        }
        if (submitButton) {
            submitButton.hidden = true;
        }
        if (confirmTextInput) {
            confirmTextInput.value = '';
        }
        setConfirmInvalid(false);
    }

    function setConfirmInvalid(isInvalid) {
        if (confirmTextInput) {
            confirmTextInput.classList.toggle('form-input-invalid', isInvalid);
            if (isInvalid) {
                confirmTextInput.setAttribute('aria-invalid', 'true');
            } else {
                confirmTextInput.removeAttribute('aria-invalid');
            }
        }
        if (confirmError) {
            confirmError.hidden = !isInvalid;
        }
    }

    cancelButtons.forEach(function (cancelButton) {
        cancelButton.addEventListener('click', resetModal);
    });

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (stepNotice) {
                stepNotice.hidden = true;
            }
            if (stepText) {
                stepText.hidden = false;
            }
            nextButton.hidden = true;
            if (submitButton) {
                submitButton.hidden = false;
            }
            if (confirmTextInput) {
                confirmTextInput.focus();
            }
        });
    }

    if (submitButton) {
        submitButton.addEventListener('click', function () {
            var value = confirmTextInput ? confirmTextInput.value.trim() : '';
            if (value !== requiredConfirmationText) {
                setConfirmInvalid(true);
                if (confirmTextInput) {
                    confirmTextInput.focus();
                }
                return;
            }

            setConfirmInvalid(false);
            confirmedInput.value = '1';
            confirmTextHidden.value = value;
            if (closeButton) {
                closeButton.click();
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (!maxChanged()) {
            confirmedInput.value = '0';
            confirmTextHidden.value = '';
            return;
        }

        if (confirmedInput.value === '1' && confirmTextHidden.value === requiredConfirmationText) {
            return;
        }

        event.preventDefault();
        resetModal();
        openButton.click();
    });

    if (confirmTextInput) {
        confirmTextInput.addEventListener('input', function () {
            setConfirmInvalid(false);
        });
    }
})();
</script>

<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<?php $communityLevelEnabled = !empty($settings['level_enabled']); ?>
<?php $communityLevelRecalculateModalId = 'community-level-recalculate-confirm-modal'; ?>
<?php $communityLevelRecalculateTargetCount = sr_community_recalculate_target_account_count($pdo); ?>
<?php $communityLevelRecalculateLoad = sr_admin_high_load_assessment([
    'target_records' => $communityLevelRecalculateTargetCount,
    'table_count' => 4,
    'batch_available' => true,
]); ?>
<?php if (!$communityLevelEnabled) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon" aria-hidden="true">i</span>
        <div class="admin-notice-copy">
            <strong><?php echo sr_e(sr_t('community::ui.level_disabled_notice_title')); ?></strong>
            <p><?php echo sr_e(sr_t('community::ui.level_disabled_notice_body')); ?></p>
            <p><a href="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="btn btn-sm btn-solid-light" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('community::ui.level_disabled_notice_link')); ?></a></p>
        </div>
    </div>
<?php } ?>
<form id="community-level-definitions-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_level_definitions">
    <input type="hidden" name="level_max_value" value="<?php echo sr_e((string) $settings['level_max_value']); ?>">
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.text.b2845de5')); ?></h2>
        </div>
        <p class="form-help">
            <?php echo sr_e(sr_t('community::ui.level_definitions_help_score')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_definitions_help_formula')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_recalculate_notice_change')); ?><br>
            <?php echo sr_e(sr_t('community::ui.level_recalculate_notice_load')); ?>
        </p>
        <p class="form-help">레벨 설정 저장은 최소 점수만 저장합니다. 회원 레벨 재계산은 저장과 별도로 실행되며, 작성 중인 최소 점수 입력값을 함께 저장하지 않습니다.</p>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?></th>
                        <th><span class="form-check form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.2ba8a858'), $communitySettingsHelp['level_min_score']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.2ba8a858')); ?></span></span></th>
                        <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($levels === []) { ?>
                        <tr><td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.b4915f04')); ?></td></tr>
                    <?php } else { ?>
                        <?php foreach ($levels as $level) { ?>
                            <tr>
                                <td><?php echo sr_e(sr_community_level_label((int) $level['level_value'], $settings)); ?></td>
                                <td><?php echo sr_e((string) $level['title']); ?></td>
                                <td>
                                    <input
                                        type="number"
                                        name="level_min_score[<?php echo sr_e((string) $level['id']); ?>]"
                                        class="form-input"
                                        min="0"
                                        max="1000000000"
                                        value="<?php echo sr_e((string) $level['min_score']); ?>"
                                    >
                                </td>
                                <td><?php echo sr_e(sr_admin_code_label((string) $level['status'], 'content_status')); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="button" class="btn btn-solid-light"<?php echo $communityLevelEnabled ? '' : ' disabled'; ?> aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-open><?php echo sr_e(sr_t('community::ui.member.9fba6ddf')); ?></button>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.save.bca4cb2b')); ?></button>
    </div>
</form>

<form
    id="community-level-recalculate-form"
    method="post"
    action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>"
    data-community-level-recalculate-form
    data-recalculate-url="<?php echo sr_e(sr_url('/admin/community/levels/recalculate')); ?>"
    data-batch-size="50"
>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="recalculate_levels">
    <input type="hidden" name="recalculate_confirmed" value="0" data-community-level-recalculate-confirmed>
    <input type="hidden" name="recalculate_confirm_text" value="" data-community-level-recalculate-confirm-text>
</form>
<div class="community-level-recalculate-progress" data-community-level-recalculate-progress hidden>
    <p class="type-small" data-community-level-recalculate-status role="status" aria-live="polite"><?php echo sr_e(sr_t('community::ui.level_recalculate_ready')); ?></p>
    <progress value="0" max="100" data-community-level-recalculate-meter></progress>
</div>
<div id="<?php echo sr_e($communityLevelRecalculateModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityLevelRecalculateModalId); ?>-label" aria-hidden="true" inert>
    <div class="modal-dialog-center">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($communityLevelRecalculateModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_title')); ?></h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div data-community-level-recalculate-step="notice">
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_notice_1')); ?></p>
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_notice_2')); ?></p>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e('부하 등급'); ?></span>
                        <div class="form-field"><p class="admin-form-static"><?php echo sr_e((string) $communityLevelRecalculateLoad['label']); ?></p></div>
                    </div>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e('처리 대상'); ?></span>
                        <div class="form-field"><p class="admin-form-static"><?php echo sr_e('활성/대기 회원 ' . number_format($communityLevelRecalculateTargetCount) . '명'); ?></p></div>
                    </div>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e('중단/실패 시 상태'); ?></span>
                        <div class="form-field"><p class="admin-form-static"><?php echo sr_e((string) $communityLevelRecalculateLoad['failure_state']); ?></p></div>
                    </div>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e('권장 실행 시점'); ?></span>
                        <div class="form-field"><p class="admin-form-static"><?php echo sr_e((string) $communityLevelRecalculateLoad['recommended_time']); ?></p></div>
                    </div>
                    <div class="form-row">
                        <span class="form-label"><?php echo sr_e('기록 위치'); ?></span>
                        <div class="form-field"><p class="admin-form-static"><?php echo sr_e('완료 결과는 감사 로그 community.levels.recalculated metadata에 대상 수와 배치 정보를 남깁니다.'); ?></p></div>
                    </div>
                </div>
                <div data-community-level-recalculate-step="text" hidden>
                    <p><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_text_help', ['text' => sr_t('community::ui.level_recalculate_confirmation_text')])); ?></p>
                    <label class="sr-only" for="community-level-recalculate-confirm-input"><?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_label')); ?></label>
                    <input id="community-level-recalculate-confirm-input" type="text" class="form-input" autocomplete="off" placeholder="<?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_text')); ?>" aria-describedby="community-level-recalculate-confirm-error" data-overlay-focus data-community-level-recalculate-confirm-input>
                    <p id="community-level-recalculate-confirm-error" class="community-confirm-validation-message type-caption" data-community-level-recalculate-confirm-error hidden><?php echo sr_e(sr_t('community::ui.level_recalculate_confirmation_error')); ?></p>
                </div>
            </div>
            <div class="modal-footer-note">
                <p class="form-help">재계산 실행은 현재 저장된 레벨 기준으로 회원 레벨을 다시 계산합니다. 레벨 설정 form의 작성 중인 최소 점수 입력값은 함께 저장되지 않습니다.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityLevelRecalculateModalId); ?>" data-community-level-recalculate-cancel><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-level-recalculate-next><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_next')); ?></button>
                <button type="button" class="btn btn-solid-primary modal-action" data-community-level-recalculate-confirm-submit hidden><?php echo sr_e(sr_t('community::ui.level_recalculate_modal_execute')); ?></button>
            </div>
        </div>
    </div>
</div>
<?php echo sr_admin_help_modal_html((string) $communitySettingsHelp['level_min_score']['id'], (string) $communitySettingsHelp['level_min_score']['title'], (string) $communitySettingsHelp['level_min_score']['body']); ?>
<script>
(function () {
    var form = document.querySelector('[data-community-level-recalculate-form]');
    if (!form || !window.fetch || !window.FormData) {
        return;
    }

    var openButton = document.querySelector('[data-community-level-recalculate-open]');
    var progress = document.querySelector('[data-community-level-recalculate-progress]');
    var status = document.querySelector('[data-community-level-recalculate-status]');
    var meter = document.querySelector('[data-community-level-recalculate-meter]');
    var confirmedInput = document.querySelector('[data-community-level-recalculate-confirmed]');
    var confirmTextHidden = document.querySelector('[data-community-level-recalculate-confirm-text]');
    var confirmTextInput = document.querySelector('[data-community-level-recalculate-confirm-input]');
    var confirmError = document.querySelector('[data-community-level-recalculate-confirm-error]');
    var stepNotice = document.querySelector('[data-community-level-recalculate-step="notice"]');
    var stepText = document.querySelector('[data-community-level-recalculate-step="text"]');
    var nextButton = document.querySelector('[data-community-level-recalculate-next]');
    var confirmSubmitButton = document.querySelector('[data-community-level-recalculate-confirm-submit]');
    var modalCloseButton = document.querySelector('[data-community-level-recalculate-close]');
    var cancelButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-level-recalculate-cancel]'));
    var requiredConfirmationText = <?php echo sr_js_json_encode(sr_t('community::ui.level_recalculate_confirmation_text')); ?>;
    var labels = <?php echo sr_js_json_encode([
        'start' => sr_t('community::ui.level_recalculate_start'),
        'running' => sr_t('community::ui.level_recalculate_running'),
        'running_unknown' => sr_t('community::ui.level_recalculate_running_unknown'),
        'error' => sr_t('community::ui.level_recalculate_error'),
    ]); ?>;

    function text(template, values) {
        return Object.keys(values).reduce(function (message, key) {
            return message.replace('{' + key + '}', String(values[key]));
        }, template);
    }

    function resetModal(clearConfirmation) {
        if (clearConfirmation === void 0) {
            clearConfirmation = true;
        }
        if (stepNotice) {
            stepNotice.hidden = false;
        }
        if (stepText) {
            stepText.hidden = true;
        }
        if (nextButton) {
            nextButton.hidden = false;
        }
        if (confirmSubmitButton) {
            confirmSubmitButton.hidden = true;
        }
        if (confirmTextInput) {
            confirmTextInput.value = '';
        }
        if (clearConfirmation && confirmTextHidden) {
            confirmTextHidden.value = '';
        }
        if (clearConfirmation && confirmedInput) {
            confirmedInput.value = '0';
        }
        setConfirmInvalid(false);
    }

    function setConfirmInvalid(isInvalid) {
        if (confirmTextInput) {
            confirmTextInput.classList.toggle('form-input-invalid', isInvalid);
            if (isInvalid) {
                confirmTextInput.setAttribute('aria-invalid', 'true');
            } else {
                confirmTextInput.removeAttribute('aria-invalid');
            }
        }
        if (confirmError) {
            confirmError.hidden = !isInvalid;
        }
    }

    function updateStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function updateProgress(processed, total, done) {
        if (!meter) {
            return;
        }

        meter.value = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : (done ? 100 : 0);
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (stepNotice) {
                stepNotice.hidden = true;
            }
            if (stepText) {
                stepText.hidden = false;
            }
            nextButton.hidden = true;
            if (confirmSubmitButton) {
                confirmSubmitButton.hidden = false;
            }
            if (confirmTextInput) {
                confirmTextInput.focus();
            }
        });
    }

    if (openButton) {
        openButton.addEventListener('click', function () {
            resetModal();
        });
    }

    cancelButtons.forEach(function (cancelButton) {
        cancelButton.addEventListener('click', resetModal);
    });

    if (confirmSubmitButton) {
        confirmSubmitButton.addEventListener('click', function (event) {
            var value = confirmTextInput ? confirmTextInput.value.trim() : '';
            if (value !== requiredConfirmationText) {
                event.preventDefault();
                event.stopPropagation();
                setConfirmInvalid(true);
                if (confirmTextInput) {
                    confirmTextInput.focus();
                }
                return;
            }

            setConfirmInvalid(false);
            if (confirmTextHidden) {
                confirmTextHidden.value = value;
            }
            if (confirmedInput) {
                confirmedInput.value = '1';
            }
            if (modalCloseButton) {
                modalCloseButton.click();
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (openButton && openButton.disabled) {
            event.preventDefault();
            return;
        }

        event.preventDefault();

        var url = form.getAttribute('data-recalculate-url') || '';
        var batchSize = parseInt(form.getAttribute('data-batch-size') || '50', 10);
        if (!url || !Number.isFinite(batchSize) || batchSize < 1) {
            return;
        }

        if (!confirmedInput || confirmedInput.value !== '1' || !confirmTextHidden || confirmTextHidden.value !== requiredConfirmationText) {
            return;
        }

        var cursor = 0;
        var processed = 0;
        var total = 0;
        var jobId = 0;
        var lockToken = '';

        if (openButton) {
            openButton.disabled = true;
        }
        if (progress) {
            progress.hidden = false;
        }
        resetModal(false);
        updateProgress(0, 0, false);
        updateStatus(labels.start);

        function runBatch() {
            var body = new FormData(form);
            body.set('cursor', String(cursor));
            body.set('batch_size', String(batchSize));
            body.set('processed_total', String(processed));
            body.set('job_id', String(jobId));
            body.set('lock_token', lockToken);

            return window.fetch(url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        throw new Error(payload && payload.message ? payload.message : labels.error);
                    }

                    return payload;
                });
            }).then(function (payload) {
                processed = Number(payload.processed_total || processed);
                total = Number(payload.total || total);
                cursor = Number(payload.next_cursor || cursor);
                jobId = Number(payload.job_id || jobId);
                lockToken = String(payload.lock_token || lockToken);
                updateProgress(processed, total, !!payload.done);

                if (payload.done) {
                    updateStatus(payload.message || text(labels.running, {
                        processed: processed,
                        total: total,
                    }));
                    if (openButton) {
                        openButton.disabled = false;
                    }
                    resetModal();
                    return;
                }

                updateStatus(total > 0 ? text(labels.running, {
                    processed: processed,
                    total: total,
                }) : text(labels.running_unknown, {
                    processed: processed,
                }));
                return runBatch();
            }).catch(function (error) {
                updateStatus(error && error.message ? error.message : labels.error);
                if (openButton) {
                    openButton.disabled = false;
                }
                resetModal();
            });
        }

        runBatch();
    });

    if (confirmTextInput) {
        confirmTextInput.addEventListener('input', function () {
            setConfirmInvalid(false);
        });
    }
})();
</script>
<?php } ?>

<script>
(function () {
    function syncCommunityBoardSidebarSiteMenu(root) {
        var scope = root || document;
        var type = scope.querySelector('[data-community-board-sidebar-menu-type]');
        var siteMenu = scope.querySelector('[data-community-board-sidebar-site-menu]');
        var requiredLabel = scope.querySelector('[data-community-board-sidebar-site-menu-required]');
        if (!type || !siteMenu) {
            return;
        }

        var enabled = type.value === 'site_menu';
        siteMenu.disabled = !enabled;
        siteMenu.required = enabled;
        if (requiredLabel) {
            requiredLabel.hidden = !enabled;
        }
        if (!enabled && typeof siteMenu.setCustomValidity === 'function') {
            siteMenu.setCustomValidity('');
        }
    }

    function formatThumbnailBytes(value) {
        var bytes = Number(value || 0);
        if (!Number.isFinite(bytes) || bytes < 0) {
            bytes = 0;
        }
        if (bytes === 0) {
            return '0 bytes';
        }
        if (bytes < 1024) {
            return bytes.toLocaleString('ko-KR', {
                maximumFractionDigits: 0
            }) + ' bytes';
        }
        if (bytes < 1048576) {
            var kb = bytes / 1024;
            return kb.toLocaleString('ko-KR', {
                minimumFractionDigits: kb >= 10 ? 0 : 1,
                maximumFractionDigits: kb >= 10 ? 1 : 1
            }) + ' KB';
        }
        var mb = bytes / 1048576;
        return mb.toLocaleString('ko-KR', {
            minimumFractionDigits: mb >= 10 ? 0 : 2,
            maximumFractionDigits: mb >= 10 ? 1 : 2
        }) + ' MB';
    }

    function syncCommunityThumbnailBytesLabel(root) {
        Array.prototype.slice.call((root || document).querySelectorAll('[data-community-thumbnail-bytes-input]')).forEach(function (input) {
            var field = input.closest ? input.closest('.form-field') : null;
            var label = field ? field.querySelector('[data-community-thumbnail-bytes-label]') : null;
            if (label) {
                label.textContent = formatThumbnailBytes(input.value);
            }
        });
    }

    function syncCommunityThumbnailCriterion(form) {
        var root = form || document;
        var checked = root.querySelector('input[name="thumbnail_criterion"]:checked');
        var selected = checked ? checked.value : 'width';
        Array.prototype.slice.call(root.querySelectorAll('[data-community-thumbnail-rule]')).forEach(function (row) {
            var visible = row.getAttribute('data-community-thumbnail-rule') === selected;
            row.hidden = !visible;
            Array.prototype.slice.call(row.querySelectorAll('[data-admin-required-when-visible]')).forEach(function (input) {
                input.required = visible;
                if (!visible && typeof input.setCustomValidity === 'function') {
                    input.setCustomValidity('');
                }
            });
        });
        syncCommunityThumbnailBytesLabel(root);
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches('[data-community-board-sidebar-menu-type]')) {
            syncCommunityBoardSidebarSiteMenu(event.target.closest('form'));
            return;
        }
        if (!event.target || event.target.name !== 'thumbnail_criterion') {
            return;
        }
        syncCommunityThumbnailCriterion(event.target.closest('form'));
    });
    document.addEventListener('input', function (event) {
        if (!event.target || !event.target.matches('[data-community-thumbnail-bytes-input]')) {
            return;
        }
        syncCommunityThumbnailBytesLabel(event.target.closest('form'));
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            syncCommunityBoardSidebarSiteMenu(document);
            syncCommunityThumbnailCriterion(document);
        });
    } else {
        syncCommunityBoardSidebarSiteMenu(document);
        syncCommunityThumbnailCriterion(document);
    }
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
