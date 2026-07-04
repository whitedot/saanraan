<?php

$pageTitle = '콘텐츠 등록자 신청';
$seo = ['title' => $pageTitle, 'robots' => 'noindex, nofollow'];
$applicationStatus = is_array($authorApplication ?? null) ? (string) ($authorApplication['status'] ?? '') : '';
$isApprovedAuthor = is_array($authorPermission ?? null) && (string) ($authorPermission['status'] ?? '') === 'allowed';
$isBlockedAuthor = is_array($authorPermission ?? null) && (string) ($authorPermission['status'] ?? '') === 'blocked';
$canApply = !empty($settings['member_submission_enabled']) && !$isApprovedAuthor && !$isBlockedAuthor;
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
<main class="ui-page">
    <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
    <?php foreach ($errors as $error) { ?><p><?php echo sr_e((string) $error); ?></p><?php } ?>
    <?php if ($notice !== '') { ?><p><?php echo sr_e($notice); ?></p><?php } ?>

    <?php if ($isApprovedAuthor) { ?>
        <p>이미 콘텐츠 등록자로 승인되어 있습니다.</p>
        <p><a href="<?php echo sr_e(sr_url('/account/content')); ?>">내 콘텐츠로 이동</a></p>
    <?php } elseif ($isBlockedAuthor) { ?>
        <p>콘텐츠 등록자 신청이 제한되어 있습니다.</p>
    <?php } elseif (!$canApply) { ?>
        <p>현재 콘텐츠 등록자 신청을 받지 않습니다.</p>
    <?php } else { ?>
        <?php if ($applicationStatus !== '') { ?>
            <p>현재 신청 상태: <?php echo sr_e(sr_content_author_application_status_label($applicationStatus)); ?></p>
            <?php if (!empty($authorApplication['review_note'])) { ?>
                <p>검토 메모: <?php echo sr_e((string) $authorApplication['review_note']); ?></p>
            <?php } ?>
        <?php } ?>
        <?php if (!empty($contentAuthorIdentityPolicy['required'])) { ?>
            <div class="alert <?php echo !empty($contentAuthorIdentityPolicy['satisfied']) ? 'alert-success' : 'alert-warning'; ?>">
                <p><?php echo !empty($contentAuthorIdentityPolicy['satisfied']) ? sr_e('콘텐츠 작성자 신청 본인확인이 완료되었습니다.') : sr_e('콘텐츠 작성자 신청 전 본인확인이 필요합니다.'); ?></p>
                <?php if (empty($contentAuthorIdentityPolicy['satisfied']) && !empty($contentAuthorIdentityPolicy['start_url'])) { ?>
                    <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $contentAuthorIdentityPolicy['start_url']); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if (!empty($contentAuthorAdultIdentityPolicy['required'])) { ?>
            <div class="alert <?php echo !empty($contentAuthorAdultIdentityPolicy['satisfied']) ? 'alert-success' : 'alert-warning'; ?>">
                <p><?php echo !empty($contentAuthorAdultIdentityPolicy['satisfied']) ? sr_e('콘텐츠 작성자 신청 성인 본인확인이 완료되었습니다.') : sr_e('콘텐츠 작성자 신청 전 성인 본인확인이 필요합니다.'); ?></p>
                <?php if (empty($contentAuthorAdultIdentityPolicy['satisfied']) && !empty($contentAuthorAdultIdentityPolicy['start_url'])) { ?>
                    <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $contentAuthorAdultIdentityPolicy['start_url']); ?>"><?php echo sr_e('성인 본인확인'); ?></a></p>
                <?php } ?>
            </div>
        <?php } ?>
        <section class="card">
            <div class="card-body">
                <form method="post" action="<?php echo sr_e(sr_url('/account/content/author-application')); ?>" class="ui-card-body-stack">
                    <?php echo sr_csrf_field(); ?>
                    <p>
                        <label for="content_author_application_note">신청 사유</label><br>
                        <textarea id="content_author_application_note" name="application_note" rows="8" maxlength="2000" class="form-textarea"><?php echo sr_e((string) ($authorApplication['application_note'] ?? '')); ?></textarea>
                    </p>
                    <p><button type="submit" class="btn btn-solid-primary">신청하기</button></p>
                </form>
            </div>
        </section>
    <?php } ?>
</main>
<?php sr_public_layout_end(); ?>
