<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';

$quizLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : [];
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
$quizLayoutMenuFields = [
    'layout_primary_menu_key' => [
        'label' => '주 메뉴 슬롯',
        'help' => '선택한 공개 레이아웃이 주 메뉴 슬롯을 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다.',
        'default' => 'header',
    ],
    'layout_secondary_menu_key' => [
        'label' => '보조 메뉴 슬롯',
        'help' => '선택한 공개 레이아웃이 보조 메뉴 슬롯을 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다.',
        'default' => '',
    ],
    'layout_tertiary_menu_key' => [
        'label' => '추가 메뉴 슬롯 1',
        'help' => '선택한 공개 레이아웃이 추가 메뉴 슬롯 1을 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다.',
        'default' => '',
    ],
    'layout_quaternary_menu_key' => [
        'label' => '추가 메뉴 슬롯 2',
        'help' => '선택한 공개 레이아웃이 추가 메뉴 슬롯 2를 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다.',
        'default' => '',
    ],
    'layout_quinary_menu_key' => [
        'label' => '추가 메뉴 슬롯 3',
        'help' => '선택한 공개 레이아웃이 추가 메뉴 슬롯 3을 출력할 때 사용할 사이트 메뉴입니다. 실제 위치는 레이아웃에 따라 달라질 수 있습니다.',
        'default' => '',
    ],
];
$quizSettingsHelpOpenLabel = '설명 보기';
$quizSettingsHelpBodyHtml = static function (array $items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<p>' . sr_e((string) $item) . '</p>';
    }
    return $html;
};
$quizSettingsHelp = [
    'defaults' => [
        'id' => 'quiz-settings-help-defaults',
        'title' => '새 퀴즈 기본값',
        'body_html' => $quizSettingsHelpBodyHtml([
            '이 설정은 새 퀴즈 생성 화면의 초기값에만 사용합니다.',
            '이미 저장된 퀴즈에는 소급 적용하지 않습니다.',
            '복사 기능은 안전을 위해 기본 상태를 초안으로 만들며, 이 설정의 기본 상태보다 복사 옵션이 우선합니다.',
        ]),
    ],
    'reward' => [
        'id' => 'quiz-settings-help-reward',
        'title' => '기본 보상',
        'body_html' => $quizSettingsHelpBodyHtml([
            '새 퀴즈 생성 시 보상 정책 입력란에 채울 기본값입니다.',
            '포인트/금액은 회원 자산 모듈의 포인트, 적립금, 예치금 같은 항목에 금액을 지급합니다.',
            '쿠폰 발급은 현재 사용 가능한 활성 쿠폰 정의를 회원에게 1장 지급합니다.',
        ]),
    ],
    'list' => [
        'id' => 'quiz-settings-help-list',
        'title' => '공개 목록',
        'body_html' => $quizSettingsHelpBodyHtml([
            '공개 퀴즈 목록에서 한 번에 가져올 퀴즈 수입니다.',
            '관리자 목록과 시도 목록의 page size는 관리자 공통 페이징 설정을 계속 사용합니다.',
        ]),
    ],
    'layout' => [
        'id' => 'quiz-settings-help-layout',
        'title' => '공개 화면 구성',
        'body_html' => $quizSettingsHelpBodyHtml([
            '퀴즈 목록과 퀴즈 풀이 화면에서 사용할 공개 레이아웃을 정합니다.',
            '사이트 메뉴 슬롯은 레이아웃이 해당 위치를 출력할 때만 보입니다.',
            '레이아웃 변경은 기존 퀴즈 데이터나 응시 기록을 바꾸지 않습니다.',
        ]),
    ],
];
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/quiz/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">공개 화면 구성</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="공개 화면 구성 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['layout']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['layout']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
            </div>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_layout_key">퀴즈 공개 레이아웃 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_layout_key" name="layout_key" class="form-select" required>
                        <?php foreach ($quizLayoutOptions as $layoutKey => $layoutOption) { ?>
                            <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help">퀴즈 목록과 퀴즈 풀이 화면에 적용할 공개 레이아웃입니다.</p>
                </div>
            </div>
            <?php foreach ($quizLayoutMenuFields as $quizLayoutMenuSettingKey => $quizLayoutMenuField) { ?>
                <?php $quizLayoutMenuInputId = 'quiz_settings_' . $quizLayoutMenuSettingKey; ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e($quizLayoutMenuInputId); ?>"><?php echo sr_e((string) $quizLayoutMenuField['label']); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e($quizLayoutMenuInputId); ?>" name="<?php echo sr_e((string) $quizLayoutMenuSettingKey); ?>" class="form-select">
                            <?php $quizSiteMenuSelectOptions((string) ($settings[$quizLayoutMenuSettingKey] ?? $quizLayoutMenuField['default'])); ?>
                        </select>
                        <p class="admin-form-help"><?php echo sr_e((string) $quizLayoutMenuField['help']); ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">새 퀴즈 기본값</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="새 퀴즈 기본값 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['defaults']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['defaults']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
            </div>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_status">기본 상태 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_status" name="default_status" class="form-select" required>
                        <?php foreach (sr_quiz_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($settings['default_status'] ?? 'draft') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_status_label($status)); ?></option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help">새 퀴즈 생성 화면의 기본 상태입니다. 복사본 기본 상태는 항상 초안입니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_quiz_mode">기본 모드 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_quiz_mode" name="default_quiz_mode" class="form-select" required>
                        <?php foreach (sr_quiz_modes() as $quizMode) { ?>
                            <option value="<?php echo sr_e($quizMode); ?>"<?php echo (string) ($settings['default_quiz_mode'] ?? 'scored') === $quizMode ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_mode_label($quizMode)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_scoring_model">기본 채점 모델 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_scoring_model" name="default_scoring_model" class="form-select" required>
                        <?php foreach (sr_quiz_scoring_models() as $scoringModel) { ?>
                            <option value="<?php echo sr_e($scoringModel); ?>"<?php echo (string) ($settings['default_scoring_model'] ?? 'correct_answer') === $scoringModel ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_scoring_model_label($scoringModel)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_pass_score">기본 통과 점수 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_pass_score" type="number" name="default_pass_score" value="<?php echo sr_e((string) ($settings['default_pass_score'] ?? 1)); ?>" class="form-input" min="0" max="100000" step="1" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_question_choice_count">새 문제 기본 선택지 수 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_question_choice_count" type="number" name="default_question_choice_count" value="<?php echo sr_e((string) ($settings['default_question_choice_count'] ?? 4)); ?>" class="form-input" min="2" max="10" step="1" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_question_score">새 문제 기본 점수 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_question_score" type="number" name="default_question_score" value="<?php echo sr_e((string) ($settings['default_question_score'] ?? 1)); ?>" class="form-input" min="0" max="10000" step="1" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_attempt_limit_policy">기본 응시 제한 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_attempt_limit_policy" name="default_attempt_limit_policy" class="form-select" required data-quiz-settings-attempt-policy>
                        <?php foreach (sr_quiz_attempt_limit_policies() as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo (string) ($settings['default_attempt_limit_policy'] ?? 'unlimited') === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_attempt_limit_policy_label($policy)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_attempt_limit_period_seconds">기본 제한 기간(초) <span class="sr-required-label" data-quiz-settings-attempt-period-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_attempt_limit_period_seconds" type="number" name="default_attempt_limit_period_seconds" value="<?php echo sr_e((string) ($settings['default_attempt_limit_period_seconds'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-settings-attempt-period>
                    <p class="admin-form-help">기본 응시 제한이 기간당 1회일 때만 사용합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">기본 보상</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="기본 보상 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['reward']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['reward']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
            </div>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_reward_enabled">기본 보상 사용</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="quiz_settings_default_reward_enabled">
                        <input id="quiz_settings_default_reward_enabled" type="checkbox" name="default_reward_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['default_reward_enabled']) ? ' checked' : ''; ?> data-quiz-settings-reward-enabled>
                        <?php echo sr_admin_choice_label_html('새 퀴즈에 보상 정책 기본값 입력'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row" data-quiz-settings-reward-row>
                <label class="form-label" for="quiz_settings_default_reward_provider">기본 보상 종류 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_reward_provider" name="default_reward_provider" class="form-select" required data-quiz-settings-reward-provider>
                        <?php foreach (sr_quiz_reward_providers() as $provider) { ?>
                            <option value="<?php echo sr_e($provider); ?>"<?php echo (string) ($settings['default_reward_provider'] ?? 'ledger_asset') === $provider ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_provider_label($provider)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row" data-quiz-settings-reward-row data-quiz-settings-reward-ledger-row>
                <label class="form-label" for="quiz_settings_default_reward_module">기본 보상 자산 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_reward_module" name="default_reward_module" class="form-select" data-quiz-settings-reward-ledger-control>
                        <option value="">선택안함</option>
                        <?php foreach ($assetOptions as $assetKey => $asset) { ?>
                            <option value="<?php echo sr_e((string) $assetKey); ?>"<?php echo (string) ($settings['default_reward_module'] ?? '') === (string) $assetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $assetKey)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row" data-quiz-settings-reward-row data-quiz-settings-reward-coupon-row>
                <label class="form-label" for="quiz_settings_default_reward_coupon_definition_id">기본 보상 쿠폰 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_reward_coupon_definition_id" name="default_reward_coupon_definition_id" class="form-select" data-quiz-settings-reward-coupon-control>
                        <option value="">선택안함</option>
                        <?php foreach ($couponRewardDefinitions as $couponDefinition) { ?>
                            <?php $definitionId = (int) ($couponDefinition['id'] ?? 0); ?>
                            <option value="<?php echo sr_e((string) $definitionId); ?>"<?php echo (string) ($settings['default_reward_coupon_definition_id'] ?? '') === (string) $definitionId ? ' selected' : ''; ?>><?php echo sr_e((string) (($couponDefinition['title'] ?? '') !== '' ? $couponDefinition['title'] : ($couponDefinition['coupon_key'] ?? ''))); ?> [<?php echo sr_e((string) ($couponDefinition['coupon_key'] ?? '')); ?>]</option>
                        <?php } ?>
                    </select>
                    <?php if ($couponRewardDefinitions === []) { ?>
                        <p class="admin-form-help">현재 선택 가능한 활성 쿠폰이 없습니다.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row" data-quiz-settings-reward-row data-quiz-settings-reward-ledger-row>
                <label class="form-label" for="quiz_settings_default_reward_amount">기본 보상 금액 <span class="sr-required-label" data-quiz-settings-reward-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_reward_amount" type="number" name="default_reward_amount" value="<?php echo sr_e((string) ($settings['default_reward_amount'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-settings-reward-ledger-control>
                </div>
            </div>
            <div class="admin-form-row" data-quiz-settings-reward-row>
                <label class="form-label" for="quiz_settings_default_reward_dedupe_scope">기본 중복 지급 기준 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_settings_default_reward_dedupe_scope" name="default_reward_dedupe_scope" class="form-select" required data-quiz-settings-reward-control>
                        <?php foreach (sr_quiz_reward_dedupe_scopes() as $scope) { ?>
                            <option value="<?php echo sr_e($scope); ?>"<?php echo (string) ($settings['default_reward_dedupe_scope'] ?? 'per_quiz') === $scope ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_dedupe_scope_label($scope)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">공개 목록/연결</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="공개 목록 설명 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizSettingsHelp['list']['id']); ?>" data-overlay="#<?php echo sr_e($quizSettingsHelp['list']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
            </div>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_default_cta_label">기본 연결 CTA 문구 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_default_cta_label" type="text" name="default_cta_label" value="<?php echo sr_e((string) ($settings['default_cta_label'] ?? '퀴즈 풀기')); ?>" class="form-input" maxlength="120" required>
                    <p class="admin-form-help">새로 연결 대상을 저장할 때 버튼 문구로 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_settings_public_list_limit">공개 목록 노출 수 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_settings_public_list_limit" type="number" name="public_list_limit" value="<?php echo sr_e((string) ($settings['public_list_limit'] ?? 50)); ?>" class="form-input" min="1" max="100" step="1" required>
                </div>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">퀴즈 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var attemptPolicy = document.querySelector('[data-quiz-settings-attempt-policy]');
    var attemptPeriod = document.querySelector('[data-quiz-settings-attempt-period]');
    var attemptPeriodLabel = document.querySelector('[data-quiz-settings-attempt-period-required]');
    function syncAttemptPeriod() {
        if (!attemptPolicy || !attemptPeriod) {
            return;
        }
        var required = attemptPolicy.value === 'per_period';
        attemptPeriod.required = required;
        if (attemptPeriodLabel) {
            attemptPeriodLabel.hidden = !required;
        }
    }
    if (attemptPolicy) {
        attemptPolicy.addEventListener('change', syncAttemptPeriod);
        syncAttemptPeriod();
    }

    var rewardEnabled = document.querySelector('[data-quiz-settings-reward-enabled]');
    var rewardProvider = document.querySelector('[data-quiz-settings-reward-provider]');
    var rewardRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-settings-reward-row]'));
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
            var row = control.closest ? control.closest('.admin-form-row') : null;
            if (!row) {
                return;
            }
            Array.prototype.slice.call(row.querySelectorAll('[data-quiz-settings-reward-required]')).forEach(function (label) {
                label.hidden = !required;
            });
        });
    }
    function syncReward() {
        if (!rewardEnabled || !rewardProvider) {
            return;
        }
        var enabled = rewardEnabled.checked;
        var ledgerSelected = enabled && rewardProvider.value === 'ledger_asset';
        var couponSelected = enabled && rewardProvider.value === 'coupon';
        setRowsHidden(rewardRows, !enabled);
        setRowsHidden(ledgerRows, !ledgerSelected);
        setRowsHidden(couponRows, !couponSelected);
        rewardProvider.disabled = !enabled;
        setControlsEnabled(rewardControls, enabled);
        setControlsEnabled(ledgerControls, ledgerSelected);
        setControlsEnabled(couponControls, couponSelected);
        setControlsRequired(ledgerControls, ledgerSelected);
        setControlsRequired(couponControls, couponSelected);
    }
    if (rewardEnabled && rewardProvider) {
        rewardEnabled.addEventListener('change', syncReward);
        rewardProvider.addEventListener('change', syncReward);
        syncReward();
    }
})();
</script>

<?php foreach ($quizSettingsHelp as $helpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $helpModal['id'], (string) $helpModal['title'], (string) $helpModal['body_html']); ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
