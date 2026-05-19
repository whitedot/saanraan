# UI-KIT 디자인 토큰 조회 계획

현재 `assets/ui-kit` 중앙 조회 도구는 제거하고, 프로젝트 디자인 토큰과 공통 UI 원형은 실제 public layout과 admin 모듈 안의 런타임별 조회 화면에서 확인한다. UI-KIT은 프로젝트 화면에 영향을 주지 않는 읽기 전용 개발자 도구여야 하며, 실제 화면과 다른 CSS 호출 순서나 별도 shell 보정으로 결과를 흉내내지 않는다.

기존 `assets/ui-kit`은 `g5codex.git` 프로젝트를 진행하면서 만들었던 UI-KIT 산출물을 현재 `saanraan` 프로젝트에 적용한 것이다. Git 히스토리를 가져온 것은 아니므로, 남아 있는 파일과 마크업은 현재 프로젝트 기준으로 다시 판정한다. 일부 파일에는 그누보드5와 이전 UI 프레임워크 계열 관성이 남아 있을 수 있다. 정리 기준은 원형을 일부 선별하는 것이 아니라, 현재 중앙 UI-KIT에 남아 있는 카테고리와 예시를 public/admin 런타임별 조회 화면으로 모두 이관한 뒤 중앙 `assets/ui-kit`을 제거하는 것이다.

## 목표

- 개발자가 `assets/tokens.css`의 현재 토큰과 런타임별 CSS의 reset/base, UI 원형을 실제 public/admin 화면 컨텍스트에서 확인할 수 있게 한다.
- `assets/admin-ui.css`, `assets/public-ui.css`, `assets/saanraan.css`, `modules/admin/assets/admin.css`처럼 실제 화면에서 쓰는 레이어 CSS를 해당 런타임의 원래 호출 방식으로 확인한다. 단, public 컴포넌트 스타일은 아직 충분히 분리되어 있지 않으므로 초기 public UI-KIT은 관리자에서 사용하는 공통 원형과 스타일을 복사해 출발점으로 삼는다.
- UI-KIT 전용 CSS가 프로젝트 토큰이나 컴포넌트 원형을 덮어쓰지 않게 하기 위해 중앙 shell CSS를 제거한다.
- 드롭다운, 모달, 탭처럼 JS 동작이 있어야 원형을 확인할 수 있는 항목은 프로젝트에서도 같은 JS를 사용할 수 있게 공용 자산으로 관리한다.
- 파일 이름, 위치, 호출 방식만 봐도 public layout 조회인지, admin 모듈 조회인지, 공용 상호작용 자산인지 구분되게 한다.

## 기본 원칙

1. UI-KIT의 의존 방향은 항상 `조회 화면 -> 실제 런타임 CSS/JS`다. 프로젝트 런타임은 중앙 UI-KIT 전용 CSS/JS를 호출하지 않는다.
2. `assets/ui-kit/*.html` 파일은 최종적으로 삭제한다. 단, 삭제 전에 모든 카테고리와 예시를 public/admin 런타임별 조회 화면으로 전량 이관한다.
3. UI-KIT은 별도 디자인 시스템을 만들지 않는다. 실제 프로젝트 CSS와 공용 JS의 현재 결과만 보여준다.
4. 조회 화면의 부가 스타일은 해당 런타임 내부에서 최소화한다. `.btn`, `.card`, `.table`, `.form-*`, `.dropdown-*`, `.modal-*`, `.tab-*` 같은 프로젝트 원형 클래스를 재정의하지 않는다.
5. 중앙 UI-KIT shell, 사이드바, 별도 미리보기 유틸리티 CSS는 제거한다.
6. UI-KIT 조회 화면에서만 필요한 배치, 간격, 색상 보조 표현은 `ui-kit-*` 접두어로만 둔다. `ui-bg-*`, `ui-text-*`, `ui-grid`, `ui-flex`, `ui-gap-*`, `ui-mt-*`, `ui-p-*` 같은 Tailwind식 범용 utility 표현은 사용하지 않는다.
7. `g5codex.git` 산출물 적용 과정에서 남은 그누보드5와 이전 UI 프레임워크 계열 잔재는 현재 프로젝트 목적 기준으로 판정한다. 실제로 필요한 원형은 `saanraan`의 공용 CSS/JS 규칙으로 재정의하고, 단순 호환용 잔재는 제거한다.
8. UI-KIT은 예쁜 데모 페이지가 아니라 현재 디자인 토큰과 공용 UI 동작을 검증하는 읽기 전용 개발자 도구다.
9. 관리자/공개 화면처럼 런타임 컨텍스트가 중요한 샘플은 별도 중앙 shell이 아니라 해당 런타임 안에서 직접 렌더링한다.

