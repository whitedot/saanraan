<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_admin_form_label_help_html(string $forId, string $label, string $modalId, string $helpLabel = '설명 보기', bool $required = false): string
{
    $forId = trim($forId);
    $modalId = trim($modalId);
    $helpLabel = trim($helpLabel) !== '' ? trim($helpLabel) : '설명 보기';
    $requiredHtml = $required ? ' <span class="sr-required-label">(필수)</span>' : '';

    return '<div class="form-label form-label-help">'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $helpLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>'
        . '<label for="' . sr_e($forId) . '">' . sr_e($label) . $requiredHtml . '</label>'
        . '</div>';
}

function sr_admin_help_modal_html(string $modalId, string $title, string $bodyHtml): string
{
    $modalId = trim($modalId);

    return '<div id="' . sr_e($modalId) . '" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="' . sr_e($modalId) . '_title" aria-hidden="true" inert data-overlay-stack="true">'
        . '<div class="modal-dialog">'
        . '<div class="modal-content">'
        . '<div class="modal-header">'
        . '<h3 id="' . sr_e($modalId) . '_title" class="modal-title">' . sr_e($title) . '</h3>'
        . '<button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('close')
        . '</button>'
        . '</div>'
        . '<div class="modal-body admin-help-modal-body">' . $bodyHtml . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-solid-light modal-action" data-overlay="#' . sr_e($modalId) . '">닫기</button>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div>';
}

function sr_admin_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_admin_page_title_reset_url(bool $visible, string $url): string
{
    if (!$visible) {
        return '';
    }

    return $url;
}

function sr_admin_read_reference_count(array $referenceResult): int
{
    $rows = $referenceResult['rows'] ?? [];

    return is_array($rows) ? count($rows) : 0;
}

function sr_admin_read_reference_error_count(array $referenceResult): int
{
    $errors = $referenceResult['errors'] ?? [];

    return is_array($errors) ? count($errors) : 0;
}

