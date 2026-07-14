<?php

$searchKeyword = isset($keyword) && is_string($keyword) ? $keyword : '';
$searchPage = isset($page) ? max(1, (int) $page) : 1;
$searchKeywordTooShort = !empty($keywordTooShort);
$searchHasNextPage = !empty($hasNextPage);
$searchBasePath = '/content/search?q=' . rawurlencode($searchKeyword);
$seo = [
    'title' => $searchKeyword !== '' ? '콘텐츠 검색 - ' . $searchKeyword : '콘텐츠 검색',
    'canonical' => $searchKeyword !== '' ? $searchBasePath . ($searchPage > 1 ? '&page=' . (string) $searchPage : '') : '/content/search',
    'robots' => 'noindex, follow',
];
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'consumer_target' => 'content.search',
    'layout_key' => (string) ($contentSearchLayoutKey ?? ''),
]));
?>
<main class="content-search-screen">
    <h1><?php echo sr_e('콘텐츠 검색'); ?></h1>

    <form class="content-search-page-form" method="get" action="<?php echo sr_e(sr_url('/content/search')); ?>">
        <label for="content_search_page_q">
            <span><?php echo sr_e('검색어'); ?></span>
            <input id="content_search_page_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($searchKeyword); ?>" autocomplete="off">
        </label>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('검색'); ?></button>
    </form>

    <?php if ($searchKeyword === '') { ?>
        <p class="content-search-empty"><?php echo sr_e('검색어를 입력해 주세요.'); ?></p>
    <?php } elseif ($searchKeywordTooShort) { ?>
        <p class="content-search-empty"><?php echo sr_e('검색어는 2글자 이상 입력해 주세요.'); ?></p>
    <?php } elseif ($items === []) { ?>
        <p class="content-search-summary"><?php echo sr_e('검색 결과가 없습니다.'); ?></p>
    <?php } else { ?>
        <p class="content-search-summary"><?php echo sr_e('검색 결과'); ?></p>
        <ol class="content-search-results">
            <?php foreach ($items as $item) { ?>
                <?php
                $itemSlug = (string) ($item['slug'] ?? '');
                $itemUrl = sr_content_path($itemSlug);
                $groupKey = (string) ($item['content_group_key'] ?? '');
                $summary = trim(preg_replace('/\s+/u', ' ', (string) ($item['summary'] ?? '')) ?? '');
                $excerpt = $summary !== ''
                    ? sr_content_body_excerpt($summary, 'plain', 180)
                    : (!empty($item['search_body_excerpt_allowed'])
                        ? sr_content_body_excerpt((string) ($item['body_text'] ?? ''), sr_content_effective_body_format($pdo, $item), 180)
                        : '');
                ?>
                <li class="content-search-result">
                    <div class="content-search-result-meta">
                        <?php if ($groupKey !== '') { ?>
                            <a href="<?php echo sr_e(sr_url(sr_content_group_path($groupKey))); ?>"><?php echo sr_e((string) ($item['content_group_title'] ?? '콘텐츠')); ?></a>
                        <?php } else { ?>
                            <span><?php echo sr_e('콘텐츠'); ?></span>
                        <?php } ?>
                        <?php echo sr_content_time_html((string) (($item['published_at'] ?? '') ?: ($item['updated_at'] ?? ''))); ?>
                    </div>
                    <h2><a href="<?php echo sr_e(sr_url($itemUrl)); ?>"><?php echo sr_e((string) ($item['title'] ?? $itemSlug)); ?></a></h2>
                    <?php if ($excerpt !== '') { ?>
                        <p><?php echo sr_e($excerpt); ?></p>
                    <?php } elseif (empty($item['search_body_excerpt_allowed'])) { ?>
                        <p><?php echo sr_e('열람 후 본문을 확인할 수 있습니다.'); ?></p>
                    <?php } ?>
                    <div class="content-search-result-stats">
                        <span><?php echo sr_e('조회 ' . number_format((int) ($item['view_count'] ?? 0))); ?></span>
                    </div>
                </li>
            <?php } ?>
        </ol>
    <?php } ?>

    <?php if ($searchKeyword !== '' && !$searchKeywordTooShort && ($searchPage > 1 || $searchHasNextPage)) { ?>
        <nav class="content-search-pagination" aria-label="<?php echo sr_e('검색 결과 페이지'); ?>">
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
