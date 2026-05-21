<?php

$adminPageTitle = '데이터 정리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="settings">
    <section class="admin-card admin-list-card card">
        <div class="card-header">
            <h2 class="card-title">보관 기간</h2>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auth_logs_days">인증 로그 보관일</label>
            <div class="admin-form-field">
                <input id="admin_retention_auth_logs_days" type="number" name="auth_logs_days" value="<?php echo sr_e((string) $values['auth_logs_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_audit_logs_days">관리자 작업 로그 보관일</label>
            <div class="admin-form-field">
                <input id="admin_retention_audit_logs_days" type="number" name="audit_logs_days" value="<?php echo sr_e((string) $values['audit_logs_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_used_tokens_days">사용 완료 토큰 보관일</label>
            <div class="admin-form-field">
                <input id="admin_retention_used_tokens_days" type="number" name="used_tokens_days" value="<?php echo sr_e((string) $values['used_tokens_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_sessions_days">만료/폐기 세션 보관일</label>
            <div class="admin-form-field">
                <input id="admin_retention_sessions_days" type="number" name="sessions_days" value="<?php echo sr_e((string) $values['sessions_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <?php if ($hasNotificationTables) { ?>
            <div class="admin-form-row">
                <label class="form-label" for="admin_retention_notifications_days">알림 보관일</label>
                <div class="admin-form-field">
                    <input id="admin_retention_notifications_days" type="number" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>" class="form-input" min="1" max="3650" required>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>">
        <?php } ?>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_module_backups_days">모듈 백업 보관일</label>
            <div class="admin-form-field">
                <input id="admin_retention_module_backups_days" type="number" name="module_backups_days" value="<?php echo sr_e((string) $values['module_backups_days']); ?>" class="form-input" min="1" max="3650" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">요청 기반 자동 정리</span>
            <div class="admin-form-field">
                <label class="admin-form-check form-label" for="modules_admin_retention_auto_cleanup_enabled">
                                    <input id="modules_admin_retention_auto_cleanup_enabled" type="checkbox" name="auto_cleanup_enabled" value="1" class="form-checkbox"<?php echo (int) $values['auto_cleanup_enabled'] === 1 ? ' checked' : ''; ?>>
                                    <?php echo sr_admin_choice_label_html('사용'); ?>
                                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auto_cleanup_interval_hours">자동 정리 간격</label>
            <div class="admin-form-field">
                <input id="admin_retention_auto_cleanup_interval_hours" type="number" name="auto_cleanup_interval_hours" value="<?php echo sr_e((string) $values['auto_cleanup_interval_hours']); ?>" class="form-input" min="1" max="720" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_retention_auto_cleanup_batch_size">자동 정리 배치 크기</label>
            <div class="admin-form-field">
                <input id="admin_retention_auto_cleanup_batch_size" type="number" name="auto_cleanup_batch_size" value="<?php echo sr_e((string) $values['auto_cleanup_batch_size']); ?>" class="form-input" min="1" max="5000" required>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <button type="button" class="btn btn-soft-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-retention-cleanup-modal" data-overlay="#admin-retention-cleanup-modal">
            정리 실행
        </button>
        <button type="submit" class="btn btn-solid-primary">보관 기간 저장</button>
    </div>
</form>

<div id="admin-retention-cleanup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-retention-cleanup-modal-label">
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
                <div class="modal-header">
                    <h3 id="admin-retention-cleanup-modal-label" class="modal-title">데이터 정리 실행</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#admin-retention-cleanup-modal">
                        <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="cleanup">
                    <p>정리 실행은 저장된 보관 기간을 기준으로 처리되며 되돌릴 수 없습니다.</p>
                    <div class="table-wrapper">
                    <table class="table">
                        <thead class="ui-table-head">
                            <tr>
                                <th>대상</th>
                                <th>기준 시각</th>
                                <th>삭제 후보</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>인증 로그</td>
                                <td><?php echo sr_e($previewCutoffs['auth_logs']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['auth_logs']); ?></td>
                            </tr>
                            <tr>
                                <td>관리자 작업 로그</td>
                                <td><?php echo sr_e($previewCutoffs['audit_logs']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['audit_logs']); ?></td>
                            </tr>
                            <tr>
                                <td>비밀번호 재설정 토큰</td>
                                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['password_resets']); ?></td>
                            </tr>
                            <tr>
                                <td>이메일 인증 토큰</td>
                                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['email_verifications']); ?></td>
                            </tr>
                            <tr>
                                <td>만료/폐기 세션</td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['sessions']); ?></td>
                            </tr>
                            <tr>
                                <td>PHP 런타임 세션</td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['runtime_sessions']); ?></td>
                            </tr>
                            <tr>
                                <td>인증 제한 카운터</td>
                                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['rate_limits']); ?></td>
                            </tr>
                            <?php if ($hasNotificationTables) { ?>
                                <tr>
                                    <td>알림</td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notifications']); ?></td>
                                </tr>
                                <tr>
                                    <td>알림 발송 대기열</td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notification_deliveries']); ?></td>
                                </tr>
                                <tr>
                                    <td>알림 읽음 기록</td>
                                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                                    <td><?php echo sr_e((string) $previewCounts['notification_reads']); ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td>모듈 파일 백업</td>
                                <td><?php echo sr_e($previewCutoffs['module_backups']); ?></td>
                                <td><?php echo sr_e((string) $previewCounts['module_backups']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label">삭제 후보 확인</span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="modules_admin_retention_cleanup_confirmed">
                                                            <input id="modules_admin_retention_cleanup_confirmed" type="checkbox" name="cleanup_confirmed" value="1" class="form-checkbox" required data-overlay-focus>
                                                            <?php echo sr_admin_choice_label_html('삭제 후보 수를 확인했습니다.'); ?>
                                                        </label>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="admin_retention_cleanup_phrase">확인 문구</label>
                        <div class="admin-form-field">
                            <input id="admin_retention_cleanup_phrase" type="text" name="cleanup_phrase" maxlength="20" placeholder="DELETE" required class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-default modal-action" data-overlay="#admin-retention-cleanup-modal">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">정리 실행</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
