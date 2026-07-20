<?php

$pageTitle = '내 시리즈';
$seo = ['title' => $pageTitle, 'canonical' => '/community/series', 'robots' => 'noindex, nofollow'];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($settings ?? []));
?>
<main class="community-screen">
    <h1><?php echo sr_e($pageTitle); ?></h1>
    <?php echo sr_public_feedback_toasts('community', $notice, $errors); ?>
    <section class="card">
        <div class="card-header"><h2 class="card-title">시리즈 만들기</h2></div>
        <div class="card-body ui-card-body-stack">
        <?php if ($boards === []) { ?>
            <p>시리즈를 만들 수 있는 게시판이 없습니다.</p>
        <?php } else { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/community/series')); ?>" class="ui-card-body-stack">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="create">
                <p><label class="ui-field"><span>게시판 <span class="sr-required-label">(필수)</span></span><select name="board_id" class="form-select" required><?php foreach ($boards as $board) { ?><option value="<?php echo sr_e((string) $board['id']); ?>"><?php echo sr_e((string) $board['title']); ?></option><?php } ?></select></label></p>
                <p><label class="ui-field"><span>제목 <span class="sr-required-label">(필수)</span></span><input type="text" name="title" maxlength="160" class="form-input" required></label></p>
                <p><label class="ui-field"><span>공개 범위</span><select name="visibility" class="form-select"><option value="public">전체 공개</option><option value="member">회원 공개</option><option value="private">비공개</option></select></label></p>
                <p><label class="ui-field"><span>설명</span><textarea name="description" rows="3" cols="60" class="form-textarea"></textarea></label></p>
                <button type="submit" class="btn btn-solid-primary">저장</button>
            </form>
        <?php } ?>
        </div>
    </section>
    <section id="community-series-list" class="card">
        <div class="card-header"><h2 class="card-title">시리즈 목록</h2></div>
        <div class="card-body ui-card-body-stack">
        <?php if ($seriesList === []) { ?><p>아직 시리즈가 없습니다.</p><?php } else { ?>
            <ul>
                <?php foreach ($seriesList as $series) { ?>
                    <li><?php echo sr_e((string) $series['title']); ?> / <?php echo sr_e(sr_community_series_visibility_label((string) $series['visibility'])); ?> / <?php echo sr_e(sr_community_series_status_label((string) $series['status'])); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
        <?php echo sr_public_pagination_html($seriesPagination, '/community/series', '내 시리즈 목록 페이지', 'page', 'community-series-list', 'community-pagination'); ?>
        </div>
    </section>
</main>
<?php sr_public_layout_end(); ?>
