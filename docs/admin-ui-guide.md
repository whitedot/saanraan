# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 공통 UI 톤을 기준으로 맞춘다. 공통 CSS는 역할별 파일로 나누되 호출 순서와 책임을 고정한다.

- `assets/common/tokens.css`: 사이트 전반에서 재사용할 `--color-*`, `--spacing`, 타이포그래피, 반경, 그림자 토큰만 둔다. 컴포넌트 선택자와 화면 배치는 넣지 않는다.
- `assets/common/ui-kit.css`: reset/base와 `btn`, `card`, `table`, `form-*`, `tab-*` 같은 공통 UI kit 원형을 둔다. 관리자, 공개 화면, 모듈 고유 레이아웃은 넣지 않는다.
- `assets/common/utilities.css`: `.sr-only`, 공통 표시 유틸리티, `ui-form-theme`처럼 여러 맥락에서 재사용되는 보강만 둔다.
- `assets/common.css`: 위 세 파일을 불러오는 정적 미리보기/호환용 manifest다. 관리자 런타임은 캐시 무효화가 파일별로 적용되도록 split 파일을 직접 호출한다.
- `assets/admin-ui.css`: `.admin-ui-scope` 안의 반복 가능한 관리자 작업 조합을 둔다.
- `modules/admin/assets/admin.css`: 관리자 shell, 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 admin 모듈의 실제 화면 구조를 둔다.

공개 화면 런타임은 `assets/saanraan.css`와 `assets/public-ui.css`를 호출한다. 현재 `assets/saanraan.css`가 공개 화면의 `--sr-*` 토큰과 기본 문서 스타일을 맡고, `assets/public-ui.css`는 공개/회원 화면의 반복 UI 조합을 맡는다. `assets/public-ui.css`는 공통 토큰이 함께 로드된 정적 UI-KIT에서도 동작하도록 `--color-*` fallback을 둘 수 있지만, 관리자용 공통 reset을 공개 런타임에 무심코 추가하지 않는다.

관리자 디자인 책임은 admin 모듈에 둔다. 각 모듈의 관리자 view는 본문 마크업과 도메인 출력만 맡고, 관리자 shell, 사이드바, 상단바, 공통 관리자 asset, 관리자 콘텐츠 컨테이너는 admin skin이 맡는다. 현재 관리자 skin은 `admin_skin_key`로 선택하며, 등록된 key가 없거나 파일이 없으면 `basic`으로 fallback한다.

관리자 shell은 화면을 편하게 그리기 위한 목적으로 view 출력 뒤 `DOMDocument`로 HTML을 다시 해석하거나 class를 주입하지 않는다. 폼 행, 카드 헤더, 테이블 wrapper, 체크박스 보조 텍스트처럼 의미가 있는 구조는 view가 최종 마크업으로 직접 출력해야 한다.

렌더 후 DOM 처리가 필요한 경우는 별도 예외로 다룬다. 예를 들어 보안 정화, 외부 HTML의 제한적 변환, 편집기 콘텐츠 처리처럼 입력 자체가 HTML이고 변환 목적이 명확한 경우에는 호출 위치와 책임 모듈을 드러내고, 허용 범위와 테스트를 함께 둔다. 단순히 관리자 화면의 반복 마크업을 줄이거나 class를 자동 보정하기 위한 후처리는 사용하지 않는다.

CSS class는 범위를 드러내는 접두어를 사용한다.

