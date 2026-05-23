<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/seo/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/seo', 'view');

$errors = [];
$notice = '';
$settings = sr_seo_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/seo', 'edit');

    $settings = [
        'title_suffix' => sr_seo_clean_single_line(sr_post_string('title_suffix', 80), 80),
        'default_description' => sr_seo_clean_single_line(sr_post_string('default_description', 255), 255),
        'default_og_image' => sr_seo_clean_single_line(sr_post_string('default_og_image', 255), 255),
        'sitemap_include_home' => ($_POST['sitemap_include_home'] ?? '') === '1',
        'robots_disallow_paths' => sr_seo_clean_textarea(sr_post_string('robots_disallow_paths', 2000), 2000),
    ];

    if (
        $settings['default_og_image'] !== ''
        && !sr_is_http_url($settings['default_og_image'])
        && !sr_is_safe_relative_url($settings['default_og_image'])
    ) {
        $errors[] = '기본 OG image URL은 http/https URL 또는 /로 시작하는 내부 경로여야 합니다.';
    }

    foreach (explode("\n", $settings['robots_disallow_paths']) as $line) {
        $path = trim($line);
        if ($path !== '' && !sr_is_safe_relative_url($path)) {
            $errors[] = 'robots.txt 차단 경로는 /로 시작하는 내부 경로만 입력할 수 있습니다.';
            break;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'seo' LIMIT 1");
    $stmt->execute();
    $seoModule = $stmt->fetch();
    if (!is_array($seoModule)) {
        $errors[] = 'SEO 모듈이 등록되어 있지 않습니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_module_settings
                (module_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = VALUES(updated_at)'
        );

        $rows = [
            ['title_suffix', $settings['title_suffix'], 'string'],
            ['default_description', $settings['default_description'], 'string'],
            ['default_og_image', $settings['default_og_image'], 'string'],
            ['sitemap_include_home', $settings['sitemap_include_home'] ? '1' : '0', 'bool'],
            ['robots_disallow_paths', $settings['robots_disallow_paths'], 'string'],
        ];

        foreach ($rows as $row) {
            $stmt->execute([
                'module_id' => (int) $seoModule['id'],
                'setting_key' => $row[0],
                'setting_value' => $row[1],
                'value_type' => $row[2],
                'created_at' => sr_now(),
                'updated_at' => sr_now(),
            ]);
        }
        sr_clear_module_settings_cache('seo');

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'seo.settings.updated',
            'target_type' => 'module',
            'target_id' => 'seo',
            'result' => 'success',
            'message' => 'SEO settings updated.',
            'metadata' => [
                'sitemap_include_home' => $settings['sitemap_include_home'],
            ],
        ]);

        $notice = 'SEO 설정을 저장했습니다.';
    }
}

$robotsPreview = sr_seo_robots_txt($site, $settings);
$sitemapUrl = sr_seo_sitemap_absolute_url($site, '/sitemap.xml');

include SR_ROOT . '/modules/seo/views/admin-settings.php';
