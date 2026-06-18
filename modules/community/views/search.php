<?php

$searchKeyword = isset($keyword) && is_string($keyword) ? $keyword : '';
$searchPage = isset($page) ? max(1, (int) $page) : 1;
$searchKeywordTooShort = !empty($keywordTooShort);
$searchHasNextPage = !empty($hasNextPage);
$searchBasePath = '/community/search?q=' . rawurlencode($searchKeyword);
$seo = [
    'title' => $searchKeyword !== '' ? '커뮤니티 검색 - ' . $searchKeyword : '커뮤니티 검색',
    'canonical' => $searchKeyword !== '' ? $searchBasePath . ($searchPage > 1 ? '&page=' . (string) $searchPage : '') : '/community/search',
    'robots' => 'noindex, follow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'layout_key' => (string) ($communityLayoutKey ?? ''),
]));
?>
    <main class="community-screen community-search-screen">
        <p><a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e('커뮤니티'); ?></a></p>
        <h1><?php echo sr_e('커뮤니티 검색'); ?></h1>

        <form class="community-search-page-form" method="get" action="<?php echo sr_e(sr_url('/community/search')); ?>">
            <label for="community_search_page_q">
                <span><?php echo sr_e('검색어'); ?></span>
                <input id="community_search_page_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($searchKeyword); ?>" autocomplete="off">
            </label>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('검색'); ?></button>
        </form>

        <?php if ($searchKeyword === '') { ?>
            <p class="community-search-empty"><?php echo sr_e('검색어를 입력해 주세요.'); ?></p>
        <?php } elseif ($searchKeywordTooShort) { ?>
            <p class="community-search-empty"><?php echo sr_e('검색어는 2글자 이상 입력해 주세요.'); ?></p>
        <?php } elseif ($posts === []) { ?>
            <p class="community-search-summary"><?php echo sr_e('검색 결과가 없습니다.'); ?></p>
        <?php } else { ?>
            <p class="community-search-summary"><?php echo sr_e('검색 결과'); ?></p>
            <ol class="community-search-results">
                <?php foreach ($posts as $post) { ?>
                    <?php
                    $postUrl = '/community/post?id=' . rawurlencode((string) (int) ($post['id'] ?? 0));
                    $boardUrl = '/community/board?key=' . rawurlencode((string) ($post['board_key'] ?? ''));
                    $postExcerpt = (int) ($post['is_secret'] ?? 0) === 1 || empty($post['search_excerpt_allowed'])
                        ? ''
                        : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 180);
                    ?>
                    <li class="community-search-result">
                        <div class="community-search-result-meta">
                            <a href="<?php echo sr_e(sr_url($boardUrl)); ?>"><?php echo sr_e((string) ($post['board_title'] ?? '게시판')); ?></a>
                            <?php if ((string) ($post['category_title'] ?? '') !== '') { ?>
                                <span><?php echo sr_e((string) $post['category_title']); ?></span>
                            <?php } ?>
                            <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
                        </div>
                        <h2 class="community-post-title community-post-search-title">
                            <a href="<?php echo sr_e(sr_url($postUrl)); ?>"><?php echo sr_e((string) ($post['title'] ?? '')); ?></a>
                        </h2>
                        <?php if ($postExcerpt !== '') { ?>
                            <p><?php echo sr_e($postExcerpt); ?></p>
                        <?php } elseif ((int) ($post['is_secret'] ?? 0) === 1) { ?>
                            <p><?php echo sr_e('비밀글입니다.'); ?></p>
                        <?php } elseif (empty($post['search_excerpt_allowed'])) { ?>
                            <p><?php echo sr_e('열람 후 본문을 확인할 수 있습니다.'); ?></p>
                        <?php } ?>
                        <div class="community-search-result-stats">
                            <span><?php echo sr_e('댓글 ' . number_format((int) ($post['published_comment_count'] ?? 0))); ?></span>
                            <span><?php echo sr_e('조회 ' . number_format((int) ($post['view_count'] ?? 0))); ?></span>
                        </div>
                    </li>
                <?php } ?>
            </ol>
        <?php } ?>

        <?php if ($searchKeyword !== '' && !$searchKeywordTooShort && ($searchPage > 1 || $searchHasNextPage)) { ?>
            <nav class="community-search-pagination" aria-label="<?php echo sr_e('검색 결과 페이지'); ?>">
                <?php if ($searchPage > 1) { ?>
                    <a href="<?php echo sr_e(sr_url($searchBasePath . '&page=' . (string) ($searchPage - 1))); ?>"><?php echo sr_e('이전'); ?></a>
                <?php } ?>
                <span><?php echo sr_e((string) $searchPage); ?></span>
                <?php if ($searchHasNextPage) { ?>
                    <a href="<?php echo sr_e(sr_url($searchBasePath . '&page=' . (string) ($searchPage + 1))); ?>"><?php echo sr_e('다음'); ?></a>
                <?php } ?>
            </nav>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
