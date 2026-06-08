<?php

$adminPageTitle = sr_t('admin::ui.admin.d0bd9568');
$auditSortOptions = sr_admin_audit_log_sort_options();
$auditDefaultSort = sr_admin_audit_log_default_sort();
$auditSort = isset($auditSort) && is_array($auditSort) ? $auditSort : sr_admin_audit_log_default_sort();
include SR_ROOT . '/modules/admin/views/layout-header.php';
$auditMetadataModals = [];
$auditActorMemberModalId = 'admin-audit-actor-member-modal';
$auditResultFilters = is_array($filters['result'] ?? null) ? $filters['result'] : [];
$auditActorTypeFilters = is_array($filters['actor_type'] ?? null) ? $filters['actor_type'] : [];
$auditDetailFilterOpen = (string) ($filters['event_type'] ?? '') !== '' || (string) ($filters['target_type'] ?? '') !== '' || (string) ($filters['target_id'] ?? '') !== '' || $auditResultFilters !== [] || $auditActorTypeFilters !== [] || (string) ($filters['ip_address'] ?? '') !== '' || (string) ($filters['date_from'] ?? '') !== '' || (string) ($filters['date_to'] ?? '') !== '';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="filtering-form admin-audit-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $auditDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields filtering-fields-fit admin-audit-filter-main">
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_field" class="filtering-label">검색조건</label>
                <select id="modules_admin_audit_logs_field" name="field" class="form-select filtering-input">
                    <?php foreach (['event_type' => sr_t('admin::ui.text.b7c0f34b'), 'target_type' => sr_t('admin::ui.text.91df7a82'), 'target_id' => '대상 식별값', 'actor_account_id' => sr_t('admin::ui.id.2ea55f7c')] as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo $filters['field'] === $value ? ' selected' : ''; ?>>
                            <?php echo sr_e($label); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill">
                <label for="modules_admin_audit_logs_keyword" class="filtering-label"><?php echo sr_e(sr_t('admin::ui.search.bda397fc')); ?></label>
                <input id="modules_admin_audit_logs_keyword" type="text" name="q" value="<?php echo sr_e($filters['q']); ?>" class="form-input filtering-input" maxlength="80" placeholder="<?php echo sr_e(sr_t('admin::ui.id.f8d506bd')); ?>">
            </div>
        </div>
        <div id="admin_audit_detail_filters" class="filtering-body admin-audit-detail-stack" data-filtering-body<?php echo $auditDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_event_type" class="filtering-label">이벤트</label>
                <input id="modules_admin_audit_logs_event_type" type="text" name="event_type" value="<?php echo sr_e((string) ($filters['event_type'] ?? '')); ?>" class="form-input" maxlength="80" placeholder="member.login.success">
            </div>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_target_type" class="filtering-label">대상 유형</label>
                <input id="modules_admin_audit_logs_target_type" type="text" name="target_type" value="<?php echo sr_e((string) ($filters['target_type'] ?? '')); ?>" class="form-input" maxlength="60" placeholder="member_account">
            </div>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_target_id" class="filtering-label">대상 식별값</label>
                <input id="modules_admin_audit_logs_target_id" type="text" name="target_id" value="<?php echo sr_e((string) ($filters['target_id'] ?? '')); ?>" class="form-input" maxlength="80" placeholder="123">
            </div>
            <div class="filtering-field">
                <span class="filtering-label"><?php echo sr_e(sr_t('admin::ui.text.109383e3')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('modules_admin_audit_logs_result', 'result', ['success' => sr_t('admin::ui.text.b4f76a33'), 'failure' => sr_t('admin::ui.text.2743911f')], $auditResultFilters, '전체'); ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">처리자 유형</span>
                <?php echo sr_admin_filter_toggle_group_html('modules_admin_audit_logs_actor_type', 'actor_type', ['admin' => sr_admin_code_label('admin', 'actor_type'), 'member' => sr_admin_code_label('member', 'actor_type'), 'system' => sr_admin_code_label('system', 'actor_type')], $auditActorTypeFilters, '전체'); ?>
            </div>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_ip_address" class="filtering-label">IP</label>
                <input id="modules_admin_audit_logs_ip_address" type="text" name="ip_address" value="<?php echo sr_e((string) ($filters['ip_address'] ?? '')); ?>" class="form-input" maxlength="45" placeholder="127.0.0.1">
            </div>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_date_from" class="filtering-label"><?php echo sr_e(sr_t('admin::ui.text.f86e346d')); ?></label>
                <input id="modules_admin_audit_logs_date_from" type="date" name="date_from" value="<?php echo sr_e($filters['date_from']); ?>" class="form-input">
            </div>
            <div class="filtering-field">
                <label for="modules_admin_audit_logs_date_to" class="filtering-label"><?php echo sr_e(sr_t('admin::ui.text.9e586213')); ?></label>
                <input id="modules_admin_audit_logs_date_to" type="date" name="date_to" value="<?php echo sr_e($filters['date_to']); ?>" class="form-input">
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $auditDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="admin_audit_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('admin::ui.text.f8d240bf')); ?></button>
        </div>
    </div>
    <?php if (($filters['event_type'] ?? '') !== '' || ($filters['target_type'] ?? '') !== '' || ($filters['target_id'] ?? '') !== '' || $auditResultFilters !== [] || $auditActorTypeFilters !== [] || ($filters['ip_address'] ?? '') !== '' || ($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') { ?>
        <div class="admin-summary-stats">
            <?php if (($filters['event_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">이벤트 <strong><?php echo sr_e((string) $filters['event_type']); ?></strong></span>
            <?php } ?>
            <?php if (($filters['target_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">대상 유형 <strong><?php echo sr_e(sr_admin_code_label((string) $filters['target_type'], 'target_type')); ?></strong></span>
            <?php } ?>
            <?php if ($auditResultFilters !== []) { ?>
                <span class="admin-summary-meta">결과 <strong><?php echo sr_e(implode(', ', array_map(function (string $result): string {
                    return sr_admin_code_label($result, 'result');
                }, $auditResultFilters))); ?></strong></span>
            <?php } ?>
            <?php if ($auditActorTypeFilters !== []) { ?>
                <span class="admin-summary-meta">처리자 유형 <strong><?php echo sr_e(implode(', ', array_map(function (string $actorType): string {
                    return sr_admin_code_label($actorType, 'actor_type');
                }, $auditActorTypeFilters))); ?></strong></span>
            <?php } ?>
            <?php if (($filters['ip_address'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">IP <strong><?php echo sr_e((string) $filters['ip_address']); ?></strong></span>
            <?php } ?>
            <?php if (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') { ?>
                <?php
                $auditDateFrom = (string) ($filters['date_from'] ?? '');
                $auditDateTo = (string) ($filters['date_to'] ?? '');
                $auditDateLabel = $auditDateFrom !== '' && $auditDateTo !== ''
                    ? $auditDateFrom . ' ~ ' . $auditDateTo
                    : ($auditDateFrom !== '' ? $auditDateFrom . '부터' : $auditDateTo . '까지');
                ?>
                <span class="admin-summary-meta">기간 <strong><?php echo sr_e($auditDateLabel); ?></strong></span>
            <?php } ?>
        </div>
    <?php } ?>
</form>

<div class="admin-card admin-list-card card admin-list-form">
    <div class="admin-list-summary-row">
        <?php if (empty($auditSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($auditSortOptions, $auditDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="작업 로그 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($auditPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-audit-log-table">
        <thead class="ui-table-head">
            <tr>
                <th<?php echo sr_admin_sort_aria('created_at', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.faea4ccf'), 'created_at', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
                <th<?php echo sr_admin_sort_aria('actor_account_id', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.750086e9'), 'actor_account_id', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
                <th<?php echo sr_admin_sort_aria('event_type', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.46b289bb'), 'event_type', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
                <th<?php echo sr_admin_sort_aria('target_type', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.8c609deb'), 'target_type', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
                <th<?php echo sr_admin_sort_aria('result', $auditSort); ?>><?php echo sr_admin_sort_header_html(sr_t('admin::ui.text.109383e3'), 'result', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
                <th<?php echo sr_admin_sort_aria('ip_address', $auditSort); ?>><?php echo sr_admin_sort_header_html('IP', 'ip_address', $auditSort, $auditSortOptions, $auditDefaultSort); ?></th>
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
                    <td><?php echo sr_admin_time_html((string) $log['created_at']); ?></td>
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
                                'raw_event_type' => (string) $log['event_type'],
                                'actor' => (int) ($log['actor_account_id'] ?? 0) > 0 ? 'account #' . (string) (int) $log['actor_account_id'] : sr_admin_audit_log_display_actor_type($log),
                                'target' => (string) ($log['target_type'] ?? '') . ((string) ($log['target_id'] ?? '') !== '' ? ' #' . (string) $log['target_id'] : ''),
                                'result' => sr_admin_code_label((string) $log['result'], 'result'),
                                'ip_address' => (string) ($log['ip_address'] ?? ''),
                                'user_agent' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($log['user_agent'] ?? ''), 300)),
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
                    <dt>이름</dt>
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
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['raw_event_type']); ?></span>
                        <span class="admin-summary-meta"><?php echo sr_admin_time_html((string) $auditMetadataModal['created_at']); ?></span>
                        <span class="admin-summary-meta">처리자 <?php echo sr_e((string) $auditMetadataModal['actor']); ?></span>
                        <span class="admin-summary-meta">대상 <?php echo sr_e((string) $auditMetadataModal['target']); ?></span>
                        <span class="admin-summary-meta">결과 <?php echo sr_e((string) $auditMetadataModal['result']); ?></span>
                        <?php if ((string) $auditMetadataModal['ip_address'] !== '') { ?>
                            <span class="admin-summary-meta">IP <?php echo sr_e((string) $auditMetadataModal['ip_address']); ?></span>
                        <?php } ?>
                        <?php if ((string) $auditMetadataModal['user_agent'] !== '') { ?>
                            <span class="admin-summary-meta">UA <?php echo sr_e((string) $auditMetadataModal['user_agent']); ?></span>
                        <?php } ?>
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
