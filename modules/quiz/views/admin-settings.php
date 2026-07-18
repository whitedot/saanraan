<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';

$quizLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : [];
$quizLayoutModuleReferences = [];
foreach ($quizLayoutOptions as $quizLayoutOption) {
    $providerModuleKey = is_array($quizLayoutOption) ? (string) ($quizLayoutOption['provider_module_key'] ?? '') : '';
    if ($providerModuleKey !== '' && $providerModuleKey !== 'quiz') {
        $quizLayoutModuleReferences[$providerModuleKey] = ['module_key' => $providerModuleKey];
    }
}
$quizSiteMenuModuleReferences = sr_module_enabled($pdo, 'site_menu')
    ? [['module_key' => 'site_menu']]
    : [];
$quizIdentityModuleReferences = [['module_key' => 'identity_verification', 'path' => '/admin/identity-providers']];
$quizReactionModuleReferences = [['module_key' => 'reaction', 'path' => '/admin/reactions/presets']];
$quizEditorModuleReferences = [];
foreach (sr_editor_contracts($pdo) as $quizEditorContract) {
    $quizEditorModuleKey = is_array($quizEditorContract) ? (string) ($quizEditorContract['module_key'] ?? '') : '';
    if ($quizEditorModuleKey !== '') {
        $quizEditorModuleReferences[$quizEditorModuleKey] = ['module_key' => $quizEditorModuleKey];
    }
}
$quizAssetModuleReferences = [];
foreach ($assetOptions as $quizAssetModuleKey => $quizAssetOption) {
    $assetProviderModuleKey = is_array($quizAssetOption)
        ? (string) ($quizAssetOption['module_key'] ?? $quizAssetModuleKey)
        : (string) $quizAssetModuleKey;
    if ($assetProviderModuleKey !== '') {
        $quizAssetModuleReferences[$assetProviderModuleKey] = ['module_key' => $assetProviderModuleKey];
    }
}
$quizCouponModuleReferences = [['module_key' => 'coupon', 'path' => '/admin/coupons']];
$quizSiteMenuOptions = isset($siteMenuOptions) && is_array($siteMenuOptions) ? $siteMenuOptions : [];
$quizSiteMenuSelectOptions = static function (string $selectedMenuKey) use ($quizSiteMenuOptions): void {
    ?>
    <option value=""<?php echo $selectedMenuKey === '' ? ' selected' : ''; ?>>사용 안 함</option>
    <?php foreach ($quizSiteMenuOptions as $menuKey => $menu) { ?>
        <?php $menuLabel = (string) ($menu['label'] ?? $menuKey); ?>
        <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo $selectedMenuKey === (string) $menuKey ? ' selected' : ''; ?>>
            <?php echo sr_e($menuLabel . ' (' . (string) $menuKey . ')'); ?>
        </option>
    <?php } ?>
    <?php
};
$quizLayoutExtraMenuItems = function_exists('sr_quiz_layout_extra_menu_items_from_settings') ? sr_quiz_layout_extra_menu_items_from_settings($settings) : [];
if (is_array($adminFormDraft ?? null)) {
    $quizLayoutExtraMenuItems = sr_admin_form_draft_parallel_rows((array) $adminFormDraft['payload'], [
        'area_key' => 'layout_extra_menu_area_keys',
        'label' => 'layout_extra_menu_labels',
        'menu_key' => 'layout_extra_menu_keys',
    ]);
}
$quizIdentityViewAvailable = isset($quizIdentityViewAvailable)
    ? (bool) $quizIdentityViewAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'quiz.view'));
$quizIdentityViewAdultAvailable = isset($quizIdentityViewAdultAvailable)
    ? (bool) $quizIdentityViewAdultAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'quiz.view.adult'));
$quizIdentityUnavailable = !$quizIdentityViewAvailable;
$quizIdentityViewInputAttributes = $quizIdentityViewAvailable
    ? ''
    : ' disabled aria-describedby="quiz-settings-identity-unavailable"';
$quizIdentityViewAdultInputAttributes = $quizIdentityViewAdultAvailable
    ? ''
    : ' disabled aria-describedby="quiz-settings-identity-adult-unavailable"';
