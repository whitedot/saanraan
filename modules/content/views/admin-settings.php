<?php

$adminPageTitle = '콘텐츠 환경설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/content/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_editor">에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="content_admin_settings_editor" name="editor" class="form-select" required>
                    <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                        <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo (string) ($settings['editor'] ?? 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $editorLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="content_admin_settings_once_history_policy">최초 1회 과거 이용 인정 기준 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="content_admin_settings_once_history_policy" name="once_history_policy" class="form-select" required>
                    <?php foreach (sr_content_once_history_policy_values() as $policyKey => $policyLabel) { ?>
                        <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($settings['once_history_policy'] ?? 'all_access') === (string) $policyKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $policyLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">과금 방식을 최초 1회로 운영할 때 기존 유료 열람, 다운로드, 쿠폰 사용을 이미 이용한 것으로 볼지 정합니다. 기존 원장 거래와 쿠폰 사용 로그는 자동 환불하거나 추가 차감하지 않습니다.</p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
