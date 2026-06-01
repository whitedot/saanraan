<?php

$pageTitle = '내 시리즈';
$seo = ['title' => $pageTitle, 'canonical' => '/community/series', 'robots' => 'noindex, nofollow'];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($settings ?? []));
?>
<main>
    <p><a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a></p>
    <h1><?php echo sr_e($pageTitle); ?></h1>
    <?php foreach ($errors as $error) { ?><p><?php echo sr_e($error); ?></p><?php } ?>
    <?php if ($notice !== '') { ?><p><?php echo sr_e($notice); ?></p><?php } ?>
    <section>
        <h2>시리즈 만들기</h2>
        <form method="post" action="<?php echo sr_e(sr_url('/community/series')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create">
            <p><label>게시판 <select name="board_id" required><?php foreach ($boards as $board) { ?><option value="<?php echo sr_e((string) $board['id']); ?>"><?php echo sr_e((string) $board['title']); ?></option><?php } ?></select></label></p>
            <p><label>제목 <input type="text" name="title" maxlength="160" required></label></p>
            <p><label>공개 범위 <select name="visibility"><option value="public">public</option><option value="member">member</option><option value="private">private</option></select></label></p>
            <p><label>설명 <textarea name="description" rows="3" cols="60"></textarea></label></p>
            <button type="submit">저장</button>
        </form>
    </section>
    <section>
        <h2>시리즈 목록</h2>
        <?php if ($seriesList === []) { ?><p>아직 시리즈가 없습니다.</p><?php } else { ?>
            <ul>
                <?php foreach ($seriesList as $series) { ?>
                    <li><?php echo sr_e((string) $series['title']); ?> / <?php echo sr_e(sr_community_series_visibility_label((string) $series['visibility'])); ?> / <?php echo sr_e(sr_community_series_status_label((string) $series['status'])); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    </section>
</main>
<?php sr_public_layout_end(); ?>
