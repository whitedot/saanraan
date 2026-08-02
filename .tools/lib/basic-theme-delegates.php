<?php

declare(strict_types=1);

function sr_check_basic_theme_delegates(): array
{
    return [
        'modules/content/theme/basic/content.php' => [
            'view' => 'modules/content/views/content.php',
            'marker' => "include SR_ROOT . '/modules/content/views/content.php';",
        ],
        'modules/content/theme/basic/group.php' => [
            'view' => 'modules/content/views/group.php',
            'marker' => "include SR_ROOT . '/modules/content/views/group.php';",
        ],
        'modules/content/theme/basic/home.php' => [
            'view' => 'modules/content/views/home.php',
            'marker' => "include SR_ROOT . '/modules/content/views/home.php';",
        ],
        'modules/community/theme/basic/form.php' => [
            'view' => 'modules/community/skins/basic/form.php',
            'marker' => 'include $communityThemeFallbackViewFile;',
        ],
        'modules/community/theme/basic/list.php' => [
            'view' => 'modules/community/skins/basic/list.php',
            'marker' => 'include $communityThemeFallbackViewFile;',
        ],
        'modules/community/theme/basic/post.php' => [
            'view' => 'modules/community/skins/basic/view.php',
            'marker' => 'include $communityThemeFallbackViewFile;',
        ],
        'modules/community/theme/basic/search.php' => [
            'view' => 'modules/community/views/search.php',
            'marker' => 'include $communityThemeFallbackViewFile;',
        ],
        'modules/quiz/theme/basic/home.php' => [
            'view' => 'modules/quiz/skins/basic/home.php',
            'marker' => "include sr_quiz_skin_view_file(\$quizSettings, 'home');",
        ],
        'modules/quiz/theme/basic/list.php' => [
            'view' => 'modules/quiz/skins/basic/list.php',
            'marker' => "include sr_quiz_skin_view_file(\$quizSettings, 'list');",
        ],
        'modules/quiz/theme/basic/result.php' => [
            'view' => 'modules/quiz/skins/basic/result.php',
            'marker' => "include sr_quiz_skin_view_file(\$quizSettings, 'result');",
        ],
        'modules/quiz/theme/basic/view.php' => [
            'view' => 'modules/quiz/skins/basic/view.php',
            'marker' => "include sr_quiz_skin_view_file(\$quizSettings, 'view');",
        ],
        'modules/survey/theme/basic/complete.php' => [
            'view' => 'modules/survey/skins/basic/complete.php',
            'marker' => "include sr_survey_skin_view_file(\$settings, 'complete');",
        ],
        'modules/survey/theme/basic/home.php' => [
            'view' => 'modules/survey/skins/basic/home.php',
            'marker' => "include sr_survey_skin_view_file(\$settings, 'home');",
        ],
        'modules/survey/theme/basic/list.php' => [
            'view' => 'modules/survey/skins/basic/list.php',
            'marker' => "include sr_survey_skin_view_file(\$settings, 'list');",
        ],
        'modules/survey/theme/basic/view.php' => [
            'view' => 'modules/survey/skins/basic/view.php',
            'marker' => "include sr_survey_skin_view_file(\$settings, 'view');",
        ],
    ];
}

function sr_check_source_with_basic_theme_delegate(string $root, string $file): string|false
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
    $normalizedFile = str_replace('\\', '/', $file);
    $relativeFile = str_starts_with($normalizedFile, $normalizedRoot . '/')
        ? substr($normalizedFile, strlen($normalizedRoot) + 1)
        : ltrim($normalizedFile, '/');
    $contents = file_get_contents($normalizedRoot . '/' . $relativeFile);
    if (!is_string($contents)) {
        return false;
    }

    $delegate = sr_check_basic_theme_delegates()[$relativeFile] ?? null;
    if (!is_array($delegate)) {
        return $contents;
    }

    $delegatedContents = file_get_contents($normalizedRoot . '/' . (string) ($delegate['view'] ?? ''));
    return is_string($delegatedContents) ? $contents . "\n" . $delegatedContents : false;
}
