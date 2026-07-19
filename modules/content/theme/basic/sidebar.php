<?php
$contentSidebarContext = isset($contentSidebarContext) && is_array($contentSidebarContext)
    ? $contentSidebarContext
    : sr_content_sidebar_context($pdo, $contentLayoutSettings ?? sr_content_settings($pdo), $contentSidebarSubject ?? []);
if (!empty($contentSidebarContext['enabled'])) {
    $contentSidebarMenu = is_array($contentSidebarContext['menu'] ?? null) ? $contentSidebarContext['menu'] : [];
    $contentSidebarPopular = is_array($contentSidebarContext['popular'] ?? null) ? $contentSidebarContext['popular'] : [];
    $contentSidebarComments = is_array($contentSidebarContext['comments'] ?? null) ? $contentSidebarContext['comments'] : [];
    ?>
    <aside class="content-sidebar" aria-label="콘텐츠 사이드">
        <?php if ((string) ($contentSidebarMenu['html'] ?? '') !== '') { ?>
            <section class="card content-sidebar-section">
                <div class="card-header"><h2 class="card-title"><?php echo sr_e((string) ($contentSidebarMenu['title'] ?? '메뉴')); ?></h2></div>
                <div class="card-body"><?php echo (string) $contentSidebarMenu['html']; ?></div>
            </section>
        <?php } ?>
        <?php if ($contentSidebarPopular !== []) { ?>
            <section class="card content-sidebar-section">
                <div class="card-header"><h2 class="card-title">인기 콘텐츠</h2></div>
                <div class="card-body"><ol class="content-sidebar-list">
                    <?php foreach ($contentSidebarPopular as $popularContent) { ?>
                        <?php $contentSidebarPopularDate = (string) (($popularContent['published_at'] ?? '') ?: ($popularContent['updated_at'] ?? '')); ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url(sr_content_path((string) ($popularContent['slug'] ?? '')))); ?>"><?php echo sr_e((string) ($popularContent['title'] ?? '')); ?></a>
                            <span>
                                조회 <?php echo sr_e(number_format((int) ($popularContent['view_count'] ?? 0))); ?>
                                <?php if ($contentSidebarPopularDate !== '') { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_content_time_html($contentSidebarPopularDate); ?>
                                <?php } ?>
                            </span>
                        </li>
                    <?php } ?>
                </ol></div>
            </section>
        <?php } ?>
        <?php if ($contentSidebarComments !== []) { ?>
            <section class="card content-sidebar-section">
                <div class="card-header"><h2 class="card-title">최신댓글</h2></div>
                <div class="card-body"><ul class="content-sidebar-list content-sidebar-comment-list">
                    <?php foreach ($contentSidebarComments as $sidebarComment) { ?>
                        <?php $contentSidebarCommentBaseUrl = sr_url(sr_content_path((string) ($sidebarComment['content_slug'] ?? ''))); ?>
                        <li>
                            <a href="<?php echo sr_e($contentSidebarCommentBaseUrl . '#content-comment-' . (string) (int) ($sidebarComment['id'] ?? 0)); ?>"><?php echo sr_e((string) ($sidebarComment['excerpt'] ?? '')); ?></a>
                            <span class="content-sidebar-comment-meta">
                                <span class="content-sidebar-comment-byline">
                                    <span><?php echo sr_e((string) ($sidebarComment['author_public_name'] ?? '회원')); ?></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_content_time_html((string) ($sidebarComment['created_at'] ?? '')); ?>
                                </span>
                                <?php if ((string) ($sidebarComment['content_title'] ?? '') !== '') { ?>
                                    <span class="content-sidebar-comment-separator" aria-hidden="true">&middot;</span>
                                    <a class="content-sidebar-comment-content" href="<?php echo sr_e($contentSidebarCommentBaseUrl); ?>"><?php echo sr_e((string) $sidebarComment['content_title']); ?></a>
                                <?php } ?>
                            </span>
                        </li>
                    <?php } ?>
                </ul></div>
            </section>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'content',
            'point_key' => 'content.sidebar.summary',
            'slot_key' => 'after_summary',
            'subject_id' => (string) (int) (($contentSidebarSubject['id'] ?? 0)),
        ]); ?>
    </aside>
    <?php
}
?>