## 구조 한계와 보정 방향

정적 `assets/ui-kit/*.html`만으로는 관리자 모드나 공개 화면의 실제 스타일을 완전히 보여주기 어렵다. 관리자 화면은 `modules/admin/helpers/shell.php`가 만든 CSS 호출 순서, `.admin-content`, `#container`, `.admin-form.ui-form-theme`, 모듈 소유 CSS인 `modules/admin/assets/admin.css`가 함께 있을 때 최종 형태가 결정된다. 이를 UI-KIT shell HTML에 직접 섞으면 사이드바와 조회 레이아웃이 관리자 CSS의 넓은 선택자에 영향을 받을 수 있고, `admin.css`를 공용 레이어로 승격하면 모듈 경계 정책을 흐릴 수 있다.

따라서 중앙 `assets/ui-kit`은 장기 유지하지 않는다. UI-KIT은 다음 두 갈래로 분해한다.

- public layout 조회 화면: 실제 `sr_public_layout_begin()` / `sr_public_layout_end()` 흐름으로 렌더링한다.
- admin 모듈 조회 화면: 실제 관리자 shell과 `modules/admin/assets/admin.css` 흐름으로 렌더링한다.

기존 중앙 UI-KIT의 `index.html`, `ui-buttons.html`, `ui-cards.html`, `ui-alerts.html`, `ui-badges.html`, `ui-modals.html`, `ui-dropdowns.html`, `ui-tabs.html`, `form-elements.html`, `form-validation.html`, `tables-static.html`, `icons-tabler.html`, `icons-lucide.html`에 있는 예시는 일부만 선별하지 않는다. 모든 예시를 public/admin 중 적합한 런타임 조회 페이지로 전량 이관한다. 양쪽에서 모두 확인할 가치가 있는 원형은 양쪽에 중복 배치해 실제 결과 차이를 볼 수 있게 한다.

`modules/admin/assets/admin.css`는 모듈 소유 CSS로 유지한다. 공용 승격 대상은 여러 런타임에서 실제로 공유하는 토큰과 공용 JS 동작에 한정한다.

## CSS 호출 계획

중앙 `assets/ui-kit/*.html`의 공통 CSS 호출 계획은 폐기한다. 조회 화면은 각 런타임의 기존 helper와 layout을 그대로 사용한다.

Admin 조회 화면:

- `modules/admin/views/layout-header.php`와 `layout-footer.php`를 통과한다.
- `sr_admin_stylesheet_tag()`가 출력하는 `tokens.css`, `admin-ui.css`, `modules/admin/assets/admin.css`를 그대로 사용한다.
- 관리자 런타임의 reset/base와 `btn`, `card`, `table`, `badge`, `form-*` 의미 클래스는 `modules/admin/assets/admin.css`가 직접 소유한다. 삭제한 common primitive/utility 산출물은 관리자 UI-KIT의 스타일 출처가 아니다.
- 관리자 조회 페이지는 `modules/admin/views/ui-kit.php` 또는 `modules/admin/views/dev-ui-kit.php`로 둔다.

Public 런타임 미리보기:

