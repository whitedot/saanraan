<?php

$pageTitle = '내 콘텐츠';
$seo = ['title' => $pageTitle, 'robots' => 'noindex, nofollow'];
$formSubmission = array_merge(
    is_array($editingSubmission ?? null) ? $editingSubmission : [],
    is_array($contentSubmissionFormValues ?? null) ? $contentSubmissionFormValues : []
);
$contentLayoutSettings = sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.form',
    'output_slots' => [
        ['module_key' => 'content', 'point_key' => 'content.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
]));
?>
<main class="ui-page">
    <div class="content-screen-frame">
        <div class="content-screen-main">
    <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
    <?php echo sr_public_feedback_toasts('content', $notice, $errors); ?>

    <?php if ($allowedSubmissionGroups === []) { ?>
        <p>제출 가능한 콘텐츠 그룹이 없습니다.</p>
        <p><a class="btn btn-outline-primary" href="<?php echo sr_e(sr_url('/account/content/author-application')); ?>">콘텐츠 등록자 신청</a></p>
    <?php } else { ?>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">콘텐츠 작성</h2>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo sr_e(sr_url($contentSubmissionFormPath)); ?>" class="ui-card-body-stack">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="submission_id" value="<?php echo sr_e((string) (int) ($formSubmission['id'] ?? 0)); ?>">
                    <p><label class="ui-field" for="account_content_group"><span>콘텐츠 그룹</span>
                        <select id="account_content_group" name="content_group_id" class="form-select">
                            <?php foreach ($allowedSubmissionGroups as $group) { ?>
                                <option value="<?php echo sr_e((string) (int) $group['id']); ?>"<?php echo (int) ($formSubmission['content_group_id'] ?? 0) === (int) $group['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $group['title']); ?></option>
                            <?php } ?>
                        </select>
                    </label></p>
                    <p><label class="ui-field" for="account_content_title"><span>제목</span>
                        <input id="account_content_title" type="text" name="title" value="<?php echo sr_e((string) ($formSubmission['title'] ?? '')); ?>" maxlength="160" class="form-input">
                    </label></p>
                    <p><label class="ui-field" for="account_content_summary"><span>요약</span>
                        <textarea id="account_content_summary" name="summary" rows="3" class="form-textarea"><?php echo sr_e((string) ($formSubmission['summary'] ?? '')); ?></textarea>
                    </label></p>
                    <p><label class="ui-field" for="account_content_body"><span>본문</span>
                        <textarea id="account_content_body" name="body_text" rows="12" class="form-textarea"><?php echo sr_e((string) ($formSubmission['body_text'] ?? '')); ?></textarea>
                    </label></p>
                    <div class="ui-actions">
                        <button type="submit" name="intent" value="draft" class="btn btn-solid-light">임시저장</button>
                        <button type="submit" name="intent" value="submit" class="btn btn-solid-primary">제출</button>
                    </div>
                </form>
            </div>
        </section>
    <?php } ?>

    <section id="content-submission-history" class="card">
        <div class="card-header">
            <h2 class="card-title">제출 내역</h2>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>제목</th><th>그룹</th><th>상태</th><th>수정</th></tr></thead>
                <tbody>
                    <?php foreach ($memberSubmissions as $submission) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $submission['title']); ?></td>
                            <td><?php echo sr_e((string) ($submission['group_title'] ?? '')); ?></td>
                            <td><?php echo sr_e(sr_content_submission_status_label((string) $submission['review_status'])); ?></td>
                            <td><?php if (in_array((string) $submission['review_status'], ['approved', 'member_draft', 'revision_requested', 'rejected'], true)) { ?><a class="btn btn-sm btn-outline-default" href="<?php echo sr_e(sr_url('/account/content?id=' . (string) (int) $submission['id'] . ($contentSubmissionPage > 1 ? '&page=' . (string) $contentSubmissionPage : ''))); ?>">수정</a><?php } ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php echo sr_public_pagination_html($contentSubmissionPagination, $contentSubmissionPaginationBasePath, '콘텐츠 제출 내역 페이지', 'page', 'content-submission-history'); ?>
    </section>
        </div>
        <?php $contentSidebarSubject = []; ?>
        <?php include SR_ROOT . '/modules/content/theme/basic/sidebar.php'; ?>
    </div>
</main>
<?php sr_public_layout_end(); ?>
