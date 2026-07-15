<?php

$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
$page = isset($page) && is_array($page) ? $page : [];
$pageAccess = isset($pageAccess) && is_array($pageAccess) ? $pageAccess : ['allowed' => true];
$contentFiles = isset($contentFiles) && is_array($contentFiles) ? $contentFiles : [];
$contentImageFiles = isset($contentImageFiles) && is_array($contentImageFiles) ? $contentImageFiles : [];
$contentComments = isset($contentComments) && is_array($contentComments) ? $contentComments : [];
$contentAdminPreview = !empty($contentAdminPreview);
$seoTitle = (string) (($page['seo_title'] ?? '') ?: ($page['title'] ?? '콘텐츠'));
$seoDescription = (string) (($page['seo_description'] ?? '') ?: ($page['summary'] ?? ''));
$seo = [
    'title' => $seoTitle,
    'description' => $seoDescription,
    'canonical' => sr_content_path((string) ($page['slug'] ?? '')),
    'og' => [
        'title' => $seoTitle,
        'description' => $seoDescription,
        'type' => 'article',
    ],
];
$contentStylesheets = sr_enabled_module_asset_paths($pdo ?? null, [
    'banner' => '/modules/banner/assets/module.css',
    'popup_layer' => '/modules/popup_layer/assets/module.css',
    'reaction' => '/modules/reaction/assets/module.css',
]);
$contentStylesheets = array_merge($contentStylesheets, sr_content_body_embed_stylesheets($page, $contentLayoutSettings, $pdo ?? null));
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.view',
    'stylesheets' => $contentStylesheets,
    'output_slots' => [
        ['module_key' => 'content', 'point_key' => 'content.view', 'slot_key' => 'before_content'],
        ['module_key' => 'content', 'point_key' => 'content.view', 'slot_key' => 'after_content'],
    ],
]));
?>

