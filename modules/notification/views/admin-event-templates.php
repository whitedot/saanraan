<?php

$adminNotificationTemplateContext = isset($adminNotificationTemplateContext) && is_array($adminNotificationTemplateContext) ? $adminNotificationTemplateContext : [];
$notificationTemplateRows = isset($notificationTemplateRows) && is_array($notificationTemplateRows) ? $notificationTemplateRows : [];
$notificationTemplateChannelOptions = isset($notificationTemplateChannelOptions) && is_array($notificationTemplateChannelOptions) ? $notificationTemplateChannelOptions : ['site'];
$notificationTemplateSortOptions = isset($notificationTemplateSortOptions) && is_array($notificationTemplateSortOptions) ? $notificationTemplateSortOptions : [];
$notificationTemplateDefaultSort = isset($notificationTemplateDefaultSort) && is_array($notificationTemplateDefaultSort) ? $notificationTemplateDefaultSort : sr_admin_sort_default('title', 'asc');
$notificationTemplateSort = isset($notificationTemplateSort) && is_array($notificationTemplateSort) ? $notificationTemplateSort : $notificationTemplateDefaultSort;
$adminPageTitle = (string) ($adminNotificationTemplateContext['title'] ?? '알림/메일 관리');
$adminPageSubtitle = (string) ($adminNotificationTemplateContext['subtitle'] ?? '회원에게 발송되는 알림 문구와 발송 수단을 관리합니다.');
$adminContainerClass = 'admin-page-notification-event-templates';
$notificationTemplateReturnPath = (string) ($adminNotificationTemplateContext['return_path'] ?? '');
$notificationTemplateExternalPushSettingsUrl = sr_url('/admin/notifications/settings#notification-settings-section-external-push');
$notificationTemplateEmailChannelEnabled = isset($pdo) && $pdo instanceof PDO && function_exists('sr_notification_email_delivery_enabled')
    ? sr_notification_email_delivery_enabled($pdo)
    : true;
$notificationTemplateMemberExternalChannelKeys = function_exists('sr_notification_member_external_channel_keys')
    ? sr_notification_member_external_channel_keys()
    : [];
