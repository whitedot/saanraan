# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 공통 UI 톤을 기준으로 맞춘다. `assets/common.css`는 버튼, 카드, 테이블, 폼 컨트롤 같은 디자인 시스템과 ui-kit 역할을 맡고, `modules/admin/assets/admin.css`는 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 관리자 전용 레이아웃만 정의한다.

관리자 디자인 책임은 admin 모듈에 둔다. 각 모듈의 관리자 view는 본문 마크업과 도메인 출력만 맡고, 관리자 shell, 사이드바, 상단바, 공통 관리자 asset, 관리자 콘텐츠 컨테이너는 admin skin이 맡는다. 현재 관리자 skin은 `admin_skin_key`로 선택하며, 등록된 key가 없거나 파일이 없으면 `basic`으로 fallback한다.

관리자 shell은 화면을 편하게 그리기 위한 목적으로 view 출력 뒤 `DOMDocument`로 HTML을 다시 해석하거나 class를 주입하지 않는다. 폼 행, 카드 헤더, 테이블 wrapper, 체크박스 보조 텍스트처럼 의미가 있는 구조는 view가 최종 마크업으로 직접 출력해야 한다.

렌더 후 DOM 처리가 필요한 경우는 별도 예외로 다룬다. 예를 들어 보안 정화, 외부 HTML의 제한적 변환, 편집기 콘텐츠 처리처럼 입력 자체가 HTML이고 변환 목적이 명확한 경우에는 호출 위치와 책임 모듈을 드러내고, 허용 범위와 테스트를 함께 둔다. 단순히 관리자 화면의 반복 마크업을 줄이거나 class를 자동 보정하기 위한 후처리는 사용하지 않는다.

CSS class는 범위를 드러내는 접두어를 사용한다.

- 반복 가능한 공통 UI는 `ui-*`, `btn`, `card`, `table`처럼 `assets/common.css`에 둔다.
- 관리자 shell과 관리자 전용 배치는 `admin-*` 접두어를 사용하고 `modules/admin/assets/admin.css`에 둔다.
- 모듈별 관리자 본문에서 도메인 고유 스타일이 필요하면 `{module_key}-admin-*` 또는 `sr-{module_key}-admin-*` 형식을 사용한다.
- 관리자 view는 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않는다.
- 탭처럼 공통 CSS에 이미 정의된 반복 UI는 `tab-nav-*`, `tab-trigger-*` 같은 기존 시맨틱 클래스를 먼저 사용한다. 토스트는 기존 관리자 메시지 클래스인 `admin-flash-message-*`에 `data-admin-toast` 동작 속성만 더해 사용하고, 위치와 닫기 버튼 배치는 `data-admin-toast-*` 속성 선택자로 처리한다.
- 공통 UI를 변경하거나 새 관리자 화면에서 UI 조합을 확인할 때는 `/admin/design-system` 미리보기 페이지에서 `assets/common.css` 기본 컴포넌트와 관리자 보조 패턴을 먼저 확인한다.

## 사이드바

사이드바는 모듈의 `admin.category_label`을 라벨로만 표시하고, 실제 조작 가능한 메뉴는 `모듈 그룹 > 메뉴 항목`의 2단계 구조로 유지한다. 라벨은 시스템 자산처럼 메뉴를 구분하는 시각적 구획일 뿐이며 접기/펼치기 버튼이나 링크로 사용하지 않는다.

## 페이지 타이틀

관리자 페이지의 주 타이틀은 admin skin이 출력하는 `#container_title` 하나를 기준으로 스타일링한다. 각 모듈 view는 `$adminPageTitle` 값만 지정하고, 페이지 최상단 `h1`을 별도로 반복하지 않는다.

## 폼 화면

등록, 수정, 설정처럼 화면의 주 작업이 폼인 페이지는 실제 view 마크업에서 다음 구조를 사용한다.

```php
<form method="post" action="..." class="admin-form-layout ui-form-theme ui-form-showcase">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2>기본 정보</h2>
        <p>
            <label>이름<br>
                <input type="text" name="title" required>
            </label>
        </p>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="..." class="btn btn-surface-default-soft">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
```

- 폼의 주 섹션은 `form > section.card`로 둔다.
- 섹션 제목은 `h2`를 사용한다. 카드 헤더가 별도로 필요하면 view에서 `card-header`, `card-title` 마크업을 직접 출력한다.
- 체크박스와 라디오처럼 컨트롤 옆 문구가 실제 레이블인 항목은 좌측 설명 칸과 우측 조작 레이블을 view에 직접 둔다. 좌측에는 전체 문구를 그대로 표시하고, 우측의 시각적 레이블은 `허용`, `사용`, `확인했습니다.`처럼 필요한 부분만 보이게 줄인다. 우측에서 생략된 맥락은 `.sr-only` 텍스트로 남겨 보조기기가 전체 의미를 읽을 수 있게 한다.
- 좌측 레이블이 필요 없는 안내 문장은 입력항목 행으로 만들지 말고 일반 텍스트 문단으로 둔다.
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 둔다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

체크박스 행 예시는 다음처럼 작성한다.

```php
<div class="af-grid">
    <div class="af-row">
        <div class="af-label"><span class="form-label">공개 회원가입 허용</span></div>
        <div class="af-field">
            <label class="af-check form-label">
                <input type="checkbox" name="allow_registration" value="1" class="form-checkbox">
                <?php echo sr_admin_choice_label_html('공개 회원가입 허용'); ?>
            </label>
        </div>
    </div>
</div>
```

회원 설정의 선택 프로필 항목처럼 한 항목에 여러 불리언 옵션이 붙는 경우에도 같은 `af-row` 안에 체크박스를 나란히 둔다. 선택 프로필은 항목별로 `보이기`와 `필수입력`을 제공하며, `필수입력`은 `보이기`가 켜진 항목에만 유효하다.

## 목록 화면

목록형 화면은 회원 목록 화면을 기준으로 맞춘다.

- 상단 요약/탭 성격의 이동 링크는 `member-summary`와 `member-summary-links`를 사용한다.
- 목록 테이블은 `member-table-card admin-member-list-form` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 행 단위 관리 버튼은 `member-cell-manage`와 `member-manage` 안에 둔다.

목록 위 필터는 테이블 카드 안에서 임의의 문단으로 붙이지 않고 `admin-filter-form`, `admin-filter-fields`, `admin-filter-field`, `admin-filter-label` 구조를 사용한다. 필터가 목록 범위를 바꾸는 조건일 때는 목록 위에 두고, 화면 전체 범위를 바꾸는 조건일 때는 목록 섹션 바깥에 둔다.

저장, 삭제, 적용 같은 짧은 결과 안내는 `sr_admin_feedback_toasts($notice, $errors)`를 사용해 토스트로 출력한다. 화면 본문에 영구적으로 남아야 하는 설명과 작업 결과 피드백을 섞지 않는다.

## 대시보드

관리자 대시보드는 기본 운영 섹션과 활성 모듈의 `dashboard.php` 계약 섹션을 함께 표시한다. 각 섹션은 `data-admin-dashboard-section` 값을 가진 독립 카드로 두고, 사용자가 드래그 앤 드롭으로 순서를 바꾸면 브라우저 `localStorage`에 표시 순서가 저장된다.

대시보드 섹션은 화면 구조를 바꾸는 관리 도구가 아니라 개인 작업 배치에 가깝다. 따라서 드래그 순서는 DB에 저장하지 않고, 모듈이 제공하는 기본 표시 순서는 `dashboard.php`의 `order` 값으로 유지한다.