<main class="example-content-theme example-content-theme-reader" data-example-theme-view="content.view">
    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => (string) ($page['id'] ?? ''),
    ]); ?>

    <article class="example-content-reader-grid">
        <aside class="example-content-reader-rail" aria-label="콘텐츠 정보">
            <p class="example-content-kicker">THEME VIEW</p>
            <?php if ((string) ($page['published_at'] ?? '') !== '') { ?>
                <?php echo sr_content_time_html((string) $page['published_at']); ?>
            <?php } ?>
            <?php if (!empty($page['view_count'])) { ?>
                <span><?php echo sr_e(number_format((int) $page['view_count'])); ?> views</span>
            <?php } ?>
        </aside>

        <section class="example-content-reader-main">
            <header class="example-content-reader-header">
                <p class="example-content-kicker">CONTENT DETAIL FROM MODULE THEME</p>
                <h1><?php echo sr_e((string) ($page['title'] ?? '')); ?></h1>
                <?php if ((string) ($page['summary'] ?? '') !== '') { ?>
                    <p><?php echo sr_e((string) $page['summary']); ?></p>
                <?php } ?>
            </header>

            <?php if (empty($pageAccess['allowed'])) { ?>
                <section class="example-content-panel">
                    <h2>열람 확인</h2>
                    <?php if ((string) ($pageAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                        <?php
                        $assetConfirmationAssetLabel = (string) ($pageAccess['asset_label'] ?? '');
                        $assetConfirmationAmount = (int) ($pageAccess['amount'] ?? 0);
                        $assetConfirmationMessage = (string) (($pageAccess['message'] ?? '') ?: (trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 콘텐츠를 열람하시겠습니까?'));
                        $assetConfirmationAction = sr_content_path((string) ($page['slug'] ?? ''));
                        $assetConfirmationId = 0;
                        $assetConfirmationContentId = 0;
                        $assetConfirmationRequestToken = (string) ($pageAccess['confirmation_request_token'] ?? '');
                        $assetConfirmationTitle = '콘텐츠 열람 확인';
                        $assetConfirmationSubmitLabel = sr_t('content::ui.text.ac5b575f');
                        $assetConfirmationCouponIssues = is_array($pageAccess['coupon_issues'] ?? null) ? $pageAccess['coupon_issues'] : [];
                        $assetConfirmationExchangeSuggestion = is_array($pageAccess['asset_exchange_suggestion'] ?? null) ? $pageAccess['asset_exchange_suggestion'] : [];
                        $assetConfirmationModalId = 'example_content_asset_access_confirmation_modal';
                        $assetConfirmationCloseOnSubmit = false;
                        include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';
                        ?>
                    <?php } else { ?>
                        <p><?php echo sr_e((string) ($pageAccess['message'] ?? sr_t('content::ui.content.7d2dd480'))); ?></p>
                    <?php } ?>
                </section>
            <?php } else { ?>
                <?php echo sr_public_feedback_toasts('content', (string) ($pageActionNotice ?? ''), is_array($pageActionErrors ?? null) ? $pageActionErrors : []); ?>
                <div class="example-content-body content-body">
                    <?php if ($contentImageFiles !== []) { ?>
                        <section class="content-image-thumbnails" aria-label="<?php echo sr_e('첨부 이미지'); ?>">
                            <?php foreach ($contentImageFiles as $contentImageFile) { ?>
                                <?php
                                $contentImageOriginalUrl = (string) ($contentImageFile['original_url'] ?? '');
                                $contentImageThumbnailUrl = (string) ($contentImageFile['thumbnail_url'] ?? $contentImageOriginalUrl);
                                if ($contentImageOriginalUrl === '' || $contentImageThumbnailUrl === '') {
                                    continue;
                                }
                                $contentImageName = (string) ($contentImageFile['original_name'] ?? '');
                                $contentImageSize = (int) ($contentImageFile['size_bytes'] ?? 0);
                                ?>
                                <figure class="content-image-thumbnail">
                                    <a class="content-image-thumbnail-link" href="<?php echo sr_e($contentImageOriginalUrl); ?>" data-content-image-layer-trigger>
                                        <img src="<?php echo sr_e($contentImageThumbnailUrl); ?>" alt="<?php echo sr_e($contentImageName); ?>" loading="lazy">
                                    </a>
                                    <figcaption class="content-image-thumbnail-caption">
                                        <a href="<?php echo sr_e(sr_content_file_public_url($contentImageFile, (int) ($page['id'] ?? 0))); ?>"><?php echo sr_e($contentImageName !== '' ? $contentImageName : '첨부 이미지'); ?></a>
                                        <?php if ($contentImageSize > 0) { ?>
                                            <span><?php echo sr_e(sr_content_format_bytes($contentImageSize)); ?></span>
                                        <?php } ?>
                                    </figcaption>
                                </figure>
                            <?php } ?>
                        </section>
                    <?php } ?>
                    <?php echo sr_content_body_html($page, $contentLayoutSettings, $pdo); ?>
                </div>

                <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$contentAdminPreview) { ?>
                    <?php echo sr_reaction_render_widget($pdo, 'content', 'content', (string) (int) ($page['id'] ?? 0), is_array($account ?? null) ? $account : null); ?>
                <?php } ?>

                <?php if ($contentFiles !== []) { ?>
                    <section class="example-content-panel">
                        <h2>Downloads</h2>
                        <ol>
                            <?php foreach ($contentFiles as $contentFile) { ?>
                                <li>
                                    <?php if (!$contentAdminPreview) { ?>
                                        <a href="<?php echo sr_e(sr_url('/content/download?id=' . rawurlencode((string) $contentFile['id']) . '&content_id=' . rawurlencode((string) (int) ($page['id'] ?? 0)))); ?>">
                                            <?php echo sr_e((string) $contentFile['title']); ?>
                                        </a>
                                    <?php } else { ?>
                                        <span><?php echo sr_e((string) $contentFile['title']); ?></span>
                                    <?php } ?>
                                    <small><?php echo sr_e((string) ($contentFile['original_name'] ?? '')); ?></small>
                                </li>
                            <?php } ?>
                        </ol>
                    </section>
                <?php } ?>

                <?php if (!$contentAdminPreview && sr_content_asset_action_required($page)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/content/action')); ?>" class="example-content-panel">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="content_id" value="<?php echo sr_e((string) ($page['id'] ?? '')); ?>">
                        <button type="submit" class="btn btn-solid-primary">
                            <?php echo sr_e((string) ($page['asset_action_label'] ?? sr_t('content::ui.text.727333ab'))); ?>
                        </button>
                    </form>
                <?php } ?>
            <?php } ?>
        </section>
    </article>

    <?php if (!empty($pageAccess['allowed'])) { ?>
        <section id="content-comments" class="example-content-panel example-content-comments" aria-label="댓글">
            <h2>Comments <?php echo sr_e(number_format((int) ($contentCommentPage['total'] ?? 0))); ?></h2>
            <?php echo sr_public_feedback_toasts('content', (string) ($contentCommentNotice ?? ''), is_array($contentCommentErrors ?? null) ? $contentCommentErrors : []); ?>
            <?php if ($contentComments === []) { ?>
                <p>등록된 댓글이 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($contentComments as $contentComment) { ?>
                        <?php $contentCommentCanViewBody = sr_content_account_can_view_comment_body($contentComment, $page, is_array($account ?? null) ? $account : null, $pdo); ?>
                        <li id="content-comment-<?php echo sr_e((string) (int) ($contentComment['id'] ?? 0)); ?>">
                            <strong><?php echo sr_e((string) ($contentComment['author_public_name'] ?? $contentComment['author_display_name'] ?? '회원')); ?></strong>
                            <?php if ($contentCommentCanViewBody) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) ($contentComment['body_text'] ?? '')); ?></p>
                                <?php echo sr_comment_extra_fields_display_html((string) ($contentComment['extra_values_json'] ?? '')); ?>
                            <?php } else { ?>
                                <p>비밀 댓글입니다.</p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
                <?php echo sr_public_pagination_html($contentCommentPage, sr_content_path((string) $page['slug']), '콘텐츠 댓글 페이지', 'comment_page', 'content-comments', 'content-comments-pagination'); ?>
            <?php } ?>

            <?php if (is_array($account ?? null) && !$contentAdminPreview) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/content/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="content_id" value="<?php echo sr_e((string) ($page['id'] ?? '')); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($contentCommentPage['page'] ?? 1)); ?>">
                    <label for="example_content_comment_body">댓글</label>
                    <textarea id="example_content_comment_body" name="body_text" rows="4" cols="60" data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo (int) ($contentCommentParentId ?? 0) < 1 ? sr_e((string) ($contentCommentBody ?? '')) : ''; ?></textarea>
                    <?php echo sr_comment_extra_fields_form_html($contentCommentExtraFieldDefinitions, (int) ($contentCommentParentId ?? 0) < 1 ? $contentCommentExtraFieldValues : [], 'comment_extra_fields', 'example_content_comment'); ?>
                    <button type="submit" class="btn btn-solid-light">댓글 등록</button>
                </form>
            <?php } ?>
        </section>
    <?php } ?>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'after_content',
        'subject_id' => (string) ($page['id'] ?? ''),
    ]); ?>
</main>

<?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php sr_public_layout_end(); ?>
