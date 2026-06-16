<?php

$adminPageTitle = '콘텐츠 작성자 승인';
$adminPageSubtitle = '콘텐츠를 등록할 수 있는 회원과 권한 상태를 관리합니다.';
$adminContainerClass = 'admin-page-content-authors admin-ui-scope';
$canEditContentAuthors = !empty($canEditContentAuthors);
$authorReturnTo = sr_admin_current_get_url('/admin/content/authors');
$authorAddMemberInputId = 'content_author_add_account_identifier';
$authorAddMemberLookupModalId = 'content_author_add_member_lookup_modal';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/authors');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
        <div class="filtering-field">
            <span class="filtering-label">상태</span>
            <?php echo sr_admin_filter_toggle_group_html('content_author_filter_status', 'status', ['allowed' => sr_content_author_permission_status_label('allowed'), 'blocked' => sr_content_author_permission_status_label('blocked')], $authorStatuses ?? [], '전체 상태'); ?>
        </div>
        <div class="filtering-field">
            <span class="filtering-label">검수</span>
            <?php echo sr_admin_filter_toggle_group_html('content_author_filter_review_required_override', 'review_required_override', ['inherit' => sr_content_author_review_override_label('inherit'), 'required' => sr_content_author_review_override_label('required'), 'exempt' => sr_content_author_review_override_label('exempt')], $authorReviewOverrides ?? [], '전체 검수'); ?>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">승인 목록</h2>
        </div>
        <?php if ($canEditContentAuthors) { ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="content-author-add-modal" data-overlay="#content-author-add-modal">승인 추가</button>
        <?php } ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <caption class="sr-only">콘텐츠 작성자 승인 목록</caption>
            <thead>
                <tr><th>회원</th><th>상태</th><th>검수</th><th>메모</th><th>수정일</th><?php if ($canEditContentAuthors) { ?><th class="text-end">관리</th><?php } ?></tr>
            </thead>
            <tbody>
                <?php if ($contentAuthorPermissions === []) { ?>
                    <tr><td colspan="<?php echo $canEditContentAuthors ? '6' : '5'; ?>" class="admin-empty-state">콘텐츠 작성자 승인이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($contentAuthorPermissions as $permission) { ?>
                        <?php $authorEditModalId = 'content-author-edit-modal-' . (string) (int) $permission['account_id']; ?>
                        <tr>
                            <td class="admin-table-break">#<?php echo sr_e((string) (int) $permission['account_id']); ?><br><?php echo sr_e((string) (($permission['display_name'] ?? '') ?: ($permission['email'] ?? ''))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_author_permission_status_label((string) $permission['status'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_content_author_review_override_label((string) $permission['review_required_override'])); ?></td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($permission['note'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) ($permission['updated_at'] ?? '')); ?></td>
                            <?php if ($canEditContentAuthors) { ?>
                                <td class="admin-table-actions-cell">
                                    <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="수정" title="수정" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($authorEditModalId); ?>" data-overlay="#<?php echo sr_e($authorEditModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($canEditContentAuthors) { ?>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
        </div>
    <?php } ?>
</section>
<?php if ($canEditContentAuthors) { ?>
    <div id="content-author-add-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="content-author-add-modal-title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="modal-content admin-form ui-form-theme">
                <div class="modal-header">
                    <h3 id="content-author-add-modal-title" class="modal-title">작성자 승인 추가</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#content-author-add-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($authorReturnTo); ?>">
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($authorAddMemberInputId); ?>">회원 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input type="hidden" name="account_identifier_field" value="all">
                            <div class="admin-lookup-control">
                                <input id="<?php echo sr_e($authorAddMemberInputId); ?>" type="text" name="account_identifier" class="form-input" maxlength="80" required data-overlay-focus>
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($authorAddMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($authorAddMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($authorAddMemberInputId); ?>">회원 검색</button>
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="content_author_add_status">상태 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <select id="content_author_add_status" name="status" class="form-select" required>
                                <option value="allowed">허용</option>
                                <option value="blocked">차단</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="content_author_add_review_required_override">검수 예외 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <select id="content_author_add_review_required_override" name="review_required_override" class="form-select" required>
                                <option value="inherit">기본 설정 따름</option>
                                <option value="required">항상 검수</option>
                                <option value="exempt">검수 면제</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="content_author_add_note">메모</label>
                        <div class="admin-form-field"><textarea id="content_author_add_note" name="note" rows="3" class="form-input"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#content-author-add-modal">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
    <?php foreach ($contentAuthorPermissions as $permission) { ?>
        <?php
        $authorEditModalId = 'content-author-edit-modal-' . (string) (int) $permission['account_id'];
        $authorName = (string) (($permission['display_name'] ?? '') ?: ($permission['email'] ?? ''));
        $authorLabel = '#' . (string) (int) $permission['account_id'] . ($authorName !== '' ? ' · ' . $authorName : '');
        ?>
        <div id="<?php echo sr_e($authorEditModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($authorEditModalId); ?>-title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="modal-content admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($authorEditModalId); ?>-title" class="modal-title">작성자 승인 수정</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($authorEditModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($authorReturnTo); ?>">
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) (int) $permission['account_id']); ?>">
                        <div class="admin-form-row">
                            <span class="form-label">회원</span>
                            <div class="admin-form-field"><p class="admin-form-static"><?php echo sr_e($authorLabel); ?></p></div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($authorEditModalId); ?>-status">상태 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($authorEditModalId); ?>-status" name="status" class="form-select" required data-overlay-focus>
                                    <option value="allowed"<?php echo (string) $permission['status'] === 'allowed' ? ' selected' : ''; ?>>허용</option>
                                    <option value="blocked"<?php echo (string) $permission['status'] === 'blocked' ? ' selected' : ''; ?>>차단</option>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($authorEditModalId); ?>-review">검수 예외 <span class="sr-required-label">(필수)</span></label>
                            <div class="admin-form-field">
                                <select id="<?php echo sr_e($authorEditModalId); ?>-review" name="review_required_override" class="form-select" required>
                                    <option value="inherit"<?php echo (string) $permission['review_required_override'] === 'inherit' ? ' selected' : ''; ?>>기본 설정 따름</option>
                                    <option value="required"<?php echo (string) $permission['review_required_override'] === 'required' ? ' selected' : ''; ?>>항상 검수</option>
                                    <option value="exempt"<?php echo (string) $permission['review_required_override'] === 'exempt' ? ' selected' : ''; ?>>검수 면제</option>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($authorEditModalId); ?>-note">메모</label>
                            <div class="admin-form-field"><textarea id="<?php echo sr_e($authorEditModalId); ?>-note" name="note" rows="3" class="form-input"><?php echo sr_e((string) ($permission['note'] ?? '')); ?></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($authorEditModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
    <?php
    $assetAdjustLookup = [
        'field_prefix' => 'content_author_add',
        'member_input_id' => $authorAddMemberInputId,
        'return_overlay_id' => 'content-author-add-modal',
        'return_label' => '작성자 승인 추가',
        'member_search_url' => sr_url('/admin/content/authors/member-search'),
    ];
    include SR_ROOT . '/modules/admin/views/asset-adjust-lookup-modals.php';
    ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
