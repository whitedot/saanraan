<?php

$adminPageTitle = sr_t('seo::ui.seo.settings.604d83e6');
$seoHelpOpenLabel = '도움말 보기';
$seoHelp = [
    'sitemap' => [
        'id' => 'seo-admin-help-sitemap',
        'title' => '사이트맵 도움말',
        'body' => '<p>사이트맵은 검색엔진에 공개 화면의 주소를 알려주는 XML 파일입니다. 홈 주소 외의 항목은 활성화된 콘텐츠, 커뮤니티 등의 모듈이 공개 조건에 맞는 주소를 제공합니다.</p>'
            . '<p>홈 URL 포함을 끄면 사이트 첫 화면 주소만 제외하며 다른 공개 주소는 그대로 남습니다. 사이트가 회원 전용 모드이면 사이트맵은 비어 있는 상태로 제공됩니다.</p>',
    ],
    'robots' => [
        'id' => 'seo-admin-help-robots',
        'title' => '검색 로봇 차단 경로 도움말',
        'body' => '<p>검색 로봇에게 방문하지 말아 달라고 알릴 사이트 내부 경로를 한 줄에 하나씩 입력합니다. 각 경로는 <code>/</code>로 시작해야 합니다. 예: <code>/account</code></p>'
            . '<p>이 설정은 협조하는 검색 로봇의 수집만 제한하며 화면 접근을 차단하는 보안 기능이 아닙니다. 비공개 화면은 로그인과 권한 설정으로 보호하세요.</p>'
            . '<p>생성되는 robots.txt에는 위 차단 경로와 사이트맵 주소가 함께 표시됩니다. 사이트가 회원 전용 모드이면 입력값보다 우선해 모든 경로의 수집을 막습니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/seo')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2><?php echo sr_e(sr_t('seo::ui.text.0c082164')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('modules_seo_admin_settings_sitemap_include_home', sr_t('seo::ui.url.51ecf74b'), $seoHelp['sitemap']['id'], $seoHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_seo_admin_settings_sitemap_include_home', 'sitemap_include_home', '1', !empty($settings['sitemap_include_home']), '포함'); ?>
                    <p class="form-help">사이트 첫 화면 주소를 사이트맵에 포함합니다.</p>
                </div>
            </div>
        </div>
        <?php if ($sitemapUrl !== '') { ?>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e(sr_t('seo::ui.text.2a5fd734')); ?></span>
                <div class="form-field">
                    <div class="seo-sitemap-actions">
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/sitemap.xml')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('seo::ui.sitemap.xml.f88f383f')); ?></a>
                        <button type="button" class="btn btn-sm btn-solid-light" data-seo-copy-url="<?php echo sr_e($sitemapUrl); ?>" data-copy-label="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.1f43e9aa')); ?>" data-copy-success="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.success.9d96e6c2')); ?>" data-copy-fail="<?php echo sr_e(sr_t('seo::ui.sitemap.copy.fail.54a85d2b')); ?>"><?php echo sr_e(sr_t('seo::ui.sitemap.copy.1f43e9aa')); ?></button>
                    </div>
                    <small class="form-help seo-sitemap-url"><?php echo sr_e($sitemapUrl); ?></small>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('seo::ui.settings.7ce8a229')); ?></h2>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/robots.txt')); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('seo::ui.text.0d512314')); ?></a>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('seo_admin_settings_robots_disallow_paths', sr_t('seo::ui.text.553ea40a'), $seoHelp['robots']['id'], $seoHelpOpenLabel); ?>
            <div class="form-field">
                <textarea id="seo_admin_settings_robots_disallow_paths" name="robots_disallow_paths" rows="8" maxlength="2000" class="form-textarea"><?php echo sr_e((string) $settings['robots_disallow_paths']); ?></textarea>
                <p class="form-help"><code>/</code>로 시작하는 사이트 내부 경로를 한 줄에 하나씩 입력합니다. 아래에서 저장될 robots.txt 내용을 미리 볼 수 있습니다.</p>
                <pre class="seo-robots-preview"><?php echo sr_e($robotsPreview); ?></pre>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('seo::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php foreach ($seoHelp as $seoHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $seoHelpModal['id'], (string) $seoHelpModal['title'], (string) $seoHelpModal['body']); ?>
<?php } ?>

<script>
(function () {
    var copyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-seo-copy-url]'));
    var fallbackCopy = function (value) {
        var textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand('copy');
        } finally {
            document.body.removeChild(textarea);
        }
    };
    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-seo-copy-url') || '';
            var label = button.getAttribute('data-copy-label') || button.textContent;
            var success = button.getAttribute('data-copy-success') || label;
            var fail = button.getAttribute('data-copy-fail') || label;
            var setText = function (text) {
                button.textContent = text;
                window.setTimeout(function () {
                    button.textContent = label;
                }, 1600);
            };
            var done = function (ok) {
                setText(ok ? success : fail);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    done(true);
                }).catch(function () {
                    done(fallbackCopy(value));
                });
                return;
            }
            done(fallbackCopy(value));
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
