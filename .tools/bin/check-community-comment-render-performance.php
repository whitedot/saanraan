#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/member/helpers/follows.php';

$adminPermissionSnapshotCalls = 0;
$boardPermissionSnapshotCalls = 0;

function sr_admin_is_owner(PDO $pdo, int $accountId): bool
{
    return false;
}

function sr_admin_current_permission_keys(PDO $pdo, int $accountId): array
{
    global $adminPermissionSnapshotCalls;
    $adminPermissionSnapshotCalls++;

    return ['/admin/community/comments|delete'];
}

function sr_community_account_board_management_permissions(PDO $pdo, int $boardId, int $accountId): array
{
    global $boardPermissionSnapshotCalls;
    $boardPermissionSnapshotCalls++;

    return ['view_manage' => true];
}

require_once $root . '/modules/community/helpers/posts-comments.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(
    "CREATE TABLE sr_member_follows (
        follower_account_id INTEGER NOT NULL,
        following_account_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        PRIMARY KEY (follower_account_id, following_account_id)
    )"
);
$pdo->exec(
    "INSERT INTO sr_member_follows (follower_account_id, following_account_id, status) VALUES
        (7, 11, 'active'),
        (7, 12, 'inactive'),
        (8, 13, 'active')"
);

$followStatuses = sr_member_follow_statuses($pdo, 7, [11, 12, 13, 7, 11, 0]);
$assert($followStatuses === [11 => 'active', 12 => 'inactive'], 'Follow status batch must return only the viewer targets in one normalized map.');

$permissionContext = sr_community_comment_permission_context($pdo, ['board_id' => 31], ['id' => 7]);
$assert($adminPermissionSnapshotCalls === 1, 'Comment permission context must load admin permissions once per context.');
$assert($boardPermissionSnapshotCalls === 1, 'Comment permission context must load board permissions once per context.');
$assert(!empty($permissionContext['can_manage_body']), 'Board manage permission must allow secret comment body access.');
$assert(!empty($permissionContext['can_hide']) && !empty($permissionContext['can_delete']), 'Admin comment delete permission must allow hide and delete actions.');

$comment = ['author_account_id' => 22, 'status' => 'published', 'is_secret' => 1];
$post = ['author_account_id' => 23, 'board_id' => 31];
$assert(sr_community_account_can_view_comment_body($comment, $post, ['id' => 7], $pdo, $permissionContext), 'Prepared permission context must authorize secret comment bodies without per-comment lookups.');
$assert(sr_community_account_can_hide_comment($pdo, $comment, $post, ['id' => 7], $permissionContext), 'Prepared permission context must authorize comment hide without per-comment lookups.');
$assert(sr_community_account_can_delete_comment($comment, ['id' => 7], $pdo, $post, $permissionContext), 'Prepared permission context must authorize comment delete without per-comment lookups.');
$assert($adminPermissionSnapshotCalls === 1 && $boardPermissionSnapshotCalls === 1, 'Per-comment permission decisions must reuse the prepared context.');

$viewAction = file_get_contents($root . '/modules/community/actions/view.php');
$commentAction = file_get_contents($root . '/modules/community/actions/comment.php');
$memberFollowHelper = file_get_contents($root . '/modules/member/helpers/follows.php');
$memberPublicIdentityHelper = file_get_contents($root . '/modules/member/helpers/public-identity.php');
$reactionHelper = file_get_contents($root . '/modules/reaction/helpers.php');
$assert(is_string($viewAction) && str_contains($viewAction, 'sr_member_public_identity_context('), 'Community post view must batch public identity context for comment authors.');
$assert(is_string($viewAction) && str_contains($viewAction, 'sr_community_comment_permission_context('), 'Community post view must prepare one comment permission context.');
$assert(is_string($commentAction) && str_contains($commentAction, "'_member_reply'"), 'Logged-in comment replies must verify the shared reply antispam form key.');
$assert(is_string($memberFollowHelper) && str_contains($memberFollowHelper, "array_key_exists('is_following', \$options)"), 'Member name menu must accept a prepared follow state.');
$assert(is_string($memberPublicIdentityHelper) && str_contains($memberPublicIdentityHelper, 'sr_member_follow_statuses('), 'Member public identity context must batch follow statuses.');
$assert(is_string($memberPublicIdentityHelper) && str_contains($memberPublicIdentityHelper, "\$menuOptions['is_following']"), 'Member public identity parts must pass prepared follow states to the member menu.');
$assert(is_string($reactionHelper) && str_contains($reactionHelper, 'function sr_reaction_record_summaries('), 'Reaction helper must expose batch target summaries.');
$assert(is_string($reactionHelper) && str_contains($reactionHelper, "array_key_exists('my_record', \$options)"), 'Reaction widget must accept a prepared viewer record.');

