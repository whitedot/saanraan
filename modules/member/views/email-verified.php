<?php

$pageTitle = '이메일 인증 완료';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
toy_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <p>이메일 인증을 완료했습니다.</p>
        <p><a href="<?php echo toy_e(toy_url('/account')); ?>">내 계정</a></p>
    </main>
<?php toy_public_layout_end(); ?>
