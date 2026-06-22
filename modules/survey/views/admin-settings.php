<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';

$surveySettingsHelpOpenLabel = '설명 보기';
$surveySettingsHelpBodyHtml = static function (array $items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<p>' . sr_e((string) $item) . '</p>';
    }
    return $html;
};
$surveySettingsHelp = [
    'layout_key' => [
        'id' => 'survey-settings-help-layout-key',
        'title' => '설문 공개 레이아웃',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 목록, 응답, 완료 화면에 적용할 공개 레이아웃입니다.',
            '기본 레이아웃은 설문·여론조사 모듈 CSS 호출 기준을 따르고, 다른 레이아웃을 선택하면 해당 레이아웃의 호출 정책을 따릅니다.',
        ]),
    ],
    'default_status' => [
        'id' => 'survey-settings-help-default-status',
        'title' => '기본 상태',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문을 만들 때 먼저 선택되어 있을 상태입니다.',
            '바로 공개하지 않으려면 초안으로 두고, 문항과 QA 점검을 마친 뒤 수정 화면에서 공개로 바꿉니다.',
        ]),
    ],
    'default_response_limit_policy' => [
        'id' => 'survey-settings-help-default-response-limit-policy',
        'title' => '기본 응답 제한',
        'body_html' => $surveySettingsHelpBodyHtml([
            '회원 또는 익명 응답자가 같은 설문에 다시 응답할 수 있는 기본 기준입니다.',
            '기간당 1회를 선택하면 제한 기간을 초 단위로 함께 입력해야 합니다.',
        ]),
    ],
    'default_response_limit_period_seconds' => [
        'id' => 'survey-settings-help-default-response-limit-period',
        'title' => '기본 제한 기간',
        'body_html' => $surveySettingsHelpBodyHtml([
            '기본 응답 제한이 기간당 1회일 때만 사용하는 초 단위 값입니다.',
            '예를 들어 하루에 한 번만 응답하게 하려면 86400을 입력합니다.',
        ]),
    ],
    'public_list_limit' => [
        'id' => 'survey-settings-help-public-list-limit',
        'title' => '공개 목록 노출 수',
        'body_html' => $surveySettingsHelpBodyHtml([
            '공개 설문 목록에서 한 번에 가져올 설문 수입니다.',
            '관리자 목록과 응답 목록의 페이지 크기는 관리자 공통 페이징 설정을 계속 사용합니다.',
        ]),
    ],
    'skin_key' => [
        'id' => 'survey-settings-help-skin-key',
        'title' => '설문 스킨',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 공개 목록, 상세/응답, 완료 화면의 본문 출력 방식입니다.',
            '허용된 스킨 Key만 저장하고, 누락된 화면 파일은 기본 스킨으로 대체합니다.',
        ]),
    ],
    'default_login_required' => [
        'id' => 'survey-settings-help-default-login-required',
        'title' => '로그인 필요',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문 생성 시 로그인 필요를 기본으로 켤지 정합니다.',
            '보상 설문이나 회원 그룹 제한 설문은 저장 시점에 로그인 필요 상태가 강제됩니다.',
        ]),
    ],
    'default_consent_required' => [
        'id' => 'survey-settings-help-default-consent-required',
        'title' => '참여 동의 필요',
        'body_html' => $surveySettingsHelpBodyHtml([
            '새 설문 생성 시 참여 동의 체크를 기본으로 켤지 정합니다.',
            '동의가 필요한 설문은 저장할 때 동의 문구도 함께 입력해야 합니다.',
        ]),
    ],
    'reaction_preset_key' => [
        'id' => 'survey-settings-help-reaction-preset',
        'title' => '설문 리액션 프리셋',
        'body_html' => $surveySettingsHelpBodyHtml([
            '개별 설문에서 프리셋을 비워둘 때 사용하는 기본 리액션 세트입니다.',
            '리액션 모듈의 프리셋 관리에서 운영자가 세트를 추가하거나 수정할 수 있습니다.',
        ]),
    ],
    'reaction_comment_preset_key' => [
        'id' => 'survey-settings-help-reaction-comment-preset',
        'title' => '댓글 리액션 프리셋',
        'body_html' => $surveySettingsHelpBodyHtml([
            '설문 댓글에 적용할 기본 리액션 세트입니다.',
            '개별 설문에서 댓글 프리셋을 지정하면 이 값보다 우선합니다.',
        ]),
    ],
];
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">공개 화면/새 설문 기본값</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_layout_key', '설문 공개 레이아웃', $surveySettingsHelp['layout_key']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="survey_settings_layout_key" name="layout_key" class="form-select" required>
                        <?php foreach ($surveyLayoutOptions as $layoutKey => $layoutOption) { ?>
                            <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($settings['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">설문 공개 화면의 바깥 레이아웃입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_skin_key', '설문 스킨', $surveySettingsHelp['skin_key']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="survey_settings_skin_key" name="skin_key" class="form-select" required>
                        <?php foreach (sr_survey_skin_options() as $skinKey => $skinLabel) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($settings['skin_key'] ?? 'basic') === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $skinLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="form-help">공개 레이아웃 안쪽의 설문 본문 출력 스킨입니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_default_status', '기본 상태', $surveySettingsHelp['default_status']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $surveyStatusToggleOptions = [];
                    foreach (sr_survey_statuses() as $status) {
                        $surveyStatusToggleOptions[$status] = sr_survey_status_label($status);
                    }
                    echo sr_admin_radio_toggle_group_html('survey_settings_default_status', 'default_status', $surveyStatusToggleOptions, (string) ($settings['default_status'] ?? 'draft'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_response_limit_policy', '기본 응답 제한', $surveySettingsHelp['default_response_limit_policy']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php
                    $surveyLimitPolicyToggleOptions = [];
                    foreach (sr_survey_response_limit_policies() as $policy) {
                        $surveyLimitPolicyToggleOptions[$policy] = sr_survey_response_limit_policy_label($policy);
                    }
                    echo sr_admin_radio_toggle_group_html('survey_settings_response_limit_policy', 'default_response_limit_policy', $surveyLimitPolicyToggleOptions, (string) ($settings['default_response_limit_policy'] ?? 'per_survey_once'), true);
                    ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_response_limit_period', '기본 제한 기간', $surveySettingsHelp['default_response_limit_period_seconds']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <input id="survey_settings_response_limit_period" type="number" name="default_response_limit_period_seconds" value="<?php echo sr_e((string) (int) ($settings['default_response_limit_period_seconds'] ?? 0)); ?>" class="form-input" min="0">
                    <p class="form-help">기간당 1회 제한일 때 초 단위로 입력합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_public_list_limit', '공개 목록 노출 수', $surveySettingsHelp['public_list_limit']['id'], $surveySettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="survey_settings_public_list_limit" type="number" name="public_list_limit" value="<?php echo sr_e((string) (int) ($settings['public_list_limit'] ?? 50)); ?>" class="form-input" min="1" max="100" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_login_required', '로그인 필요', $surveySettingsHelp['default_login_required']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_login_required', 'default_login_required', '1', (int) ($settings['default_login_required'] ?? 1) === 1, '새 설문에 기본 적용'); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_consent_required', '참여 동의 필요', $surveySettingsHelp['default_consent_required']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('survey_settings_consent_required', 'default_consent_required', '1', (int) ($settings['default_consent_required'] ?? 0) === 1, '새 설문에 기본 적용'); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_reaction_preset_key', '설문 리액션 프리셋', $surveySettingsHelp['reaction_preset_key']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="survey_settings_reaction_preset_key" name="reaction_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">개별 설문에서 값을 비워두면 이 값을 사용합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('survey_settings_reaction_comment_preset_key', '댓글 리액션 프리셋', $surveySettingsHelp['reaction_comment_preset_key']['id'], $surveySettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="survey_settings_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($settings['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">설문 댓글 리액션에 적용할 기본 프리셋입니다.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/surveys')); ?>">설문 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($surveySettingsHelp as $helpModal): ?>
    <?php echo sr_admin_help_modal_html((string) $helpModal['id'], (string) $helpModal['title'], (string) $helpModal['body_html']); ?>
<?php endforeach; ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