foreach ([
    'modules/community/theme/basic/post.php',
    'modules/community/skins/basic/view.php',
] as $viewFile) {
    $view = file_get_contents($root . '/' . $viewFile);
    $assert(is_string($view) && str_contains($view, 'sr_reaction_record_summaries('), $viewFile . ' must batch comment reaction summaries.');
    $assert(is_string($view) && str_contains($view, 'sr_member_public_identity_parts('), $viewFile . ' must render names through the prepared member public identity context.');
    $assert(is_string($view) && str_contains($view, "\$communityCommentPermissionContext ?? []"), $viewFile . ' must reuse the prepared comment permission context.');
    $assert(is_string($view) && substr_count($view, 'id="community_comment_edit_modal"') === 1, $viewFile . ' must render one shared member comment edit modal.');
    $assert(is_string($view) && substr_count($view, 'id="community_comment_reply_modal"') === 1, $viewFile . ' must render one shared member comment reply modal.');
    $assert(is_string($view) && substr_count($view, 'id="community_report_comment_modal"') === 1, $viewFile . ' must render one shared comment report modal.');
    $assert(is_string($view) && str_contains($view, 'data-community-comment-reply data-comment-id='), $viewFile . ' member reply buttons must pass the parent target to the shared modal.');
    $assert(is_string($view) && str_contains($view, 'data-community-comment-edit data-comment-id='), $viewFile . ' edit buttons must pass the target to the shared modal.');
    $assert(is_string($view) && str_contains($view, "sr_t('community::ui.text.cb0f2404')"), $viewFile . ' edit modal body field must use the concise content label.');
    $assert(is_string($view) && str_contains($view, 'data-community-comment-report data-comment-id='), $viewFile . ' report buttons must pass the target to the shared modal.');
    $assert(is_string($view) && substr_count($view, 'class="modal-dialog community-comment-editor-dialog"') === 3, $viewFile . ' reply and edit dialogs must use the bounded editor modal layout.');
    $assert(is_string($view) && substr_count($view, 'class="community-comment-editor-field"') === 3, $viewFile . ' reply and edit body controls must use the editor field layout.');
    $assert(is_string($view) && str_contains($view, 'class="community-comment-reply-source" tabindex="0" aria-label="답글 대상 댓글"'), $viewFile . ' long reply source text must expose a keyboard-scrollable preview.');
    $assert(is_string($view) && !str_contains($view, "\$communityCommentReportModalId"), $viewFile . ' must not generate a report modal id per comment.');
}
$communityModuleScript = file_get_contents($root . '/modules/community/assets/module.js');
$communityModuleStyles = file_get_contents($root . '/modules/community/theme/basic/assets/module.css');
$assert(is_string($communityModuleScript) && str_contains($communityModuleScript, 'function initCommentSharedModals()'), 'Community JavaScript must populate shared comment modals.');
$assert(is_string($communityModuleScript) && str_contains($communityModuleScript, "replyButton.getAttribute('data-comment-id')"), 'Shared reply modal must receive the selected parent comment id.');
$assert(is_string($communityModuleScript) && str_contains($communityModuleScript, "editButton.getAttribute('data-comment-body')"), 'Shared edit modal must restore the exact escaped comment body payload.');
$assert(is_string($communityModuleScript) && str_contains($communityModuleScript, 'communityPrepareCommentModalEditor(replyBody);'), 'Shared reply modal must refresh its editor after becoming visible.');
$assert(is_string($communityModuleScript) && str_contains($communityModuleScript, 'communityPrepareCommentModalEditor(editBody);'), 'Shared edit modal must refresh its editor after becoming visible.');
$assert(is_string($communityModuleStyles) && str_contains($communityModuleStyles, '.community-comment-editor-dialog > .modal-content'), 'Comment editor dialogs must bound their content to the viewport.');
$assert(is_string($communityModuleStyles) && str_contains($communityModuleStyles, '.community-comment-editor-dialog .sr-ckeditor .ck-editor__editable_inline'), 'Comment CKEditor instances must use a modal-sized editable area.');
$assert(is_string($communityModuleStyles) && str_contains($communityModuleStyles, 'max-height: min(18vh, 8rem);'), 'Long reply source previews must scroll within a bounded height.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "Community comment render performance check passed.\n";
