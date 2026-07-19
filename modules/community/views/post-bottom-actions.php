<?php

$communityBottomCanEditPost = false;
$communityBottomCanWriteNotice = false;
$communityBottomCanHidePost = false;
$communityBottomCanDeletePost = false;
if (is_array($account ?? null)) {
    $communityBottomCanEditPost = sr_community_account_can_edit_post($post, $account);
    $communityBottomCanWriteNotice = sr_community_account_can_write_notice($pdo, [
        'id' => (int) ($post['board_id'] ?? 0),
        'status' => (string) ($post['board_status'] ?? 'enabled'),
    ], $account, sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit'));
    $communityBottomCanHidePost = sr_community_account_can_hide_post($pdo, $post, $account);
    $communityBottomCanDeletePost = sr_community_account_can_delete_post($post, $account);
}
$communityBottomHasManagementActions = $communityBottomCanWriteNotice || $communityBottomCanHidePost;
$communityBottomReportModalId = 'community_report_post_bottom_modal_' . (string) (int) ($post['id'] ?? 0);
$communityBottomManagementMenuId = 'community_post_bottom_management_menu_' . (string) (int) ($post['id'] ?? 0);
$communityBottomGuestEditPasswordId = 'modules_community_view_bottom_guest_post_password';
$communityBottomGuestDeletePasswordId = 'modules_community_view_bottom_guest_post_delete_password';
?>
<div class="community-post-view-actions community-post-view-actions-bottom" aria-label="게시글 하단 작업">
    <div class="community-action-group community-action-group-leading">
        <a class="btn btn-outline-default" href="<?php echo sr_e($communityPostBoardUrl); ?>"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
        <?php if (is_array($account ?? null)) { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                <button type="submit" class="btn btn-outline-default"><?php echo sr_e($isScrapped ? sr_t('community::ui.text.d013b859') : sr_t('community::ui.text.3eac8b2a')); ?></button>
            </form>
            <?php if ($canReportPost) { ?>
                <button type="button" class="btn btn-outline-warning" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityBottomReportModalId); ?>" data-overlay="#<?php echo sr_e($communityBottomReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
                <div id="<?php echo sr_e($communityBottomReportModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityBottomReportModalId . '_title'); ?>" aria-hidden="true" inert>
                    <div class="modal-dialog">
                        <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="modal-content">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="target_type" value="post">
                            <input type="hidden" name="target_id" value="<?php echo sr_e((string) $post['id']); ?>">
                            <div class="modal-header">
                                <h3 id="<?php echo sr_e($communityBottomReportModalId . '_title'); ?>" class="modal-title"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></h3>
                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityBottomReportModalId); ?>">
                                    <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="form-row">
                                    <label for="<?php echo sr_e($communityBottomReportModalId . '_reason_key'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <div class="form-field">
                                        <select id="<?php echo sr_e($communityBottomReportModalId . '_reason_key'); ?>" name="reason_key" class="form-select" required data-overlay-focus>
                                            <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <label for="<?php echo sr_e($communityBottomReportModalId . '_memo_text'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></label>
                                    <div class="form-field">
                                        <textarea id="<?php echo sr_e($communityBottomReportModalId . '_memo_text'); ?>" name="memo_text" rows="4" class="form-textarea"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityBottomReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                <button type="submit" class="btn btn-solid-warning modal-action"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="community-action-group community-action-group-trailing">
        <button type="button" class="btn btn-outline-default community-post-comments-jump" data-community-scroll-target="#comments" aria-label="<?php echo sr_e('댓글 ' . number_format($communityPostCommentCount) . '개로 바로가기'); ?>">
            <?php echo sr_material_icon_html('comment', '', ''); ?>
            <span><?php echo sr_e(number_format($communityPostCommentCount)); ?></span>
        </button>
        <?php if (is_array($account ?? null)) { ?>
            <?php if ($communityBottomCanEditPost) { ?>
                <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></a>
            <?php } ?>
            <?php if ($communityBottomCanDeletePost) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                </form>
            <?php } ?>
            <?php if ($communityBottomHasManagementActions) { ?>
                <div class="dropdown community-post-management-dropdown" data-dropdown-placement="top-end">
                    <button type="button" class="dropdown-toggle btn btn-outline-default" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo sr_e($communityBottomManagementMenuId); ?>">
                        <?php echo sr_e('관리'); ?>
                        <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                    </button>
                    <div id="<?php echo sr_e($communityBottomManagementMenuId); ?>" class="dropdown-menu community-post-management-menu" role="menu" aria-orientation="vertical">
                        <?php if ($communityBottomCanWriteNotice) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/community/notice')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <input type="hidden" name="intent" value="<?php echo (int) ($post['is_notice'] ?? 0) === 1 ? 'remove' : 'set'; ?>">
                                <button type="submit" class="dropdown-item" role="menuitem"><?php echo sr_e((int) ($post['is_notice'] ?? 0) === 1 ? '공지 해제' : '공지 지정'); ?></button>
                            </form>
                        <?php } ?>
                        <?php if ($communityBottomCanWriteNotice && $communityBottomCanHidePost) { ?>
                            <hr class="dropdown-divider">
                        <?php } ?>
                        <?php if ($communityBottomCanHidePost) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/community/hide')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <button type="submit" class="dropdown-item" role="menuitem"><?php echo sr_e('숨김'); ?></button>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        <?php } elseif ((int) ($post['author_account_id'] ?? 0) < 1 && (string) ($post['guest_password_hash'] ?? '') !== '') { ?>
            <details>
                <summary class="btn btn-outline-default"><?php echo sr_e('비회원 글 수정·삭제'); ?></summary>
                <form method="post" action="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <p>
                        <label for="<?php echo sr_e($communityBottomGuestEditPasswordId); ?>">
                            <span><?php echo sr_e('수정 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <input id="<?php echo sr_e($communityBottomGuestEditPasswordId); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                        </label>
                    </p>
                    <button type="submit" class="btn btn-outline-primary"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></button>
                </form>
                <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <p>
                        <label for="<?php echo sr_e($communityBottomGuestDeletePasswordId); ?>">
                            <span><?php echo sr_e('삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <input id="<?php echo sr_e($communityBottomGuestDeletePasswordId); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                        </label>
                    </p>
                    <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                </form>
            </details>
        <?php } ?>
    </div>
</div>
