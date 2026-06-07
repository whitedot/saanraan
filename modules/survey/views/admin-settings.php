<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/surveys/settings')); ?>" class="admin-card card admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <div class="card-header"><h2 class="card-title">새 설문 기본값</h2></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-field">
                <label class="form-label" for="survey_settings_default_status">기본 상태</label>
                <select id="survey_settings_default_status" name="default_status" class="form-select">
                    <?php foreach (sr_survey_statuses() as $status): ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($settings['default_status'] ?? 'draft') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_status_label($status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_settings_response_limit_policy">기본 응답 제한</label>
                <select id="survey_settings_response_limit_policy" name="default_response_limit_policy" class="form-select">
                    <?php foreach (sr_survey_response_limit_policies() as $policy): ?>
                        <option value="<?php echo sr_e($policy); ?>"<?php echo (string) ($settings['default_response_limit_policy'] ?? 'per_survey_once') === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_response_limit_policy_label($policy)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_settings_response_limit_period">기본 제한 기간</label>
                <input id="survey_settings_response_limit_period" type="number" name="default_response_limit_period_seconds" value="<?php echo sr_e((string) (int) ($settings['default_response_limit_period_seconds'] ?? 0)); ?>" class="form-input" min="0">
                <p class="admin-form-help">기간당 1회 제한일 때 초 단위로 입력합니다.</p>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_settings_public_list_limit">공개 목록 노출 수</label>
                <input id="survey_settings_public_list_limit" type="number" name="public_list_limit" value="<?php echo sr_e((string) (int) ($settings['public_list_limit'] ?? 50)); ?>" class="form-input" min="1" max="100">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-field">
                <label class="admin-form-check form-label" for="survey_settings_login_required">
                    <input id="survey_settings_login_required" type="checkbox" name="default_login_required" value="1" class="form-checkbox"<?php echo (int) ($settings['default_login_required'] ?? 1) === 1 ? ' checked' : ''; ?>>
                    로그인 필요
                </label>
            </div>
            <div class="form-field">
                <label class="admin-form-check form-label" for="survey_settings_consent_required">
                    <input id="survey_settings_consent_required" type="checkbox" name="default_consent_required" value="1" class="form-checkbox"<?php echo (int) ($settings['default_consent_required'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    참여 동의 필요
                </label>
            </div>
        </div>
    </div>
    <div class="card-footer form-actions">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