function sr_admin_read_reference_button_html(string $modalId, array $referenceResult, string $label = '참조 현황'): string
{
    $modalId = trim($modalId);
    $rowCount = sr_admin_read_reference_count($referenceResult);
    $errorCount = sr_admin_read_reference_error_count($referenceResult);
    $buttonClass = $errorCount > 0 ? 'btn-outline-danger' : ($rowCount > 0 ? 'btn-solid-light' : 'btn-outline-secondary');
    $ariaLabel = $label . ' 참조 ' . (string) $rowCount . '건';
    if ($errorCount > 0) {
        $ariaLabel .= ', 오류 ' . (string) $errorCount . '건';
    }

    return '<button type="button" class="btn btn-sm btn-icon ' . sr_e($buttonClass) . '" aria-label="' . sr_e($ariaLabel) . '" title="' . sr_e($ariaLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html($errorCount > 0 ? 'warning' : 'travel_explore')
        . '</button>';
}

function sr_admin_read_reference_modal_html(string $modalId, string $title, array $referenceResult): string
{
    $modalId = trim($modalId);
    $rows = $referenceResult['rows'] ?? [];
    $errors = $referenceResult['errors'] ?? [];
    $rows = is_array($rows) ? $rows : [];
    $errors = is_array($errors) ? $errors : ['참조 현황을 읽을 수 없습니다.'];

    ob_start();
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($title); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e('닫기'); ?>" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php if ($errors !== []) { ?>
                        <div class="alert alert-danger" role="alert">
                            <strong><?php echo sr_e('계약 오류'); ?></strong>
                            <?php foreach ($errors as $error) { ?>
                                <p><?php echo sr_e((string) $error); ?></p>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <section class="card admin-list-card admin-list-form">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo sr_e('참조 목록'); ?></h4>
                            <span class="admin-summary-meta"><?php echo sr_e(number_format(count($rows)) . '건'); ?></span>
                        </div>
                        <div class="table-wrapper">
                            <table class="table table-list">
                                <thead>
                                    <tr>
                                        <th><?php echo sr_e('모듈'); ?></th>
                                        <th><?php echo sr_e('참조'); ?></th>
                                        <th><?php echo sr_e('상태'); ?></th>
                                        <th><?php echo sr_e('메시지'); ?></th>
                                        <th><?php echo sr_e('갱신'); ?></th>
                                        <th class="text-end"><?php echo sr_e('관리'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows === []) { ?>
                                        <tr>
                                            <td colspan="6" class="admin-empty-state"><?php echo sr_e('현재 읽기 참조가 없습니다.'); ?></td>
                                        </tr>
                                    <?php } ?>
                                    <?php foreach ($rows as $row) { ?>
                                        <?php
                                        $status = (string) ($row['status'] ?? 'unknown');
                                        $statusClass = match ($status) {
                                            'ok' => 'is-success',
                                            'stale', 'disabled_target' => 'is-warning',
                                            default => 'is-danger',
                                        };
                                        $statusLabel = match ($status) {
                                            'ok' => '정상',
                                            'stale' => '갱신 필요',
                                            'disabled_target' => '비활성 대상',
                                            'missing_target' => '대상 없음',
                                            default => '확인 필요',
                                        };
                                        $adminUrl = (string) ($row['admin_url'] ?? '');
                                        ?>
                                        <tr>
                                            <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_code_label((string) ($row['consumer_module_key'] ?? ''), 'module_key')); ?></td>
                                            <td class="admin-table-break">
                                                <?php echo sr_e((string) ($row['title'] ?? '')); ?><br>
                                                <span class="admin-summary-meta"><?php echo sr_e(sr_admin_code_label((string) ($row['reference_type'] ?? ''), 'reference_type') . ' #' . (string) ($row['reference_id'] ?? '')); ?></span>
                                            </td>
                                            <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e($statusLabel); ?></span></td>
                                            <td class="admin-table-break"><?php echo sr_e((string) ($row['message'] ?? ($row['policy_status'] ?? ''))); ?></td>
                                            <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($row['updated_at'] ?? '')); ?></td>
                                            <td class="admin-table-actions-cell">
                                                <?php if ($adminUrl !== '') { ?>
                                                    <a href="<?php echo sr_e(sr_url($adminUrl)); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e('관리 화면 열기'); ?>" title="<?php echo sr_e('관리 화면 열기'); ?>"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e('닫기'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function sr_admin_filter_toggle_group_html(string $id, string $name, array $options, array $selectedValues, string $allLabel = '전체'): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_filter_toggle';
    $name = preg_replace('/\[\]\z/', '', trim($name)) ?? trim($name);
    $name = $name !== '' ? $name : 'filter';
    $toggleOptions = [];
    foreach ($options as $value => $label) {
        $value = (string) $value;
        $labelText = trim((string) $label);
        if ($value === '' || $value === 'all' || $labelText === trim($allLabel)) {
            continue;
        }

        $toggleOptions[$value] = $label;
    }
    $selectedMap = array_intersect_key($selectedMap, $toggleOptions);

    $html = '<div id="' . sr_e($id) . '" class="filtering-toggle-group" data-filtering-toggle-group>';

    $html .= '<span class="filtering-toggle-item">'
        . '<input id="' . sr_e($idBase . '_all') . '" type="checkbox" class="form-choice-toggle-input sr-only" data-filtering-toggle-all' . ($selectedMap === [] ? ' checked' : '') . '>'
        . '<label for="' . sr_e($idBase . '_all') . '" class="btn btn-choice-light btn-group-start">' . sr_e($allLabel) . '</label>'
        . '</span>';

    $index = 0;
    $lastIndex = max(0, count($toggleOptions) - 1);
    foreach ($toggleOptions as $value => $label) {
        $optionValue = (string) $value;

        $groupClass = $index === $lastIndex ? 'btn-group-end' : 'btn-group-middle';
        $inputId = $idBase . '_' . (string) $index;
        $html .= '<span class="filtering-toggle-item">'
            . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($name) . '[]" value="' . sr_e($optionValue) . '" class="form-choice-toggle-input sr-only" data-filtering-toggle-choice' . (isset($selectedMap[$optionValue]) ? ' checked' : '') . '>'
            . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light ' . $groupClass . '">' . sr_e((string) $label) . '</label>'
            . '</span>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_filter_radio_toggle_group_html(string $id, string $name, array $options, array $selectedValues, string $allLabel = '전체'): string
{
    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_filter_radio_toggle';
    $name = preg_replace('/\[\]\z/', '', trim($name)) ?? trim($name);
    $name = $name !== '' ? $name : 'filter';
    $allLabelText = trim($allLabel);
    $toggleOptions = [];
    foreach ($options as $value => $label) {
        $value = (string) $value;
        $labelText = trim((string) $label);
        if ($value === '' || $labelText === $allLabelText) {
            continue;
        }

        $toggleOptions[$value] = $label;
    }

    $selectedValue = null;
    foreach ($selectedValues as $rawSelectedValue) {
        $candidate = (string) $rawSelectedValue;
        if (isset($toggleOptions[$candidate])) {
            $selectedValue = $candidate;
            break;
        }
    }

    $html = '<div id="' . sr_e($id) . '" class="filtering-toggle-group filtering-radio-toggle-group" data-filtering-radio-toggle-group>';
    $html .= '<span class="filtering-toggle-item">'
        . '<input id="' . sr_e($idBase . '_all') . '" type="radio" name="' . sr_e($name) . '" value="" class="form-choice-toggle-input sr-only" data-filtering-radio-toggle-choice' . ($selectedValue === null ? ' checked' : '') . '>'
        . '<label for="' . sr_e($idBase . '_all') . '" class="btn btn-choice-light btn-group-start">' . sr_e($allLabel) . '</label>'
        . '</span>';

    $index = 0;
    $lastIndex = max(0, count($toggleOptions) - 1);
    foreach ($toggleOptions as $value => $label) {
        $optionValue = (string) $value;
        $groupClass = $index === $lastIndex ? 'btn-group-end' : 'btn-group-middle';
        $inputId = $idBase . '_' . (string) $index;
        $html .= '<span class="filtering-toggle-item">'
            . '<input id="' . sr_e($inputId) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($optionValue) . '" class="form-choice-toggle-input sr-only" data-filtering-radio-toggle-choice' . ($selectedValue === $optionValue ? ' checked' : '') . '>'
            . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light ' . $groupClass . '">' . sr_e((string) $label) . '</label>'
            . '</span>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_code_label_options(array $values, string $type): array
{
    $options = [];
    foreach ($values as $value) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }
        $options[$value] = sr_admin_code_label($value, $type);
    }

    return $options;
}

function sr_admin_status_description_options(string $context, array $labels = [], array $descriptions = []): array
{
    $defaultDescriptions = [
        'active' => '현재 사용할 수 있는 정상 상태입니다.',
        'enabled' => '관리자 설정과 공개/운영 흐름에서 사용됩니다.',
        'disabled' => '사용하지 않도록 중지된 상태입니다.',
        'hidden' => '공개 화면이나 일반 흐름에서 숨겨진 상태입니다.',
        'deleted' => '삭제 처리되어 일반 흐름에서 제외된 상태입니다.',
        'pending' => '처리나 확인을 기다리는 상태입니다.',
        'draft' => '아직 공개 또는 사용 흐름에 들어가지 않은 임시 상태입니다.',
        'scheduled' => '예약 시각이나 조건에 따라 이후 적용될 상태입니다.',
        'published' => '공개 화면 또는 사용 흐름에 노출되는 상태입니다.',
        'archived' => '보관되어 일반 사용 흐름에서는 분리된 상태입니다.',
        'failed' => '처리 실패 상태입니다. 오류 내용과 후속 조치가 필요합니다.',
        'completed' => '요청한 처리가 완료된 상태입니다.',
        'rejected' => '검토 결과 거절된 상태입니다.',
        'cancelled' => '요청 또는 처리가 취소된 상태입니다.',
        'canceled' => '요청 또는 처리가 취소된 상태입니다.',
        'queued' => '작업 또는 발송을 기다리는 대기 상태입니다.',
        'processing' => '현재 처리 중인 상태입니다.',
        'sent' => '외부 발송이 완료된 상태입니다.',
        'dead' => '자동 재시도 한도를 넘겨 별도 확인이 필요한 상태입니다.',
        'open' => '새로 접수되어 조치가 필요한 상태입니다.',
        'reviewing' => '관리자가 내용을 검토 중인 상태입니다.',
        'resolved' => '검토와 조치가 끝난 상태입니다.',
        'dismissed' => '검토 결과 조치하지 않기로 한 상태입니다.',
        'suspended' => '이용이 제한된 상태입니다.',
        'withdrawn' => '회원 탈퇴가 처리된 상태입니다.',
        'anonymized' => '개인 식별 정보가 익명화된 상태입니다.',
        'requested' => '회원 또는 관리자가 처리를 요청한 상태입니다.',
        'installing' => '설치 또는 적용 절차가 진행 중인 상태입니다.',
        'removed' => '원본 대상이 제거되어 일반 표시에서 제외된 상태입니다.',
        'broken' => '대상 연결이 깨져 확인이 필요한 상태입니다.',
        'private' => '비공개 대상이라 일반 공개 흐름에서 제한되는 상태입니다.',
        'revoked' => '부여된 권한이나 이용권이 회수된 상태입니다.',
        'expired' => '유효 기간이 지나 더 이상 사용할 수 없는 상태입니다.',
    ];

    $contextDescriptions = [
        'module_status' => [
            'enabled' => '설치되어 관리자와 런타임에서 사용할 수 있는 모듈입니다.',
            'disabled' => '설치되어 있지만 관리자와 런타임 사용이 중지된 모듈입니다.',
            'installing' => '설치 절차가 진행 중이거나 완료 확인이 필요한 모듈입니다.',
            'failed' => '설치 또는 업데이트 중 오류가 발생해 확인이 필요한 모듈입니다.',
        ],
        'member_status' => [
            'active' => '로그인과 서비스 이용이 가능한 정상 회원입니다.',
            'pending' => '가입 또는 관리 확인을 기다리는 회원입니다.',
            'suspended' => '관리 정책에 따라 이용이 제한된 회원입니다.',
            'withdrawn' => '탈퇴 처리되어 일반 이용 대상에서 제외된 회원입니다.',
            'anonymized' => '재식별 가능한 회원 정보가 제거된 계정 기록입니다.',
        ],
        'content_status' => [
            'draft' => '작성 중인 임시 상태로 공개되지 않습니다.',
            'scheduled' => '예약 기준에 따라 이후 공개 또는 적용됩니다.',
            'enabled' => '사용 가능하도록 켜진 상태입니다.',
            'disabled' => '사용하지 않도록 꺼진 상태입니다.',
            'archived' => '보관 상태로 일반 관리 흐름에서 분리됩니다.',
            'published' => '공개 화면이나 사용 흐름에 노출됩니다.',
            'hidden' => '공개 화면이나 일반 목록에서 숨겨집니다.',
            'deleted' => '삭제 처리되어 일반 흐름에서 제외됩니다.',
            'pending' => '승인, 검토, 처리 대기 상태입니다.',
        ],
        'notification_status' => [
            'queued' => '알림 등록이나 발송 준비를 기다립니다.',
            'active' => '알림이 등록되어 대상자에게 표시될 수 있습니다.',
            'deleted' => '삭제 처리되어 일반 알림 흐름에서 제외됩니다.',
        ],
        'delivery_status' => [
            'queued' => '외부 채널 발송을 기다립니다.',
            'processing' => '발송 처리기가 처리 중입니다.',
            'sent' => '외부 채널 발송이 완료되었습니다.',
            'failed' => '발송 실패 후 재시도 또는 확인이 필요합니다.',
            'canceled' => '관리자 또는 정책에 의해 발송이 취소되었습니다.',
            'dead' => '최대 시도 이후 자동 재시도에서 제외된 상태입니다.',
        ],
        'privacy_request_status' => [
            'requested' => '개인정보 요청 대응 기록이 접수되었습니다.',
            'reviewing' => '관리자가 요청 대응 기록을 검토 중입니다.',
            'completed' => '요청 대응 기록이 완료되었습니다.',
            'rejected' => '검토 결과 요청 대응 기록을 거절로 종결했습니다.',
            'cancelled' => '요청 대응 기록이 취소되어 더 처리하지 않습니다.',
        ],
        'report_status' => [
            'open' => '신고가 접수되어 조치가 필요합니다.',
            'reviewing' => '관리자가 신고 내용을 검토 중입니다.',
            'resolved' => '신고 검토와 조치가 완료되었습니다.',
            'dismissed' => '조치하지 않기로 판단한 신고입니다.',
        ],
        'url_embed_status' => [
            'active' => 'URL 임베드 대상이 정상적으로 연결되어 있습니다.',
            'removed' => '원본 URL 또는 캐시 대상이 제거된 상태입니다.',
            'broken' => 'URL 임베드 대상 연결이 깨져 확인이 필요합니다.',
            'private' => '비공개 대상이라 공개 렌더링이 제한됩니다.',
            'deleted' => '삭제된 대상으로 일반 렌더링에서 제외됩니다.',
        ],
        'url_embed_cache_status' => [
            'fresh' => '현재 저장값을 공개 화면에서 사용할 수 있습니다.',
            'stale' => '대상 변경 때문에 갱신이 필요하며, 다음 렌더링 때 현재 조건으로 다시 확인합니다.',
            'deleted' => 'URL이 가리키는 대상이 삭제되어 공개 렌더링에서 제외됩니다.',
            'broken' => 'URL이 가리키는 대상을 공개 카드로 연결할 수 없습니다.',
        ],
    ];

    if ($labels === []) {
        $contextLabels = sr_admin_code_label_context_options();
        $labels = isset($contextLabels[$context]) && is_array($contextLabels[$context]) ? $contextLabels[$context] : [];
    }

    $items = [];
    foreach ($labels as $value => $label) {
        $key = (string) $value;
        if ($key === '') {
            continue;
        }

        $description = (string) ($descriptions[$key] ?? $contextDescriptions[$context][$key] ?? $defaultDescriptions[$key] ?? '');
        if ($description === '') {
            $description = '현재 목록에서 "' . (string) $label . '"(으)로 분류된 항목입니다.';
        }

        $items[$key] = [
            'label' => (string) $label,
            'description' => $description,
        ];
    }

    return $items;
}

function sr_admin_card_description_list_html(string $title, array $items, string $ariaLabel = ''): string
{
    if ($items === []) {
        return '';
    }

    $blockAria = $ariaLabel !== '' ? $ariaLabel : $title;
    $html = '<div class="card-description-block" aria-label="' . sr_e($blockAria) . '">';
    $html .= '<h3 class="card-description-title">' . sr_e($title) . '</h3>';
    $html .= '<dl class="card-description-list">';

    foreach ($items as $item) {
        $label = is_array($item) ? (string) ($item['label'] ?? '') : '';
        $description = is_array($item) ? (string) ($item['description'] ?? '') : '';
        if ($label === '' || $description === '') {
            continue;
        }

        $html .= '<div><dt>' . sr_e($label) . '</dt><dd>' . sr_e($description) . '</dd></div>';
    }

    return $html . '</dl></div>';
}

function sr_admin_status_description_list_html(string $context, array $labels = [], array $descriptions = [], string $title = '상태 설명'): string
{
    return sr_admin_card_description_list_html($title, sr_admin_status_description_options($context, $labels, $descriptions), $title);
}

function sr_admin_radio_toggle_group_html(string $idBase, string $name, array $options, string $selectedValue, bool $required = false, string $inputAttributes = ''): string
{
    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($idBase));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_radio_toggle';
    $html = '<div class="filtering-toggle-group admin-radio-toggle-group" role="radiogroup">';
    $index = 0;
    $lastIndex = max(0, count($options) - 1);

    foreach ($options as $value => $label) {
        $optionValue = (string) $value;
        $groupClass = $index === 0 ? 'btn-group-start' : ($index === $lastIndex ? 'btn-group-end' : 'btn-group-middle');
        $inputId = $index === 0 ? $idBase : $idBase . '_' . (string) $index;
        $html .= '<span class="filtering-toggle-item">'
            . '<input id="' . sr_e($inputId) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($optionValue) . '" class="form-choice-toggle-input sr-only"' . ($required ? ' required' : '') . $inputAttributes . ($selectedValue === $optionValue ? ' checked' : '') . '>'
            . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light ' . $groupClass . '">' . sr_e((string) $label) . '</label>'
            . '</span>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_checkbox_toggle_html(string $id, string $name, string $value, bool $checked, string $label, string $inputAttributes = ''): string
{
    $inputId = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $inputId = is_string($inputId) && $inputId !== '' ? $inputId : 'admin_checkbox_toggle';

    return '<div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">'
        . '<span class="filtering-toggle-item">'
        . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($name) . '" value="' . sr_e($value) . '" class="form-choice-toggle-input sr-only"' . $inputAttributes . ($checked ? ' checked' : '') . '>'
        . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light">' . sr_admin_choice_label_html($label) . '</label>'
        . '</span>'
        . '</div>';
}

function sr_admin_switch_html(string $id, string $name, string $value, bool $checked, string $label, string $uncheckedValue = '', string $inputAttributes = ''): string
{
    $inputId = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $inputId = is_string($inputId) && $inputId !== '' ? $inputId : 'admin_switch';
    $html = '';

    if ($uncheckedValue !== '') {
        $html .= '<input type="hidden" name="' . sr_e($name) . '" value="' . sr_e($uncheckedValue) . '">';
    }

    $html .= '<label class="form-check form-label" for="' . sr_e($inputId) . '">'
        . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($name) . '" value="' . sr_e($value) . '" class="form-switch form-switch-light"' . $inputAttributes . ($checked ? ' checked' : '') . '>'
        . sr_admin_choice_label_html($label)
        . '</label>';

    return $html;
}

function sr_admin_select_badge_list_html(string $id, string $name, array $options, array $selectedValues, string $emptyLabel = '선택 항목 없음', string $placeholder = '선택', string $rootAttributes = ''): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_select_badge_list';
    $html = '<div id="' . sr_e($id) . '" class="admin-select-badge-list" data-admin-select-badge-list' . $rootAttributes . '>';

    if ($options === []) {
        $html .= '<span class="form-help">' . sr_e($emptyLabel) . '</span>';
        return $html . '</div>';
    }

    $html .= '<div class="admin-select-badge-list-control">'
        . '<select id="' . sr_e($idBase . '_select') . '" class="form-select admin-select-badge-list-select" data-admin-select-badge-list-select>'
        . '<option value="">' . sr_e($placeholder) . '</option>';
    foreach ($options as $value => $option) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        $label = is_array($option) ? (string) ($option['label'] ?? $value) : (string) $option;
        $summary = is_array($option) ? (string) ($option['summary'] ?? '') : '';
        $summaryAttributes = '';
        if (is_array($option) && is_array($option['summaries'] ?? null)) {
            foreach ($option['summaries'] as $summaryKey => $summaryValue) {
                $summaryKey = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) $summaryKey) ?? '';
                if ($summaryKey !== '') {
                    $summaryAttributes .= ' data-admin-select-badge-summary-' . sr_e($summaryKey) . '="' . sr_e((string) $summaryValue) . '"';
                }
            }
        }
        $assetAttributes = '';
        if (is_array($option) && is_array($option['assets'] ?? null)) {
            $assetValues = [];
            foreach ($option['assets'] as $assetValue) {
                $assetValue = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) $assetValue) ?? '';
                if ($assetValue !== '') {
                    $assetValues[] = $assetValue;
                }
            }
            if ($assetValues !== []) {
                $assetAttributes = ' data-admin-select-badge-assets="' . sr_e(implode(' ', array_values(array_unique($assetValues)))) . '"';
            }
        }
        $html .= '<option value="' . sr_e($value) . '" data-admin-select-badge-label="' . sr_e($label) . '" data-admin-select-badge-summary="' . sr_e($summary) . '"' . $summaryAttributes . $assetAttributes . '>'
            . sr_e($label)
            . '</option>';
    }
    $html .= '</select>'
        . '<button type="button" class="btn btn-icon btn-solid-light admin-select-badge-list-clear" data-admin-select-badge-clear aria-label="선택 항목 모두 제거" title="선택 항목 모두 제거" disabled>' . sr_material_icon_html('delete') . '</button>'
        . '</div><div class="badge-list" data-admin-select-badge-list-items>';

    foreach ($options as $value => $option) {
        $value = (string) $value;
        if ($value === '' || !isset($selectedMap[$value])) {
            continue;
        }

        $label = is_array($option) ? (string) ($option['label'] ?? $value) : (string) $option;
        $summary = is_array($option) ? (string) ($option['summary'] ?? '') : '';
        $html .= sr_admin_select_badge_list_item_html($name, $value, $label, $summary);
    }

    $html .= '</div></div>';

    static $scriptPrinted = false;
    if (!$scriptPrinted) {
        $scriptPrinted = true;
        $html .= '<script>
(function () {
    function optionLabel(option) {
        return option ? (option.getAttribute("data-admin-select-badge-label") || option.textContent || "").replace(/\s+/g, " ").trim() : "";
    }
    function optionSummary(option, root) {
        if (!option) {
            return "";
        }
        var sourceSelector = root ? (root.getAttribute("data-admin-select-badge-summary-source") || "") : "";
        var source = sourceSelector ? document.querySelector(sourceSelector) : null;
        var defaultSourceValue = root ? (root.getAttribute("data-admin-select-badge-summary-default") || "") : "";
        var sourceValue = source && source.value ? String(source.value).replace(/[^a-zA-Z0-9_-]/g, "") : "";
        var assets = selectedAssets(root);
        var summarySourceValue = sourceValue || defaultSourceValue || "neutral";
        if (summarySourceValue && assets.length > 0) {
            var assetSummaries = [];
            assets.forEach(function (asset) {
                var sourceAssetSummary = option.getAttribute("data-admin-select-badge-summary-" + summarySourceValue + "_" + asset);
                if (sourceAssetSummary) {
                    assetSummaries.push(sourceAssetSummary);
                }
            });
            if (assetSummaries.length > 0) {
                return assetSummaries.join(" / ");
            }
        }
        if (sourceValue) {
            var sourceSummary = option.getAttribute("data-admin-select-badge-summary-" + sourceValue);
            if (sourceSummary !== null) {
                return sourceSummary || "";
            }
        }
        return option.getAttribute("data-admin-select-badge-summary") || "";
    }
    function selectedAssets(root) {
        var sourceSelector = root ? (root.getAttribute("data-admin-select-badge-asset-source") || "") : "";
        var source = sourceSelector ? document.querySelector(sourceSelector) : null;
        if (!source) {
            return [];
        }
        var values = [];
        source.querySelectorAll("input[type=checkbox], input[type=radio]").forEach(function (control) {
            if (!control.disabled && control.checked && control.value) {
                values.push(String(control.value).replace(/[^a-zA-Z0-9_-]/g, ""));
            }
        });
        if (values.length === 0 && source.matches && (source.matches("select") || source.matches("input"))) {
            if (!source.disabled && source.value) {
                values.push(String(source.value).replace(/[^a-zA-Z0-9_-]/g, ""));
            }
        }
        return values.filter(function (value, index, list) {
            return value && list.indexOf(value) === index;
        });
    }
    function optionMatchesAssets(option, root) {
        var assets = selectedAssets(root);
        var optionAssets = option ? (option.getAttribute("data-admin-select-badge-assets") || "").split(/\s+/).filter(Boolean) : [];
        if (!root || !root.getAttribute("data-admin-select-badge-asset-source")) {
            return true;
        }
        if (assets.length === 0) {
            return true;
        }
        if (optionAssets.length === 0) {
            return true;
        }
        return optionAssets.some(function (asset) {
            return assets.indexOf(asset) !== -1;
        });
    }
    function selectedValues(root) {
        var values = {};
        root.querySelectorAll("[data-admin-select-badge-value]").forEach(function (input) {
            if (input.value) {
                values[input.value] = true;
            }
        });
        return values;
    }
    function optionByValue(select, value) {
        if (!select) {
            return null;
        }
        for (var index = 0; index < select.options.length; index += 1) {
            if (select.options[index].value === value) {
                return select.options[index];
            }
        }
        return null;
    }
    function syncBadgeSummaries(root) {
        var select = root.querySelector("[data-admin-select-badge-list-select]");
        if (!select) {
            return;
        }
        root.querySelectorAll("[data-admin-select-badge-item]").forEach(function (item) {
            var input = item.querySelector("[data-admin-select-badge-value]");
            var option = input ? optionByValue(select, input.value) : null;
            var summary = optionSummary(option, root);
            var meta = item.querySelector(".badge-list-summary");
            if (summary && !meta) {
                meta = document.createElement("span");
                meta.className = "badge-list-summary";
                item.insertBefore(meta, input || item.lastChild);
            }
            if (meta) {
                meta.textContent = summary;
                if (!summary) {
                    meta.remove();
                }
            }
        });
    }
    function syncOptions(root) {
        var select = root.querySelector("[data-admin-select-badge-list-select]");
        if (!select) {
            return;
        }
        syncBadgeSummaries(root);
        var values = selectedValues(root);
        root.querySelectorAll("[data-admin-select-badge-item]").forEach(function (item) {
            var input = item.querySelector("[data-admin-select-badge-value]");
            var option = input ? optionByValue(select, input.value) : null;
            if (option && !optionMatchesAssets(option, root)) {
                item.remove();
            }
        });
        values = selectedValues(root);
        Array.prototype.forEach.call(select.options, function (option) {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            var blocked = !!values[option.value] || !optionMatchesAssets(option, root);
            option.hidden = blocked;
            option.disabled = blocked;
        });
        select.value = "";
        var clearButton = root.querySelector("[data-admin-select-badge-clear]");
        if (clearButton) {
            clearButton.disabled = Object.keys(values).length === 0;
        }
    }
    function addItem(root, value, label, summary) {
        var items = root.querySelector("[data-admin-select-badge-list-items]");
        if (!items || !value || selectedValues(root)[value]) {
            return;
        }
        var name = root.getAttribute("data-admin-select-badge-name") || "";
        var badge = document.createElement("span");
        badge.className = "badge-list-item";
        badge.setAttribute("data-admin-select-badge-item", "true");
        var title = document.createElement("span");
        title.className = "badge-list-label";
        title.textContent = label || value;
        badge.appendChild(title);
        if (summary) {
            var meta = document.createElement("span");
            meta.className = "badge-list-summary";
            meta.textContent = summary;
            badge.appendChild(meta);
        }
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = name + "[]";
        input.value = value;
        input.setAttribute("data-admin-select-badge-value", "true");
        badge.appendChild(input);
        var button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-icon-xs btn-ghost-danger admin-select-badge-list-remove";
        button.setAttribute("data-admin-select-badge-remove", "true");
        button.setAttribute("aria-label", "선택 항목 제거");
        button.textContent = "×";
        badge.appendChild(button);
        items.appendChild(badge);
        syncOptions(root);
    }
    document.addEventListener("change", function (event) {
        var select = event.target && event.target.closest ? event.target.closest("[data-admin-select-badge-list-select]") : null;
        if (!select || !select.value) {
            return;
        }
        var root = select.closest("[data-admin-select-badge-list]");
        if (!root) {
            return;
        }
        var option = select.selectedOptions && select.selectedOptions.length > 0 ? select.selectedOptions[0] : null;
        addItem(root, select.value, optionLabel(option), optionSummary(option, root));
    });
    document.addEventListener("click", function (event) {
        var clearButton = event.target && event.target.closest ? event.target.closest("[data-admin-select-badge-clear]") : null;
        if (clearButton) {
            var clearRoot = clearButton.closest("[data-admin-select-badge-list]");
            if (clearRoot) {
                clearRoot.querySelectorAll("[data-admin-select-badge-item]").forEach(function (item) {
                    item.remove();
                });
                syncOptions(clearRoot);
            }
            return;
        }
        var button = event.target && event.target.closest ? event.target.closest("[data-admin-select-badge-remove]") : null;
        if (!button) {
            return;
        }
        var root = button.closest("[data-admin-select-badge-list]");
        var item = button.closest("[data-admin-select-badge-item]");
        if (item) {
            item.remove();
        }
        if (root) {
            syncOptions(root);
        }
    });
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("[data-admin-select-badge-list]").forEach(syncOptions);
    });
    document.addEventListener("change", function (event) {
        document.querySelectorAll("[data-admin-select-badge-list][data-admin-select-badge-summary-source], [data-admin-select-badge-list][data-admin-select-badge-asset-source]").forEach(function (root) {
            var sourceSelector = root.getAttribute("data-admin-select-badge-summary-source") || "";
            var assetSelector = root.getAttribute("data-admin-select-badge-asset-source") || "";
            var source = sourceSelector ? document.querySelector(sourceSelector) : null;
            var assetSource = assetSelector ? document.querySelector(assetSelector) : null;
            var sourceChanged = !!(sourceSelector && event.target && event.target.matches && event.target.matches(sourceSelector));
            var assetChanged = !!(assetSource && event.target && assetSource.contains(event.target));
            if (sourceChanged || assetChanged) {
                syncOptions(root);
            }
        });
    });
    document.querySelectorAll("[data-admin-select-badge-list]").forEach(syncOptions);
})();
</script>';
    }

    return str_replace('class="admin-select-badge-list"', 'class="admin-select-badge-list" data-admin-select-badge-name="' . sr_e($name) . '"', $html);
}