- `sr_public_layout_begin()`과 `sr_public_layout_end()`를 통과한다.
- `sr_stylesheet_tag()`가 출력하는 `assets/saanraan.css`, `assets/public-ui.css`와 필요한 layout context stylesheet를 그대로 사용한다.
- public 런타임 미리보기는 현재 `/admin/ui-kit-public`에 둔 관리자 보호 개발 도구이며, public layout이나 개발자용 public view로 옮기는 것은 실제 공개 조회 페이지가 필요할 때만 진행한다.
- 현재 public 전용 컴포넌트 스타일시트가 충분하지 않으므로, public UI-KIT 구축 시 관리자 조회 화면의 공통 원형 마크업과 필요한 스타일 규칙을 public 레이어로 복사해 시작한다.
- 복사 대상은 버튼, 카드, 폼, validation, 테이블, 알림, 배지, 드롭다운, 모달, 탭처럼 public에서도 필요한 공통 원형이다.
- 일반 public 런타임은 `modules/admin/assets/admin.css`를 직접 호출하지 않는다. 단, `/admin/ui-kit-public` 미리보기는 public 원형 이관 전 확인용 관리자 도구이므로 관리자 컴포넌트 레이어를 명시적으로 호출할 수 있다.
- public에 복사한 뒤에는 public 런타임에서 실제로 쓰는 class와 토큰 기준으로 이름과 범위를 정리한다. 관리자 모듈 전용 selector나 admin domain 구조는 public으로 가져오지 않는다.

`assets/common.css`는 중앙 UI-KIT manifest 용도만 남아 있었으므로 중앙 UI-KIT 제거와 함께 삭제한다. 관리자 화면의 실제 편집 기준은 `assets/tokens.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css`다.

`assets/ui-kit/css/preview-utilities.css`와 `assets/ui-kit/css/ui-guide.css`는 중앙 shell 전용 파일이므로 제거한다. 샘플 배치에 필요한 최소 표현 class는 런타임별 조회용 CSS인 `modules/admin/assets/ui-kit.css`와 `assets/public-ui-kit.css`에 `ui-kit-*` 접두어로만 둔다. 이 class는 UI-KIT 샘플의 배치와 예시 상태 표시를 위한 것이며, 실제 프로젝트 컴포넌트 원형으로 승격하지 않는다.

## JS 호출 계획

JS는 두 종류로 나눈다.

`assets/ui-kit/js/common.js`는 제거했다. 이 파일은 UI-KIT 디자인 토큰 조회나 현재 공통 UI 원형 동작에 필요한 코드가 아니라, 예전 그누보드5 계열 전역 helper와 jQuery 의존 코드를 보존한 잔존 파일이었다. `check_field`, `number_format`, `del`, cookie helper, `flash_movie`, `win_password_lost`, `g5_is_mobile`, sideview/selectbox 처리처럼 현재 UI-KIT 조회 목적과 맞지 않는 전역 함수가 포함되어 있었으므로 공용 JS로 승격하지 않았다.

### 프로젝트 공용 상호작용 JS

드롭다운, 오버레이/모달, 탭처럼 실제 프로젝트 화면에서도 필요한 동작은 UI-KIT 전용 경로가 아니라 공용 자산으로 둔다.

공용 경로:

```text
assets/common-ui.js
```

저비용 호스팅과 단순 호출을 고려해 단일 파일로 시작한다. 기능별 분리가 필요해지면 `assets/common-js/dropdown.js`, `assets/common-js/overlay.js`, `assets/common-js/tablist.js`처럼 나누는 방식을 다시 검토한다.

공용 승격 완료 대상:

- 드롭다운
- 오버레이/모달
- 탭

공용 JS의 규칙:

- 특정 UI-KIT shell에 의존하지 않는다.
- `data-*`, `aria-*`, 또는 공통 원형 클래스 기반으로 동작한다.
- 관리자/공개 화면에서 같은 마크업 규칙을 쓰면 동일하게 동작한다.
- DOMContentLoaded 후 안전하게 초기화한다.
- 해당 요소가 없는 페이지에서는 아무 일도 하지 않는다.

드롭다운은 `.dropdown`, `.dropdown-toggle`, `.dropdown-menu` 마크업과 `assets/common-ui.js`로 동작한다. 옵션을 class 문자열이나 CSS custom property에서 파싱하는 방식은 이전 UI 프레임워크 계열 잔재이므로 새 프로젝트 공용 규칙으로 유지하지 않는다.

