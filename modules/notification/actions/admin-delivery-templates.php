<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/delivery-templates', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/delivery-templates', 'edit');

    $intent = sr_post_string('intent', 40);
    $templateKey = sr_delivery_template_key(sr_post_string('template_key', 190));
    $contract = $templateKey !== '' ? sr_delivery_template_contract($pdo, $templateKey) : null;
    $postErrors = [];
    $postNotice = '';

    try {
        if (!is_array($contract)) {
            throw new InvalidArgumentException('발송 템플릿을 찾을 수 없습니다.');
        }
        if (empty($contract['editable'])) {
            throw new InvalidArgumentException('수정할 수 없는 발송 템플릿입니다.');
        }

        if ($intent === 'restore') {
            sr_delivery_template_delete_override($pdo, $templateKey);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'delivery_template.restored',
                'target_type' => 'delivery_template',
                'target_id' => $templateKey,
                'result' => 'success',
                'message' => 'Delivery template override restored to default.',
                'metadata' => [
                    'template_key' => $templateKey,
                    'owner_module' => (string) ($contract['owner_module'] ?? ''),
                ],
            ]);
            $postNotice = '발송 템플릿을 기본값으로 복원했습니다.';
        } else {
            $subject = sr_post_string('subject_template', 190);
            $body = sr_post_string_without_truncation('body_template', 5000) ?? '';
            $link = sr_post_string('link_template', 255);
            $channels = isset($_POST['channels']) && is_array($_POST['channels'])
                ? array_values(array_map('strval', $_POST['channels']))
                : [];
            $status = sr_post_string('status', 30) === 'inactive' ? 'inactive' : 'active';
            sr_delivery_template_save_override($pdo, $contract, $subject, $body, $link, $channels, $status, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'delivery_template.updated',
                'target_type' => 'delivery_template',
                'target_id' => $templateKey,
                'result' => 'success',
                'message' => 'Delivery template override updated.',
                'metadata' => [
                    'template_key' => $templateKey,
                    'owner_module' => (string) ($contract['owner_module'] ?? ''),
                    'status' => $status,
                    'channels' => sr_delivery_template_normalize_channels($channels),
                ],
            ]);
            $postNotice = '발송 템플릿을 저장했습니다.';
        }
    } catch (Throwable $exception) {
        $postErrors[] = $exception->getMessage();
    }

    sr_admin_redirect_with_result(sr_admin_action_result($postErrors, $postNotice), '/admin/delivery-templates');
}

$deliveryTemplateContracts = sr_delivery_template_contracts($pdo);
$deliveryTemplateRows = [];
foreach ($deliveryTemplateContracts as $templateKey => $contract) {
    if (!is_array($contract) || (string) ($contract['category'] ?? '') !== 'transactional_email') {
        continue;
    }
    $effective = sr_delivery_template_effective($pdo, (string) $templateKey);
    $row = is_array($effective) ? $effective : $contract;
    $row['template_key'] = (string) $templateKey;
    $row['label'] = sr_delivery_template_display_label($contract, (string) $templateKey);
    $row['variables'] = isset($contract['variables']) && is_array($contract['variables']) ? $contract['variables'] : [];
    $row['required_variables'] = isset($contract['required_variables']) && is_array($contract['required_variables']) ? $contract['required_variables'] : [];
    $row['has_override'] = !empty($row['has_override']);
    $row['module_enabled'] = !empty($contract['module_enabled']);
    $row['body_editable'] = !empty($contract['body_editable']);
    $deliveryTemplateRows[] = $row;
}

$deliveryTemplateSortOptions = [
    'label' => static fn(array $row): string => (string) ($row['label'] ?? ''),
    'module' => static fn(array $row): string => (string) ($row['owner_module'] ?? ''),
    'status' => static fn(array $row): string => (string) ($row['status'] ?? ''),
    'source' => static fn(array $row): string => !empty($row['has_override']) ? 'override' : 'default',
];
$deliveryTemplateDefaultSort = sr_admin_sort_default('label', 'asc');
$deliveryTemplateSort = sr_admin_sort_from_request($deliveryTemplateSortOptions, $deliveryTemplateDefaultSort);
$deliveryTemplateSortKey = (string) ($deliveryTemplateSort['key'] ?? 'label');
$deliveryTemplateSortDirection = (string) ($deliveryTemplateSort['dir'] ?? 'asc');
usort($deliveryTemplateRows, static function (array $left, array $right) use ($deliveryTemplateSortOptions, $deliveryTemplateSortKey, $deliveryTemplateSortDirection): int {
    $leftValue = $deliveryTemplateSortOptions[$deliveryTemplateSortKey]($left);
    $rightValue = $deliveryTemplateSortOptions[$deliveryTemplateSortKey]($right);
    $result = strnatcasecmp($leftValue, $rightValue);
    if ($result === 0) {
        $result = strnatcasecmp((string) ($left['template_key'] ?? ''), (string) ($right['template_key'] ?? ''));
    }

    return $deliveryTemplateSortDirection === 'desc' ? -$result : $result;
});

include SR_ROOT . '/modules/notification/views/admin-delivery-templates.php';
