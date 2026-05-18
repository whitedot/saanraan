# UI-KIT 디자인 토큰 조회 계획

`assets/ui-kit`은 프로젝트 디자인 토큰과 공통 UI 원형을 개발자가 확인하는 정적 조회 도구다. UI-KIT은 프로젝트 화면에 영향을 주지 않고, 실제 프로젝트 CSS와 공용 상호작용 JS를 읽어서 현재 상태를 보여주는 역할만 한다.

이 UI-KIT은 `g5codex.git` 프로젝트를 진행하면서 만들었던 UI-KIT 산출물을 현재 `saanraan` 프로젝트에 적용한 것이다. Git 히스토리를 가져온 것은 아니므로, 남아 있는 파일과 마크업은 현재 프로젝트 기준으로 다시 판정한다. 일부 파일에는 그누보드5, Tailwind/Preline 계열 관성이 남아 있을 수 있다. 정리 기준은 원형을 무조건 삭제하거나 보존하는 것이 아니라, 현재 프로젝트의 디자인 토큰 조회 목적에 맞게 흡수할 것은 공용 자산으로 승격하고 맞지 않는 잔재는 제거하는 것이다.

## 목표

- 개발자가 `assets/common.css`의 현재 토큰, reset/base, 공통 UI 원형을 카테고리별 HTML에서 확인할 수 있게 한다.
- `assets/admin-ui.css`, `assets/public-ui.css`, `assets/saanraan.css`처럼 실제 화면에서 쓰는 레이어 CSS를 조회 대상으로 포함한다.
- UI-KIT 전용 CSS가 프로젝트 토큰이나 컴포넌트 원형을 덮어쓰지 않게 한다.
- 드롭다운, 모달, 탭처럼 JS 동작이 있어야 원형을 확인할 수 있는 항목은 프로젝트에서도 같은 JS를 사용할 수 있게 공용 자산으로 관리한다.
- 파일 이름, 위치, 호출 방식만 봐도 프로젝트 기준 CSS/JS인지, UI-KIT 조회 shell 전용인지, 공용 상호작용 자산인지 구분되게 한다.

## 기본 원칙

1. UI-KIT의 의존 방향은 항상 `UI-KIT -> 프로젝트 CSS/JS`다. 프로젝트 런타임은 UI-KIT 전용 CSS/JS를 호출하지 않는다.
2. `assets/ui-kit/*.html` 파일은 삭제하지 않고 카테고리별 토큰/원형 조회 화면으로 유지한다.
3. UI-KIT은 별도 디자인 시스템을 만들지 않는다. 실제 프로젝트 CSS와 공용 JS의 현재 결과만 보여준다.
4. UI-KIT 전용 스타일은 조회 화면 shell, 사이드바, 토큰 표, 샘플 배치에만 사용한다.
5. UI-KIT 전용 스타일은 `.btn`, `.card`, `.table`, `.form-*`, `.dropdown-*`, `.modal-*`, `.tab-*` 같은 프로젝트 원형 클래스를 재정의하지 않는다.
6. `g5codex.git` 산출물 적용 과정에서 남은 그누보드5, Tailwind/Preline 계열 잔재는 현재 프로젝트 목적 기준으로 판정한다. 실제로 필요한 원형은 `saanraan`의 공용 CSS/JS 규칙으로 재정의하고, 단순 호환용 잔재는 제거한다.
7. UI-KIT은 예쁜 데모 페이지가 아니라 현재 디자인 토큰과 공용 UI 동작을 검증하는 읽기 전용 개발자 도구다.

## CSS 호출 계획

모든 `assets/ui-kit/*.html`은 같은 순서로 CSS를 호출한다.

```html
<link rel="stylesheet" href="../common.css?v=...">
<link rel="stylesheet" href="../admin-ui.css?v=...">
<link rel="stylesheet" href="../saanraan.css?v=...">
<link rel="stylesheet" href="../public-ui.css?v=...">
<link rel="stylesheet" href="css/ui-guide.css?v=...">
```