드롭다운 공용화 시 옵션은 `data-*` 속성으로 정리한다. 기본 위치는 `.dropdown` root이며, 테이블 액션처럼 기존 마크업 구조상 root 정리가 늦어지는 경우에는 전환 기간 동안 toggle의 `data-dropdown-*`도 읽는다.

```html
<div
    class="dropdown"
    data-dropdown-placement="bottom-end"
    data-dropdown-auto-close="inside"
>
    <button type="button" class="dropdown-toggle" aria-expanded="false">
        메뉴
    </button>
    <div class="dropdown-menu" role="menu" aria-hidden="true">
        ...
    </div>
</div>
```

드롭다운 공용 JS는 다음 순서로 옵션을 읽는다.

1. `data-dropdown-trigger`
2. `data-dropdown-placement`
3. `data-dropdown-auto-close`

기존 class 기반 옵션은 UI-KIT 정리 과정에서 `data-*` 속성으로 바꿨다. 공용 JS도 class 기반 옵션 fallback을 제거하고 `data-dropdown-*`만 읽는다.

### 런타임별 조회 화면 전용 JS

중앙 UI-KIT shell 전용 JS는 최종 제거한다.

제거 대상:

- `assets/ui-kit/js/ui-kit/ui-sidebar-toggle.js`
- `assets/ui-kit/js/ui-kit/ui-theme-toggle.js`
- 향후 중앙 shell을 전제로 한 토큰 값 표시용 JS

토큰 값 표시가 필요하면 public/admin 각 조회 화면에서 작은 inline script나 런타임별 dev script로 구현한다. 이 스크립트는 `getComputedStyle()`로 현재 적용된 CSS custom property 값을 읽어 화면에 표시만 하고, 프로젝트 CSS나 DOM 규칙을 변경하지 않는다.

## HTML 이관 계획

다음 중앙 UI-KIT 파일의 예시는 삭제 전에 전량 이관한다.

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

각 HTML의 기존 원형은 일부만 선별하지 않는다. 모든 섹션과 예시를 public/admin 런타임 조회 화면 중 하나 이상으로 옮긴다.

- 버튼 예시는 public/admin 양쪽에서 모두 확인한다.
- 카드 예시는 public surface와 admin card를 각각 확인한다.
- 알림, 배지, 모달, 드롭다운, 탭은 공용 JS 동작까지 public/admin 양쪽에서 확인한다.
- 폼과 validation 예시는 public form, admin form 구조를 각각 확인한다.
- 테이블 예시는 admin list/table 기준을 우선하고, public에서 필요한 표 스타일이 있으면 public에도 중복 배치한다.
- 아이콘 예시는 런타임별 색상/크기 토큰 적용 차이를 볼 수 있도록 양쪽에 둔다.
- Public 쪽에 아직 대응 스타일이 없는 예시는 admin 기준 구현을 복사해 public stylesheet에 먼저 반영한 뒤 public 조회 화면으로 옮긴다.

## 잔재 정리 기준

`g5codex.git` 산출물을 적용하면서 남은 코드가 현재 UI-KIT에 있을 때는 다음 기준으로 판단한다.

- 현재 프로젝트의 토큰/원형 확인에 필요한가?
- 실제 관리자/공개 화면에서도 같은 방식으로 쓸 수 있는가?
- 공용 자산으로 승격했을 때 이름, 경로, 옵션 규칙이 `saanraan` 프로젝트와 맞는가?
- UI-KIT 조회 화면을 유지하기 위한 임시 보정일 뿐인가?

드롭다운/오버레이/탭처럼 실제 화면에서도 필요한 상호작용은 공용 JS로 승격한다. 다만 이전 UI 프레임워크 마크업이나 class 기반 옵션은 그대로 보존하지 않고, 현재 프로젝트에서 읽기 쉬운 `data-*` 규칙으로 정리한다.

`assets/ui-kit/js/common.js`처럼 그누보드5 전역 helper, jQuery 호환 shim, 오래된 브라우저/Flash helper, 현재 프로젝트에서 쓰지 않는 sideview/selectbox 처리를 담은 파일은 제거한다.

`assets/ui-kit/css/preview-utilities.css`처럼 UI-KIT 미리보기만 성립시키는 대형 보조 CSS는 제거한다. `assets/ui-kit/css/ui-guide.css`도 중앙 shell 제거와 함께 삭제한다.