- 반복 가능한 공통 UI는 `ui-*`, `btn`, `card`, `table`처럼 `assets/common/ui-kit.css` 또는 `assets/common/utilities.css`에 둔다.
- 관리자 shell과 관리자 전용 배치는 `admin-*` 접두어를 사용하고 `modules/admin/assets/admin.css`에 둔다.
- 모듈별 관리자 본문에서 도메인 고유 스타일이 필요하면 `{module_key}-admin-*` 또는 `sr-{module_key}-admin-*` 형식을 사용한다.
- 관리자 view는 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않는다.
- 탭처럼 공통 CSS에 이미 정의된 반복 UI는 `tab-nav-*`, `tab-trigger-*` 같은 기존 시맨틱 클래스를 먼저 사용한다. 토스트는 기존 관리자 메시지 클래스인 `admin-flash-message-*`에 `data-admin-toast` 동작 속성만 더해 사용하고, 위치와 닫기 버튼 배치는 `data-admin-toast-*` 속성 선택자로 처리한다.
- 공통 UI를 변경하거나 새 관리자 화면에서 UI 조합을 확인할 때는 `/assets/ui-kit/index.html` 개발자 참고 화면에서 UI-KIT 항목과 Base/Admin/Public/Skin 맥락을 먼저 확인한다.

## 화면 내 이동 링크

사이드 메뉴에 이미 등록된 관리자 화면 이동 링크는 본문 상단에 다시 만들지 않는다. 예를 들어 회원 그룹의 그룹 목록, 자동 규칙, 재평가, 수동 배정처럼 사이드 메뉴가 1차 탐색을 제공하는 화면은 각 view 안에서 `admin-local-nav` 버튼 묶음을 반복하지 않는다.

`admin-local-nav`는 사이드 메뉴에 없는 같은 화면 안의 하위 흐름이나 필터성 이동에만 사용한다. 상태 요약 링크, 단일 사이드 메뉴 아래의 잔액/조정/거래 내역 전환, 별도 사이드 메뉴 항목이 없는 발송 대기열 같은 경우가 여기에 해당한다. 새 항목 추가, 수정, 목록 복귀처럼 현재 작업의 직접 액션은 카드 헤더나 폼 액션 영역에 둔다.

## 텍스트 색상과 크기

관리자 화면의 기본 글자색은 `html`, `body`, `.admin-content`에서 상속되는 `--color-body-color`를 기준으로 한다. 기본 글자 크기는 `--text-body`인 `16px`를 기준으로 한다. 일반 본문, 테이블 셀, 테이블 헤더, 폼 라벨, 필터 라벨, 카드 제목에는 별도 회색 색상이나 작은 글자 크기를 반복해서 지정하지 않는다.

옅은 색상은 의미가 분명한 보조 텍스트에만 사용한다. 예를 들어 `hint-text`, `muted-text`, `admin-form-help`, `admin-card-subtitle`, 빈 상태 안내, 보조 메타 정보처럼 본문보다 우선순위가 낮은 텍스트가 여기에 해당한다. 성공, 경고, 위험, 링크, 활성 탭처럼 상태나 상호작용을 나타내는 색상은 예외로 유지한다.

`var(--text-xs)`, `var(--text-sm)`, `.8125rem`처럼 기본보다 작은 크기는 화면 밀도나 정보 계층이 분명한 경우에만 사용한다. 도움말, 힌트, 보조 메타, 상태 배지, 사이드바 보조 메뉴, 테이블 안의 부가 정보가 여기에 해당한다. 새 관리자 화면의 본문과 입력 라벨은 먼저 16px 상속을 기준으로 확인한다.

## 사이드바

사이드바는 모듈의 `admin.category_label`을 라벨로만 표시하고, 실제 조작 가능한 메뉴는 `모듈 그룹 > 메뉴 항목`의 2단계 구조로 유지한다. 라벨은 시스템 자산처럼 메뉴를 구분하는 시각적 구획일 뿐이며 접기/펼치기 버튼이나 링크로 사용하지 않는다.

## 페이지 타이틀

관리자 페이지의 주 타이틀은 admin skin이 출력하는 `#container_title` 하나를 기준으로 스타일링한다. 각 모듈 view는 `$adminPageTitle` 값만 지정하고, 페이지 최상단 `h1`을 별도로 반복하지 않는다.

## 폼 화면

등록, 수정, 설정처럼 화면의 주 작업이 폼인 페이지는 실제 view 마크업에서 다음 구조를 사용한다.

