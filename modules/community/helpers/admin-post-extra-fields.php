<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers/posts-extra-fields.php';

function sr_community_admin_post_extra_fields_editor_html(
    string $id,
    string $name,
    mixed $value,
    string $title = '게시글 추가 입력 항목',
    string $help = ''
): string {
    $safeId = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($id)), '-');
    if ($safeId === '') {
        $safeId = 'community-post-extra-fields';
    }
    $modalId = $safeId . '-modal';
    $json = sr_community_extra_field_definitions_json($value);

    ob_start();
    ?>
    <section id="<?php echo sr_e($safeId); ?>-section" class="card admin-list-card admin-list-form" data-admin-section-anchor data-community-admin-post-extra-fields-editor>
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e($title); ?></h2>
            <div class="card-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($modalId); ?>" data-overlay="#<?php echo sr_e($modalId); ?>" data-community-admin-post-extra-field-add>항목 추가</button>
            </div>
        </div>
        <div class="table-wrapper" data-community-admin-post-extra-field-table-wrap hidden>
            <table class="table table-list">
                <caption class="sr-only"><?php echo sr_e($title); ?> 목록</caption>
                <thead><tr><th>순서</th><th>라벨</th><th>유형</th><th>표시</th><th>개인정보</th><th class="text-end">작업</th></tr></thead>
                <tbody data-community-admin-post-extra-field-list data-admin-reorder-list></tbody>
            </table>
        </div>
        <p class="admin-empty-state" data-community-admin-post-extra-field-empty hidden>추가 입력 항목이 없습니다.</p>
        <?php if ($help !== '') { ?><p class="form-help"><?php echo sr_e($help); ?></p><?php } ?>
        <textarea id="<?php echo sr_e($safeId); ?>" name="<?php echo sr_e($name); ?>" hidden data-community-admin-post-extra-fields-json><?php echo sr_e($json); ?></textarea>

        <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-title" aria-hidden="true" inert data-community-admin-post-extra-field-modal>
            <div class="modal-dialog modal-dialog-lg modal-dialog-scrollable">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($modalId); ?>-title" class="modal-title" data-community-admin-post-extra-field-modal-title>게시글 입력 항목 추가</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" data-community-admin-post-extra-field-index>
                        <input type="hidden" data-community-admin-post-extra-field-input="key">
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-label">라벨 <span class="sr-required-label">(필수)</span></label><div class="form-field"><input id="<?php echo sr_e($modalId); ?>-label" type="text" maxlength="120" class="form-input form-control-full" data-overlay-focus data-community-admin-post-extra-field-input="label"></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-type">유형 <span class="sr-required-label">(필수)</span></label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-type" class="form-select" data-community-admin-post-extra-field-input="type"><option value="text">텍스트</option><option value="textarea">긴 텍스트</option><option value="select">선택</option><option value="checkbox">체크박스</option></select></div></div>
                        <div class="form-row" data-community-admin-post-extra-field-options-row hidden><label class="form-label" for="<?php echo sr_e($modalId); ?>-options">선택지 <span class="sr-required-label">(필수)</span></label><div class="form-field"><textarea id="<?php echo sr_e($modalId); ?>-options" rows="4" maxlength="6000" class="form-textarea form-control-full" data-community-admin-post-extra-field-input="options"></textarea><p class="form-help">한 줄에 하나씩 입력합니다.</p></div></div>
                        <div class="form-row"><span class="form-label">입력 여부</span><div class="form-field"><label class="form-check form-label"><input type="checkbox" value="1" class="form-switch form-switch-light" data-community-admin-post-extra-field-input="required"><?php echo sr_admin_choice_label_html('필수 입력'); ?></label></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-visibility">공개 범위</label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-visibility" class="form-select" data-community-admin-post-extra-field-input="visibility"><option value="public">공개</option><option value="admin">관리자 전용</option></select></div></div>
                        <div class="form-row"><span class="form-label">표시 위치</span><div class="form-field"><label class="form-check form-label"><input type="checkbox" value="1" class="form-switch form-switch-light" data-community-admin-post-extra-field-input="show_on_view"><?php echo sr_admin_choice_label_html('본문 화면 표시'); ?></label><label class="form-check form-label"><input type="checkbox" value="1" class="form-switch form-switch-light" data-community-admin-post-extra-field-input="show_in_admin"><?php echo sr_admin_choice_label_html('관리자 목록 표시'); ?></label></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-purpose">수집·이용 목적</label><div class="form-field"><input id="<?php echo sr_e($modalId); ?>-purpose" type="text" maxlength="255" class="form-input form-control-full" data-community-admin-post-extra-field-input="privacy_purpose"><p class="form-help">이 정보를 받는 목적을 기록합니다.</p></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-export">내 정보 사본에 포함</label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-export" class="form-select" data-community-admin-post-extra-field-input="export_policy"><option value="include">포함함</option><option value="exclude">포함하지 않음</option></select></div></div>
                        <div class="form-row"><label class="form-label" for="<?php echo sr_e($modalId); ?>-cleanup">계정 정리 시 처리</label><div class="form-field"><select id="<?php echo sr_e($modalId); ?>-cleanup" class="form-select" data-community-admin-post-extra-field-input="cleanup_policy"><option value="anonymize">개인정보 제거</option><option value="retain">그대로 보관</option></select></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button><button type="button" class="btn btn-solid-primary modal-action" data-community-admin-post-extra-field-save>적용</button></div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}