- `../common.css`: 공통 토큰, reset/base, 공통 UI 원형의 기준이다.
- `../admin-ui.css`: 관리자 레이어의 반복 UI 조합을 확인한다.
- `../saanraan.css`: 공개 화면의 `--sr-*` 토큰과 기본 문서 스타일을 확인한다.
- `../public-ui.css`: 공개/회원 화면의 반복 UI 조합을 확인한다.
- `css/ui-guide.css`: UI-KIT 조회 화면 자체의 shell과 토큰 표시 레이아웃만 담당한다.

`assets/ui-kit/css/preview-utilities.css`는 정리 대상이다. 이 파일은 UI-KIT HTML을 유지하기 위한 Tailwind식 보조 번들이어서, 개발자가 실제 프로젝트 CSS와 미리보기 보조 CSS를 구분하기 어렵게 만든다. HTML의 유틸리티 클래스 의존을 제거한 뒤 삭제한다.

## JS 호출 계획

JS는 두 종류로 나눈다.

`assets/ui-kit/js/common.js`는 제거 대상이다. 이 파일은 UI-KIT 디자인 토큰 조회나 현재 공통 UI 원형 동작에 필요한 코드가 아니라, 예전 그누보드5 계열 전역 helper와 jQuery 의존 코드를 보존한 잔존 파일이다. `check_field`, `number_format`, `del`, cookie helper, `flash_movie`, `win_password_lost`, `g5_is_mobile`, sideview/selectbox 처리처럼 현재 UI-KIT 조회 목적과 맞지 않는 전역 함수가 포함되어 있으므로 공용 JS로 승격하지 않는다.

### 프로젝트 공용 상호작용 JS

드롭다운, 오버레이/모달, 탭처럼 실제 프로젝트 화면에서도 필요한 동작은 UI-KIT 전용 경로가 아니라 공용 자산으로 둔다.

후보 경로:

```text
assets/common-ui.js
```

또는 기능별 분리가 필요하면:

```text
assets/common-js/dropdown.js
assets/common-js/overlay.js
assets/common-js/tablist.js
```

초기에는 저비용 호스팅과 단순 호출을 고려해 `assets/common-ui.js` 단일 파일을 우선 검토한다.

공용 승격 대상:

- `assets/ui-kit/js/ui-kit/ui-dropdown-menu.js`
- `assets/ui-kit/js/ui-kit/ui-overlay.js`
- `assets/ui-kit/js/ui-kit/ui-tablist.js`

공용 JS의 규칙:

- 특정 UI-KIT shell에 의존하지 않는다.
- `data-*`, `aria-*`, 또는 공통 원형 클래스 기반으로 동작한다.
- 관리자/공개 화면에서 같은 마크업 규칙을 쓰면 동일하게 동작한다.
- DOMContentLoaded 후 안전하게 초기화한다.
- 해당 요소가 없는 페이지에서는 아무 일도 하지 않는다.

드롭다운은 현재 `ui-dropdowns.html`에서 `.hs-dropdown`, `.hs-dropdown-toggle`, `.hs-dropdown-menu` 마크업과 `assets/ui-kit/js/ui-kit/ui-dropdown-menu.js`로 동작한다. JS 자체는 vanilla JS 기반이라 공용 승격 후보지만, 옵션을 `[--placement:bottom-end]`, `[--auto-close:inside]`, `[--trigger:hover]`처럼 class 문자열에서 파싱하는 방식은 Tailwind/Preline 계열 잔재이므로 프로젝트 공용 규칙으로 유지하지 않는다.

드롭다운 공용화 시 옵션은 `data-*` 속성으로 정리한다.

```html
<div
    class="dropdown hs-dropdown"
    data-dropdown-placement="bottom-end"
    data-dropdown-auto-close="inside"
>
    <button type="button" class="dropdown-toggle hs-dropdown-toggle" aria-expanded="false">
        메뉴
    </button>
    <div class="hs-dropdown-menu" role="menu" aria-hidden="true">
        ...
    </div>
</div>
```

드롭다운 공용 JS는 다음 순서로 옵션을 읽는다.