2026-05-18에 `preview-utilities.css` 호출과 파일을 제거했다. 이후 남은 HTML 배치 보정도 `ui-guide.css`의 명시적 `ui-*` class와 실제 프로젝트 원형 class 기준으로 정리했다. 그러나 중앙 `assets/ui-kit` 방식 자체가 런타임별 CSS 결과를 혼동시킨다는 결론에 따라, 이 정리는 최종 구조가 아니라 이관 전 중간 단계로 취급한다.

2026-05-19에 `/admin/ui-kit`과 `/admin/ui-kit-public` 샘플에 남아 있던 Tailwind식 `ui-*` 표현을 제거했다. 샘플 파일과 런타임별 UI-KIT CSS에는 `ui-bg-*`, `ui-text-*`, `ui-border-*`, `ui-rounded-*`, `ui-gap-*`, `ui-grid`, `ui-flex` 같은 범용 utility class를 두지 않고, UI-KIT 조회 화면 전용 표현은 `ui-kit-*` 접두어로만 구분한다. `ui-form-theme`처럼 실제 공통 폼 컨텍스트를 나타내는 프로젝트 의미 class는 예외로 유지한다.

## HTML 이관 기준

- `bg-gray-50`, `text-2xl`, `font-bold`, `mt-4`, `flex`, `gap-*`, `ui-bg-*`, `ui-text-*`, `ui-grid`, `ui-flex` 같은 미리보기 유틸리티 클래스 의존은 런타임별 조회 화면으로 옮길 때 제거한다.
- 조회 화면 배치가 필요한 경우 public/admin 런타임의 기존 layout/card/form/table 구조를 우선 사용한다.
- 프로젝트 원형 샘플에는 실제 프로젝트 클래스를 그대로 사용한다.
- 기존 중앙 UI-KIT의 예시는 삭제하거나 요약하지 않고 모두 옮긴다. 단, 같은 예시가 public/admin 양쪽에 필요한 경우에는 복제해서 각각의 실제 CSS 결과를 확인한다.

예:

```html
<button class="btn btn-primary">Primary</button>
<div class="card">...</div>
<table class="table">...</table>
```

## 프로젝트 영향 방지

- `assets/tokens.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css`, `assets/saanraan.css`, `assets/public-ui.css`에는 UI-KIT 조회 화면 전용 스타일을 넣지 않는다.
- 중앙 `assets/ui-kit/css/ui-guide.css`는 최종 제거한다.
- 중앙 `assets/ui-kit/js/*` 중 shell 전용 JS는 최종 제거한다.
- `assets/ui-kit/js/common.js`는 프로젝트 공용 JS로 승격하지 않고 제거한다.
- 공용으로 승격한 `assets/common-ui.js`만 프로젝트 런타임에서 호출할 수 있다.
- 공용 JS로 승격한 드롭다운/오버레이/탭 동작은 더 이상 UI-KIT 전용 이름이나 경로에 두지 않는다.
- public/admin 조회 화면이 필요로 하는 최소 dev-only 스타일은 해당 런타임의 조회 view 안에서 제한적으로 둔다. 공용 CSS 파일에는 넣지 않는다.

## 캐시 갱신

관리자/공개 런타임 CSS와 공용 JS 호출은 PHP helper가 실제 파일의 `filemtime()` 값을 `?v=` query string으로 붙여 캐시를 갱신한다.

중앙 정적 UI-KIT HTML은 최종 제거하므로 별도 cache busting 값을 관리하지 않는다. Public/admin 조회 화면은 각각 기존 PHP helper의 `filemtime()` 기반 cache busting을 따른다.

## 단계별 작업 계획

