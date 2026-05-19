<?php

$adminPageTitle = '데이터 정리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/retention')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <h2>보관 기간</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">인증 로그 보관일</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">인증 로그 보관일</span>
                <input type="number" name="auth_logs_days" value="<?php echo sr_e((string) $values['auth_logs_days']); ?>" min="1" max="3650" required class="form-input">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">감사 로그 보관일</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">감사 로그 보관일</span>
                <input type="number" name="audit_logs_days" value="<?php echo sr_e((string) $values['audit_logs_days']); ?>" min="1" max="3650" required class="form-input">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">사용 완료 토큰 보관일</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">사용 완료 토큰 보관일</span>
                <input type="number" name="used_tokens_days" value="<?php echo sr_e((string) $values['used_tokens_days']); ?>" min="1" max="3650" required class="form-input">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">만료/폐기 세션 보관일</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">만료/폐기 세션 보관일</span>
                <input type="number" name="sessions_days" value="<?php echo sr_e((string) $values['sessions_days']); ?>" min="1" max="3650" required class="form-input">
                </label>
            </div>
        </div>
        <?php if ($hasNotificationTables) { ?>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">알림 보관일</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">알림 보관일</span>
                    <input type="number" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>" min="1" max="3650" required class="form-input">
                    </label>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="notifications_days" value="<?php echo sr_e((string) $values['notifications_days']); ?>">
        <?php } ?>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">모듈 백업 보관일</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">모듈 백업 보관일</span>
                <input type="number" name="module_backups_days" value="<?php echo sr_e((string) $values['module_backups_days']); ?>" min="1" max="3650" required class="form-input">
                </label>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2>실행 확인</h2>
        <p>정리 실행은 되돌릴 수 없습니다. 삭제 후보 수를 확인한 뒤 확인 문구를 입력하세요.</p>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">아래 삭제 후보 수를 확인했습니다.</span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input type="checkbox" name="cleanup_confirmed" value="1" class="form-checkbox" required>
                        <?php echo sr_admin_choice_label_html('아래 삭제 후보 수를 확인했습니다.'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">확인 문구</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">확인 문구</span>
                <input type="text" name="cleanup_phrase" maxlength="20" placeholder="DELETE" required class="form-input">
                </label>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">정리 실행</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">삭제 후보 미리보기</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>대상</th>
                <th>기준 시각</th>
                <th>삭제 후보</th>
                <th>이번 삭제</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>인증 로그</td>
                <td><?php echo sr_e($previewCutoffs['auth_logs']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['auth_logs']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['auth_logs'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>감사 로그</td>
                <td><?php echo sr_e($previewCutoffs['audit_logs']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['audit_logs']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['audit_logs'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>비밀번호 재설정 토큰</td>
                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['password_resets']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['password_resets'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>이메일 인증 토큰</td>
                <td><?php echo sr_e($previewCutoffs['used_tokens']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['email_verifications']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['email_verifications'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>만료/폐기 세션</td>
                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['sessions']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['sessions'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>PHP 런타임 세션</td>
                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['runtime_sessions']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['runtime_sessions'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>인증 제한 카운터</td>
                <td><?php echo sr_e($previewCutoffs['sessions']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['rate_limits']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['rate_limits'] ?? '')); ?></td>
            </tr>
            <?php if ($hasNotificationTables) { ?>
                <tr>
                    <td>알림</td>
                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                    <td><?php echo sr_e((string) $previewCounts['notifications']); ?></td>
                    <td><?php echo sr_e((string) ($deletedCounts['notifications'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>알림 발송 대기열</td>
                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                    <td><?php echo sr_e((string) $previewCounts['notification_deliveries']); ?></td>
                    <td><?php echo sr_e((string) ($deletedCounts['notification_deliveries'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>알림 읽음 기록</td>
                    <td><?php echo sr_e($previewCutoffs['notifications']); ?></td>
                    <td><?php echo sr_e((string) $previewCounts['notification_reads']); ?></td>
                    <td><?php echo sr_e((string) ($deletedCounts['notification_reads'] ?? '')); ?></td>
                </tr>
            <?php } ?>
            <tr>
                <td>모듈 파일 백업</td>
                <td><?php echo sr_e($previewCutoffs['module_backups']); ?></td>
                <td><?php echo sr_e((string) $previewCounts['module_backups']); ?></td>
                <td><?php echo sr_e((string) ($deletedCounts['module_backups'] ?? '')); ?></td>
            </tr>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