$notificationTemplateMemberExternalChannelEnabled = [];
if (isset($pdo) && $pdo instanceof PDO) {
    foreach ($notificationTemplateMemberExternalChannelKeys as $memberExternalChannel) {
        $memberExternalChannel = (string) $memberExternalChannel;
        $notificationTemplateMemberExternalChannelEnabled[$memberExternalChannel] = function_exists('sr_notification_member_external_delivery_enabled')
            ? sr_notification_member_external_delivery_enabled($pdo, $memberExternalChannel)
            : true;
    }
}
$notificationTemplateTotalCount = 0;
$notificationTemplateEnabledCount = 0;
foreach ($notificationTemplateRows as $notificationTemplateStatusRow) {
    if ((string) ($notificationTemplateStatusRow['row_type'] ?? 'event') === 'delivery') {
        continue;
    }
    $notificationTemplateTotalCount++;
    if (!empty($notificationTemplateStatusRow['enabled'])) {
        $notificationTemplateEnabledCount++;
    }
}
$notificationTemplateAllEnabled = $notificationTemplateTotalCount > 0 && $notificationTemplateEnabledCount === $notificationTemplateTotalCount;
$notificationTemplateMixedEnabled = $notificationTemplateEnabledCount > 0 && $notificationTemplateEnabledCount < $notificationTemplateTotalCount;
$notificationTemplateHelpOpenLabel = '도움말 보기';
$notificationTemplateHelp = [
    'variables' => [
        'id' => 'notification-template-variables-help',
        'title' => '알림 문구와 변수 도움말',
        'body' => '<p><code>{display_name}</code>처럼 중괄호로 표시된 값은 알림을 만들 때 실제 회원 정보나 내용으로 바뀝니다. 변수 버튼을 누르면 본문에서 선택한 위치에 추가됩니다.</p>'
            . '<p>자동 메일 항목에서 ‘필수’로 표시된 변수는 제목, 본문, 연결 주소 중 한 곳 이상에 남겨야 하며, 목록에 없는 변수는 저장할 수 없습니다.</p>'
            . '<p>그 밖의 사이트 알림 항목은 표시된 변수를 유지하고 새 변수는 임의로 만들지 마세요. 알림을 만들 때 값이 전달되지 않은 변수는 문구에 그대로 보일 수 있으므로 저장 뒤 실제 알림을 확인하는 것이 좋습니다.</p>',
    ],
    'link' => [
        'id' => 'notification-template-link-help',
        'title' => '연결 주소 도움말',
        'body' => '<p>연결 주소는 이 자동 메일을 사용하는 기능이 별도 주소를 지원할 때 전달하는 값이며, 메일 본문에 저절로 붙지는 않습니다.</p>'
            . '<p>받는 사람이 메일에서 주소를 확인해야 한다면 본문에도 해당 주소 변수를 넣으세요.</p>',
    ],
    'channels' => [
        'id' => 'notification-template-channels-help',
        'title' => '발송 수단 도움말',
        'body' => '<p>‘사이트 알림’은 회원이 사이트 안에서 확인하는 알림입니다. 이메일과 외부 발송 수단은 이 화면에서 선택한 뒤에도 알림 모듈의 채널 설정과 회원의 수신 설정이 모두 허용되어야 실제로 발송됩니다.</p>'
            . '<p>‘채널 중지’가 표시된 수단은 현재 공통 설정이 꺼져 있습니다. 선택한 채로 저장할 수는 있지만 해당 수단으로는 발송되지 않으며 목록 상태가 ‘사용(부분중지)’로 표시됩니다.</p>',
    ],
    'alimtalk' => [
        'id' => 'notification-template-alimtalk-help',
        'title' => '알림톡 템플릿 코드 도움말',
        'body' => '<p>알림톡을 선택하면 카카오 비즈니스에서 승인받은 템플릿의 코드를 입력해야 합니다. 이 화면에 코드를 저장해도 템플릿 등록이나 승인이 이루어지지는 않습니다.</p>'
            . '<p>승인된 템플릿의 내용과 변수, 발송 서비스 설정이 이 알림 항목과 맞는지 확인하세요. 사이트 알림이나 이메일 문구는 이 화면의 제목과 본문을 사용하지만 알림톡 본문은 승인된 카카오 템플릿을 사용합니다.</p>',
    ],
    'status' => [
        'id' => 'notification-template-status-help',
        'title' => '상태와 전체 사용 도움말',
        'body' => '<p>상태를 중지하면 이 항목의 새 사이트 알림이나 메일·외부 발송을 만들지 않습니다. 사용 중이어도 채널 공통 설정이나 회원 수신 설정에 따라 일부 수단은 발송되지 않을 수 있습니다.</p>'
            . '<p>목록의 ‘전체 사용’은 이 화면의 알림 항목 상태를 한꺼번에 바꿉니다. 함께 표시되는 자동 메일 템플릿의 상태에는 적용되지 않습니다.</p>'
            . '<p>자동 메일 항목의 ‘기본값 복원’은 운영자가 저장한 문구와 상태를 지우고 제공 모듈의 현재 기본값을 사용합니다. 이후 모듈 업데이트로 기본 문구가 바뀌면 그 변경도 적용됩니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<section class="card admin-list-card">
    <div class="card-header">
        <h2 class="card-title">알림/메일 목록</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="전체 사용 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($notificationTemplateHelp['status']['id']); ?>" data-overlay="#<?php echo sr_e($notificationTemplateHelp['status']['id']); ?>">
                <?php echo sr_material_icon_html('help'); ?>
            </button>
            <form method="post" action="<?php echo sr_e(sr_url($notificationTemplateReturnPath)); ?>" class="admin-notification-template-bulk-form" data-notification-template-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="bulk_status">
                <?php echo sr_admin_switch_html('notification_template_bulk_enabled', 'bulk_enabled', '1', $notificationTemplateAllEnabled, '전체 사용', '0', ' data-notification-template-bulk-switch' . ($notificationTemplateMixedEnabled ? ' data-notification-template-bulk-mixed="1"' : '') . ($notificationTemplateTotalCount < 1 ? ' disabled' : '')); ?>
            </form>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-notification-template-table">
            <caption class="sr-only">알림/메일 목록</caption>
            <thead>
                <tr>
                    <th class="admin-notification-template-status-cell"<?php echo sr_admin_sort_aria('status', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th class="admin-notification-template-title-cell"<?php echo sr_admin_sort_aria('title', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th class="admin-notification-template-channel-cell"<?php echo sr_admin_sort_aria('channels', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('발송 수단', 'channels', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th class="admin-table-actions-cell">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notificationTemplateRows === []) { ?>
                    <tr><td colspan="4" class="admin-empty-state">표시할 알림/메일 항목이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($notificationTemplateRows as $rowIndex => $templateRow) { ?>
                    <?php
                    $eventKey = (string) ($templateRow['event_key'] ?? '');
                    $rowType = (string) ($templateRow['row_type'] ?? 'event');
                    $templateKey = (string) ($templateRow['template_key'] ?? '');
                    $label = (string) ($templateRow['label'] ?? $eventKey);
                    $enabled = !empty($templateRow['enabled']);
                    $hasOverride = !empty($templateRow['has_override']);
                    $statusLabel = $enabled ? '사용' : '중지';
                    $statusClass = $enabled ? 'is-success' : 'is-danger';
                    $channels = isset($templateRow['channels']) && is_array($templateRow['channels']) ? $templateRow['channels'] : ['site'];
                    $hasUnavailableEmailChannel = $enabled && in_array('email', $channels, true) && !$notificationTemplateEmailChannelEnabled;
                    $hasUnavailableMemberExternalChannel = false;
                    foreach ($channels as $statusChannel) {
                        $statusChannel = (string) $statusChannel;
                        if (in_array($statusChannel, $notificationTemplateMemberExternalChannelKeys, true)
                            && !($notificationTemplateMemberExternalChannelEnabled[$statusChannel] ?? true)
                        ) {
                            $hasUnavailableMemberExternalChannel = true;
                            break;
                        }
                    }
                    if ($enabled && ($hasUnavailableEmailChannel || $hasUnavailableMemberExternalChannel)) {
                        $statusLabel = '사용(부분중지)';
                        $statusClass = 'is-warning';
                    }
                    $modalId = 'notification-template-modal-' . (string) $rowIndex;
                    $fieldSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $eventKey) ?? (string) $rowIndex;
                    $variables = isset($templateRow['variables']) && is_array($templateRow['variables']) ? $templateRow['variables'] : [];
                    $titleText = (string) ($templateRow['title_template'] ?? '');
                    ?>
                    <tr>
                        <td class="admin-table-nowrap admin-notification-template-status-cell">
                            <span class="badge-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e($statusLabel); ?></span>
                        </td>
                        <td class="admin-table-break admin-notification-template-title-cell">
                            <?php echo sr_e($titleText); ?>
                        </td>
                        <td class="admin-table-nowrap admin-notification-template-channel-cell">
                            <span class="badge-list">
                                <?php foreach ($channels as $channel) { ?>
                                    <?php
                                    $channel = (string) $channel;
                                    $channelUnavailable = ($channel === 'email' && !$notificationTemplateEmailChannelEnabled)
                                        || (in_array($channel, $notificationTemplateMemberExternalChannelKeys, true) && !($notificationTemplateMemberExternalChannelEnabled[$channel] ?? true));
                                    $channelBadgeClass = $channelUnavailable ? 'is-danger' : 'is-success';
                                    $channelLabel = sr_admin_code_label($channel, 'notification_channel') . ($channelUnavailable ? ' 중지' : '');
                                    ?>
                                    <span class="badge-status <?php echo sr_e($channelBadgeClass); ?>"><?php echo sr_e($channelLabel); ?></span>
                                <?php } ?>
                            </span>
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
    <div class="card-description-block" aria-label="상태 설명">
        <h3 class="card-description-title">상태 설명</h3>
        <dl class="card-description-list">
            <div>
                <dt>사용</dt>
                <dd>조건이 맞으면 사이트 알림을 만들고 선택한 발송 수단으로 전달을 준비합니다.</dd>
            </div>
            <div>
                <dt>사용(부분중지)</dt>
                <dd>항목은 활성화되어 있지만 선택된 발송 수단 중 일부가 현재 채널 설정 때문에 발송되지 않습니다.</dd>
            </div>
            <div>
                <dt>중지</dt>
                <dd>새 사이트 알림과 외부 발송을 만들지 않습니다.</dd>
            </div>
            <div>
                <dt>이메일 중지</dt>
                <dd>이메일이 선택되어 있지만 알림 모듈의 이메일 채널이 꺼져 있어 메일을 보내지 않습니다.</dd>
            </div>
            <div>
                <dt>외부채널 중지</dt>
                <dd>외부 발송 수단이 선택되어 있지만 <a href="<?php echo sr_e($notificationTemplateExternalPushSettingsUrl); ?>" target="_blank" rel="noopener noreferrer">알림 모듈의 외부채널 수신 허용</a>이 꺼져 있어 해당 수단으로 보내지 않습니다.</dd>
            </div>
        </dl>
    </div>
</section>

<?php foreach ($notificationTemplateRows as $rowIndex => $templateRow) { ?>
    <?php
    $eventKey = (string) ($templateRow['event_key'] ?? '');
    $rowType = (string) ($templateRow['row_type'] ?? 'event');
    $templateKey = (string) ($templateRow['template_key'] ?? '');
    $label = (string) ($templateRow['label'] ?? $eventKey);
    $enabled = !empty($templateRow['enabled']);
    $channels = isset($templateRow['channels']) && is_array($templateRow['channels']) ? $templateRow['channels'] : ['site'];
    $rowChannelOptions = $rowType === 'delivery' && isset($templateRow['available_channels']) && is_array($templateRow['available_channels'])
        ? $templateRow['available_channels']
        : $notificationTemplateChannelOptions;
    foreach ($channels as $selectedChannel) {
        $selectedChannel = (string) $selectedChannel;
        if (in_array($selectedChannel, $notificationTemplateMemberExternalChannelKeys, true) && !in_array($selectedChannel, $rowChannelOptions, true)) {
            $rowChannelOptions[] = $selectedChannel;
        }
    }
    $showChannelSelector = !($rowType === 'delivery' && $rowChannelOptions === ['email']);
    $channelTemplates = isset($templateRow['channel_templates']) && is_array($templateRow['channel_templates']) ? $templateRow['channel_templates'] : [];
    $alimtalkTemplate = isset($channelTemplates['alimtalk']) && is_array($channelTemplates['alimtalk']) ? $channelTemplates['alimtalk'] : [];
    $showAlimtalkTemplateCode = $rowType !== 'delivery' && (in_array('alimtalk', $rowChannelOptions, true) || in_array('alimtalk', $channels, true) || (string) ($alimtalkTemplate['provider_template_code'] ?? '') !== '');
    $modalId = 'notification-template-modal-' . (string) $rowIndex;
    $fieldSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $eventKey) ?? (string) $rowIndex;
    $bodyFieldId = 'notification_template_body_' . $fieldSuffix;
    $variables = isset($templateRow['variables']) && is_array($templateRow['variables']) ? $templateRow['variables'] : [];
    $requiredVariables = isset($templateRow['required_variables']) && is_array($templateRow['required_variables']) ? $templateRow['required_variables'] : [];
    $bodyEditable = $rowType !== 'delivery' || !empty($templateRow['body_editable']);
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <form method="post" action="<?php echo sr_e(sr_url($notificationTemplateReturnPath)); ?>" class="modal-content ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="template_type" value="<?php echo sr_e($rowType === 'delivery' ? 'delivery' : 'event'); ?>">
                <?php if ($rowType === 'delivery') { ?>
                    <input type="hidden" name="template_key" value="<?php echo sr_e($templateKey); ?>">
                <?php } else { ?>
                    <input type="hidden" name="event_key" value="<?php echo sr_e($eventKey); ?>">
                <?php } ?>
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($label); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body admin-form">
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('notification_template_title_' . $fieldSuffix, '제목', $notificationTemplateHelp['variables']['id'], $notificationTemplateHelpOpenLabel, true, true); ?>
                        <div class="form-field">
                            <input id="notification_template_title_<?php echo sr_e($fieldSuffix); ?>" type="text" name="title_template" value="<?php echo sr_e((string) ($templateRow['title_template'] ?? '')); ?>" maxlength="<?php echo $rowType === 'delivery' ? '190' : '160'; ?>" required class="form-input form-control-full" data-overlay-focus>
                            <small class="form-help">알림 목록이나 메일함에 표시되는 제목입니다. 사용 가능한 변수를 넣을 수 있습니다.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($bodyFieldId, '본문', $notificationTemplateHelp['variables']['id'], $notificationTemplateHelpOpenLabel, $bodyEditable); ?>
                        <div class="form-field">
                            <textarea id="<?php echo sr_e($bodyFieldId); ?>" name="body_template" rows="8" maxlength="<?php echo $rowType === 'delivery' ? '5000' : '4000'; ?>"<?php echo $bodyEditable ? ' required' : ' readonly'; ?> class="form-textarea form-control-full"><?php echo sr_e((string) ($templateRow['body_template'] ?? '')); ?></textarea>
                            <small class="form-help"><?php echo $bodyEditable ? '변수 버튼을 누르면 본문의 현재 커서 위치에 추가됩니다.' : '본문은 제공 모듈이 관리하므로 여기서 변경할 수 없습니다.'; ?></small>
                            <?php if ($variables !== []) { ?>
                                <div class="badge-list admin-delivery-template-variable-list notification-template-variable-list" aria-label="본문 변수" data-notification-template-variable-list>
                                    <?php foreach ($variables as $name => $variableLabel) { ?>
                                        <?php $required = in_array((string) $name, $requiredVariables, true); ?>
                                        <button type="button" class="badge-list-item admin-delivery-template-variable-button notification-template-variable-button" data-notification-template-variable="<?php echo sr_e((string) $name); ?>" data-notification-template-target="<?php echo sr_e($bodyFieldId); ?>" aria-label="<?php echo sr_e((string) $variableLabel . ' 변수 추가'); ?>">
                                            <span class="badge-list-label">{<?php echo sr_e((string) $name); ?>}</span>
                                            <span class="badge-list-summary"><?php echo sr_e((string) $variableLabel . ($required ? ' / 필수' : '')); ?></span>
                                        </button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($rowType === 'delivery') { ?>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html('notification_template_link_' . $fieldSuffix, '연결 주소', $notificationTemplateHelp['link']['id'], $notificationTemplateHelpOpenLabel); ?>
                            <div class="form-field">
                                <input id="notification_template_link_<?php echo sr_e($fieldSuffix); ?>" type="text" name="link_template" value="<?php echo sr_e((string) ($templateRow['link_template'] ?? '')); ?>" maxlength="255" class="form-input form-control-full">
                                <small class="form-help">메일 본문에 자동으로 붙는 주소가 아닙니다. 본문에 보여야 하면 본문에도 주소 변수를 넣으세요.</small>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($showChannelSelector) { ?>
                        <div class="form-row">
                            <div class="form-label form-label-help">
                                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="발송 수단 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($notificationTemplateHelp['channels']['id']); ?>" data-overlay="#<?php echo sr_e($notificationTemplateHelp['channels']['id']); ?>">
                                    <?php echo sr_material_icon_html('help'); ?>
                                </button>
                                <span>발송 수단 <span class="sr-required-label">(필수)</span></span>
                            </div>
                            <div class="form-field">
                                <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="발송 수단">
                                    <?php foreach ($rowChannelOptions as $channelIndex => $channel) { ?>
                                        <?php
                                        $channel = (string) $channel;
                                        $channelInputId = 'notification_template_channel_' . $fieldSuffix . '_' . (string) $channelIndex;
                                        $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($rowChannelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                        ?>
                                        <?php
                                        $channelChoiceLabel = sr_admin_code_label($channel, 'notification_channel');
                                        if (($channel === 'email' && !$notificationTemplateEmailChannelEnabled)
                                            || (in_array($channel, $notificationTemplateMemberExternalChannelKeys, true) && !($notificationTemplateMemberExternalChannelEnabled[$channel] ?? true))
                                        ) {
                                            $channelChoiceLabel .= ' (채널 중지)';
                                        }
                                        ?>
                                        <span class="filtering-toggle-item">
                                            <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($channel, $channels, true) ? ' checked' : ''; ?>>
                                            <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html($channelChoiceLabel); ?></label>
                                        </span>
                                    <?php } ?>
                                </div>
                                <small class="form-help">채널 설정과 회원 수신 설정이 모두 허용된 수단만 실제로 발송됩니다.</small>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($showAlimtalkTemplateCode) { ?>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html('notification_template_alimtalk_code_' . $fieldSuffix, '알림톡 템플릿 코드', $notificationTemplateHelp['alimtalk']['id'], $notificationTemplateHelpOpenLabel); ?>
                            <div class="form-field">
                                <input id="notification_template_alimtalk_code_<?php echo sr_e($fieldSuffix); ?>" type="text" name="alimtalk_template_code" value="<?php echo sr_e((string) ($alimtalkTemplate['provider_template_code'] ?? '')); ?>" maxlength="120" pattern="[A-Za-z0-9._-]{1,120}" class="form-input form-control-full">
                                <small class="form-help">알림톡 본문은 카카오 비즈니스에서 승인된 템플릿을 사용합니다. 이 화면에서는 승인 템플릿 코드만 연결합니다.</small>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('notification_template_enabled_' . $fieldSuffix, '상태', $notificationTemplateHelp['status']['id'], $notificationTemplateHelpOpenLabel); ?>
                        <div class="form-field">
                            <?php echo sr_admin_switch_html('notification_template_enabled_' . $fieldSuffix, 'enabled', '1', $enabled, '사용'); ?>
                            <small class="form-help">중지하면 이 항목의 새 사이트 알림이나 메일·외부 발송을 만들지 않습니다.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($rowType === 'delivery') { ?>
                        <button type="submit" name="intent" value="restore" class="btn btn-outline-danger modal-action"<?php echo empty($templateRow['has_override']) ? ' disabled aria-disabled="true"' : ''; ?>>기본값 복원</button>
                    <?php } ?>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php foreach ($notificationTemplateHelp as $notificationTemplateHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $notificationTemplateHelpModal['id'], (string) $notificationTemplateHelpModal['title'], (string) $notificationTemplateHelpModal['body']); ?>
<?php } ?>

<script>
(function () {
    var bulkSwitch = document.querySelector('[data-notification-template-bulk-switch]');
    if (bulkSwitch) {
        if (bulkSwitch.getAttribute('data-notification-template-bulk-mixed') === '1') {
            bulkSwitch.indeterminate = true;
        }
        bulkSwitch.addEventListener('change', function () {
            bulkSwitch.indeterminate = false;
            if (bulkSwitch.form) {
                bulkSwitch.form.submit();
            }
        });
    }

    document.querySelectorAll('[data-notification-template-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-notification-template-target') || '';
            var target = document.getElementById(targetId);
            var name = button.getAttribute('data-notification-template-variable') || '';
            if (!target || !name) {
                return;
            }
            var token = '{' + name + '}';
            var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
            var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : target.value.length;
            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            if (typeof target.setSelectionRange === 'function') {
                target.setSelectionRange(start + token.length, start + token.length);
            }
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