1. 문서 기준을 “중앙 정적 UI-KIT 유지”에서 “public/admin 런타임별 UI-KIT으로 전량 이관 후 중앙 UI-KIT 제거”로 고정한다.
2. 기존 `assets/ui-kit`의 모든 HTML 파일과 섹션 목록을 작성한다.
3. 각 섹션을 public 조회 화면과 admin 조회 화면 양쪽으로 매핑한다.
4. 기존 중앙 UI-KIT의 모든 예시를 public/admin 런타임별 조회 화면으로 전량 이관한다. 일부만 선별하거나 삭제하지 않는다.
5. Admin 조회 화면을 `modules/admin/views/ui-kit.php`로 추가하고 실제 관리자 layout header/footer를 사용한다.
6. Public 런타임 미리보기를 `modules/admin/views/ui-kit-public.php`로 추가하고 `sr_public_layout_begin()` / `sr_public_layout_end()`를 사용한다.
7. Public 쪽에 대응 스타일이 없는 공통 원형은 admin 조회 화면/관리자 스타일을 기준으로 확인하고, public 런타임에 정식 반영할 때 public 소유 stylesheet로 이관한다.
8. 일반 public 런타임은 `modules/admin/assets/admin.css`를 직접 호출하지 않게 하고, admin domain selector는 public 범위에 맞게 정리한다. `/admin/ui-kit-public`은 이관 전 조회용 예외로 둔다.
9. 드롭다운/오버레이/탭처럼 상호작용이 필요한 예시는 양쪽 조회 화면에서 `assets/common-ui.js` 동작을 검증한다.
10. 토큰 값 표시가 필요하면 public/admin 조회 화면 각각에서 현재 런타임의 computed CSS custom property 값을 표시한다.
11. 전량 이관이 끝난 뒤 `assets/ui-kit` 디렉터리를 삭제한다.
12. `assets/common.css`가 중앙 UI-KIT manifest 용도로만 남았는지 확인하고, 실제 런타임에서 쓰지 않으면 제거한다.
13. `rg "assets/ui-kit|ui-guide|preview-utilities|UI-KIT"`로 잔여 참조를 확인하고 정리한다.
14. public/admin 조회 화면을 브라우저 스크린샷으로 검증한다.
15. 관련 문서와 필요 시 Wiki를 중앙 UI-KIT 제거 기준으로 갱신한다.

2026-05-18에 중앙 UI-KIT의 Tailwind식 미리보기 의존과 `preview-utilities.css`, `assets/ui-kit/js/common.js` 제거 정리를 진행했다. 이후 중앙 `assets/ui-kit` 방식 자체가 public/admin 실제 런타임 결과를 혼동시킨다는 결론에 도달했고, 기존 예시를 `/admin/ui-kit`과 `/admin/ui-kit-public`으로 전량 이관한 뒤 중앙 `assets/ui-kit`과 `assets/common.css`를 제거했다.

## 검증 기준

- Admin 조회 화면이 실제 관리자 layout과 CSS 호출 순서로 열린다.
- Public 런타임 미리보기가 실제 public layout과 CSS 호출 순서로 열린다.
- Public 런타임 미리보기의 공통 원형은 대응 public 스타일이 없을 때 admin 기준을 복사한 public 소유 stylesheet로 표시된다.
- Public 런타임이 `modules/admin/assets/admin.css`를 직접 호출하지 않는다.
- 기존 중앙 UI-KIT의 모든 카테고리와 예시가 public/admin 조회 화면 중 하나 이상으로 이관되어 있다.
- 중앙 `assets/ui-kit` 디렉터리가 제거되어 있다.
- UI-KIT 전용 CSS가 프로젝트 원형 클래스를 재정의하지 않는다.
- `assets/tokens.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css` 수정 결과가 관리자 UI-KIT 샘플에 바로 반영된다.
- 드롭다운 옵션은 새 마크업에서 `data-dropdown-*` 속성으로 표현된다.
- 드롭다운, 모달, 탭은 public/admin 조회 화면과 실제 프로젝트 화면에서 같은 공용 JS로 동작한다.
- 프로젝트 런타임은 중앙 UI-KIT shell CSS/JS를 호출하지 않는다.
- `preview-utilities.css`, `ui-guide.css`, 중앙 UI-KIT shell JS의 잔여 참조가 없다.
- UI-KIT 샘플과 조회 전용 CSS에는 Tailwind식 `ui-*` utility class가 남아 있지 않고, 조회 화면 전용 표현은 `ui-kit-*`로만 식별된다.
- `git diff --check`가 통과한다.