function sr_admin_select_badge_list_item_html(string $name, string $value, string $label, string $summary = ''): string
{
    $html = '<span class="badge-list-item" data-admin-select-badge-item>'
        . '<span class="badge-list-label">' . sr_e($label) . '</span>';
    if ($summary !== '') {
        $html .= '<span class="badge-list-summary">' . sr_e($summary) . '</span>';
    }
    $html .= '<input type="hidden" name="' . sr_e($name) . '[]" value="' . sr_e($value) . '" data-admin-select-badge-value>'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-danger admin-select-badge-list-remove" data-admin-select-badge-remove aria-label="선택 항목 제거">×</button>'
        . '</span>';

    return $html;
}

function sr_admin_member_group_key_badge_select_html(string $id, string $name, array $selectedKeys, array $memberGroups): string
{
    $options = [];
    foreach ($memberGroups as $memberGroup) {
        $groupKey = (string) ($memberGroup['group_key'] ?? '');
        if ($groupKey === '') {
            continue;
        }

        $title = trim((string) ($memberGroup['title'] ?? ''));
        $label = $title !== '' ? $title . ' (' . $groupKey . ')' : $groupKey;
        $options[$groupKey] = $label;
    }

    return sr_admin_select_badge_list_html($id, $name, $options, $selectedKeys, '활성 회원 그룹 없음', '그룹 선택');
}