1. `data-dropdown-trigger`
2. `data-dropdown-placement`
3. `data-dropdown-auto-close`

기존 class 기반 옵션은 UI-KIT 정리 과정에서 `data-*` 속성으로 바꾼다. 필요하면 짧은 전환 기간 동안만 class 기반 옵션을 fallback으로 읽되, 새 마크업에는 사용하지 않는다.

### UI-KIT 조회 화면 전용 JS

UI-KIT shell이나 조회 편의 기능에만 필요한 JS는 `assets/ui-kit/js` 아래에 유지한다.

유지 대상:

- `assets/ui-kit/js/ui-kit/ui-sidebar-toggle.js`
- `assets/ui-kit/js/ui-kit/ui-theme-toggle.js`
- 향후 추가할 토큰 값 표시용 JS

향후 토큰 값 표시용 JS 후보:

```text
assets/ui-kit/js/token-inspector.js
```

이 JS는 `getComputedStyle()`로 현재 적용된 CSS custom property 값을 읽어 화면에 표시만 한다. 프로젝트 CSS나 DOM 규칙을 변경하지 않는다.

## HTML 유지 계획

다음 파일은 유지한다.

- `index.html`
- `ui-buttons.html`
- `ui-cards.html`
- `ui-alerts.html`
- `ui-badges.html`
- `ui-modals.html`
- `ui-dropdowns.html`
- `ui-tabs.html`
- `form-elements.html`
- `form-validation.html`
- `tables-static.html`
- `icons-tabler.html`
- `icons-lucide.html`

각 HTML은 기존 원형을 유지하되, 의미를 “독립 쇼케이스”가 아니라 “현재 프로젝트 토큰/원형 조회 페이지”로 정리한다.

- 버튼 페이지는 `btn`, `btn-*`, 색상/크기/상태 토큰 확인에 집중한다.
- 카드 페이지는 `card`, surface, border, shadow, radius 확인에 집중한다.
- 폼 페이지는 `form-*`, input, validation, spacing, focus 상태 확인에 집중한다.
- 테이블 페이지는 `table`, row 상태, border, density 확인에 집중한다.
- 드롭다운/모달/탭 페이지는 CSS 원형과 공용 JS 동작을 함께 확인한다.
- 아이콘 페이지는 아이콘 자체와 크기/색상 토큰 적용 결과를 확인한다.

## 잔재 정리 기준

`g5codex.git` 산출물을 적용하면서 남은 코드가 현재 UI-KIT에 있을 때는 다음 기준으로 판단한다.

- 현재 프로젝트의 토큰/원형 확인에 필요한가?
- 실제 관리자/공개 화면에서도 같은 방식으로 쓸 수 있는가?
- 공용 자산으로 승격했을 때 이름, 경로, 옵션 규칙이 `saanraan` 프로젝트와 맞는가?
- UI-KIT 조회 화면을 유지하기 위한 임시 보정일 뿐인가?

드롭다운/오버레이/탭처럼 실제 화면에서도 필요한 상호작용은 공용 JS로 승격한다. 다만 기존 `hs-*` 마크업이나 `[--placement:*]` 같은 class 옵션은 그대로 보존하지 않고, 현재 프로젝트에서 읽기 쉬운 `data-*` 규칙으로 정리한다.

`assets/ui-kit/js/common.js`처럼 그누보드5 전역 helper, jQuery 호환 shim, 오래된 브라우저/Flash helper, 현재 프로젝트에서 쓰지 않는 sideview/selectbox 처리를 담은 파일은 제거한다.

`assets/ui-kit/css/preview-utilities.css`처럼 UI-KIT 미리보기만 성립시키는 대형 보조 CSS는 제거한다. 필요한 조회 shell 스타일은 `assets/ui-kit/css/ui-guide.css`에 명시적 `ui-*` 클래스로만 둔다.

## HTML 정리 기준

- `bg-gray-50`, `text-2xl`, `font-bold`, `mt-4`, `flex`, `gap-*` 같은 미리보기 유틸리티 클래스 의존을 단계적으로 제거한다.
- 조회 화면 배치가 필요한 경우 `ui-guide-*`, `ui-token-*`, `ui-sample-*` 같은 UI-KIT 전용 클래스를 사용한다.
- 프로젝트 원형 샘플에는 실제 프로젝트 클래스를 그대로 사용한다.

