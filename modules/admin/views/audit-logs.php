<?php

$adminPageTitle = sr_t('admin::ui.admin.d0bd9568');
$auditSort = isset($auditSort) && is_array($auditSort) ? $auditSort : sr_admin_audit_log_default_sort();
include SR_ROOT . '/modules/admin/views/layout-header.php';
$auditMetadataModals = [];
$auditActorMemberModalId = 'admin-audit-actor-member-modal';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-filter admin-audit-filter ui-form-theme">
    <?php if (($filters['event_type'] ?? '') !== '') { ?>
        <input type="hidden" name="event_type" value="<?php echo sr_e((string) $filters['event_type']); ?>">
    <?php } ?>
    <?php if (($filters['target_type'] ?? '') !== '') { ?>
        <input type="hidden" name="target_type" value="<?php echo sr_e((string) $filters['target_type']); ?>">
    <?php } ?>
    <?php if (($filters['target_id'] ?? '') !== '') { ?>
        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $filters['target_id']); ?>">
    <?php } ?>
    <div class="admin-filter-header">
        <strong><?php echo sr_e(sr_t('admin::ui.search.3aa5fca0')); ?></strong>
    </div>
    <div class="admin-filter-grid admin-audit-search-grid">
        <label class="admin-filter-field admin-audit-filter-field" for="modules_admin_audit_logs_field">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.b79bc9c8')); ?></span>
            <select id="modules_admin_audit_logs_field" name="field" class="form-select">
                <?php foreach (['event_type' => sr_t('admin::ui.text.b7c0f34b'), 'target_type' => sr_t('admin::ui.text.91df7a82'), 'target_id' => '대상 식별값', 'actor_account_id' => sr_t('admin::ui.id.2ea55f7c')] as $value => $label) { ?>
                    <option value="<?php echo sr_e($value); ?>"<?php echo $filters['field'] === $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field admin-audit-filter-result" for="modules_admin_audit_logs_result">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.109383e3')); ?></span>
            <select id="modules_admin_audit_logs_result" name="result" class="form-select">
                <?php foreach (['' => sr_t('admin::ui.all.a4b69faf'), 'success' => sr_t('admin::ui.text.b4f76a33'), 'failure' => sr_t('admin::ui.text.2743911f')] as $value => $label) { ?>
                    <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['result'] === (string) $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field admin-audit-filter-date" for="modules_admin_audit_logs_date_from">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.f86e346d')); ?></span>
            <input id="modules_admin_audit_logs_date_from" type="date" name="date_from" value="<?php echo sr_e($filters['date_from']); ?>" class="form-input">
        </label>
        <label class="admin-filter-field admin-audit-filter-date" for="modules_admin_audit_logs_date_to">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.9e586213')); ?></span>
            <input id="modules_admin_audit_logs_date_to" type="date" name="date_to" value="<?php echo sr_e($filters['date_to']); ?>" class="form-input">
        </label>
        <label class="admin-filter-field admin-audit-filter-keyword" for="modules_admin_audit_logs_keyword">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.bda397fc')); ?></span>
            <input id="modules_admin_audit_logs_keyword" type="text" name="q" value="<?php echo sr_e($filters['q']); ?>" class="form-input" maxlength="80" placeholder="<?php echo sr_e(sr_t('admin::ui.id.f8d506bd')); ?>">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('admin::ui.text.f8d240bf')); ?></button>
    </div>
    <?php if (($filters['event_type'] ?? '') !== '' || ($filters['target_type'] ?? '') !== '' || ($filters['target_id'] ?? '') !== '') { ?>
        <div class="admin-summary-stats">
            <?php if (($filters['event_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">이벤트 <strong><?php echo sr_e((string) $filters['event_type']); ?></strong></span>
            <?php } ?>
            <?php if (($filters['target_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">대상 유형 <strong><?php echo sr_e(sr_admin_code_label((string) $filters['target_type'], 'target_type')); ?></strong></span>
            <?php } ?>
            <a href="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-summary-meta">필터 해제</a>
        </div>
    <?php } ?>
</form>

<div class="admin-card admin-list-card card admin-list-form">
    <div class="admin-list-summary-row">
        <?php if (empty($auditSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="작업 로그 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($auditPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-audit-log-table">
        <thead class="ui-table-head">
            <tr>
                <th<?php echo sr_admin_sort_aria('created_at', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.faea4ccf'), 'created_at', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('actor_account_id', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.750086e9'), 'actor_account_id', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('event_type', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.46b289bb'), 'event_type', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('target_type', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.8c609deb'), 'target_type', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('result', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.109383e3'), 'result', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('ip_address', $auditSort); ?>><?php echo sr_admin_sort_header_html('IP', 'ip_address', $auditSort, sr_admin_audit_log_sort_options(), sr_admin_audit_log_default_sort()); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.4cd44bae')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.7d98432e')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs === []) { ?>
                <tr>
                    <td colspan="8" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.admin.7d324209')); ?></td>
                </tr>
            <?php } ?>
            <?php foreach ($logs as $log) { ?>
                <tr>
                    <td><?php echo sr_e((string) $log['created_at']); ?></td>
                    <td>
                        <?php $actorAccountId = (int) ($log['actor_account_id'] ?? 0); ?>
                        <?php if ($actorAccountId > 0) { ?>
                            <button type="button" class="btn btn-sm btn-soft-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($auditActorMemberModalId); ?>" data-overlay="#<?php echo sr_e($auditActorMemberModalId); ?>" data-admin-audit-actor-member data-account-id="<?php echo sr_e((string) $actorAccountId); ?>" data-member-url="<?php echo sr_e(sr_url('/admin/members/summary?id=' . (string) $actorAccountId)); ?>">
                                회원 정보
                            </button>
                        <?php } else { ?>
                            <?php echo sr_e(sr_admin_audit_log_display_actor_type($log)); ?>
                        <?php } ?>
                    </td>
                    <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                    <td><?php echo sr_e(sr_admin_audit_log_display_target($log)); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $log['result'], 'result')); ?></td>
                    <td><?php echo sr_e((string) $log['ip_address']); ?></td>
                    <td class="admin-audit-message"><?php echo sr_e(sr_admin_audit_log_display_message($log)); ?></td>
                    <td class="admin-audit-metadata">
                        <?php $metadata = sr_admin_audit_log_display_metadata($log); ?>
                        <?php if ($metadata === '') { ?>
                            -
                        <?php } else { ?>
                            <?php
                            $metadataModalId = 'admin-audit-metadata-modal-' . (int) $log['id'];
                            $auditMetadataModals[] = [
                                'id' => $metadataModalId,
                                'created_at' => (string) $log['created_at'],
                                'event_type' => sr_admin_event_type_label((string) $log['event_type']),
                                'metadata' => $metadata,
                            ];
                            ?>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('admin::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('admin::ui.text.ac5b575f')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($metadataModalId); ?>" data-overlay="#<?php echo sr_e($metadataModalId); ?>"><?php echo sr_material_icon_html('visibility'); ?></button>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</div>

<?php echo sr_admin_pagination_html($auditPagination, '작업 로그 페이지'); ?>

<div id="<?php echo sr_e($auditActorMemberModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($auditActorMemberModalId); ?>_title" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($auditActorMemberModalId); ?>_title" class="modal-title">처리자 회원 정보</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($auditActorMemberModalId); ?>">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <p class="admin-form-help" data-admin-audit-actor-loading>회원 정보를 불러오는 중입니다.</p>
                <p class="admin-form-help hidden" data-admin-audit-actor-error></p>
                <dl class="admin-module-detail-list hidden" data-admin-audit-actor-detail>
                    <dt>공개 해시</dt>
                    <dd data-admin-audit-actor-field="account_public_hash">-</dd>
                    <dt>표시 이름</dt>
                    <dd data-admin-audit-actor-field="display_name">-</dd>
                    <dt>이메일</dt>
                    <dd data-admin-audit-actor-field="email">-</dd>
                    <dt>상태</dt>
                    <dd data-admin-audit-actor-field="status_label">-</dd>
                    <dt>이메일 인증</dt>
                    <dd data-admin-audit-actor-field="email_verified_label">-</dd>
                    <dt>마지막 로그인</dt>
                    <dd data-admin-audit-actor-field="last_login_at">-</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-solid-primary hidden" data-admin-audit-actor-edit-link>회원 관리</a>
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($auditActorMemberModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($auditMetadataModals as $auditMetadataModal) { ?>
    <div id="<?php echo sr_e((string) $auditMetadataModal['id']); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e((string) $auditMetadataModal['id']); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog admin-audit-metadata-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e((string) $auditMetadataModal['id']); ?>_title" class="modal-title"><?php echo sr_e(sr_t('admin::ui.text.a72ac849')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['event_type']); ?></span>
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['created_at']); ?></span>
                    </div>
                    <pre class="admin-audit-metadata-pre"><code><?php echo sr_e((string) $auditMetadataModal['metadata']); ?></code></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<script>
(function () {
    'use strict';

    var modal = document.getElementById('<?php echo sr_e($auditActorMemberModalId); ?>');
    if (!modal || !window.fetch) {
        return;
    }

    var loading = modal.querySelector('[data-admin-audit-actor-loading]');
    var error = modal.querySelector('[data-admin-audit-actor-error]');
    var detail = modal.querySelector('[data-admin-audit-actor-detail]');
    var editLink = modal.querySelector('[data-admin-audit-actor-edit-link]');
    var fields = {};
    modal.querySelectorAll('[data-admin-audit-actor-field]').forEach(function (field) {
        fields[field.getAttribute('data-admin-audit-actor-field') || ''] = field;
    });

    function setText(field, value) {
        if (!fields[field]) {
            return;
        }

        fields[field].textContent = value ? String(value) : '-';
    }

    function resetModal() {
        if (loading) {
            loading.classList.remove('hidden');
        }
        if (error) {
            error.classList.add('hidden');
            error.textContent = '';
        }
        if (detail) {
            detail.classList.add('hidden');
        }
        if (editLink) {
            editLink.classList.add('hidden');
            editLink.classList.remove('modal-action');
            editLink.setAttribute('href', '#');
        }
        Object.keys(fields).forEach(function (field) {
            fields[field].textContent = '-';
        });
    }

    function showError(message) {
        if (loading) {
            loading.classList.add('hidden');
        }
        if (detail) {
            detail.classList.add('hidden');
        }
        if (error) {
            error.textContent = message || '회원 정보를 불러오지 못했습니다.';
            error.classList.remove('hidden');
        }
    }

    function showMember(member) {
        if (loading) {
            loading.classList.add('hidden');
        }
        if (error) {
            error.classList.add('hidden');
        }
        setText('account_public_hash', member.account_public_hash || '');
        setText('display_name', member.display_name || '');
        setText('email', member.email || '');
        setText('status_label', member.status_label || member.status || '');
        setText('email_verified_label', member.email_verified_label || '');
        setText('last_login_at', member.last_login_at || '');
        if (editLink && member.edit_url) {
            editLink.setAttribute('href', member.edit_url);
            editLink.classList.add('modal-action');
            editLink.classList.remove('hidden');
        }
        if (detail) {
            detail.classList.remove('hidden');
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-admin-audit-actor-member]');
        if (!trigger) {
            return;
        }

        var url = trigger.getAttribute('data-member-url') || '';
        resetModal();
        if (!url) {
            showError('회원 정보 조회 주소가 없습니다.');
            return;
        }

        window.fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload || !payload.ok) {
                    throw new Error(payload && payload.message ? payload.message : '회원 정보를 불러오지 못했습니다.');
                }

                return payload;
            });
        }).then(function (payload) {
            showMember(payload.member || {});
        }).catch(function (fetchError) {
            showError(fetchError && fetchError.message ? fetchError.message : '회원 정보를 불러오지 못했습니다.');
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
