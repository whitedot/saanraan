<?php

$adminPageTitle = '발송 템플릿 관리';
$adminPageSubtitle = '회원 인증 메일과 정책 고지처럼 모듈이 소유한 transactional email 기본 문구의 사용자 수정값을 관리합니다.';
$adminContainerClass = 'admin-page-delivery-templates';
$deliveryTemplateRows = isset($deliveryTemplateRows) && is_array($deliveryTemplateRows) ? $deliveryTemplateRows : [];
$deliveryTemplateSortOptions = isset($deliveryTemplateSortOptions) && is_array($deliveryTemplateSortOptions) ? $deliveryTemplateSortOptions : [];
$deliveryTemplateDefaultSort = isset($deliveryTemplateDefaultSort) && is_array($deliveryTemplateDefaultSort) ? $deliveryTemplateDefaultSort : sr_admin_sort_default('label', 'asc');
$deliveryTemplateSort = isset($deliveryTemplateSort) && is_array($deliveryTemplateSort) ? $deliveryTemplateSort : $deliveryTemplateDefaultSort;
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<section class="card admin-list-card">
    <div class="card-header">
        <h2 class="card-title">발송 템플릿 목록</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-delivery-template-table">
            <caption class="sr-only">발송 템플릿 목록</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('label', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('템플릿', 'label', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('module', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('소유 모듈', 'module', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('source', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('적용값', 'source', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th class="admin-table-actions-cell">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($deliveryTemplateRows === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">표시할 발송 템플릿이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($deliveryTemplateRows as $rowIndex => $templateRow) { ?>
                    <?php
                    $templateKey = (string) ($templateRow['template_key'] ?? '');
                    $label = (string) ($templateRow['label'] ?? $templateKey);
                    $modalId = 'delivery-template-modal-' . (string) $rowIndex;
                    $hasOverride = !empty($templateRow['has_override']);
                    $active = (string) ($templateRow['status'] ?? 'active') === 'active';
                    ?>
                    <tr>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e($label); ?></strong>
                            <small><?php echo sr_e($templateKey); ?></small>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) ($templateRow['owner_module'] ?? '')); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo $hasOverride ? 'is-warning' : 'is-info'; ?>"><?php echo sr_e($hasOverride ? '사용자 수정' : '기본값'); ?></span>
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo $active ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e($active ? '사용' : '기본값 사용'); ?></span>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e('수정'); ?>" title="<?php echo sr_e('수정'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($modalId); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                                    <?php echo sr_material_icon_html('edit'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($deliveryTemplateRows as $rowIndex => $templateRow) { ?>
    <?php
    $templateKey = (string) ($templateRow['template_key'] ?? '');
    $label = (string) ($templateRow['label'] ?? $templateKey);
    $modalId = 'delivery-template-modal-' . (string) $rowIndex;
    $fieldSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $templateKey) ?? (string) $rowIndex;
    $bodyFieldId = 'delivery_template_body_' . $fieldSuffix;
    $variables = isset($templateRow['variables']) && is_array($templateRow['variables']) ? $templateRow['variables'] : [];
    $requiredVariables = isset($templateRow['required_variables']) && is_array($templateRow['required_variables']) ? $templateRow['required_variables'] : [];
    $channels = isset($templateRow['channels']) && is_array($templateRow['channels']) ? $templateRow['channels'] : ['email'];
    $availableChannels = isset($templateRow['available_channels']) && is_array($templateRow['available_channels']) ? $templateRow['available_channels'] : $channels;
    $status = (string) ($templateRow['status'] ?? 'active');
    $bodyEditable = !empty($templateRow['body_editable']);
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/delivery-templates')); ?>" class="modal-content ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="template_key" value="<?php echo sr_e($templateKey); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($label); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body admin-form">
                    <div class="form-row">
                        <label class="form-label" for="delivery_template_subject_<?php echo sr_e($fieldSuffix); ?>">제목 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="delivery_template_subject_<?php echo sr_e($fieldSuffix); ?>" type="text" name="subject_template" value="<?php echo sr_e((string) ($templateRow['subject_template'] ?? '')); ?>" maxlength="190" required class="form-input form-control-full" data-overlay-focus>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($bodyFieldId); ?>">본문<?php echo $bodyEditable ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></label>
                        <div class="form-field">
                            <textarea id="<?php echo sr_e($bodyFieldId); ?>" name="body_template" rows="8" maxlength="5000" class="form-textarea form-control-full"<?php echo $bodyEditable ? ' required' : ' readonly'; ?>><?php echo sr_e((string) ($templateRow['body_template'] ?? '')); ?></textarea>
                            <?php if ($variables !== []) { ?>
                                <div class="badge-list admin-delivery-template-variable-list" aria-label="본문 변수" data-delivery-template-variable-list>
                                    <?php foreach ($variables as $name => $variableLabel) { ?>
                                        <?php $required = in_array((string) $name, $requiredVariables, true); ?>
                                        <button type="button" class="badge-list-item admin-delivery-template-variable-button" data-delivery-template-variable="<?php echo sr_e((string) $name); ?>" data-delivery-template-target="<?php echo sr_e($bodyFieldId); ?>" aria-label="<?php echo sr_e((string) $variableLabel . ' 변수 추가'); ?>">
                                            <span class="badge-list-label">{<?php echo sr_e((string) $name); ?>}</span>
                                            <span class="badge-list-summary"><?php echo sr_e((string) $variableLabel . ($required ? ' / 필수' : '')); ?></span>
                                        </button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="delivery_template_link_<?php echo sr_e($fieldSuffix); ?>">연결 주소</label>
                        <div class="form-field">
                            <input id="delivery_template_link_<?php echo sr_e($fieldSuffix); ?>" type="text" name="link_template" value="<?php echo sr_e((string) ($templateRow['link_template'] ?? '')); ?>" maxlength="255" class="form-input form-control-full">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">발송 수단 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="발송 수단">
                                <?php foreach ($availableChannels as $channelIndex => $channel) { ?>
                                    <?php
                                    $channel = (string) $channel;
                                    $channelInputId = 'delivery_template_channel_' . $fieldSuffix . '_' . (string) $channelIndex;
                                    $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($availableChannels) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                    ?>
                                    <span class="filtering-toggle-item">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($channel, $channels, true) ? ' checked' : ''; ?>>
                                        <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="delivery_template_status_<?php echo sr_e($fieldSuffix); ?>">상태</label>
                        <div class="form-field">
                            <select id="delivery_template_status_<?php echo sr_e($fieldSuffix); ?>" name="status" class="form-select">
                                <option value="active"<?php echo $status === 'active' ? ' selected' : ''; ?>>사용자 수정값 사용</option>
                                <option value="inactive"<?php echo $status === 'inactive' ? ' selected' : ''; ?>>기본값 사용</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="intent" value="restore" class="btn btn-outline-danger modal-action"<?php echo empty($templateRow['has_override']) ? ' disabled aria-disabled="true"' : ''; ?>>기본값 복원</button>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" name="intent" value="save" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<script>
(function () {
    function insertVariable(button) {
        var variable = button.getAttribute('data-delivery-template-variable') || '';
        var targetId = button.getAttribute('data-delivery-template-target') || '';
        var target = document.getElementById(targetId);
        if (!variable || !target || target.readOnly) {
            return;
        }
        var token = '{' + variable + '}';
        var start = target.selectionStart || 0;
        var end = target.selectionEnd || 0;
        var value = target.value || '';
        target.value = value.slice(0, start) + token + value.slice(end);
        target.focus();
        target.selectionStart = target.selectionEnd = start + token.length;
    }

    document.querySelectorAll('[data-delivery-template-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
            insertVariable(button);
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
