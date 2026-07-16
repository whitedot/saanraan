<?php

$adminPageTitle = '발송 템플릿 관리';
$adminPageSubtitle = '회원 인증과 정책 고지처럼 시스템이 자동으로 보내는 메일 문구를 관리합니다.';
$adminContainerClass = 'admin-page-delivery-templates';
$deliveryTemplateRows = isset($deliveryTemplateRows) && is_array($deliveryTemplateRows) ? $deliveryTemplateRows : [];
$deliveryTemplateSortOptions = isset($deliveryTemplateSortOptions) && is_array($deliveryTemplateSortOptions) ? $deliveryTemplateSortOptions : [];
$deliveryTemplateDefaultSort = isset($deliveryTemplateDefaultSort) && is_array($deliveryTemplateDefaultSort) ? $deliveryTemplateDefaultSort : sr_admin_sort_default('label', 'asc');
$deliveryTemplateSort = isset($deliveryTemplateSort) && is_array($deliveryTemplateSort) ? $deliveryTemplateSort : $deliveryTemplateDefaultSort;
$deliveryTemplateHelpOpenLabel = '도움말 보기';
$deliveryTemplateHelp = [
    'variables' => [
        'id' => 'delivery-template-variables-help',
        'title' => '메일 문구와 변수 도움말',
        'body' => '<p><code>{verification_url}</code>처럼 중괄호로 표시된 값은 메일을 보낼 때 실제 회원 정보나 주소로 바뀝니다. 변수 버튼을 누르면 본문에서 선택한 위치에 추가됩니다.</p>'
            . '<p>‘필수’로 표시된 변수는 제목, 본문, 연결 주소 중 한 곳 이상에 남겨야 합니다. 목록에 없는 변수를 입력하거나 필수 변수를 모두 지우면 저장할 수 없습니다.</p>'
            . '<p>본문을 수정할 수 없는 템플릿은 제공 모듈이 본문을 관리합니다. 이 화면에서는 제목과 허용된 다른 항목만 바꿀 수 있습니다.</p>',
    ],
    'link' => [
        'id' => 'delivery-template-link-help',
        'title' => '연결 주소 도움말',
        'body' => '<p>연결 주소는 이 템플릿을 사용하는 기능이 별도 주소를 지원할 때 전달하는 값입니다. 자동 메일 본문에 이 주소가 저절로 붙지는 않습니다.</p>'
            . '<p>받는 사람이 메일에서 주소를 확인해야 한다면 본문에도 해당 주소 변수를 넣으세요. 사용 가능한 변수만 입력할 수 있습니다.</p>',
    ],
    'status' => [
        'id' => 'delivery-template-status-help',
        'title' => '상태와 기본값 복원 도움말',
        'body' => '<p>상태를 ‘중지’로 저장하면 이 템플릿을 사용하는 새 메일을 보내지 않습니다.</p>'
            . '<p>‘기본값 복원’은 운영자가 저장한 제목, 본문, 연결 주소, 상태를 모두 지우고 제공 모듈의 현재 기본값을 다시 사용합니다. 이후 모듈 업데이트로 기본 문구가 바뀌면 그 변경도 적용됩니다.</p>',
    ],
];
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
                    <th<?php echo sr_admin_sort_aria('module', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('제공 모듈', 'module', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('source', $deliveryTemplateSort); ?>><?php echo sr_admin_sort_header_html('문구 출처', 'source', $deliveryTemplateSort, $deliveryTemplateSortOptions, $deliveryTemplateDefaultSort); ?></th>
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
                            <span class="badge-status <?php echo $active ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e($active ? '사용' : '중지'); ?></span>
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
    $showChannelSelector = $availableChannels !== ['email'];
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
                        <?php echo sr_admin_form_label_help_html('delivery_template_subject_' . $fieldSuffix, '제목', $deliveryTemplateHelp['variables']['id'], $deliveryTemplateHelpOpenLabel, true, true); ?>
                        <div class="form-field">
                            <input id="delivery_template_subject_<?php echo sr_e($fieldSuffix); ?>" type="text" name="subject_template" value="<?php echo sr_e((string) ($templateRow['subject_template'] ?? '')); ?>" maxlength="190" required class="form-input form-control-full" data-overlay-focus>
                            <small class="form-help">받는 사람의 메일함에 표시되는 제목입니다. 사용 가능한 변수를 넣을 수 있습니다.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($bodyFieldId, '본문', $deliveryTemplateHelp['variables']['id'], $deliveryTemplateHelpOpenLabel, $bodyEditable); ?>
                        <div class="form-field">
                            <textarea id="<?php echo sr_e($bodyFieldId); ?>" name="body_template" rows="8" maxlength="5000" class="form-textarea form-control-full"<?php echo $bodyEditable ? ' required' : ' readonly'; ?>><?php echo sr_e((string) ($templateRow['body_template'] ?? '')); ?></textarea>
                            <small class="form-help"><?php echo $bodyEditable ? '변수 버튼을 누르면 본문의 현재 커서 위치에 추가됩니다.' : '본문은 제공 모듈이 관리하므로 여기서 변경할 수 없습니다.'; ?></small>
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
                        <?php echo sr_admin_form_label_help_html('delivery_template_link_' . $fieldSuffix, '연결 주소', $deliveryTemplateHelp['link']['id'], $deliveryTemplateHelpOpenLabel); ?>
                        <div class="form-field">
                            <input id="delivery_template_link_<?php echo sr_e($fieldSuffix); ?>" type="text" name="link_template" value="<?php echo sr_e((string) ($templateRow['link_template'] ?? '')); ?>" maxlength="255" class="form-input form-control-full">
                            <small class="form-help">메일 본문에 자동으로 붙는 주소가 아닙니다. 본문에 보여야 하면 본문에도 주소 변수를 넣으세요.</small>
                        </div>
                    </div>
                    <?php if ($showChannelSelector) { ?>
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
                    <?php } ?>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('delivery_template_status_' . $fieldSuffix, '상태', $deliveryTemplateHelp['status']['id'], $deliveryTemplateHelpOpenLabel); ?>
                        <div class="form-field">
                            <select id="delivery_template_status_<?php echo sr_e($fieldSuffix); ?>" name="status" class="form-select">
                                <option value="active"<?php echo $status === 'active' ? ' selected' : ''; ?>>사용</option>
                                <option value="inactive"<?php echo $status === 'inactive' ? ' selected' : ''; ?>>중지</option>
                            </select>
                            <small class="form-help">중지하면 이 템플릿을 사용하는 새 메일을 보내지 않습니다.</small>
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

<?php foreach ($deliveryTemplateHelp as $deliveryTemplateHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $deliveryTemplateHelpModal['id'], (string) $deliveryTemplateHelpModal['title'], (string) $deliveryTemplateHelpModal['body']); ?>
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
