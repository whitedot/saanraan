<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$searchKeyword = isset($keyword) && is_string($keyword) ? $keyword : '';
$searchPage = isset($page) ? max(1, (int) $page) : 1;
$searchBasePath = '/community/search?q=' . rawurlencode($searchKeyword);
$seo = [
    'title' => $searchKeyword !== '' ? '커뮤니티 검색 - ' . $searchKeyword : '커뮤니티 검색',
    'canonical' => $searchKeyword !== '' ? $searchBasePath . ($searchPage > 1 ? '&page=' . (string) $searchPage : '') : '/community/search',
    'robots' => 'noindex, follow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.search',
    'layout_key' => (string) ($communityLayoutKey ?? ''),
]));
?>

<main class="example-community-theme example-community-search" data-example-theme-view="community.search">
    <header class="example-community-hero">
        <p class="example-content-kicker">SEARCH VIEW FROM THEME</p>
        <h1>Community Search</h1>
    </header>

    <form class="example-community-searchbar" method="get" action="<?php echo sr_e(sr_url('/community/search')); ?>">
        <label for="example_community_search_q">검색어</label>
        <input id="example_community_search_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($searchKeyword); ?>" autocomplete="off">
        <button type="submit" class="btn btn-solid-primary">검색</button>
    </form>

    <section class="example-community-stream" aria-label="검색 결과">
        <?php if ($searchKeyword === '') { ?>
            <p class="example-community-panel">검색어를 입력해 주세요.</p>
        <?php } elseif (!empty($keywordTooShort)) { ?>
            <p class="example-community-panel">검색어는 2글자 이상 입력해 주세요.</p>
        <?php } elseif ($posts === []) { ?>
            <p class="example-community-panel">검색 결과가 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($posts as $post) { ?>
                <?php
                $postUrl = '/community/post?id=' . rawurlencode((string) (int) ($post['id'] ?? 0));
                $boardUrl = '/community/board?key=' . rawurlencode((string) ($post['board_key'] ?? ''));
                $postExcerpt = (int) ($post['is_secret'] ?? 0) === 1 || empty($post['search_excerpt_allowed'])
                    ? ''
                    : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), sr_community_post_body_format($pdo, $post, $settings), 180);
                ?>
                <article class="example-community-post-card">
                    <p class="example-content-kicker">
                        <a href="<?php echo sr_e(sr_url($boardUrl)); ?>"><?php echo sr_e((string) ($post['board_title'] ?? '게시판')); ?></a>
                    </p>
                    <h2>
                        <a href="<?php echo sr_e(sr_url($postUrl)); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a><?php echo sr_community_post_comment_count_html($post); ?>
                    </h2>
                    <?php if ($postExcerpt !== '') { ?>
                        <p><?php echo sr_e($postExcerpt); ?></p>
                    <?php } ?>
                    <footer><?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?></footer>
                </article>
            <?php } ?>
        <?php } ?>
    </section>

    <?php if ($searchKeyword !== '' && empty($keywordTooShort) && ($searchPage > 1 || !empty($hasNextPage))) { ?>
        <nav class="example-community-pagination" aria-label="검색 결과 페이지">
            <?php if ($searchPage > 1) { ?>
                <a href="<?php echo sr_e(sr_url($searchBasePath . '&page=' . (string) ($searchPage - 1))); ?>">이전</a>
            <?php } ?>
            <span><?php echo sr_e((string) $searchPage); ?></span>
            <?php if (!empty($hasNextPage)) { ?>
                <a href="<?php echo sr_e(sr_url($searchBasePath . '&page=' . (string) ($searchPage + 1))); ?>">다음</a>
            <?php } ?>
        </nav>
    <?php } ?>
</main>

<?php sr_public_layout_end(); ?>