function sr_admin_row_action_button_class(string $status): string
{
    return match ($status) {
        'published', 'active', 'accepted', 'sent', 'completed' => 'btn-solid-primary',
        'pending', 'reviewing', 'queued', 'flagged', 'member' => 'btn-solid-light',
        'hidden', 'archived', 'private', 'canceled' => 'btn-outline-secondary',
        'deleted', 'rejected', 'cancelled', 'failed', 'excluded' => 'btn-outline-danger',
        default => 'btn-solid-light',
    };
}

function sr_admin_row_action_confirm_message(string $status, string $label): string
{
    return match ($status) {
        'deleted' => $label . ' 상태로 변경할까요? 삭제 상태로 바뀐 항목은 사용자 화면에 노출되지 않습니다.',
        'rejected' => $label . ' 상태로 변경할까요? 요청 거절 처리가 기록됩니다.',
        'cancelled', 'canceled' => $label . ' 상태로 변경할까요? 취소 처리가 기록됩니다.',
        'failed' => $label . ' 상태로 변경할까요? 실패 상태로 기록됩니다.',
        'excluded' => $label . ' 상태로 변경할까요? 이 응답은 분석 대상에서 제외됩니다.',
        default => '',
    };
}

function sr_admin_row_action_confirm_attr(string $status, string $label): string
{
    $message = sr_admin_row_action_confirm_message($status, $label);
    if ($message === '') {
        return '';
    }

    return ' onclick="return confirm(\'' . sr_e($message) . '\');"';
}