```php
<form method="post" action="..." class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <h2>기본 정보</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">이름</span></div>
            <div class="admin-form-field">
                <input type="text" name="title" required>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="..." class="btn btn-surface-default-soft">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
```

- 폼의 주 섹션은 `form > section.admin-card.card`로 둔다.
- 섹션 제목은 `h2`를 사용한다. 카드 헤더가 별도로 필요하면 view에서 `card-header`, `card-title` 마크업을 직접 출력한다.
- 체크박스와 라디오처럼 컨트롤 옆 문구가 실제 레이블인 항목은 좌측 설명 칸과 우측 조작 레이블을 view에 직접 둔다. 좌측에는 전체 문구를 그대로 표시하고, 우측의 시각적 레이블은 `허용`, `사용`, `확인했습니다.`처럼 필요한 부분만 보이게 줄인다. 우측에서 생략된 맥락은 `.sr-only` 텍스트로 남겨 보조기기가 전체 의미를 읽을 수 있게 한다.
- 좌측 레이블이 필요 없는 안내 문장은 입력항목 행으로 만들지 말고 일반 텍스트 문단으로 둔다.
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 두되 문서 흐름 안에서 출력한다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

체크박스 행 예시는 다음처럼 작성한다.

```php
<div class="admin-form-grid">
    <div class="admin-form-row">
        <div class="admin-form-label"><span class="form-label">공개 회원가입 허용</span></div>
        <div class="admin-form-field">
            <label class="admin-form-check form-label">
                <input type="checkbox" name="allow_registration" value="1" class="form-checkbox">
                <?php echo sr_admin_choice_label_html('공개 회원가입 허용'); ?>
            </label>
        </div>
    </div>
</div>
```

회원 설정의 선택 프로필 항목처럼 한 항목에 여러 불리언 옵션이 붙는 경우에도 같은 `af-row` 안에 체크박스를 나란히 둔다. 선택 프로필은 항목별로 `보이기`와 `필수입력`을 제공하며, `필수입력`은 `보이기`가 켜진 항목에만 유효하다.

## 목록 화면

목록형 화면은 공통 관리자 목록 원형을 기준으로 맞춘다.

- 상단 요약/탭 성격의 이동 링크는 `admin-summary`와 `admin-summary-links`를 사용한다.
- 목록 테이블은 `admin-card card` 또는 `admin-list-card admin-card card` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 테이블 전체를 감싸는 일괄 작업 폼은 `admin-list-form`을 사용한다.
- 행 단위 관리 버튼은 `admin-cell-actions`와 `admin-row-actions` 안에 둔다.

목록 위 필터는 테이블 카드 안에서 임의의 문단으로 붙이지 않고 `admin-filter`, `admin-filter-fields`, `admin-filter-field`, `admin-filter-label` 구조를 사용한다. 필터가 목록 범위를 바꾸는 조건일 때는 목록 위에 두고, 화면 전체 범위를 바꾸는 조건일 때는 목록 섹션 바깥에 둔다.

저장, 삭제, 적용 같은 짧은 결과 안내는 `sr_admin_feedback_toasts($notice, $errors)`를 사용해 토스트로 출력한다. 화면 본문에 영구적으로 남아야 하는 설명과 작업 결과 피드백을 섞지 않는다.

## 대시보드

관리자 대시보드는 기본 운영 섹션과 활성 모듈의 `dashboard.php` 계약 섹션을 함께 표시한다. 각 섹션은 `data-admin-dashboard-section` 값을 가진 독립 카드로 두고, 사용자가 드래그 앤 드롭으로 순서를 바꾸면 브라우저 `localStorage`에 표시 순서가 저장된다.

대시보드 섹션은 화면 구조를 바꾸는 관리 도구가 아니라 개인 작업 배치에 가깝다. 따라서 드래그 순서는 DB에 저장하지 않고, 모듈이 제공하는 기본 표시 순서는 `dashboard.php`의 `order` 값으로 유지한다.
