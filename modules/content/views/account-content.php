<?php

$pageTitle = '내 콘텐츠';
$seo = ['title' => $pageTitle, 'robots' => 'noindex, nofollow'];
$formSubmission = is_array($editingSubmission ?? null) ? $editingSubmission : [];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, ['style_profile' => 'kit']);
?>
<main>
    <h1><?php echo sr_e($pageTitle); ?></h1>
    <?php foreach ($errors as $error) { ?><p><?php echo sr_e((string) $error); ?></p><?php } ?>
    <?php if ($notice !== '') { ?><p><?php echo sr_e($notice); ?></p><?php } ?>

    <?php if ($allowedSubmissionGroups === []) { ?>
        <p>제출 가능한 콘텐츠 그룹이 없습니다.</p>
        <p><a href="<?php echo sr_e(sr_url('/account/content/author-application')); ?>">콘텐츠 등록자 신청</a></p>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/account/content' . (!empty($formSubmission['id']) ? '?id=' . (string) (int) $formSubmission['id'] : ''))); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="submission_id" value="<?php echo sr_e((string) (int) ($formSubmission['id'] ?? 0)); ?>">
            <p>
                <label for="account_content_group">콘텐츠 그룹</label><br>
                <select id="account_content_group" name="content_group_id">
                    <?php foreach ($allowedSubmissionGroups as $group) { ?>
                        <option value="<?php echo sr_e((string) (int) $group['id']); ?>"<?php echo (int) ($formSubmission['content_group_id'] ?? 0) === (int) $group['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $group['title']); ?></option>
                    <?php } ?>
                </select>
            </p>
            <p>
                <label for="account_content_title">제목</label><br>
                <input id="account_content_title" type="text" name="title" value="<?php echo sr_e((string) ($formSubmission['title'] ?? '')); ?>" maxlength="160">
            </p>
            <p>
                <label for="account_content_summary">요약</label><br>
                <textarea id="account_content_summary" name="summary" rows="3"><?php echo sr_e((string) ($formSubmission['summary'] ?? '')); ?></textarea>
            </p>
            <p>
                <label for="account_content_body">본문</label><br>
                <textarea id="account_content_body" name="body_text" rows="12"><?php echo sr_e((string) ($formSubmission['body_text'] ?? '')); ?></textarea>
            </p>
            <p>
                <button type="submit" name="intent" value="draft">임시저장</button>
                <button type="submit" name="intent" value="submit">제출</button>
            </p>
        </form>
    <?php } ?>

    <h2>제출 내역</h2>
    <table>
        <thead><tr><th>제목</th><th>그룹</th><th>상태</th><th>수정</th></tr></thead>
        <tbody>
            <?php foreach ($memberSubmissions as $submission) { ?>
                <tr>
                    <td><?php echo sr_e((string) $submission['title']); ?></td>
                    <td><?php echo sr_e((string) ($submission['group_title'] ?? '')); ?></td>
                    <td><?php echo sr_e((string) $submission['review_status']); ?></td>
                    <td><?php if (in_array((string) $submission['review_status'], ['member_draft', 'revision_requested', 'rejected'], true)) { ?><a href="<?php echo sr_e(sr_url('/account/content?id=' . (string) (int) $submission['id'])); ?>">수정</a><?php } ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</main>
<?php sr_public_layout_end(); ?>
