<?php

$seo = [
    'title' => '설문 UI Kit',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => [
        '/assets/public-ui-kit.css',
        '/modules/survey/assets/public.css',
    ],
]);
?>
<main class="public-ui-kit survey-ui-kit">
    <section class="card public-ui-kit-summary">
        <div class="card-header"><h1 class="card-title">설문 UI Kit</h1></div>
        <div class="card-body">
            <p class="public-ui-kit-subtitle">설문 모듈의 목록, 안내, 동의, 문항, 완료 상태 기준입니다.</p>
            <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="설문 UI Kit 샘플">
                <a class="btn btn-sm btn-soft-default" href="#survey-kit-list">목록</a>
                <a class="btn btn-sm btn-soft-default" href="#survey-kit-form">응답</a>
                <a class="btn btn-sm btn-soft-default" href="#survey-kit-state">상태</a>
            </nav>
        </div>
    </section>
    <div class="ui-kit-sample-body public-ui-kit-samples">
        <section id="survey-kit-list" class="public-ui-kit-section ui-kit-space-before-base">
            <h2 class="public-ui-kit-section-title">목록</h2>
            <div class="card"><div class="card-body sr-public-main"><section class="sr-public-section"><div class="sr-public-container">
                <h1>설문</h1>
                <div class="sr-survey-list">
                    <article class="sr-survey-item"><h2><a href="#survey-kit-form">서비스 이용 경험 조사</a></h2><p>최근 이용 경험을 바탕으로 답변합니다.</p></article>
                </div>
            </div></section></div></div>
        </section>
        <section id="survey-kit-form" class="public-ui-kit-section ui-kit-space-before-base">
            <h2 class="public-ui-kit-section-title">응답</h2>
            <div class="card"><div class="card-body sr-public-main"><section class="sr-public-section"><div class="sr-public-container">
                <h1>서비스 이용 경험 조사</h1>
                <section class="sr-survey-info"><h2>참여 안내</h2><p>서비스 개선을 위한 익명 통계로 활용합니다.</p><p>예상 소요 시간: 3분</p></section>
                <form class="sr-survey-form">
                    <fieldset class="sr-survey-question">
                        <legend>참여 동의</legend>
                        <label class="sr-survey-choice"><input type="checkbox" checked><span>위 안내를 확인했고 설문 참여에 동의합니다.</span></label>
                    </fieldset>
                    <fieldset class="sr-survey-question">
                        <legend>1. 전반적인 만족도를 선택하세요.</legend>
                        <label class="sr-survey-choice"><input type="radio" name="survey_ui_choice" checked><span>만족</span></label>
                        <label class="sr-survey-choice"><input type="radio" name="survey_ui_choice"><span>보통</span></label>
                    </fieldset>
                    <fieldset class="sr-survey-question">
                        <legend>2. 추가 의견을 적어 주세요.</legend>
                        <textarea rows="4">화면 흐름이 이해하기 쉬웠습니다.</textarea>
                    </fieldset>
                    <button type="button" class="btn btn-solid-primary">제출</button>
                </form>
            </div></section></div></div>
        </section>
        <section id="survey-kit-state" class="public-ui-kit-section ui-kit-space-before-base">
            <h2 class="public-ui-kit-section-title">상태</h2>
            <div class="card"><div class="card-body">
                <div class="sr-survey-result"><h2>참여 완료</h2><p>설문 응답을 저장했습니다.</p><p>보상이 지급되었습니다.</p></div>
                <div class="sr-form-errors"><p>필수 문항에 답변해 주세요.</p></div>
            </div></div>
        </section>
    </div>
</main>
<?php sr_public_layout_end(); ?>
