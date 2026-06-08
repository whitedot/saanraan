<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/comments', 'view');

if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/comments', 'edit');
    sr_require_csrf();

    $commentIdValue = sr_post_string('comment_id', 20);
    $commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
    $status = sr_quiz_clean_key(sr_post_string('status', 20), 20);
    $comment = sr_quiz_comment_by_id($pdo, $commentId);
    if (!is_array($comment)) {
        sr_render_error(404, '댓글을 찾을 수 없습니다.');
    }
    if (!in_array($status, sr_quiz_comment_statuses(), true)) {
        sr_render_error(400, '변경할 수 없는 댓글 상태입니다.');
    }

    sr_quiz_update_comment_status($pdo, $commentId, $status);
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'quiz.comment.status_updated',
        'target_type' => 'quiz_comment',
        'target_id' => (string) $commentId,
        'result' => 'success',
        'message' => 'Quiz comment status updated.',
        'metadata' => [
            'quiz_id' => (int) ($comment['quiz_id'] ?? 0),
            'before_status' => (string) ($comment['status'] ?? ''),
            'after_status' => $status,
        ],
    ]);

    $_SESSION['sr_quiz_admin_comment_notice'] = '댓글 상태를 변경했습니다.';
    sr_redirect('/admin/quiz/comments?' . http_build_query(sr_quiz_admin_comment_filters_from_request()));
}

$commentFilters = sr_quiz_admin_comment_filters_from_request();
$comments = sr_quiz_admin_comments($pdo, $commentFilters, 200);
$commentStatusOptions = [];
foreach (sr_quiz_comment_statuses() as $status) {
    $commentStatusOptions[$status] = sr_quiz_comment_status_label($status);
}
$commentNotice = (string) ($_SESSION['sr_quiz_admin_comment_notice'] ?? '');
unset($_SESSION['sr_quiz_admin_comment_notice']);
$commentDetailFilterOpen = (string) ($commentFilters['status'] ?? '') !== '' || (string) ($commentFilters['secret'] ?? '') !== '';
$canEditComments = sr_admin_has_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/comments', 'edit');

$adminPageTitle = '퀴즈 댓글 관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($commentNotice !== '') { ?>
    <div class="alert alert-success" role="alert">
        <p><?php echo sr_e($commentNotice); ?></p>
    </div>
<?php } ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/quiz/comments')); ?>" class="filtering-form admin-quiz-comment-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $commentDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields">
            <div class="filtering-field filtering-field-fill admin-quiz-comment-filter-keyword">
                <label for="quiz_comment_keyword_filter" class="filtering-label">검색어</label>
                <input id="quiz_comment_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($commentFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="퀴즈 키, 제목, 작성자, 댓글 본문">
            </div>
        </div>
        <div id="quiz_comment_detail_filters" class="filtering-body" data-filtering-body<?php echo $commentDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('quiz_comment_status_filter', 'status', $commentStatusOptions, [(string) ($commentFilters['status'] ?? '')], '전체'); ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">비밀 댓글</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('quiz_comment_secret_filter', 'secret', ['yes' => '예', 'no' => '아니오'], [(string) ($commentFilters['secret'] ?? '')], '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $commentDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="quiz_comment_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">댓글 목록</h2>
    </div>
    <div class="admin-list-summary-row">
        <p class="admin-summary-meta">최대 200건을 최신순으로 표시합니다.</p>
    </div>
    <div class="table-wrapper">
        <table class="table admin-quiz-comment-table">
            <thead class="ui-table-head">
                <tr>
                    <th>작성일</th>
                    <th>퀴즈</th>
                    <th>작성자</th>
                    <th>댓글</th>
                    <th>상태</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($comments === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">조건에 맞는 댓글이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($comments as $comment) { ?>
                    <?php $currentStatus = (string) ($comment['status'] ?? 'published'); ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) ($comment['created_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($comment['quiz_title'] ?? '')); ?></strong><br>
                            <a href="<?php echo sr_e(sr_url('/quiz/' . rawurlencode((string) ($comment['quiz_key'] ?? '')) . '#quiz-comments')); ?>" target="_blank" rel="noopener"><code><?php echo sr_e((string) ($comment['quiz_key'] ?? '')); ?></code></a>
                        </td>
                        <td class="admin-table-break">
                            <?php echo sr_e((string) ($comment['author_public_name_snapshot'] ?? '회원')); ?><br>
                            <span class="admin-summary-meta">회원 ID <?php echo sr_e((string) (int) ($comment['author_account_id'] ?? 0)); ?></span>
                        </td>
                        <td class="admin-table-break">
                            <?php echo sr_member_mention_plain_text_html((string) ($comment['body_text'] ?? '')); ?>
                            <?php if ((int) ($comment['is_secret'] ?? 0) === 1) { ?>
                                <br><span class="admin-summary-meta">비밀 댓글</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_quiz_admin_status_class($currentStatus)); ?>"><?php echo sr_e(sr_quiz_comment_status_label($currentStatus)); ?></span></td>
                        <td class="admin-table-actions-cell text-end">
                            <?php if ($canEditComments) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz/comments?' . http_build_query($commentFilters))); ?>" class="admin-row-actions">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) (int) ($comment['id'] ?? 0)); ?>">
                                    <select name="status" class="form-select">
                                        <?php foreach ($commentStatusOptions as $status => $label) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-solid-primary">변경</button>
                                </form>
                            <?php } else { ?>
                                <span class="admin-summary-meta">권한 없음</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
