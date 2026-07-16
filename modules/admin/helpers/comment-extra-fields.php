<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/comment-extra-fields.php';

function sr_admin_comment_extra_fields_editor_html(string $id, string $name, mixed $value, string $title = '댓글 추가 입력 항목', string $help = '', string $footerHtml = ''): string
{
    $safeId = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($id)), '-');
    if ($safeId === '') {
        $safeId = 'comment-extra-fields';
    }
    $modalId = $safeId . '-modal';
    $json = sr_comment_extra_field_definitions_json($value);
    ob_start();
    ?>
    <section id="<?php echo sr_e($safeId); ?>-section" class="card admin-list-card admin-list-form admin-extra-field-editor admin-comment-extra-fields" data-admin-comment-extra-fields-editor data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e($title); ?></h2>
            <div class="admin-row-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-controls="<?php echo sr_e($modalId); ?>" data-overlay="#<?php echo sr_e($modalId); ?>" data-admin-comment-extra-field-add>항목 추가</button>
            </div>
        </div>
        <div class="table-wrapper" data-admin-comment-extra-field-table-wrap hidden>
            <table class="table table-list admin-extra-field-table" data-admin-comment-extra-field-table>
                <caption class="sr-only"><?php echo sr_e($title); ?> 목록</caption>
                <thead><tr><th class="admin-extra-field-order-cell">순서</th><th>라벨</th><th>유형</th><th>개인정보 처리</th><th class="text-end">작업</th></tr></thead>
                <tbody data-admin-comment-extra-field-list data-admin-reorder-list></tbody>
            </table>
        </div>
        <?php if ($help !== '') { ?><p class="form-help"><?php echo sr_e($help); ?></p><?php } ?>
        <p class="admin-empty-state" data-admin-comment-extra-field-empty hidden>추가 입력 항목이 없습니다.</p>
        <?php if ($footerHtml !== '') { ?><?php echo $footerHtml; ?><?php } ?>
        <textarea id="<?php echo sr_e($safeId); ?>" name="<?php echo sr_e($name); ?>" hidden data-admin-comment-extra-fields-json><?php echo sr_e($json); ?></textarea>

        <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-title" aria-hidden="true" inert data-overlay-stack="true" data-admin-comment-extra-field-modal>
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h2 id="<?php echo sr_e($modalId); ?>-title" class="modal-title" data-admin-comment-extra-field-modal-title>댓글 입력 항목 추가</h2>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" data-overlay="#<?php echo sr_e($modalId); ?>" aria-label="닫기"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" data-admin-comment-extra-field-index>
                        <input type="hidden" data-admin-comment-extra-field-input="key">
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-label">라벨 <span class="sr-required-label">(필수)</span></label><div class="form-field"><input id="<?php echo sr_e($modalId); ?>-field-label" type="text" maxlength="120" class="form-input form-control-full" data-overlay-focus data-admin-comment-extra-field-input="label"></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-type">유형 <span class="sr-required-label">(필수)</span></label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-field-type" class="form-select" data-admin-comment-extra-field-input="type"><option value="text">텍스트</option><option value="textarea">긴 텍스트</option><option value="select">선택</option><option value="checkbox">체크박스</option></select></div></div>
                        <div class="form-row" data-admin-comment-extra-field-options-row hidden><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-options">선택지 <span class="sr-required-label">(필수)</span></label><div class="form-field"><textarea id="<?php echo sr_e($modalId); ?>-field-options" rows="4" maxlength="6000" class="form-textarea form-control-full" data-admin-comment-extra-field-input="options"></textarea><p class="form-help">한 줄에 하나씩 입력합니다.</p></div></div>
                        <div class="form-row"><span class="form-label">입력 여부</span><div class="form-field"><label class="form-check form-label"><input type="checkbox" value="1" class="form-switch form-switch-light" data-admin-comment-extra-field-input="required"><?php echo sr_admin_choice_label_html('필수 입력'); ?></label></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-purpose">수집·이용 목적</label><div class="form-field"><input id="<?php echo sr_e($modalId); ?>-field-purpose" type="text" maxlength="255" class="form-input form-control-full" data-admin-comment-extra-field-input="privacy_purpose"><p class="form-help">이 항목을 수집하는 이유를 기록합니다.</p></div></div>
                        <div class="form-row"><span class="form-label">목적 안내</span><div class="form-field"><label class="form-check form-label"><input type="checkbox" value="1" class="form-switch form-switch-light" data-admin-comment-extra-field-input="show_privacy_purpose"><?php echo sr_admin_choice_label_html('입력 항목 아래에 표시'); ?></label><p class="form-help">끄면 목적은 관리·개인정보 처리 기준에만 보관하고 댓글 작성 화면에는 표시하지 않습니다.</p></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-export">내 정보 사본에 포함</label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-field-export" class="form-select" data-admin-comment-extra-field-input="export_policy"><option value="include">포함함</option><option value="exclude">포함하지 않음</option></select></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-field-cleanup">계정 정리 시 처리</label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-field-cleanup" class="form-select" data-admin-comment-extra-field-input="cleanup_policy"><option value="anonymize">개인정보 제거</option><option value="retain">그대로 보관</option></select></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button><button type="button" class="btn btn-solid-primary modal-action" data-admin-comment-extra-field-save>적용</button></div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}
