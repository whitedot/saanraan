<div class="ui-kit-sample-section" data-ui-kit-sample="ui-compositions">
    <p>같은 버튼과 글자 체계를 사용하되 화면의 목적에 맞게 정보 밀도와 구획을 선택합니다.</p>
    <div class="ui-kit-compositions">
        <article class="ui-kit-editorial-example">
            <span class="badge badge-soft-primary">읽기 중심</span>
            <h3>한 가지 이야기에 집중하는 화면</h3>
            <p>대표 콘텐츠는 여백과 제목의 위계로 강조합니다. 모든 문단을 카드에 담을 필요는 없습니다.</p>
            <a class="btn btn-text btn-ghost-primary" href="#ui-kit-typography">글자 체계 살펴보기</a>
        </article>
        <section aria-label="밀도 있는 목록 예시">
            <h3>목록 중심</h3>
            <ul class="ui-kit-list-example">
                <li><a href="#ui-kit-ui-badges">상태와 제목을 빠르게 훑기</a><span class="badge-status is-info">안내</span></li>
                <li><a href="#ui-kit-ui-buttons">행동은 필요한 위치에만 배치</a><span class="badge-status is-success">완료</span></li>
                <li><a href="#ui-kit-tables-static">여러 속성은 표로 비교</a><span class="badge-status is-warning">검토</span></li>
            </ul>
        </section>
        <section class="card">
            <div class="card-header"><h3 class="card-title">작업 중심</h3></div>
            <div class="card-body ui-field">
                <label class="form-label" for="kit-composition-title">제목 <span class="sr-required-label">(필수)</span></label>
                <input class="form-input" id="kit-composition-title" required aria-describedby="kit-composition-help" placeholder="제목을 입력하세요">
                <p class="ui-field-help" id="kit-composition-help">설명은 label 아래에 두고 입력과 연결합니다.</p>
                <button class="btn btn-solid-primary" type="button" disabled aria-busy="true">저장 중…</button>
            </div>
        </section>
    </div>
    <div class="ui-kit-grid ui-kit-card-grid ui-kit-gap-base ui-kit-space-before-base">
        <section class="ui-empty-state" aria-labelledby="kit-empty-title">
            <h3 id="kit-empty-title">검색 결과가 없습니다</h3>
            <p>조건을 줄이거나 다른 검색어를 사용해 보세요.</p>
            <a class="btn btn-outline-default" href="#ui-kit-form-elements">검색 조건 예시 보기</a>
        </section>
        <section class="ui-field" aria-label="입력 오류 예시">
            <label class="form-label" for="kit-composition-invalid">표시 이름 <span class="sr-required-label">(필수)</span></label>
            <input class="form-input form-input-invalid" id="kit-composition-invalid" required aria-invalid="true" aria-describedby="kit-composition-error">
            <p class="validation-error-note" id="kit-composition-error">표시 이름을 입력해 주세요.</p>
            <p class="ui-field-help">입력 오류는 필드 옆에, 저장 결과는 기존 alert를 이용한 토스트로 알립니다.</p>
        </section>
    </div>
    <div class="ui-kit-space-before-base">
        <h3>피드백과 이동</h3>
        <div class="alert alert-success" role="status">저장했습니다. 토스트 표면도 이 alert를 사용하며 위치는 소비 화면에서 정합니다.</div>
        <nav aria-label="페이지 이동 예시" class="ui-kit-cluster ui-kit-gap-2">
            <span class="btn btn-solid-primary" aria-current="page">1</span>
            <a class="btn btn-ghost-default" href="#ui-kit-ui-compositions" aria-label="2페이지 예시">2</a>
            <a class="btn btn-ghost-default" href="#ui-kit-ui-compositions" aria-label="다음 페이지 예시">다음</a>
        </nav>
        <p class="ui-field-help">정적 구성 예시입니다. 실제 페이지 URL과 권한은 소유 모듈에서 계산하고 기존 pagination helper에 전달합니다.</p>
    </div>
</div>
