<?php

require_once SR_ROOT . '/modules/quiz/helpers.php';

$seo = [
    'title' => '퀴즈 UI Kit',
    'robots' => 'noindex, nofollow',
];

$quizLayoutSettings = isset($quizLayoutSettings) && is_array($quizLayoutSettings)
    ? $quizLayoutSettings
    : sr_quiz_settings($pdo);

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_quiz_public_layout_context($quizLayoutSettings, [
    'style_profile' => 'kit',
    'include_installed_layout_options' => true,
    'stylesheets' => [
        '/assets/public-ui-kit.css',
        '/modules/quiz/assets/ui-kit.css',
    ],
]));
?>

    <main class="public-ui-kit quiz-ui-kit">
        <section class="card public-ui-kit-summary">
            <div class="card-header">
                <h1 class="card-title">퀴즈 UI Kit</h1>
            </div>
            <div class="card-body">
                <p class="public-ui-kit-subtitle">퀴즈 모듈이 공개 화면에서 쓰는 목록, 응시, 결과 UI 기준입니다.</p>
                <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="퀴즈 UI Kit 샘플">
                    <a class="btn btn-sm btn-soft-default" href="#ui-kit-list">목록</a>
                    <a class="btn btn-sm btn-soft-default" href="#ui-kit-attempt">응시</a>
                    <a class="btn btn-sm btn-soft-default" href="#ui-kit-states">상태</a>
                    <a class="btn btn-sm btn-soft-default" href="#ui-kit-themes">테마</a>
                </nav>
            </div>
        </section>

        <div class="ui-kit-sample-body public-ui-kit-samples quiz-ui-kit-samples">
            <section id="ui-kit-list" class="public-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-list-title">
                <h2 id="ui-kit-list-title" class="public-ui-kit-section-title">목록</h2>
                <div class="card">
                    <div class="card-body quiz-ui-kit-frame sr-quiz-page">
                        <div class="sr-public-main">
                            <section class="sr-public-section">
                                <div class="sr-public-container">
                                    <h1>퀴즈</h1>
                                    <p>공개된 퀴즈를 빠르게 고르고 응시 화면으로 이동합니다.</p>
                                    <ul>
                                        <li><a href="#ui-kit-attempt">기초 이해도 점검</a></li>
                                        <li><a href="#ui-kit-attempt">회원 혜택 확인 퀴즈</a></li>
                                        <li><a href="#ui-kit-attempt">콘텐츠 읽기 완료 테스트</a></li>
                                    </ul>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>

            <section id="ui-kit-attempt" class="public-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-attempt-title">
                <h2 id="ui-kit-attempt-title" class="public-ui-kit-section-title">응시</h2>
                <div class="card">
                    <div class="card-body quiz-ui-kit-frame sr-quiz-page">
                        <div class="sr-public-main">
                            <section class="sr-public-section">
                                <div class="sr-public-container">
                                    <h1>기초 이해도 점검</h1>
                                    <p>안내문을 읽고 알맞은 답을 선택합니다.</p>
                                    <form class="sr-quiz-form">
                                        <fieldset class="sr-quiz-question">
                                            <legend>1. 퀴즈 응시 기록은 어디에 저장되나요?</legend>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_1">
                                                <input id="quiz_ui_choice_1" type="radio" name="quiz_ui_answer_1" checked>
                                                <span>퀴즈 모듈의 응시 기록</span>
                                            </label>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_2">
                                                <input id="quiz_ui_choice_2" type="radio" name="quiz_ui_answer_1">
                                                <span>공통 레이아웃 설정</span>
                                            </label>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_3">
                                                <input id="quiz_ui_choice_3" type="radio" name="quiz_ui_answer_1">
                                                <span>사이트 메뉴 슬롯</span>
                                            </label>
                                        </fieldset>
                                        <fieldset class="sr-quiz-question">
                                            <legend>2. 보상 지급 전에 확인할 조건을 모두 선택하세요.</legend>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_4">
                                                <input id="quiz_ui_choice_4" type="checkbox" checked>
                                                <span>응시 가능 기간</span>
                                            </label>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_5">
                                                <input id="quiz_ui_choice_5" type="checkbox" checked>
                                                <span>회원별 응시 제한</span>
                                            </label>
                                            <label class="sr-quiz-choice" for="quiz_ui_choice_6">
                                                <input id="quiz_ui_choice_6" type="checkbox">
                                                <span>푸터 저작권 문구</span>
                                            </label>
                                        </fieldset>
                                        <button type="button" class="btn btn-solid-primary">제출</button>
                                    </form>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>

            <section id="ui-kit-states" class="public-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-states-title">
                <h2 id="ui-kit-states-title" class="public-ui-kit-section-title">상태</h2>
                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">결과</h3>
                        </div>
                        <div class="card-body quiz-ui-kit-frame sr-quiz-page">
                            <div class="sr-quiz-result">
                                <h2>퀴즈 결과</h2>
                                <p>점수: 3</p>
                                <p>통과했습니다.</p>
                                <p>결과: 기본 흐름을 이해했습니다.</p>
                                <p>보상이 지급되었습니다.</p>
                                <p><a class="btn btn-solid-primary" href="#ui-kit-list">돌아가기</a></p>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">안내와 오류</h3>
                        </div>
                        <div class="card-body quiz-ui-kit-frame sr-quiz-page">
                            <div class="sr-quiz-preview-notice">
                                <p>관리자 미리보기입니다. 제출은 저장되지 않습니다.</p>
                            </div>
                            <div class="sr-form-errors">
                                <p>현재 응시할 수 없는 퀴즈입니다.</p>
                                <p>응시 제한에 따라 다시 제출할 수 없습니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="ui-kit-themes" class="public-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-themes-title">
                <h2 id="ui-kit-themes-title" class="public-ui-kit-section-title">테마</h2>
                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">카드형</h3>
                        </div>
                        <div class="card-body quiz-ui-kit-frame sr-quiz-page sr-quiz-theme-card">
                            <div class="sr-public-main">
                                <section class="sr-public-section">
                                    <div class="sr-public-container">
                                        <h1>카드형 퀴즈</h1>
                                        <p>본문을 흰 배경 카드 안에 모읍니다.</p>
                                        <div class="sr-quiz-result">
                                            <h2>진행 상태</h2>
                                            <p>문항과 결과 영역이 같은 밀도로 정리됩니다.</p>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">집중형</h3>
                        </div>
                        <div class="card-body quiz-ui-kit-frame sr-quiz-page sr-quiz-theme-focus">
                            <div class="sr-public-main">
                                <section class="sr-public-section">
                                    <div class="sr-public-container">
                                        <h1>집중형 퀴즈</h1>
                                        <p>주변 배경을 어둡게 낮추고 선택 영역을 강조합니다.</p>
                                        <fieldset class="sr-quiz-question">
                                            <legend>1. 집중형에서도 선택지는 읽기 쉬워야 합니다.</legend>
                                            <label class="sr-quiz-choice" for="quiz_ui_focus_choice_1">
                                                <input id="quiz_ui_focus_choice_1" type="radio" name="quiz_ui_focus_answer" checked>
                                                <span>확인했습니다.</span>
                                            </label>
                                        </fieldset>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

<?php sr_public_layout_end(); ?>
