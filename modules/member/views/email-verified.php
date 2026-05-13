<?php

$pageTitle = '이메일 인증 완료';
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p>이메일 인증을 완료했습니다.</p>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">내 계정</a></p>
    </main>
<?php sr_public_layout_end(); ?>
