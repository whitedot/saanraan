<?php

$adminPageTitle = '콘텐츠 작성자 승인';
$adminPageSubtitle = '회원에게 콘텐츠 제출 권한을 직접 부여하고 제출본 검수 방식을 정합니다.';
$adminContainerClass = 'admin-page-content-authors admin-ui-scope';
$canEditContentAuthors = !empty($canEditContentAuthors);
$authorReturnTo = sr_admin_current_get_url('/admin/content/authors');
$authorAddMemberInputId = 'content_author_add_account_identifier';
$authorAddMemberLookupModalId = 'content_author_add_member_lookup_modal';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/authors');
$contentAuthorHelpOpenLabel = '도움말 보기';
$contentAuthorHelp = [
    'status' => [
        'id' => 'content-author-status-help',
        'title' => '작성자 상태 도움말',
        'body' => '<p>‘허용’은 이 회원에게 콘텐츠 제출 권한을 직접 부여합니다. 사이트의 회원 제출 기능과 제출할 콘텐츠 그룹의 회원 제출 기능도 모두 사용 중이어야 합니다.</p>'
            . '<p>‘차단’은 이 화면에서 부여한 직접 권한을 사용하지 않는다는 뜻입니다. 회원이 콘텐츠 그룹에서 허용한 회원 그룹에 속해 있으면 그 그룹에는 계속 제출할 수 있습니다.</p>'
            . '<p>상태를 바꿔도 이미 제출하거나 공개한 콘텐츠는 삭제되거나 취소되지 않습니다.</p>',
    ],
    'review' => [
        'id' => 'content-author-review-help',
        'title' => '검수 방식 도움말',
        'body' => '<p>‘기본 설정 따름’은 콘텐츠 그룹의 검수 설정을 따르고, 그룹에 별도 설정이 없으면 콘텐츠 환경설정의 기본 검수 값을 사용합니다.</p>'
            . '<p>‘항상 검수’와 ‘검수 면제’는 사이트와 콘텐츠 그룹의 설정보다 우선합니다. 변경한 방식은 이후 회원이 콘텐츠를 제출할 때 적용되며, 이미 처리된 제출본의 상태는 바꾸지 않습니다.</p>',
    ],
];
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
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">승인 목록</h2>
        </div>
        <?php if ($canEditContentAuthors) { ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="content-author-add-modal" data-overlay="#content-author-add-modal">승인 추가</button>
        <?php } ?>
    </div>
    <?php echo sr_admin_pagination_summary_html($contentAuthorPagination); ?>
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
    <?php echo sr_admin_pagination_html($contentAuthorPagination, '콘텐츠 작성자 승인 목록 페이지'); ?>
</section>
<?php if ($canEditContentAuthors) { ?>
    <div id="content-author-add-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="content-author-add-modal-title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/authors')); ?>" class="modal-content admin-form ui-form-theme">
                <div class="modal-header">
                    <h3 id="content-author-add-modal-title" class="modal-title">작성자 승인 추가</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#content-author-add-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($authorReturnTo); ?>">
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($authorAddMemberInputId); ?>">회원 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input type="hidden" name="account_identifier_field" value="all">
                            <div class="admin-lookup-control">
                                <input id="<?php echo sr_e($authorAddMemberInputId); ?>" type="text" name="account_identifier" class="form-input" maxlength="80" required data-overlay-focus>
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($authorAddMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($authorAddMemberLookupModalId); ?>" data-admin-member-lookup-open data-target="#<?php echo sr_e($authorAddMemberInputId); ?>">회원 검색</button>
                            </div>
                            <small class="form-help">회원 검색에서 대상을 찾아 선택하세요. 이미 등록된 회원을 선택하면 기존 설정을 새 값으로 바꿉니다.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('content_author_add_status', '상태', $contentAuthorHelp['status']['id'], $contentAuthorHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <select id="content_author_add_status" name="status" class="form-select" required>
                                <option value="allowed">허용</option>
                                <option value="blocked">차단</option>
                            </select>
                            <small class="form-help">차단해도 회원 그룹을 통해 받은 제출 권한은 유지될 수 있습니다.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('content_author_add_review_required_override', '검수 방식', $contentAuthorHelp['review']['id'], $contentAuthorHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <select id="content_author_add_review_required_override" name="review_required_override" class="form-select" required>
                                <option value="inherit">기본 설정 따름</option>
                                <option value="required">항상 검수</option>
                                <option value="exempt">검수 면제</option>
                            </select>
                            <small class="form-help">항상 검수와 검수 면제는 사이트·콘텐츠 그룹 설정보다 우선합니다.</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="content_author_add_note">메모</label>
                        <div class="form-field">
                            <textarea id="content_author_add_note" name="note" rows="3" class="form-input"></textarea>
                            <small class="form-help">운영자만 확인하는 내부 메모입니다.</small>
                        </div>
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
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($authorEditModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($authorReturnTo); ?>">
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) (int) $permission['account_id']); ?>">
                        <div class="form-row">
                            <span class="form-label">회원</span>
                            <div class="form-field"><p class="admin-form-static"><?php echo sr_e($authorLabel); ?></p></div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($authorEditModalId . '-status', '상태', $contentAuthorHelp['status']['id'], $contentAuthorHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <select id="<?php echo sr_e($authorEditModalId); ?>-status" name="status" class="form-select" required data-overlay-focus>
                                    <option value="allowed"<?php echo (string) $permission['status'] === 'allowed' ? ' selected' : ''; ?>>허용</option>
                                    <option value="blocked"<?php echo (string) $permission['status'] === 'blocked' ? ' selected' : ''; ?>>차단</option>
                                </select>
                                <small class="form-help">차단해도 회원 그룹을 통해 받은 제출 권한은 유지될 수 있습니다.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html($authorEditModalId . '-review', '검수 방식', $contentAuthorHelp['review']['id'], $contentAuthorHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <select id="<?php echo sr_e($authorEditModalId); ?>-review" name="review_required_override" class="form-select" required>
                                    <option value="inherit"<?php echo (string) $permission['review_required_override'] === 'inherit' ? ' selected' : ''; ?>>기본 설정 따름</option>
                                    <option value="required"<?php echo (string) $permission['review_required_override'] === 'required' ? ' selected' : ''; ?>>항상 검수</option>
                                    <option value="exempt"<?php echo (string) $permission['review_required_override'] === 'exempt' ? ' selected' : ''; ?>>검수 면제</option>
                                </select>
                                <small class="form-help">항상 검수와 검수 면제는 사이트·콘텐츠 그룹 설정보다 우선합니다.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="<?php echo sr_e($authorEditModalId); ?>-note">메모</label>
                            <div class="form-field">
                                <textarea id="<?php echo sr_e($authorEditModalId); ?>-note" name="note" rows="3" class="form-input"><?php echo sr_e((string) ($permission['note'] ?? '')); ?></textarea>
                                <small class="form-help">운영자만 확인하는 내부 메모입니다.</small>
                            </div>
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
    <?php foreach ($contentAuthorHelp as $contentAuthorHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $contentAuthorHelpModal['id'], (string) $contentAuthorHelpModal['title'], (string) $contentAuthorHelpModal['body']); ?>
    <?php } ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
