<?php

$adminPageTitle = '발송 템플릿';
$adminPageSubtitle = '회원에게 보내는 이메일과 알림 문구를 한 곳에서 관리합니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$categoryLabels = [
    'transactional_email' => '필수 안내',
    'notification_event' => '회원 활동',
    'admin_operational' => '운영 업무',
];
$statusLabels = [
    'default' => '기본값',
    'override' => '수정됨',
    'inactive' => '사용 안 함',
    'module_disabled' => '모듈 꺼짐',
];
$statusBadgeClasses = [
    'default' => 'is-success',
    'override' => 'is-warning',
    'inactive' => 'is-danger',
    'module_disabled' => 'is-warning',
];
$channelLabels = [
    'email' => '이메일',
    'site' => '사이트 내 알림',
    'slack_webhook' => 'Slack',
    'discord_webhook' => 'Discord',
    'telegram_bot' => 'Telegram',
    'kakao_alimtalk' => '알림톡',
    'alimtalk' => '알림톡',
    'kakao_friendtalk' => '친구톡',
    'friendtalk' => '친구톡',
    'sms' => '문자',
    'lms' => '문자(LMS)',
    'mms' => '문자(MMS)',
    'messaging' => '메시징',
    'message' => '쪽지',
    'direct_message' => '쪽지',
    'web_push' => '웹 푸시',
    'app_push' => '앱 푸시',
    'webhook' => 'Webhook',
];
$moduleLabels = [];
foreach (array_keys($moduleOptions) as $moduleKey) {
    $moduleMetadata = function_exists('sr_module_metadata') ? sr_module_metadata((string) $moduleKey) : [];
    $moduleName = is_array($moduleMetadata) ? trim((string) ($moduleMetadata['name'] ?? '')) : '';
    $moduleLabels[(string) $moduleKey] = $moduleName !== '' ? $moduleName : (string) $moduleKey;
}
asort($moduleLabels);
$deliveryTemplateTitle = static function (array $template): string {
    $effective = isset($template['effective']) && is_array($template['effective']) ? $template['effective'] : $template;
    $title = sr_clean_single_line((string) ($effective['subject_template'] ?? ''), 190);
    return $title !== '' ? $title : sr_delivery_template_display_label($template, '발송 템플릿');
};
$deliveryTemplateChannelLabel = static function (string $channel) use ($channelLabels): string {
    $channel = strtolower(trim($channel));
    if (isset($channelLabels[$channel])) {
        return (string) $channelLabels[$channel];
    }
    if (function_exists('sr_notification_member_external_channel_label')) {
        $externalLabel = sr_notification_member_external_channel_label($channel);
        if ($externalLabel !== $channel) {
            return $externalLabel;
        }
    }
    $fallback = trim(str_replace(['_', '-'], ' ', $channel));
    return $fallback !== '' ? ucwords($fallback) : '기타';
};
$deliveryTemplateSelectedChannels = static function (array $template): array {
    $effective = isset($template['effective']) && is_array($template['effective']) ? $template['effective'] : $template;
    $channels = sr_delivery_template_normalize_channels(isset($effective['channels']) && is_array($effective['channels']) ? $effective['channels'] : (array) ($template['channels'] ?? []));
    $availableChannels = sr_delivery_template_normalize_channels(isset($template['available_channels']) && is_array($template['available_channels']) ? $template['available_channels'] : $channels);
    if ((string) ($template['category'] ?? '') !== 'notification_event') {
        return $channels;
    }
    $selectedExternal = array_values(array_filter($channels, static fn (string $channel): bool => !in_array($channel, ['site', 'email'], true)));
    if ($selectedExternal !== []) {
        return $channels;
    }
    $availableExternal = array_values(array_filter($availableChannels, static fn (string $channel): bool => !in_array($channel, ['site', 'email'], true)));
    return sr_delivery_template_normalize_channels(array_merge($channels, $availableExternal));
};
$deliveryTemplateAvailableChannels = static function (array $template) use ($deliveryTemplateSelectedChannels): array {
    $selectedChannels = $deliveryTemplateSelectedChannels($template);
    $availableChannels = sr_delivery_template_normalize_channels(isset($template['available_channels']) && is_array($template['available_channels']) ? $template['available_channels'] : (array) ($template['channels'] ?? []));
    return sr_delivery_template_normalize_channels(array_merge($availableChannels, $selectedChannels));
};
$deliveryTemplateChannelSummary = static function (array $template) use ($deliveryTemplateChannelLabel, $deliveryTemplateSelectedChannels): string {
    $labels = [];
    foreach ($deliveryTemplateSelectedChannels($template) as $channel) {
        $label = $deliveryTemplateChannelLabel((string) $channel);
        $labels[$label] = $label;
    }
    return $labels !== [] ? implode(', ', array_values($labels)) : '없음';
};
$deliveryTemplateStatusKey = static function (array $template): string {
    $statusKey = !empty($template['has_override']) ? 'override' : 'default';
    if (empty($template['module_enabled'])) {
        $statusKey = 'module_disabled';
    }
    if ((string) ($template['effective_status'] ?? '') === 'inactive') {
        $statusKey = 'inactive';
    }
    return $statusKey;
};
$deliveryTemplateModalId = static function (string $templateKey): string {
    return 'delivery-template-modal-' . preg_replace('/[^a-z0-9_-]+/', '-', str_replace('.', '-', $templateKey));
};
$deliveryTemplateFormId = static function (string $templateKey): string {
    return 'delivery-template-form-' . preg_replace('/[^a-z0-9_-]+/', '-', str_replace('.', '-', $templateKey));
};
$deliveryTemplateSortOptions = [
    'module' => [],
    'category' => [],
    'title' => [],
    'status' => [],
    'channels' => [],
];
$deliveryTemplateDefaultSort = sr_admin_sort_default('module', 'asc');
$deliveryTemplateSort = sr_admin_sort_from_request($deliveryTemplateSortOptions, $deliveryTemplateDefaultSort);
$deliveryTemplateSortValue = static function (array $template, string $sortKey) use ($moduleLabels, $categoryLabels, $statusLabels, $deliveryTemplateTitle, $deliveryTemplateChannelSummary, $deliveryTemplateStatusKey): string {
    if ($sortKey === 'category') {
        return $categoryLabels[(string) ($template['category'] ?? '')] ?? (string) ($template['category'] ?? '');
    }
    if ($sortKey === 'title') {
        return $deliveryTemplateTitle($template);
    }
    if ($sortKey === 'status') {
        $statusKey = $deliveryTemplateStatusKey($template);
        return $statusLabels[$statusKey] ?? $statusKey;
    }
    if ($sortKey === 'channels') {
        return $deliveryTemplateChannelSummary($template);
    }
    $ownerModule = (string) ($template['owner_module'] ?? '');
    return $moduleLabels[$ownerModule] ?? $ownerModule;
};
uasort($filteredTemplates, static function (array $left, array $right) use ($deliveryTemplateSort, $deliveryTemplateSortValue): int {
    $sortKey = (string) ($deliveryTemplateSort['key'] ?? 'module');
    $leftValue = $deliveryTemplateSortValue($left, $sortKey);
    $rightValue = $deliveryTemplateSortValue($right, $sortKey);
    $comparison = strnatcasecmp($leftValue, $rightValue);
    if ($comparison === 0) {
        $comparison = strnatcasecmp((string) ($left['template_key'] ?? ''), (string) ($right['template_key'] ?? ''));
    }
    return (string) ($deliveryTemplateSort['dir'] ?? 'asc') === 'desc' ? -$comparison : $comparison;
});
$deliveryTemplateEditUrl = static function (string $templateKey) use ($deliveryTemplateSort): string {
    $params = ['edit' => $templateKey];
    if (empty($deliveryTemplateSort['is_default'])) {
        $params[(string) ($deliveryTemplateSort['sort_param'] ?? 'sort')] = (string) ($deliveryTemplateSort['key'] ?? '');
        $params[(string) ($deliveryTemplateSort['dir_param'] ?? 'dir')] = (string) ($deliveryTemplateSort['dir'] ?? '');
    }
    return sr_url('/admin/delivery-templates?' . http_build_query($params));
};
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">템플릿 목록</h2>
    </div>
    <div class="admin-list-summary-row admin-delivery-template-summary-row">
        <span class="admin-list-summary">총 <strong><?php echo sr_e(number_format(count($filteredTemplates))); ?></strong>건</span>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <caption class="sr-only">발송 템플릿 목록</caption>
            <colgroup>
                <col class="admin-delivery-template-module-column">
                <col class="admin-delivery-template-category-column">
                <col>
                <col class="admin-delivery-template-status-column">
                <col class="admin-delivery-template-channel-column">
                <col class="admin-delivery-template-actions-column">
            </colgroup>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('module', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('모듈', 'module', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('category', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('용도', 'category', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('channels', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('발송 수단', 'channels', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredTemplates as $templateKey => $template) { ?>
                    <?php
                    $statusKey = $deliveryTemplateStatusKey($template);
                    $ownerModule = (string) ($template['owner_module'] ?? '');
                    $titleText = $deliveryTemplateTitle($template);
                    $channelSummary = $deliveryTemplateChannelSummary($template);
                    ?>
                    <tr>
                        <td><?php echo sr_e($moduleLabels[$ownerModule] ?? $ownerModule); ?></td>
                        <td><?php echo sr_e($categoryLabels[(string) ($template['category'] ?? '')] ?? (string) ($template['category'] ?? '')); ?></td>
                        <td><?php echo sr_e($titleText); ?></td>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($statusBadgeClasses[$statusKey] ?? 'is-warning'); ?>"><?php echo sr_e($statusLabels[$statusKey] ?? $statusKey); ?></span></td>
                        <td><?php echo sr_e($channelSummary); ?></td>
                        <td class="admin-table-actions-cell">
                            <a href="<?php echo sr_e($deliveryTemplateEditUrl((string) $templateKey)); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e($titleText . ' 수정'); ?>" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                        </td>
                    </tr>
                <?php } ?>
                <?php if ($filteredTemplates === []) { ?>
                    <tr><td colspan="6">표시할 발송 템플릿이 없습니다.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (is_array($editTemplate)) { ?>
    <?php
    $templateKey = $editKey;
    $template = $editTemplate;
    $effective = isset($template['effective']) && is_array($template['effective']) ? $template['effective'] : $template;
    $sampleValues = isset($template['sample_values']) && is_array($template['sample_values']) ? $template['sample_values'] : [];
    $bodyTemplateForEditor = (string) ($effective['body_template'] ?? '');
    if ((string) ($template['category'] ?? '') === 'notification_event' && !empty($template['body_editable'])) {
        $bodyTemplateForEditor = sr_delivery_template_body_template_for_editor($bodyTemplateForEditor, (string) ($effective['link_template'] ?? ''));
    } else {
        $bodyTemplateForEditor = sr_delivery_template_unwrap_code_text($bodyTemplateForEditor);
    }
    $preview = [
        'subject' => sr_clean_single_line(sr_delivery_template_render_string((string) ($effective['subject_template'] ?? ''), $sampleValues), 190),
        'body' => sr_clean_text(sr_delivery_template_unwrap_code_text(sr_delivery_template_render_string($bodyTemplateForEditor, $sampleValues)), 5000),
    ];
    $variables = isset($template['variables']) && is_array($template['variables']) ? $template['variables'] : [];
    $channels = $deliveryTemplateSelectedChannels($template);
    $availableChannels = $deliveryTemplateAvailableChannels($template);
    $channelSummary = $deliveryTemplateChannelSummary($template);
    $ownerModule = (string) ($template['owner_module'] ?? '');
    $modalId = $deliveryTemplateModalId((string) $templateKey);
    $formId = $deliveryTemplateFormId((string) $templateKey);
    $fieldSuffix = preg_replace('/[^a-z0-9_]+/', '_', str_replace('.', '_', (string) $templateKey));
    $titleText = $deliveryTemplateTitle($template);
    $modalClass = 'modal-overlay modal-overlay-fade overlay overlay-open';
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="<?php echo sr_e($modalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="false">
        <div class="modal-dialog modal-dialog-lg admin-delivery-template-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <div>
                        <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title">발송 템플릿 수정</h3>
                        <p class="form-help"><?php echo sr_e($titleText); ?></p>
                    </div>
                    <a href="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </a>
                </div>

                <div class="modal-body admin-delivery-template-modal-body">
                    <div class="admin-delivery-template-modal-meta">
                        <span class="badge"><?php echo sr_e($moduleLabels[$ownerModule] ?? $ownerModule); ?></span>
                        <span class="badge"><?php echo sr_e($categoryLabels[(string) ($template['category'] ?? '')] ?? (string) ($template['category'] ?? '')); ?></span>
                        <span class="badge"><?php echo sr_e($channelSummary); ?></span>
                    </div>
                    <?php if (trim((string) ($template['description'] ?? '')) !== '') { ?>
                        <p class="form-help"><?php echo sr_e((string) ($template['description'] ?? '')); ?></p>
                    <?php } ?>

                    <form id="<?php echo sr_e($formId); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>" class="admin-form ui-form-theme">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="template_key" value="<?php echo sr_e((string) $templateKey); ?>">
                        <input type="hidden" name="intent" value="save">

                        <div class="form-row">
                            <label class="form-label" for="delivery_template_subject_<?php echo sr_e($fieldSuffix); ?>">제목 <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <input id="delivery_template_subject_<?php echo sr_e($fieldSuffix); ?>" type="text" name="subject_template" value="<?php echo sr_e((string) ($effective['subject_template'] ?? '')); ?>" maxlength="190" required class="form-input form-control-full" data-overlay-focus>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="delivery_template_body_<?php echo sr_e($fieldSuffix); ?>">본문<?php echo !empty($template['body_editable']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></label>
                            <div class="form-field">
                                <?php if (!empty($template['body_editable'])) { ?>
                                    <textarea id="delivery_template_body_<?php echo sr_e($fieldSuffix); ?>" name="body_template" rows="8" class="form-textarea form-control-full" required><?php echo sr_e($bodyTemplateForEditor); ?></textarea>
                                <?php } else { ?>
                                    <textarea id="delivery_template_body_<?php echo sr_e($fieldSuffix); ?>" rows="5" class="form-textarea form-control-full" readonly><?php echo sr_e('본문은 소유 모듈의 builder가 생성합니다.'); ?></textarea>
                                    <input type="hidden" name="body_template" value="<?php echo sr_e((string) ($template['body_template'] ?? '')); ?>">
                                <?php } ?>
                                <?php if ($variables !== []) { ?>
                                    <div class="badge-list admin-delivery-template-variable-list" aria-label="본문 변수">
                                        <?php foreach ($variables as $name => $label) { ?>
                                            <?php if (!empty($template['body_editable'])) { ?>
                                                <button type="button" class="badge-list-item admin-delivery-template-variable-button" data-delivery-template-variable="<?php echo sr_e((string) $name); ?>" data-delivery-template-target="delivery_template_body_<?php echo sr_e($fieldSuffix); ?>" aria-label="<?php echo sr_e((string) $label . ' 변수 추가'); ?>">
                                                    <span class="badge-list-label">{<?php echo sr_e((string) $name); ?>}</span>
                                                    <span class="badge-list-summary"><?php echo sr_e((string) $label); ?></span>
                                                </button>
                                            <?php } else { ?>
                                                <span class="badge-list-item">
                                                    <span class="badge-list-label">{<?php echo sr_e((string) $name); ?>}</span>
                                                    <span class="badge-list-summary"><?php echo sr_e((string) $label); ?></span>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <input type="hidden" name="link_template" value="<?php echo sr_e((string) ($effective['link_template'] ?? '')); ?>">

                        <div class="form-row">
                            <span class="form-label">발송 수단</span>
                            <div class="form-field">
                                <div class="filtering-toggle-group admin-checkbox-toggle-group admin-delivery-template-channel-toggle-group" role="group" aria-label="발송 수단">
                                    <?php foreach ($availableChannels as $channelIndex => $channel) { ?>
                                        <?php
                                        $channelInputId = 'delivery_template_channel_' . $fieldSuffix . '_' . (string) $channelIndex;
                                        $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($availableChannels) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                        ?>
                                        <span class="filtering-toggle-item">
                                            <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e((string) $channel); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($channel, $channels, true) ? ' checked' : ''; ?>>
                                            <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html($deliveryTemplateChannelLabel((string) $channel)); ?></label>
                                        </span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="delivery_template_status_<?php echo sr_e($fieldSuffix); ?>">상태 <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <select id="delivery_template_status_<?php echo sr_e($fieldSuffix); ?>" name="status" class="form-select" required>
                                    <option value="active"<?php echo (string) ($effective['status'] ?? 'active') === 'active' ? ' selected' : ''; ?>>사용</option>
                                    <option value="inactive"<?php echo (string) ($effective['status'] ?? '') === 'inactive' ? ' selected' : ''; ?>>사용 안 함</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <span class="form-label">미리보기</span>
                            <div class="form-field">
                                <strong><?php echo sr_e((string) ($preview['subject'] ?? '')); ?></strong>
                                <div class="admin-delivery-template-preview-body"><?php echo sr_e((string) ($preview['body'] ?? '')); ?></div>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (in_array('email', (array) ($template['channels'] ?? []), true)) { ?>
                    <div class="modal-footer-note">테스트 발송은 현재 관리자 계정 이메일로만 보냅니다.</div>
                <?php } ?>
                <div class="modal-footer admin-delivery-template-footer">
                    <a href="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>" class="btn btn-solid-light modal-action">닫기</a>
                    <div class="admin-delivery-template-footer-actions">
                        <?php if (!empty($template['has_override'])) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="template_key" value="<?php echo sr_e((string) $templateKey); ?>">
                                <input type="hidden" name="intent" value="restore_default">
                                <button type="submit" class="btn btn-outline-danger modal-action"><?php echo sr_material_icon_html('restart_alt'); ?><span>기본값으로 즉시 복원</span></button>
                            </form>
                        <?php } ?>
                        <?php if (in_array('email', (array) ($template['channels'] ?? []), true)) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="template_key" value="<?php echo sr_e((string) $templateKey); ?>">
                                <input type="hidden" name="intent" value="test_email">
                                <button type="submit" class="btn btn-secondary modal-action"><?php echo sr_material_icon_html('mail'); ?><span>테스트 발송</span></button>
                            </form>
                        <?php } ?>
                        <button type="submit" class="btn btn-solid-primary modal-action" form="<?php echo sr_e($formId); ?>"><?php echo sr_material_icon_html('save'); ?><span>저장</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('[data-delivery-template-variable]') : null;
            if (!button) {
                return;
            }
            var targetId = button.getAttribute('data-delivery-template-target') || '';
            var textarea = targetId ? document.getElementById(targetId) : null;
            var variableName = button.getAttribute('data-delivery-template-variable') || '';
            if (!textarea || textarea.readOnly || textarea.disabled || variableName === '') {
                return;
            }
            var token = '{' + variableName + '}';
            var value = textarea.value || '';
            var start = value.length;
            var end = value.length;
            if (document.activeElement === textarea && typeof textarea.selectionStart === 'number' && typeof textarea.selectionEnd === 'number') {
                start = textarea.selectionStart;
                end = textarea.selectionEnd;
            }
            textarea.value = value.slice(0, start) + token + value.slice(end);
            var cursor = start + token.length;
            textarea.focus();
            if (typeof textarea.setSelectionRange === 'function') {
                textarea.setSelectionRange(cursor, cursor);
            }
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }());
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
