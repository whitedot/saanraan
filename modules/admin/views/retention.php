<?php

$adminPageTitle = sr_t('admin::ui.text.fbef5b12');
$adminPageSubtitle = [
    sr_t('admin::retention.policy.deleted_content.title'),
    sr_t('admin::retention.policy.deleted_content.body'),
];
$retentionHelpOpenLabel = sr_t('admin::retention.help.open');
$retentionHelpBodyHtml = static function (array $translationKeys): string {
    $html = '';
    foreach ($translationKeys as $translationKey) {
        $html .= '<p>' . sr_e(sr_t('admin::' . $translationKey)) . '</p>';
    }

    return $html;
};
$retentionHelp = [
    'auth_logs_days' => [
        'id' => 'admin-retention-auth-logs-help-modal',
        'title' => sr_t('admin::retention.help.auth_logs.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.auth_logs.body.1',
            'retention.help.auth_logs.body.2',
            'retention.help.auth_logs.body.3',
        ]),
    ],
    'audit_logs_days' => [
        'id' => 'admin-retention-audit-logs-help-modal',
        'title' => sr_t('admin::retention.help.audit_logs.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.audit_logs.body.1',
            'retention.help.audit_logs.body.2',
            'retention.help.audit_logs.body.3',
        ]),
    ],
    'used_tokens_days' => [
        'id' => 'admin-retention-used-tokens-help-modal',
        'title' => sr_t('admin::retention.help.used_tokens.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.used_tokens.body.1',
            'retention.help.used_tokens.body.2',
            'retention.help.used_tokens.body.3',
        ]),
    ],
    'sessions_days' => [
        'id' => 'admin-retention-sessions-help-modal',
        'title' => sr_t('admin::retention.help.sessions.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.sessions.body.1',
            'retention.help.sessions.body.2',
            'retention.help.sessions.body.3',
        ]),
    ],
    'banner_clicks_days' => [
        'id' => 'admin-retention-banner-clicks-help-modal',
        'title' => sr_t('admin::retention.help.banner_clicks.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.banner_clicks.body.1',
            'retention.help.banner_clicks.body.2',
            'retention.help.banner_clicks.body.3',
        ]),
    ],
    'notifications_days' => [
        'id' => 'admin-retention-notifications-help-modal',
        'title' => sr_t('admin::retention.help.notifications.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.notifications.body.1',
            'retention.help.notifications.body.2',
            'retention.help.notifications.body.3',
        ]),
    ],
    'module_backups_days' => [
        'id' => 'admin-retention-module-backups-help-modal',
        'title' => sr_t('admin::retention.help.module_backups.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.module_backups.body.1',
            'retention.help.module_backups.body.2',
            'retention.help.module_backups.body.3',
        ]),
    ],
    'auto_cleanup_enabled' => [
        'id' => 'admin-retention-auto-cleanup-enabled-help-modal',
        'title' => sr_t('admin::retention.help.auto_cleanup_enabled.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.auto_cleanup_enabled.body.1',
            'retention.help.auto_cleanup_enabled.body.2',
            'retention.help.auto_cleanup_enabled.body.3',
        ]),
    ],
    'auto_cleanup_interval_hours' => [
        'id' => 'admin-retention-auto-cleanup-interval-help-modal',
        'title' => sr_t('admin::retention.help.auto_cleanup_interval.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.auto_cleanup_interval.body.1',
            'retention.help.auto_cleanup_interval.body.2',
            'retention.help.auto_cleanup_interval.body.3',
        ]),
    ],
    'auto_cleanup_batch_size' => [
        'id' => 'admin-retention-auto-cleanup-batch-help-modal',
        'title' => sr_t('admin::retention.help.auto_cleanup_batch.title'),
        'body_html' => $retentionHelpBodyHtml([
            'retention.help.auto_cleanup_batch.body.1',
            'retention.help.auto_cleanup_batch.body.2',
            'retention.help.auto_cleanup_batch.body.3',
        ]),
    ],
];
$retentionTargetLabels = [
    'auth_logs' => sr_t('admin::ui.text.428871d5'),
    'audit_logs' => sr_t('admin::ui.admin.d0bd9568'),
    'password_resets' => sr_t('admin::ui.password.settings.86a49355'),
    'email_verifications' => sr_t('admin::ui.email.e74975e8'),
    'sessions' => sr_t('admin::ui.text.7c27e716'),
    'runtime_sessions' => sr_t('admin::ui.php.19df2136'),
    'rate_limits' => sr_t('admin::ui.text.b3b88e44'),
    'content_asset_access_pending_logs' => sr_t('admin::retention.pending.content_access'),
    'content_asset_action_pending_logs' => sr_t('admin::retention.pending.content_action'),
    'community_asset_pending_logs' => sr_t('admin::retention.pending.community_asset'),
    'module_upload_work_dirs' => sr_t('admin::retention.module_upload_work_dirs'),
    'banner_clicks' => sr_t('admin::ui.banner_clicks.retention_target'),
    'notification_deliveries' => sr_t('admin::ui.notification.56c30db0'),
    'notification_reads' => sr_t('admin::ui.notification.82294dd1'),
    'notifications' => sr_t('admin::ui.notification.12ddd6ca'),
    'admin_notification_reads' => sr_t('admin::retention.admin_notification_reads'),
    'admin_notifications' => sr_t('admin::retention.admin_notifications'),
    'module_backups' => sr_t('admin::ui.text.b7aa8533'),
    'identity_verification_closed_attempts' => sr_t('admin::retention.identity_verification_closed_attempts'),
    'identity_verification_expired_results' => sr_t('admin::retention.identity_verification_expired_results'),
    'point_legal_transactions' => '포인트 법정 보관 만료 거래 익명화',
    'point_legal_expiration_consumptions' => '포인트 법정 보관 만료 소비 연결 익명화',
    'reward_legal_transactions' => '적립금 법정 보관 만료 거래 익명화',
    'reward_legal_expiration_consumptions' => '적립금 법정 보관 만료 소비 연결 익명화',
    'reward_legal_withdrawal_requests' => '적립금 법정 보관 만료 출금 신청 익명화',
    'deposit_legal_transactions' => '예치금 법정 보관 만료 거래 익명화',
    'deposit_legal_refund_requests' => '예치금 법정 보관 만료 환불 신청 익명화',
    'coupon_legal_issues' => '쿠폰 법정 보관 만료 지급 기록 익명화',
    'coupon_legal_redemptions' => '쿠폰 법정 보관 만료 사용 기록 익명화',
    'coupon_legal_claim_logs' => '쿠폰 법정 보관 만료 발급 로그 익명화',
    'coupon_dispute_notes' => '쿠폰 분쟁 보관 만료 메모 정리',
    'asset_exchange_legal_logs' => '환전 법정 보관 만료 로그 익명화',
    'asset_exchange_dispute_notes' => '환전 분쟁 보관 만료 실패 사유 정리',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="settings">
    <section class="card admin-list-card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.7a617d5f')); ?></h2>
        </div>
        <div class="form-row">
            <span class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('admin::ui.text.642180ad') . ' ' . $retentionHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($retentionHelp['auto_cleanup_enabled']['id']); ?>" data-overlay="#<?php echo sr_e($retentionHelp['auto_cleanup_enabled']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <span><?php echo sr_e(sr_t('admin::ui.text.642180ad')); ?></span>
            </span>
            <div class="form-field">
                <label class="form-check form-label" for="modules_admin_retention_auto_cleanup_enabled">
                    <input id="modules_admin_retention_auto_cleanup_enabled" type="checkbox" name="auto_cleanup_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) $values['auto_cleanup_enabled'] === 1 ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_auto_cleanup_interval_hours', sr_t('admin::ui.text.3a13e62b'), $retentionHelp['auto_cleanup_interval_hours']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_auto_cleanup_interval_hours" type="number" name="auto_cleanup_interval_hours" value="<?php echo sr_e((string) $values['auto_cleanup_interval_hours']); ?>" class="form-input" min="1" max="720" required>
                    <span class="input-group-text">시간</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_auto_cleanup_batch_size', sr_t('admin::ui.text.df1bcbf1'), $retentionHelp['auto_cleanup_batch_size']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_auto_cleanup_batch_size" type="number" name="auto_cleanup_batch_size" value="<?php echo sr_e((string) $values['auto_cleanup_batch_size']); ?>" class="form-input" min="1" max="5000" required>
                    <span class="input-group-text">건</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_auth_logs_days', sr_t('admin::ui.text.6cc455be'), $retentionHelp['auth_logs_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_auth_logs_days" type="number" name="auth_logs_days" value="<?php echo sr_e((string) $values['auth_logs_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_audit_logs_days', sr_t('admin::ui.admin.e6a220a3'), $retentionHelp['audit_logs_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_audit_logs_days" type="number" name="audit_logs_days" value="<?php echo sr_e((string) $values['audit_logs_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_used_tokens_days', sr_t('admin::ui.active.e5845fb2'), $retentionHelp['used_tokens_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_used_tokens_days" type="number" name="used_tokens_days" value="<?php echo sr_e((string) $values['used_tokens_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_sessions_days', sr_t('admin::ui.text.48581a76'), $retentionHelp['sessions_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_sessions_days" type="number" name="sessions_days" value="<?php echo sr_e((string) $values['sessions_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_banner_clicks_days', sr_t('admin::ui.banner_clicks.retention'), $retentionHelp['banner_clicks_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_banner_clicks_days" type="number" name="banner_clicks_days" value="<?php echo sr_e((string) $values['banner_clicks_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
        <?php if ($hasNotificationTables) { ?>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('admin_retention_notifications_days', sr_t('admin::ui.notification.9bf7948a'), $retentionHelp['notifications_days']['id'], $retentionHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group admin-input-unit">
                        <input id="admin_retention_notifications_days" type="number" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>" class="form-input" min="1" max="3650" required>
                        <span class="input-group-text">일</span>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>">
        <?php } ?>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('admin_retention_module_backups_days', sr_t('admin::ui.text.b4268fef'), $retentionHelp['module_backups_days']['id'], $retentionHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_retention_module_backups_days" type="number" name="module_backups_days" value="<?php echo sr_e((string) $values['module_backups_days']); ?>" class="form-input" min="1" max="3650" required>
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="button" class="btn btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-retention-cleanup-modal" data-overlay="#admin-retention-cleanup-modal" data-admin-retention-cleanup-mode="retention">
            <?php echo sr_e(sr_t('admin::ui.text.90922df1')); ?>
        </button>
        <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-retention-cleanup-modal" data-overlay="#admin-retention-cleanup-modal" data-admin-retention-cleanup-mode="immediate">
            즉시 정리
        </button>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.save.864e6c0c')); ?></button>
    </div>
</form>

<?php $autoCleanupStatusRows = is_array($autoCleanupRuntimeStatus ?? null) ? $autoCleanupRuntimeStatus : []; ?>
<?php if ($autoCleanupStatusRows !== []) { ?>
    <section class="card admin-list-card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e('자동 정리 실행 상태'); ?></h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <caption class="sr-only"><?php echo sr_e('자동 정리 실행 상태'); ?></caption>
                <thead>
                <tr>
                    <th><?php echo sr_e('범위'); ?></th>
                    <th><?php echo sr_e('마지막 시도'); ?></th>
                    <th><?php echo sr_e('마지막 성공'); ?></th>
                    <th><?php echo sr_e('마지막 실패'); ?></th>
                    <th><?php echo sr_e('실패 메시지'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($autoCleanupStatusRows as $autoCleanupStatusRow) { ?>
                    <?php
                    $autoCleanupScope = (string) ($autoCleanupStatusRow['scope'] ?? '');
                    $autoCleanupScopeLabel = $autoCleanupScope === 'admin' ? '관리자 요청' : '공개 요청';
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_e($autoCleanupScopeLabel); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($autoCleanupStatusRow['last_attempt_at'] ?? ''), '-'); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($autoCleanupStatusRow['last_success_at'] ?? ''), '-'); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($autoCleanupStatusRow['last_failure_at'] ?? ''), '-'); ?></td>
                        <td class="admin-table-break"><?php echo (string) ($autoCleanupStatusRow['last_failure_message'] ?? '') !== '' ? sr_e((string) $autoCleanupStatusRow['last_failure_message']) : '-'; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php foreach ($retentionHelp as $retentionHelpModal) { ?>
    <?php echo sr_admin_help_modal_html($retentionHelpModal['id'], $retentionHelpModal['title'], $retentionHelpModal['body_html']); ?>
<?php } ?>

<div id="admin-retention-cleanup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-retention-cleanup-modal-label">
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="modal-content admin-form ui-form-theme" data-admin-retention-cleanup-form>
            <div class="modal-header">
                <h3 id="admin-retention-cleanup-modal-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.text.0b10a14f')); ?></h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-retention-cleanup-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="cleanup">
                <div class="form-row">
                    <span class="form-label">정리 방식 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                    <div class="form-field">
                        <label class="form-check form-label" for="admin_retention_cleanup_mode_retention">
                            <input id="admin_retention_cleanup_mode_retention" type="radio" name="cleanup_mode" value="retention" class="form-radio" checked>
                            기준에 따라 정리
                        </label>
                        <label class="form-check form-label" for="admin_retention_cleanup_mode_immediate">
                            <input id="admin_retention_cleanup_mode_immediate" type="radio" name="cleanup_mode" value="immediate" class="form-radio">
                            즉시 정리
                        </label>
                    </div>
                </div>
                <p><?php echo sr_e(sr_t('admin::ui.save.5d203a1c')); ?></p>
                <p class="form-help">즉시 정리는 운영 보관일 기준 대상을 현재 시각 기준으로 정리합니다. 법정 보관 만료 대상은 기존 법정 기준을 유지합니다.</p>
                <div class="table-wrapper">
                <table class="table table-list">
                    <thead>
                        <tr>
                            <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
                            <th><?php echo sr_e(sr_t('admin::ui.text.8e07f3ae')); ?></th>
                            <th><?php echo sr_e('기준 후보'); ?></th>
                            <th><?php echo sr_e('즉시 후보'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cleanupTargetKeys as $retentionTargetKey) { ?>
                            <?php
                            if (!array_key_exists($retentionTargetKey, $previewCounts)) {
                                continue;
                            }
                            $retentionTarget = is_array($previewTargets[$retentionTargetKey] ?? null) ? $previewTargets[$retentionTargetKey] : [];
                            $retentionCutoffKey = (string) ($retentionTarget['cutoff_key'] ?? '');
                            if ($retentionCutoffKey === '' || !array_key_exists($retentionCutoffKey, $previewCutoffs)) {
                                continue;
                            }
                            $retentionTargetLabel = (string) ($retentionTargetLabels[$retentionTargetKey] ?? str_replace('_', ' ', $retentionTargetKey));
                            ?>
                            <tr>
                                <td><?php echo sr_e($retentionTargetLabel); ?></td>
                                <td><?php echo sr_e((string) $previewCutoffs[$retentionCutoffKey]); ?></td>
                                <td><?php echo sr_e((string) $previewCounts[$retentionTargetKey]); ?></td>
                                <td><?php echo sr_e((string) ($immediatePreviewCounts[$retentionTargetKey] ?? $previewCounts[$retentionTargetKey])); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
                <div class="form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('admin::ui.delete.b5dd39cf')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                    <div class="form-field">
                        <?php echo sr_admin_switch_html('modules_admin_retention_cleanup_confirmed', 'cleanup_confirmed', '1', false, sr_t('admin::ui.delete.ec013040'), '', ' required'); ?>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="admin_retention_cleanup_phrase"><?php echo sr_e(sr_t('admin::ui.text.82e63a67')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <div class="validation-field">
                            <input id="admin_retention_cleanup_phrase" type="text" name="cleanup_phrase" maxlength="20" placeholder="DELETE" required class="form-input form-control-icon-end" aria-describedby="admin_retention_cleanup_phrase_error" data-admin-confirm-phrase="DELETE" data-admin-confirm-message="<?php echo sr_e(sr_t('admin::retention.cleanup.phrase_error')); ?>">
                            <div class="validation-static-icon" hidden data-admin-confirm-phrase-icon>
                                <?php echo sr_material_icon_html('info', 'validation-error-icon', sr_t('admin::retention.cleanup.phrase_error')); ?>
                            </div>
                        </div>
                        <p id="admin_retention_cleanup_phrase_error" class="validation-error-note" hidden data-admin-confirm-phrase-error><?php echo sr_e(sr_t('admin::retention.cleanup.phrase_error')); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-note">
                <p class="form-help">정리 실행은 보관 정책 저장과 별도로 바로 처리됩니다. 위 보관 정책 입력값은 함께 저장되지 않습니다.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-retention-cleanup-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">선택한 방식으로 정리</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-admin-retention-cleanup-form]');
    if (!form) {
        return;
    }

    var input = form.querySelector('[data-admin-confirm-phrase]');
    var errorNote = form.querySelector('[data-admin-confirm-phrase-error]');
    var errorIcon = form.querySelector('[data-admin-confirm-phrase-icon]');
    if (!input) {
        return;
    }

    var expectedPhrase = input.getAttribute('data-admin-confirm-phrase') || '';
    var errorMessage = input.getAttribute('data-admin-confirm-message') || '';
    var setCleanupMode = function (mode) {
        var targetMode = mode === 'immediate' ? 'immediate' : 'retention';
        var modeInput = form.querySelector('input[name="cleanup_mode"][value="' + targetMode + '"]');
        if (modeInput) {
            modeInput.checked = true;
        }
    };

    Array.prototype.slice.call(document.querySelectorAll('[data-admin-retention-cleanup-mode]')).forEach(function (button) {
        button.addEventListener('click', function () {
            setCleanupMode(button.getAttribute('data-admin-retention-cleanup-mode') || 'retention');
        });
    });

    var clearPhraseError = function () {
        input.setCustomValidity('');
        input.classList.remove('form-input-invalid');
        input.removeAttribute('aria-invalid');
        if (errorNote) {
            errorNote.hidden = true;
        }
        if (errorIcon) {
            errorIcon.hidden = true;
        }
    };

    var showPhraseError = function () {
        input.setCustomValidity(errorMessage);
        input.classList.add('form-input-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (errorNote) {
            errorNote.hidden = false;
        }
        if (errorIcon) {
            errorIcon.hidden = false;
        }
    };

    input.addEventListener('input', function () {
        if (input.value.trim() === expectedPhrase) {
            clearPhraseError();
        } else if (errorNote && !errorNote.hidden) {
            showPhraseError();
        } else {
            input.setCustomValidity('');
            input.classList.remove('form-input-invalid');
            input.removeAttribute('aria-invalid');
            if (errorIcon) {
                errorIcon.hidden = true;
            }
        }
    });

    form.addEventListener('submit', function (event) {
        var normalizedValue = input.value.trim();
        if (normalizedValue === '' || normalizedValue === expectedPhrase) {
            if (normalizedValue === expectedPhrase) {
                clearPhraseError();
            }
            return;
        }

        showPhraseError();
        event.preventDefault();
        input.focus();
        input.reportValidity();
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