예:

```html
<button class="btn btn-primary">Primary</button>
<div class="card">...</div>
<table class="table">...</table>
```

## 프로젝트 영향 방지

- `assets/common.css`, `assets/admin-ui.css`, `assets/saanraan.css`, `assets/public-ui.css`에는 UI-KIT 조회 화면 전용 스타일을 넣지 않는다.
- `assets/ui-kit/css/ui-guide.css`는 프로젝트 런타임에서 호출하지 않는다.
- `assets/ui-kit/js/*` 중 shell 전용 JS는 프로젝트 런타임에서 호출하지 않는다.
- `assets/ui-kit/js/common.js`는 프로젝트 공용 JS로 승격하지 않고 UI-KIT HTML 호출에서도 제거한다.
- 공용으로 승격한 JS만 프로젝트 런타임에서 호출할 수 있다.
- 공용 JS로 승격한 파일은 더 이상 UI-KIT 전용 이름이나 경로에 두지 않는다.

## 캐시 갱신

관리자/공개 런타임 CSS와 공용 JS 호출은 PHP helper가 실제 파일의 `filemtime()` 값을 `?v=` query string으로 붙여 캐시를 갱신한다.

정적 UI-KIT HTML은 PHP helper를 거치지 않으므로 CSS/JS 파일을 수정하거나 재생성하면 해당 HTML의 `?v=` 값도 함께 갱신한다. 장기적으로는 UI-KIT HTML 생성 스크립트나 PHP 미리보기 진입점을 두어 이 값을 자동 갱신하는 방식을 검토한다.

## 단계별 작업 계획

1. 문서 기준을 “정적 변환 쇼케이스”에서 “디자인 토큰 조회 도구”로 고정한다.
2. 모든 `assets/ui-kit/*.html`의 CSS 호출 순서를 통일한다.
3. `preview-utilities.css`에 의존하는 HTML class를 조사한다.
4. 필요한 조회 화면 배치를 `ui-guide.css`의 `ui-*` 클래스로 옮긴다.
5. `preview-utilities.css` 호출을 제거하고 파일을 삭제한다.
6. 모든 `assets/ui-kit/*.html`에서 `js/common.js` 호출을 제거하고 `assets/ui-kit/js/common.js` 파일을 삭제한다.
7. 드롭다운 옵션 표기를 `[--placement:*]`, `[--auto-close:*]`, `[--trigger:*]` class에서 `data-dropdown-*` 속성으로 바꾼다.
8. 드롭다운/오버레이/탭 JS를 공용 JS로 승격한다.
9. 관리자/공개 런타임에서 공용 JS를 필요 페이지 또는 공통 shell에서 호출하는 방식을 정한다.
10. UI-KIT HTML은 공용 JS와 UI-KIT shell 전용 JS를 구분해 호출한다.
11. 토큰 값 표시용 `token-inspector.js`를 추가해 CSS custom property의 현재 computed value를 표시한다.
12. `common.css`의 토큰 하나를 바꾸면 UI-KIT에서 값과 샘플이 바로 바뀌는지 확인한다.

## 검증 기준

- UI-KIT HTML이 `preview-utilities.css` 없이 열린다.
- UI-KIT HTML이 `assets/ui-kit/js/common.js` 없이 열린다.
- UI-KIT 전용 CSS가 프로젝트 원형 클래스를 재정의하지 않는다.
- `assets/common.css` 수정 결과가 UI-KIT 샘플에 바로 반영된다.
- 드롭다운 옵션은 새 마크업에서 `data-dropdown-*` 속성으로 표현된다.
- 드롭다운, 모달, 탭은 UI-KIT과 프로젝트 화면에서 같은 공용 JS로 동작한다.
- 프로젝트 런타임은 UI-KIT shell CSS/JS를 호출하지 않는다.
- `git diff --check`가 통과한다.