$quizReactionAvailable = isset($quizReactionAvailable)
    ? (bool) $quizReactionAvailable
    : (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php'));
$quizReactionInputAttributes = $quizReactionAvailable
    ? ''
    : ' disabled aria-describedby="quiz-settings-reaction-unavailable"';
$quizSettingsHelpOpenLabel = '설명 보기';
$quizSettingsHelpBodyHtml = static function (array $items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<p>' . sr_e((string) $item) . '</p>';
    }
    return $html;
};
$quizSettingsHelp = [
    'layout_key' => [
        'id' => 'quiz-settings-help-layout-key',
        'title' => '퀴즈 공개 레이아웃',
        'body_html' => $quizSettingsHelpBodyHtml([
            '선택한 테마 아래에서 퀴즈 목록, 풀이, 결과 화면을 감싸는 공개 화면 틀입니다.',
            '공통 레이아웃과 퀴즈 공개 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.',
        ]),
    ],
    'theme_key' => [
        'id' => 'quiz-settings-help-theme-key',
        'title' => '퀴즈 공개 테마',
        'body_html' => $quizSettingsHelpBodyHtml([
            '퀴즈 공개 테마는 퀴즈 공개 화면의 본문 구조, 색, 표면, 테두리, 상호작용 상태를 적용하는 설정입니다.',
            '테마 변경은 퀴즈 데이터나 응시 기록을 바꾸지 않고 선택한 theme 디렉터리의 공개 view와 asset을 공개 출력에 적용합니다.',
        ]),
    ],
    'skin_key' => [
        'id' => 'quiz-settings-help-skin-key',
        'title' => '퀴즈 스킨',
        'body_html' => $quizSettingsHelpBodyHtml([
            '퀴즈 공개 목록, 상세/응시, 결과 화면의 본문 출력 방식입니다.',
            '허용된 스킨 Key만 저장하고, 누락된 화면 파일은 기본 스킨으로 대체합니다.',
        ]),
    ],
    'default_status' => [
        'id' => 'quiz-settings-help-default-status',
        'title' => '기본 상태',
        'body_html' => $quizSettingsHelpBodyHtml([
            '새 퀴즈 만들기 화면을 열 때 먼저 선택되어 있을 상태입니다.',
            '바로 방문자에게 보이지 않게 하려면 초안으로 두고, 검수가 끝난 뒤 퀴즈 수정 화면에서 공개로 바꿉니다.',
            '퀴즈 복사본은 안전을 위해 이 값과 관계없이 초안으로 시작합니다.',
        ]),
    ],
    'default_quiz_mode' => [
        'id' => 'quiz-settings-help-default-quiz-mode',
        'title' => '기본 모드',
        'body_html' => $quizSettingsHelpBodyHtml([
            '새 퀴즈를 어떤 목적으로 운영할지 정하는 초기값입니다.',
            '정답 통과는 맞힌 개수로 성공 여부를 나누고, 총점 결과는 점수 구간별 안내를 보여주며, 카테고리 진단은 답변 성향에 맞는 결과를 보여줄 때 사용합니다.',
        ]),
    ],
    'default_scoring_model' => [
        'id' => 'quiz-settings-help-default-scoring-model',
        'title' => '기본 채점 모델',
        'body_html' => $quizSettingsHelpBodyHtml([
            '새 퀴즈가 답안을 점수로 계산하는 기본 방식입니다.',
            '정답 개수, 문항별 점수, 카테고리 점수처럼 운영 목적에 맞는 계산 방식을 선택합니다.',
        ]),
    ],
    'default_pass_score' => [
        'id' => 'quiz-settings-help-default-pass-score',
        'title' => '기본 통과 점수',
        'body_html' => $quizSettingsHelpBodyHtml([
            '정답 통과형 퀴즈에서 몇 점 이상이면 성공으로 볼지 정하는 초기값입니다.',
            '예를 들어 3문제 중 2문제 이상 맞히면 통과라면 2를 입력합니다.',
        ]),
    ],
    'default_question_choice_count' => [
        'id' => 'quiz-settings-help-default-question-choice-count',
        'title' => '새 문제 기본 선택지 수',
        'body_html' => $quizSettingsHelpBodyHtml([
            '문제 추가 모달을 열었을 때 먼저 보여줄 선택지 개수입니다.',
            '운영자가 자주 만드는 문제 형식에 맞춰 두면 문제를 만들 때 선택지를 매번 추가하거나 줄이는 일이 줄어듭니다.',
        ]),
    ],
    'default_question_score' => [
        'id' => 'quiz-settings-help-default-question-score',
        'title' => '새 문제 기본 점수',
        'body_html' => $quizSettingsHelpBodyHtml([
            '문제 추가 모달을 열었을 때 새 문제에 먼저 들어갈 점수입니다.',
            '문항별 점수 방식이나 총점 결과형 퀴즈에서 기본 배점을 빠르게 맞출 때 사용합니다.',
        ]),
    ],
    'default_attempt_limit_policy' => [
        'id' => 'quiz-settings-help-default-attempt-limit-policy',
        'title' => '기본 응시 제한',
        'body_html' => $quizSettingsHelpBodyHtml([
            '회원이 같은 퀴즈를 몇 번 풀 수 있는지에 대한 새 퀴즈 기본값입니다.',
            '기간당 1회를 선택하면 아래 제한 기간을 함께 입력해야 합니다.',
        ]),
    ],
    'default_attempt_limit_period_seconds' => [
        'id' => 'quiz-settings-help-default-attempt-limit-period',
        'title' => '기본 제한 기간',
        'body_html' => $quizSettingsHelpBodyHtml([
            '기본 응시 제한이 기간당 1회일 때만 사용하는 초 단위 값입니다.',
            '예를 들어 하루에 한 번만 풀 수 있게 하려면 86400을 입력합니다.',
        ]),
    ],
    'default_reward_provider' => [
        'id' => 'quiz-settings-help-default-reward-provider',
        'title' => '기본 보상 종류',
        'body_html' => $quizSettingsHelpBodyHtml([
            '새 퀴즈 생성 시 보상 정책에 먼저 들어갈 보상 방식입니다.',
            '포인트/금액은 포인트, 적립금, 예치금 같은 항목에 금액을 지급합니다.',
            '쿠폰 발급은 현재 사용 가능한 활성 쿠폰 정의를 회원에게 1장 지급합니다.',
        ]),
    ],
    'default_reward_module' => [
        'id' => 'quiz-settings-help-default-reward-module',
        'title' => '기본 보상 포인트/금액 항목',
        'body_html' => $quizSettingsHelpBodyHtml([
            '보상 종류가 포인트/금액일 때 지급할 포인트/금액 항목입니다.',
            '예를 들어 포인트를 선택하면 회원의 포인트 잔액에 기본 보상 금액이 더해집니다.',
        ]),
    ],
    'default_reward_coupon_definition_id' => [
        'id' => 'quiz-settings-help-default-reward-coupon',
        'title' => '기본 보상 쿠폰',
        'body_html' => $quizSettingsHelpBodyHtml([
            '보상 종류가 쿠폰 발급일 때 지급할 쿠폰입니다.',
        ]) . '<p><a href="' . sr_e(sr_url('/admin/coupons')) . '" target="_blank" rel="noopener noreferrer">쿠폰 관리</a>에 등록되어 있고 활성 상태이며 사용 기간 안에 있는 쿠폰만 선택할 수 있습니다.</p>',
    ],
    'default_reward_amount' => [
        'id' => 'quiz-settings-help-default-reward-amount',
        'title' => '기본 보상 금액',
        'body_html' => $quizSettingsHelpBodyHtml([
            '보상 종류가 포인트/금액일 때 지급할 수량 또는 금액입니다.',
            '예를 들어 포인트 항목에 100을 입력하면 통과한 회원에게 100포인트 지급을 시도합니다.',
        ]),
    ],
    'default_reward_dedupe_scope' => [
        'id' => 'quiz-settings-help-default-reward-dedupe-scope',
        'title' => '기본 중복 지급 기준',
        'body_html' => $quizSettingsHelpBodyHtml([
            '같은 회원에게 보상을 다시 지급할 수 있는 기준입니다.',
            '퀴즈당 1회는 같은 퀴즈에서 한 번만 지급하고, 출처당 1회는 같은 콘텐츠나 게시글 연결 기준으로 한 번만 지급합니다.',
            '응시마다 지급은 반복 응시를 허용하는 이벤트에서만 신중하게 사용합니다.',
        ]),
    ],
    'default_cta_label' => [
        'id' => 'quiz-settings-help-default-cta-label',
        'title' => '기본 연결 CTA 문구',
        'body_html' => $quizSettingsHelpBodyHtml([
            '콘텐츠나 커뮤니티 게시글에 퀴즈 버튼을 붙일 때 기본으로 사용할 버튼 문구입니다.',
            '예: 퀴즈 풀기, 진단 시작하기, 혜택 받기',
        ]),
    ],
    'public_list_limit' => [
        'id' => 'quiz-settings-help-public-list-limit',
        'title' => '공개 목록 페이지당 표시 수',
        'body_html' => $quizSettingsHelpBodyHtml([
            '공개 퀴즈 목록의 한 페이지에 표시할 퀴즈 수입니다. 다음 페이지에서 나머지 퀴즈를 계속 탐색할 수 있습니다.',
            '관리자 목록과 시도 목록의 page size는 관리자 공통 페이징 설정을 계속 사용합니다.',
        ]),
    ],
];
$quizLayoutExtraMenuRows = static function (array $menuItems, bool $template = false) use ($quizSiteMenuSelectOptions): void {
    foreach ($template ? [['area_key' => '', 'label' => '', 'menu_key' => '']] : $menuItems as $menuItem) {
        $areaKey = is_array($menuItem) ? (string) ($menuItem['area_key'] ?? '') : '';
        $menuLabel = is_array($menuItem) ? (string) ($menuItem['label'] ?? '') : '';
        $selectedMenuKey = is_array($menuItem) ? (string) ($menuItem['menu_key'] ?? '') : (string) $menuItem;
        ?>
        <div class="admin-layout-menu-row"<?php echo $template ? ' hidden data-admin-layout-menu-template' : ''; ?> data-admin-layout-menu-row>
            <input type="text" name="layout_extra_menu_area_keys[]" value="<?php echo sr_e($areaKey); ?>" class="form-input admin-layout-menu-key-input" maxlength="60" pattern="(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})" inputmode="latin" autocapitalize="none" spellcheck="false" placeholder="자동 key" aria-label="추가 메뉴 자동 key" readonly data-admin-layout-menu-key data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <input type="text" name="layout_extra_menu_labels[]" value="<?php echo sr_e($menuLabel); ?>" class="form-input" maxlength="80" placeholder="이름" aria-label="추가 메뉴 이름" data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
            <select name="layout_extra_menu_keys[]" class="form-select" data-admin-layout-menu-select data-admin-layout-menu-field<?php echo $template ? ' disabled' : ''; ?>>
                <?php $quizSiteMenuSelectOptions((string) $selectedMenuKey); ?>
            </select>
            <button type="button" class="btn btn-sm btn-icon btn-outline-danger admin-layout-menu-remove" data-admin-layout-menu-remove aria-label="추가 메뉴 제거" title="제거"><?php echo sr_material_icon_html('delete'); ?></button>
        </div>
        <?php
    }
};
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$quizSettingsSectionNavItems = [
    'quiz-settings-section-display' => '공개 화면',
    'quiz-settings-section-defaults' => '새 퀴즈',
    'quiz-settings-section-identity' => '본인확인',
    'quiz-settings-section-reaction' => '리액션',
    'quiz-settings-section-reward' => '기본 보상',
    'quiz-settings-section-links' => '목록/연결',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="퀴즈 설정 섹션">
    <?php $quizSettingsSectionNavIndex = 0; ?>
    <?php foreach ($quizSettingsSectionNavItems as $quizSettingsSectionId => $quizSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $quizSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $quizSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $quizSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $quizSettingsSectionLabel); ?>
        </a>
        <?php $quizSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form id="quiz-settings-form" method="post" action="<?php echo sr_e(sr_url('/admin/quiz/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <?php echo sr_admin_form_draft_status_html($adminFormDraft ?? null, 'quiz-settings-form'); ?>

    <section id="quiz-settings-section-display" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">공개 화면 구성</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_theme_key', '퀴즈 공개 테마', $quizSettingsHelp['theme_key']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="quiz_settings_theme_key" name="theme_key" class="form-select" required>
                        <?php foreach ($publicThemeOptions as $themeKey => $themeOption) { ?>
                            <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) ($settings['theme_key'] ?? 'basic') === (string) $themeKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">퀴즈 공개 화면의 본문 구조, 색, 표면, 상호작용 상태에 적용할 시각 테마입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_layout_key', '퀴즈 공개 레이아웃', $quizSettingsHelp['layout_key']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="quiz_settings_layout_key" name="layout_key" class="form-select" required>
                        <?php foreach ($quizLayoutOptions as $layoutKey => $layoutOption) { ?>
                            <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">선택한 테마 아래에서 퀴즈 화면을 감싸는 공개 화면 틀입니다. 공통 레이아웃과 필요한 화면 대상을 지원하는 다른 모듈 레이아웃도 선택할 수 있습니다.</p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizLayoutModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_skin_key', '퀴즈 스킨', $quizSettingsHelp['skin_key']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="quiz_settings_skin_key" name="skin_key" class="form-select" required>
                        <?php foreach (sr_quiz_skin_options() as $skinKey => $skinLabel) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($settings['skin_key'] ?? 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $skinLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">선택한 공개 레이아웃 안쪽의 퀴즈 본문 출력 스킨입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_settings_layout_primary_menu_key">주 메뉴</label>
                <div class="form-field">
                    <select id="quiz_settings_layout_primary_menu_key" name="layout_primary_menu_key" class="form-select">
                        <?php $quizSiteMenuSelectOptions((string) ($settings['layout_primary_menu_key'] ?? 'header')); ?>
                    </select>
                    <p class="form-help">퀴즈 공개 레이아웃의 기본 메뉴 위치에 표시할 메뉴를 정합니다.</p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizSiteMenuModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">추가 메뉴</span>
                <div class="form-field">
                    <div class="admin-layout-menu-list" data-admin-layout-menu-list>
                        <div class="admin-layout-menu-header"<?php echo $quizLayoutExtraMenuItems === [] ? ' hidden' : ''; ?> aria-hidden="true" data-admin-layout-menu-header>
                            <span>Key</span>
                            <span>이름</span>
                            <span>메뉴</span>
                            <span>동작</span>
                        </div>
                        <?php $quizLayoutExtraMenuRows($quizLayoutExtraMenuItems); ?>
                        <?php $quizLayoutExtraMenuRows([], true); ?>
                        <div class="admin-layout-menu-actions" data-admin-layout-menu-actions>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-layout-menu-add><?php echo sr_material_icon_html('add'); ?> 추가 메뉴 추가</button>
                        </div>
                    </div>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizSiteMenuModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">사업자정보</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('quiz_settings_business_info_visible', 'business_info_visible', '1', !empty($settings['business_info_visible']), '노출'); ?>
                    <p class="form-help">사이트 설정에 저장된 사업자 정보 중 값이 있는 항목을 퀴즈 공개 레이아웃 푸터에 표시합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">퀴즈 사이드</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('quiz_settings_sidebar_enabled', 'sidebar_enabled', '1', !empty($settings['sidebar_enabled']), '사용'); ?>
                    <p class="form-help">퀴즈 메인을 제외한 전체 목록과 퀴즈 풀이·결과 화면에 사이드를 표시합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_settings_sidebar_menu_type">사이드 메뉴 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('quiz_settings_sidebar_menu_type', 'sidebar_menu_type', $quizSidebarMenuTypeOptions, (string) ($settings['sidebar_menu_type'] ?? 'groups'), true, ' data-quiz-settings-sidebar-menu-type'); ?>
                    <p class="form-help">사이드 첫 영역에 표시할 탐색 메뉴를 정합니다.</p>
                </div>
            </div>
            <?php if ($siteMenuOptions !== []) { ?>
                <?php $quizSidebarUsesSiteMenu = (string) ($settings['sidebar_menu_type'] ?? 'groups') === 'site_menu'; ?>
                <div class="form-row" data-quiz-settings-sidebar-site-menu-row<?php echo $quizSidebarUsesSiteMenu ? '' : ' hidden'; ?>>
                    <label class="form-label" for="quiz_settings_sidebar_site_menu_key">사이드 사이트 메뉴 <span class="sr-required-label" data-quiz-settings-sidebar-site-menu-required<?php echo $quizSidebarUsesSiteMenu ? '' : ' hidden'; ?>>(필수)</span></label>
                    <div class="form-field">
                        <select id="quiz_settings_sidebar_site_menu_key" name="sidebar_site_menu_key" class="form-select" data-quiz-settings-sidebar-site-menu<?php echo $quizSidebarUsesSiteMenu ? ' required' : ' disabled'; ?>>
                            <option value="">선택 안 함</option>
                            <?php foreach ($siteMenuOptions as $menuKey => $menuOption) { ?>
                                <option value="<?php echo sr_e((string) $menuKey); ?>"<?php echo (string) ($settings['sidebar_site_menu_key'] ?? '') === (string) $menuKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($menuOption['label'] ?? $menuKey)); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">사이드 메뉴에서 사이트 메뉴를 선택한 경우 사용합니다.</p>
                    </div>
                </div>
            <?php } ?>
            <div class="form-row">
                <span class="form-label">사이드 표시 개수</span>
                <div class="form-field">
                    <div class="admin-inline-fields">
                        <label>인기 퀴즈 <input type="number" name="sidebar_popular_limit" value="<?php echo sr_e((string) ($settings['sidebar_popular_limit'] ?? 5)); ?>" min="1" max="10" class="form-input" required></label>
                        <label>최신댓글 <input type="number" name="sidebar_comments_limit" value="<?php echo sr_e((string) ($settings['sidebar_comments_limit'] ?? 5)); ?>" min="1" max="10" class="form-input" required></label>
                    </div>
                    <p class="form-help">각 영역에 표시할 항목 수를 1~10개로 정합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-settings-section-defaults" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">새 퀴즈 기본값</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_status', '기본 상태', $quizSettingsHelp['default_status']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $quizStatusToggleOptions = [];
                    foreach (sr_quiz_statuses() as $status) {
                        $quizStatusToggleOptions[$status] = sr_quiz_status_label($status);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_status', 'default_status', $quizStatusToggleOptions, (string) ($settings['default_status'] ?? 'draft'), true);
                    ?>
                    <p class="form-help">새 퀴즈 생성 화면의 기본 상태입니다. 복사본 기본 상태는 항상 초안입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_quiz_mode', '기본 모드', $quizSettingsHelp['default_quiz_mode']['id'], $quizSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <?php
                    $quizModeToggleOptions = [];
                    foreach (sr_quiz_modes() as $quizMode) {
                        $quizModeToggleOptions[$quizMode] = sr_quiz_mode_label($quizMode);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_quiz_mode', 'default_quiz_mode', $quizModeToggleOptions, (string) ($settings['default_quiz_mode'] ?? 'scored'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_scoring_model', '기본 채점 모델', $quizSettingsHelp['default_scoring_model']['id'], $quizSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <?php
                    $quizScoringToggleOptions = [];
                    foreach (sr_quiz_scoring_models() as $scoringModel) {
                        $quizScoringToggleOptions[$scoringModel] = sr_quiz_scoring_model_label($scoringModel);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_scoring_model', 'default_scoring_model', $quizScoringToggleOptions, (string) ($settings['default_scoring_model'] ?? 'correct_answer'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_pass_score', '기본 통과 점수', $quizSettingsHelp['default_pass_score']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_settings_default_pass_score" type="number" name="default_pass_score" value="<?php echo sr_e((string) ($settings['default_pass_score'] ?? 1)); ?>" class="form-input" min="0" max="100000" step="1" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_question_choice_count', '새 문제 기본 선택지 수', $quizSettingsHelp['default_question_choice_count']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_settings_default_question_choice_count" type="number" name="default_question_choice_count" value="<?php echo sr_e((string) ($settings['default_question_choice_count'] ?? 4)); ?>" class="form-input" min="2" max="10" step="1" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_question_score', '새 문제 기본 점수', $quizSettingsHelp['default_question_score']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_settings_default_question_score" type="number" name="default_question_score" value="<?php echo sr_e((string) ($settings['default_question_score'] ?? 1)); ?>" class="form-input" min="0" max="10000" step="1" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_attempt_limit_policy', '기본 응시 제한', $quizSettingsHelp['default_attempt_limit_policy']['id'], $quizSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <?php
                    $quizAttemptPolicyToggleOptions = [];
                    foreach (sr_quiz_attempt_limit_policies() as $policy) {
                        $quizAttemptPolicyToggleOptions[$policy] = sr_quiz_attempt_limit_policy_label($policy);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_attempt_limit_policy', 'default_attempt_limit_policy', $quizAttemptPolicyToggleOptions, (string) ($settings['default_attempt_limit_policy'] ?? 'unlimited'), true, ' data-quiz-settings-attempt-policy');
                    ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-label form-label-help">
                    <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="기본 제한 기간 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['default_attempt_limit_period_seconds']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['default_attempt_limit_period_seconds']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    <label for="quiz_settings_default_attempt_limit_period_seconds">기본 제한 기간(초) <span class="sr-required-label" data-quiz-settings-attempt-period-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <input id="quiz_settings_default_attempt_limit_period_seconds" type="number" name="default_attempt_limit_period_seconds" value="<?php echo sr_e((string) ($settings['default_attempt_limit_period_seconds'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-settings-attempt-period>
                    <p class="form-help">기본 응시 제한이 기간당 1회일 때만 사용합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-settings-section-reaction" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">댓글·리액션 기본값</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="quiz_settings_comment_editor">댓글 입력 방식 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('quiz_settings_comment_editor', 'comment_editor', $editorOptions, (string) ($settings['comment_editor'] ?? 'textarea'), true); ?>
                    <p class="form-help">퀴즈 댓글·답글·수정 입력에 사용할 에디터입니다. CKEditor는 일반 편집 도구 구성을 사용하며, 변경하면 기존 댓글의 표시 방식도 함께 바뀝니다.</p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizEditorModuleReferences); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_settings_reaction_preset_key">퀴즈 리액션 프리셋</label>
                <div class="form-field">
                    <select id="quiz_settings_reaction_preset_key" name="reaction_preset_key" class="form-select"<?php echo $quizReactionInputAttributes; ?>>
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">개별 퀴즈에서 값을 비워두면 이 값을 사용합니다.</p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizReactionModuleReferences); ?>
                    <?php if (!$quizReactionAvailable) { ?>
                        <p id="quiz-settings-reaction-unavailable" class="form-help form-help-warning"><a href="<?php echo sr_e(sr_url('/admin/modules')); ?>" target="_blank" rel="noopener noreferrer">리액션 모듈</a>을 설치하고 활성화하면 리액션 기본값을 사용할 수 있습니다.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_settings_reaction_comment_preset_key">댓글 리액션 프리셋</label>
                <div class="form-field">
                    <select id="quiz_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select"<?php echo $quizReactionInputAttributes; ?>>
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">퀴즈 댓글 리액션에 적용할 기본 프리셋입니다.</p>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizReactionModuleReferences); ?>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-settings-section-reward" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">기본 보상</h2>
        </div>
        <div class="form-grid">
            <div class="form-row" data-quiz-settings-reward-row>
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_reward_provider', '기본 보상 종류', $quizSettingsHelp['default_reward_provider']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $quizRewardProviderToggleOptions = [];
                    foreach (sr_quiz_default_reward_providers() as $provider) {
                        $quizRewardProviderToggleOptions[$provider] = sr_quiz_reward_provider_label($provider);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_reward_provider', 'default_reward_provider', $quizRewardProviderToggleOptions, (string) ($settings['default_reward_provider'] ?? 'ledger_asset'), true, ' data-quiz-settings-reward-provider');
                    ?>
                </div>
            </div>
            <div class="form-row" data-quiz-settings-reward-row data-quiz-settings-reward-ledger-row>
                <div class="form-label form-label-help">
                    <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="기본 보상 포인트/금액 항목 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['default_reward_module']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['default_reward_module']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    <label for="quiz_settings_default_reward_module">기본 보상 포인트/금액 항목 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <select id="quiz_settings_default_reward_module" name="default_reward_module" class="form-select" data-quiz-settings-reward-ledger-control>
                        <option value="">선택안함</option>
                        <?php foreach ($assetOptions as $assetKey => $asset) { ?>
                            <option value="<?php echo sr_e((string) $assetKey); ?>"<?php echo (string) ($settings['default_reward_module'] ?? '') === (string) $assetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $assetKey)); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizAssetModuleReferences); ?>
                </div>
            </div>
            <div class="form-row" data-quiz-settings-reward-row data-quiz-settings-reward-coupon-row>
                <div class="form-label form-label-help">
                    <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="기본 보상 쿠폰 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['default_reward_coupon_definition_id']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['default_reward_coupon_definition_id']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    <label for="quiz_settings_default_reward_coupon_definition_id">기본 보상 쿠폰 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <select id="quiz_settings_default_reward_coupon_definition_id" name="default_reward_coupon_definition_id" class="form-select" data-quiz-settings-reward-coupon-control>
                        <option value="">선택안함</option>
                        <?php foreach ($couponRewardDefinitions as $couponDefinition) { ?>
                            <?php $definitionId = (int) ($couponDefinition['id'] ?? 0); ?>
                            <option value="<?php echo sr_e((string) $definitionId); ?>"<?php echo (string) ($settings['default_reward_coupon_definition_id'] ?? '') === (string) $definitionId ? ' selected' : ''; ?>><?php echo sr_e((string) (($couponDefinition['title'] ?? '') !== '' ? $couponDefinition['title'] : ($couponDefinition['coupon_key'] ?? ''))); ?> [<?php echo sr_e((string) ($couponDefinition['coupon_key'] ?? '')); ?>]</option>
                        <?php } ?>
                    </select>
                    <?php if ($couponRewardDefinitions === []) { ?>
                        <p class="form-help form-help-warning">현재 선택 가능한 활성 쿠폰이 없습니다. <a href="<?php echo sr_e(sr_url('/admin/coupons')); ?>" target="_blank" rel="noopener noreferrer">쿠폰 관리</a>에서 사용 가능한 쿠폰을 먼저 등록하거나 활성화하세요.</p>
                    <?php } ?>
                    <?php echo sr_admin_module_reference_list_html($pdo, $quizCouponModuleReferences); ?>
                </div>
            </div>
            <div class="form-row" data-quiz-settings-reward-row data-quiz-settings-reward-ledger-row>
                <div class="form-label form-label-help">
                    <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="기본 보상 금액 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['default_reward_amount']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['default_reward_amount']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    <label for="quiz_settings_default_reward_amount">기본 보상 금액 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <input id="quiz_settings_default_reward_amount" type="number" name="default_reward_amount" value="<?php echo sr_e((string) ($settings['default_reward_amount'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-settings-reward-ledger-control>
                </div>
            </div>
            <div class="form-row" data-quiz-settings-reward-row data-quiz-settings-reward-policy-row>
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_reward_dedupe_scope', '기본 중복 지급 기준', $quizSettingsHelp['default_reward_dedupe_scope']['id'], $quizSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <?php
                    $quizRewardDedupeToggleOptions = [];
                    foreach (sr_quiz_reward_dedupe_scopes() as $scope) {
                        $quizRewardDedupeToggleOptions[$scope] = sr_quiz_reward_dedupe_scope_label($scope);
                    }
                    echo sr_admin_radio_toggle_group_html('quiz_settings_default_reward_dedupe_scope', 'default_reward_dedupe_scope', $quizRewardDedupeToggleOptions, (string) ($settings['default_reward_dedupe_scope'] ?? 'per_quiz'), true, ' data-quiz-settings-reward-control');
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-settings-section-links" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">공개 목록/연결</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label">내부 모듈 간 임베드</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('quiz_settings_internal_embed_enabled', 'internal_embed_enabled', '1', !empty($settings['internal_embed_enabled']), '사용'); ?>
                    <p class="form-help">꺼져 있으면 콘텐츠나 커뮤니티 본문에 붙여 넣은 퀴즈 URL을 자동 표시하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_default_cta_label', '기본 연결 CTA 문구', $quizSettingsHelp['default_cta_label']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_settings_default_cta_label" type="text" name="default_cta_label" value="<?php echo sr_e((string) ($settings['default_cta_label'] ?? '퀴즈 풀기')); ?>" class="form-input" maxlength="120" required>
                    <p class="form-help">새로 연결 대상을 저장할 때 버튼 문구로 사용합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_settings_public_list_limit', '공개 목록 페이지당 표시 수', $quizSettingsHelp['public_list_limit']['id'], $quizSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_settings_public_list_limit" type="number" name="public_list_limit" value="<?php echo sr_e((string) ($settings['public_list_limit'] ?? 50)); ?>" class="form-input" min="1" max="100" step="1" required>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-settings-section-identity" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">본인확인</h2>
        </div>
        <div class="form-row">
            <span class="form-label">퀴즈 참여 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('quiz_settings_identity_view_required', 'identity_view_required', '1', $quizIdentityViewAvailable && !empty($settings['identity_view_required']), '사용', '', $quizIdentityViewInputAttributes); ?>
                <p class="form-help">사용하면 퀴즈 상세와 응시 전에 본인확인을 요구합니다.</p>
                <?php echo sr_admin_module_reference_list_html($pdo, $quizIdentityModuleReferences); ?>
                <?php if ($quizIdentityUnavailable) { ?>
                    <p id="quiz-settings-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 목적에 맞는 제공자가 준비되지 않은 항목은 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label">퀴즈 참여 성인 본인확인</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('quiz_settings_identity_view_adult_required', 'identity_view_adult_required', '1', $quizIdentityViewAdultAvailable && !empty($settings['identity_view_adult_required']), '사용', '', $quizIdentityViewAdultInputAttributes); ?>
                <?php if ($quizIdentityViewAdultAvailable) { ?>
                    <p class="form-help form-help-info">사용하면 성인 여부가 확인된 회원만 퀴즈에 접근할 수 있습니다.</p>
                <?php } else { ?>
                    <p id="quiz-settings-identity-adult-unavailable" class="form-help form-help-warning">현재 저장할 수 없습니다. <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 생년월일 사용을 켜고 퀴즈 성인 참여 목적 제공자를 설정하세요.</p>
                <?php } ?>
                <?php echo sr_admin_module_reference_list_html($pdo, $quizIdentityModuleReferences); ?>
            </div>
        </div>
    </section>

    <?php echo sr_admin_comment_extra_fields_editor_html(
        'quiz_comment_extra_fields_json',
        'comment_extra_fields_json',
        $settings['comment_extra_fields_json'] ?? '[]',
        '댓글 추가 입력 항목',
        '새 퀴즈 등록 화면을 열 때 미리 채워지는 항목입니다. 기존 퀴즈에는 반영되지 않으며, 등록 화면에서 수정한 최종 값이 해당 퀴즈에 저장됩니다.'
    ); ?>
    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">퀴즈 목록</a>
        <div class="admin-form-secondary-actions admin-form-draft-actions">
            <button type="submit" class="btn btn-solid-primary admin-form-final-save">저장</button>
            <button type="submit" name="admin_form_action" value="save_draft" class="btn btn-solid-light admin-form-draft-save" formnovalidate>임시저장</button>
            <?php if (is_array($adminFormDraft ?? null)) { ?>
                <button type="submit" name="admin_form_action" value="discard_draft" class="btn btn-outline-danger admin-form-draft-delete" formnovalidate>임시저장 삭제</button>
            <?php } ?>
        </div>
    </div>
</form>
<?php echo sr_admin_form_draft_restore_script($adminFormDraft ?? null, 'quiz-settings-form'); ?>

<script>
(function () {
    var attemptPolicyControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-attempt-policy]'));
    var attemptPeriod = document.querySelector('[data-quiz-settings-attempt-period]');
    var attemptPeriodLabel = document.querySelector('[data-quiz-settings-attempt-period-required]');
    function checkedValue(name, fallback) {
        var checked = document.querySelector('input[name="' + name + '"]:checked, select[name="' + name + '"]');
        return checked ? checked.value : fallback;
    }
    function syncAttemptPeriod() {
        if (attemptPolicyControls.length === 0 || !attemptPeriod) {
            return;
        }
        var required = checkedValue('default_attempt_limit_policy', 'unlimited') === 'per_period';
        attemptPeriod.required = required;
        if (attemptPeriodLabel) {
            attemptPeriodLabel.hidden = !required;
        }
    }
    if (attemptPolicyControls.length > 0) {
        attemptPolicyControls.forEach(function (control) {
            control.addEventListener('change', syncAttemptPeriod);
        });
        syncAttemptPeriod();
    }

    var sidebarMenuTypeControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-sidebar-menu-type]'));
    var sidebarSiteMenuRow = document.querySelector('[data-quiz-settings-sidebar-site-menu-row]');
    var sidebarSiteMenu = document.querySelector('[data-quiz-settings-sidebar-site-menu]');
    var sidebarSiteMenuRequired = document.querySelector('[data-quiz-settings-sidebar-site-menu-required]');
    function syncSidebarSiteMenu() {
        if (!sidebarSiteMenuRow || !sidebarSiteMenu) {
            return;
        }
        var active = checkedValue('sidebar_menu_type', 'groups') === 'site_menu';
        sidebarSiteMenuRow.hidden = !active;
        sidebarSiteMenu.disabled = !active;
        sidebarSiteMenu.required = active;
        if (sidebarSiteMenuRequired) {
            sidebarSiteMenuRequired.hidden = !active;
        }
    }
    sidebarMenuTypeControls.forEach(function (control) {
        control.addEventListener('change', syncSidebarSiteMenu);
    });
    syncSidebarSiteMenu();

    var rewardProviderControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-provider]'));
    var rewardRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-row]'));
    var rewardPolicyRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-policy-row]'));
    var ledgerRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-ledger-row]'));
    var couponRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-coupon-row]'));
    var rewardControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-control]'));
    var ledgerControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-ledger-control]'));
    var couponControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-coupon-control]'));
    function setRowsHidden(rows, hidden) {
        rows.forEach(function (row) {
            row.hidden = hidden;
        });
    }
    function setControlsEnabled(controls, enabled) {
        controls.forEach(function (control) {
            control.disabled = !enabled;
        });
    }
    function setControlsRequired(controls, required) {
        controls.forEach(function (control) {
            control.required = required;
            var row = control.closest ? control.closest('.form-row') : null;
            if (!row) {
                return;
            }
            Array.prototype.slice.call(row.querySelectorAll('[data-quiz-settings-reward-required]')).forEach(function (label) {
                label.hidden = !required;
            });
        });
    }
    function syncReward() {
        if (rewardProviderControls.length === 0) {
            return;
        }
        var rewardProvider = checkedValue('default_reward_provider', 'ledger_asset');
        var ledgerSelected = rewardProvider === 'ledger_asset';
        var couponSelected = rewardProvider === 'coupon';
        var rewardSelected = ledgerSelected || couponSelected;
        setRowsHidden(rewardRows, false);
        setRowsHidden(rewardPolicyRows, !rewardSelected);
        setRowsHidden(ledgerRows, !ledgerSelected);
        setRowsHidden(couponRows, !couponSelected);
        rewardProviderControls.forEach(function (control) {
            control.disabled = false;
        });
        setControlsEnabled(rewardControls, rewardSelected);
        setControlsEnabled(ledgerControls, ledgerSelected);
        setControlsEnabled(couponControls, couponSelected);
        setControlsRequired(ledgerControls, ledgerSelected);
        setControlsRequired(couponControls, couponSelected);
    }
    if (rewardProviderControls.length > 0) {
        rewardProviderControls.forEach(function (control) {
            control.addEventListener('change', syncReward);
        });
        syncReward();
    }
})();
</script>

<?php foreach ($quizSettingsHelp as $helpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $helpModal['id'], (string) $helpModal['title'], (string) $helpModal['body_html']); ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
