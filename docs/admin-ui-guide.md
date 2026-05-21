# 관리자 UI 작성 기준

관리자 화면은 G5 Codex 계열의 관리자 UI 톤을 기준으로 맞춘다. public/admin 양쪽에서 쓰는 이름이 같은 기본 UI class라도 런타임 CSS 파일은 분리한다. 관리자 반복 UI 원형은 admin 모듈의 `modules/admin/assets/common.css`, 공개 반복 UI 원형은 `assets/common.css`가 각각 소유하고, 관리자 shell과 관리자 전용 배치는 `modules/admin/assets/admin.css`로 제한한다. `ui-kit.css` 파일명은 UI-KIT 조회 화면의 보조 스타일처럼 UI-KIT 자체에 남긴다. 모듈 도메인 고유 보정은 각 모듈의 `admin.stylesheets`로 분리한다.

- `assets/tokens.css`: 사이트 전반에서 재사용할 `--color-*`, `--spacing`, 타이포그래피, 반경, 그림자 토큰을 둔다.
- `assets/icons.css`: 프로젝트 기본 아이콘셋인 self-hosted Google Material Symbols Outlined 폰트와 `.sr-icon` 표시 규칙을 둔다.
- `modules/admin/assets/common.css`: 관리자 런타임의 reset/base와 `btn`, `card`, `table`, `badge`, `form-*`, `dropdown-*`, `modal-*`, `tab-*`, icon sizing 같은 반복 UI 원형을 둔다.
- `assets/common.css`: 공개 런타임의 reset/base와 반복 UI 원형을 둔다. 관리자 런타임과 파일을 공유하지 않는다.
- `assets/admin-ui.css`: `.admin-ui-scope` 안의 반복 가능한 관리자 작업 조합만 둔다.
- `modules/admin/assets/admin.css`: 관리자 shell, 사이드바, 상단바, 관리자 콘텐츠 폭, 목록/폼 배치 같은 admin 모듈의 실제 화면 구조를 둔다. 공용 UI 원형이나 모듈 도메인 class를 이 파일에 다시 넣지 않는다.
- `modules/{module_key}/assets/*.css`: 모듈 관리자 본문에서만 쓰는 도메인 고유 스타일을 둔다. 모듈은 `module.php`의 `admin.stylesheets`에 자기 `assets/` 아래 CSS만 선언하고, admin shell은 활성 모듈의 선언을 검증해 공통 관리자 CSS 뒤에 출력한다.

공개 화면 런타임은 `assets/tokens.css`, `assets/icons.css`, `assets/common.css`, `assets/saanraan.css`, `assets/public-ui.css`를 호출한다. `assets/icons.css`가 공용 아이콘 폰트를 맡고, `assets/common.css`가 공개 런타임 원형을 맡고, `assets/saanraan.css`가 공개 화면의 `--sr-*` 토큰과 기본 문서 스타일을 맡으며, `assets/public-ui.css`는 공개/회원 화면의 반복 UI 조합을 맡는다. 일반 공개 런타임과 `/ui-kit` 공개 UI-KIT 모두 관리자 런타임 CSS인 `modules/admin/assets/common.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css`를 직접 호출하지 않는다.

관리자/공개 런타임 CSS 호출은 PHP helper가 실제 파일의 `filemtime()` 값을 `?v=` query string으로 붙여 캐시를 갱신한다.

관리자 런타임 CSS 호출 순서는 `Pretendard`, `assets/tokens.css`, `assets/icons.css`, `modules/admin/assets/common.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css`, 활성 모듈의 `admin.stylesheets` 선언 순서다. 활성 모듈 stylesheet는 `/modules/{module_key}/assets/*.css` 안쪽 파일만 허용하고 외부 URL, `..` 경로, 모듈 폴더 밖 파일은 무시한다.

프로젝트 기본 아이콘셋은 Google Material Symbols Outlined다. 아이콘 폰트는 `assets/fonts/material-symbols-outlined.ttf`로 self-hosting하고 public/admin 런타임에서 preload한다. `sr_material_icon_html()`로 출력한 `.sr-icon[data-sr-material-icon]`은 `sr_material_icon_bootstrap_script()`가 폰트 준비 완료 class를 붙이기 전까지 투명하게 유지하므로, `home`, `settings` 같은 Material ligature 글자가 로딩 중 잠깐 보이지 않는다.

Material Symbols는 메뉴, 툴바, 본문 액션처럼 페이지에서 독립 아이콘을 표시할 때 사용한다. 체크박스 체크 표시, 드롭다운 caret, 스위치 thumb처럼 컴포넌트 내부 상태나 조작 힌트는 해당 컴포넌트 CSS가 소유하며 Material 아이콘으로 대체하지 않는다. 드롭다운 caret처럼 여러 위치에서 재사용하는 방향 화살표는 `sr_ui_arrow_icon_html()` helper로 출력한다.

드롭다운, 오버레이/모달, 탭처럼 관리자와 공개 화면에서 함께 쓸 수 있는 기본 상호작용은 `assets/common-ui.js`에 둔다. 관리자 UI-KIT과 public 런타임 미리보기도 이 파일을 호출해 같은 동작 원형을 확인한다.

관리자 디자인 책임은 admin 모듈에 둔다. 각 모듈의 관리자 view는 본문 마크업과 도메인 출력만 맡고, 관리자 shell, 사이드바, 상단바, 공통 관리자 asset, 관리자 콘텐츠 컨테이너는 admin skin이 맡는다. 현재 관리자 skin은 `admin_skin_key`로 선택하며, 등록된 key가 없거나 파일이 없으면 `basic`으로 fallback한다.

관리자 shell은 화면을 편하게 그리기 위한 목적으로 view 출력 뒤 `DOMDocument`로 HTML을 다시 해석하거나 class를 주입하지 않는다. 폼 행, 카드 헤더, 테이블 wrapper, 체크박스 보조 텍스트처럼 의미가 있는 구조는 view가 최종 마크업으로 직접 출력해야 한다.

렌더 후 DOM 처리가 필요한 경우는 별도 예외로 다룬다. 예를 들어 보안 정화, 외부 HTML의 제한적 변환, 편집기 콘텐츠 처리처럼 입력 자체가 HTML이고 변환 목적이 명확한 경우에는 호출 위치와 책임 모듈을 드러내고, 허용 범위와 테스트를 함께 둔다. 단순히 관리자 화면의 반복 마크업을 줄이거나 class를 자동 보정하기 위한 후처리는 사용하지 않는다.

CSS class는 범위를 드러내는 접두어를 사용한다.

- 관리자 반복 UI는 `btn`, `card`, `table`, `badge`, `form-*`처럼 `modules/admin/assets/common.css`에 직접 둔다. 공개 런타임의 같은 이름 class는 `assets/common.css`에서 따로 소유한다.
- 관리자 shell과 관리자 전용 배치는 `admin-*` 접두어를 사용하고 `modules/admin/assets/admin.css`에 둔다.
- 모듈별 관리자 본문에서 도메인 고유 스타일이 필요하면 `{module_key}-admin-*` 또는 `sr-{module_key}-admin-*` 형식을 사용하고, 해당 CSS는 모듈의 `assets/` 아래 stylesheet에서 소유한다.
- 관리자 view는 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않는다.
- 탭처럼 공통 CSS에 이미 정의된 반복 UI는 `tab-nav-*`, `tab-trigger-*` 같은 기존 시맨틱 클래스를 먼저 사용한다. 탭 전환 화면은 초기 HTML에서 `role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-controls`, `aria-labelledby`, `aria-selected`를 직접 출력하고, `data-admin-tabs` 스크립트는 선택 상태와 키보드 이동만 보강한다. 토스트는 기존 관리자 메시지 클래스인 `admin-flash-message-*`에 `data-admin-toast` 동작 속성만 더해 사용하고, 위치와 닫기 버튼 배치는 `data-admin-toast-*` 속성 선택자로 처리한다.
- UI-KIT 조회 화면의 배치와 예시 상태 표시처럼 실제 컴포넌트 원형이 아닌 표현은 `ui-kit-*` 접두어로만 둔다. `ui-bg-*`, `ui-text-*`, `ui-grid`, `ui-flex`, `ui-gap-*` 같은 Tailwind식 범용 utility 표현은 관리자/공개 UI-KIT 샘플에 사용하지 않는다.
- 공통 UI를 변경하거나 새 관리자 화면에서 UI 조합을 확인할 때는 `/admin/ui-kit` 관리자 조회 화면과 `/ui-kit` public 런타임 UI-KIT에서 런타임별 결과를 확인한다.

버튼, 배지, 탭, 드롭다운, 모달처럼 UI-KIT 샘플과 실제 화면이 함께 쓰는 컴포넌트는 기본, hover, focus-visible, disabled 상태를 관리자에서는 `modules/admin/assets/common.css`, 공개에서는 `assets/common.css`가 각각 소유한다. `btn-solid-*`, `btn-outline-*`, `btn-soft-*`, `btn-ghost-*`, `btn-gradient-*`, `btn-icon`, `badge`, `tab-trigger-*`, `dropdown-*`, `modal-*` 계열을 새로 쓰면 `/admin/ui-kit`과 `/ui-kit`에서 상태별 표현을 함께 확인한다.

UI-KIT 샘플은 외부 아이콘 런타임에 의존하지 않는다. 실제 아이콘 자리에는 self-hosted Material Symbols helper인 `sr_material_icon_html()`을 사용하고, 샘플 설명이나 임시 보조 표식처럼 아이콘 자체가 목적이 아닌 위치에만 텍스트 보조 라벨을 둔다.

## 화면 내 이동 링크

사이드 메뉴에 이미 등록된 관리자 화면 이동 링크는 본문 상단에 다시 만들지 않는다. 예를 들어 회원 그룹의 그룹 목록, 자동 규칙, 재평가, 수동 배정처럼 사이드 메뉴가 1차 탐색을 제공하는 화면은 각 view 안에서 `admin-local-nav` 버튼 묶음을 반복하지 않는다.

`admin-local-nav`는 사이드 메뉴에 없는 같은 화면 안의 하위 흐름이나 필터성 이동에만 사용한다. 상태 요약 링크, 별도 사이드 메뉴 항목이 없는 발송 대기열 같은 경우가 여기에 해당한다. 잔액/조정/거래 내역처럼 독립 운영 화면으로 자주 오가는 흐름은 사이드 메뉴 항목으로 둔다. 새 항목 추가, 수정, 목록 복귀처럼 현재 작업의 직접 액션은 카드 헤더나 폼 액션 영역에 둔다.

사이드 메뉴와 같은 전체 화면 이동 버튼을 본문 상단에 반복하지는 않지만, 현재 데이터 컨텍스트를 유지하는 액션 링크는 본문에 둘 수 있다. 예를 들어 회원 자산 화면에서 특정 회원을 조회한 뒤 같은 `account_identifier`로 잔액, 조정, 거래 내역을 오가는 버튼이나 잔액 목록 행의 `조정`, `거래 내역` 버튼은 허용한다.

## 텍스트 색상과 크기

관리자 화면의 기본 글자색은 `html`, `body`, `.admin-content`에서 상속되는 `--color-body-color`를 기준으로 한다. 기본 글자 크기는 `--text-body`인 `16px`를 기준으로 한다. 일반 본문, 테이블 셀, 테이블 헤더, 폼 라벨, 필터 라벨, 카드 제목에는 별도 회색 색상이나 작은 글자 크기를 반복해서 지정하지 않는다.

옅은 색상은 의미가 분명한 보조 텍스트에만 사용한다. 예를 들어 `hint-text`, `muted-text`, `admin-form-help`, `admin-card-subtitle`, 빈 상태 안내, 보조 메타 정보처럼 본문보다 우선순위가 낮은 텍스트가 여기에 해당한다. 성공, 경고, 위험, 링크, 활성 탭처럼 상태나 상호작용을 나타내는 색상은 예외로 유지한다.

`var(--text-xs)`, `var(--text-sm)`, `.8125rem`처럼 기본보다 작은 크기는 화면 밀도나 정보 계층이 분명한 경우에만 사용한다. 도움말, 힌트, 보조 메타, 상태 배지, 사이드바 보조 메뉴, 테이블 안의 부가 정보가 여기에 해당한다. 새 관리자 화면의 본문과 입력 라벨은 먼저 16px 상속을 기준으로 확인한다.

관리자 런타임의 기본 입력 필드는 class 없는 `input`, `select`, `textarea`가 아니라 `.form-input`, `.form-select`, `.form-textarea`, `.form-checkbox`, `.form-radio` 시맨틱 클래스를 사용한다. 이 클래스들은 다른 요소의 자식 선택자가 아니라 `admin.css`의 직접 선택자로 스타일을 소유한다. class 없는 필드 선택자는 기존 화면 호환을 위한 fallback으로만 남긴다.

## 사이드바

사이드바는 모듈의 `admin.category_label`을 라벨로만 표시하고, 실제 조작 가능한 메뉴는 `모듈 그룹 > 메뉴 항목`의 2단계 구조를 기본으로 유지한다. 단, 하위 메뉴 항목이 하나뿐인 모듈 그룹은 그룹 자체를 해당 항목 링크로 렌더링해 바로 화면을 연다. 라벨은 시스템, 회원, 사이트, 커뮤니티, 운영처럼 메뉴를 구분하는 시각적 구획일 뿐이며 접기/펼치기 버튼이나 링크로 사용하지 않는다. 루트 경로에서 열 초기화면 후보 선택은 사이트 설정의 `화면` 섹션에서 다룬다.

`/admin/menu`에서 저장한 순서와 숨김 설정은 기본 선언보다 우선한다. 다만 번들 메뉴 구조를 정리하면서 생긴 과거 기본 순서값은 사용자 커스텀 순서로 보지 않고 새 기본 순서를 따른다.

관리자 메뉴 표시 설정 화면은 분류, 모듈 그룹, 항목을 같은 저장 표에서 다루되 대상 칸에서 들여쓰기와 보조 경로로 위계를 드러낸다. 순서 드래그는 같은 범위와 같은 부모 안에서만 가능하게 유지하고, 이동 가능한 위치에만 고스트 라인을 표시한다. 분류나 모듈 그룹을 드래그하면 하위 행도 같은 블록으로 함께 이동하며, 저장 후 다시 펼칠 때도 부모와 자식 행이 분리되지 않아야 한다. 저장/초기화 액션 영역은 긴 메뉴 목록에서도 접근 가능하도록 화면 하단에 sticky로 유지한다. 기본값 초기화는 메뉴 표시 순서와 숨김 설정이 모두 초기화된다는 안내에 동의한 경우에만 처리한다.

관리자 UI-KIT은 일반 메뉴 트리에 넣지 않고 사이드바 메뉴 목록 아래쪽의 보조 링크로 노출한다. UI-KIT은 실제 업무 메뉴가 아니라 관리자 UI 점검 화면이므로 메뉴 순서/숨김 오버라이드 대상과 분리한다.

관리자 메뉴 아이콘의 표현 선택권은 각 모듈에 둔다. 모듈은 `module.php`의 `admin.icon`에 관리자 shell이 제공하는 심볼 이름을 선언하거나, 자기 모듈의 `assets/` 아래에 둔 `png`, `webp` 파일을 선언할 수 있다. 관리자 shell은 선언을 그대로 믿지 않고 허용된 심볼과 모듈 내부 자산 경로만 렌더링하며, 선언이 없거나 유효하지 않으면 카테고리 기본 아이콘으로 fallback한다.

관리자 메뉴 심볼 이름 목록과 Material Symbols 매핑은 `modules/admin/helpers/icons.php`의 공통 계약이 소유한다. admin skin은 심볼 이름을 직접 해석하지 않고 helper 매핑을 통해 Material 아이콘 이름으로 변환한다. 기본 표현은 Material Symbols다.

모듈 메뉴에 허용되는 심볼 이름은 `settings`, `admin-mode`, `users`, `user`, `content`, `stats`, `home`, `folder`, `image`, `layers`, `search`, `menu-list`, `bell`, `shield`, `coins`, `wallet`, `gift`, `message-circle`이다. 툴바 전용 심볼은 같은 helper 계약에 있더라도 `module_menu`가 꺼져 있으면 모듈 선언에서 사용할 수 없다.

아이콘은 메뉴 텍스트를 보조하는 장식 요소로 취급한다. 접근 가능한 이름은 메뉴 label이 맡고, 이미지 아이콘도 `alt=""`와 `aria-hidden="true"`로 출력한다.

모듈 전역 설정은 가능하면 목록 화면 안에 배치하지 않고 독립 설정 화면으로 둔다. 이때 `admin-menu.php`에는 `목록`, `설정`처럼 같은 모듈 그룹 안의 별도 항목을 선언하고, 설정 저장 POST는 설정 화면의 action이 소유하게 한다.

## 사이트/모듈 설정 화면

사이트 설정 화면은 사이트 이름, 시간대, locale, 운영 상태, 공개 화면 레이아웃, UI 색상 모드, 관리자 스킨처럼 운영자가 의미를 이해하고 직접 변경할 수 있는 전용 필드만 다룬다. 시간대와 locale은 자유 입력이 아니라 사용 가능한 값 중에서 선택하는 select로 제공한다. 공개 화면과 관리자 화면 표시 옵션은 같은 `화면` 섹션에서 한 번에 저장한다. 탑바 다크 모드 전환 버튼은 `ui_color_scheme` 값을 라이트/다크로 즉시 저장하는 빠른 전환이며, 시스템 설정 모드는 사이트 설정 화면에서 선택한다. `sr_site_settings`는 내부 저장소로 유지하지만, 임의 key/value를 추가·삭제하는 범용 관리자 화면은 제공하지 않는다.

코어나 모듈이 사이트 범위 설정을 추가로 필요로 하면 먼저 명확한 전용 관리자 화면, 설정 schema, 업데이트 스크립트, 또는 소유 모듈의 설정 화면을 만든다. 기능 플래그나 외부 연동 옵션처럼 운영자가 판단해야 하는 값도 의미, 허용 값, 검증 규칙이 드러나는 전용 UI로 승격한다. 범용 key/value UI로 전용 화면 책임을 우회하지 않는다.

전용 UI로 승격할 기준:

- 설정 의미를 설명해야 한다.
- 값 형식이 복잡하다.
- 실수하면 운영 장애가 난다.
- 여러 필드가 함께 저장되어야 한다.
- 모듈 도메인 정책과 연결된다.

전용 UI가 있는 모듈 전역 설정은 목록/검색/행 관리 화면에 섞지 않고 별도 설정 화면과 저장 action으로 분리한다. 예를 들어 배너 기본 스킨은 `/admin/banners/settings`, 팝업레이어 기본 스킨은 `/admin/popup-layers/settings`가 소유한다.

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
            <label class="form-label" for="example_title">이름</label>
            <div class="admin-form-field">
                <input id="example_title" type="text" name="title" required>
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
- 일반 입력, 셀렉트, 텍스트 영역처럼 한 행에 주 컨트롤이 하나인 경우 `admin-form-row` 바로 아래에 `label.form-label`을 두고, `admin-form-field` 바로 아래에 컨트롤을 둔다. 이때 `label`의 `for`와 컨트롤의 `id`를 연결하고, 숨김 라벨을 감싼 중첩 `label`은 만들지 않는다.
- 체크박스와 라디오처럼 컨트롤 옆 문구가 실제 레이블인 항목은 좌측 설명 칸과 우측 조작 레이블을 view에 직접 둔다. 좌측에는 전체 문구를 그대로 표시하고, 우측의 시각적 레이블은 `허용`, `사용`, `확인했습니다.`처럼 필요한 부분만 보이게 줄인다. 우측에서 생략된 맥락은 `.sr-only` 텍스트로 남겨 보조기기가 전체 의미를 읽을 수 있게 한다.
- 체크박스, 라디오, 여러 입력이 묶인 복합 행처럼 좌측 문구가 특정 입력 하나에 직접 연결되지 않는 경우 `admin-form-row` 바로 아래에 `span.form-label`을 둔다.
- 좌측 레이블이 필요 없는 안내 문장은 입력항목 행으로 만들지 말고 일반 텍스트 문단으로 둔다.
- 저장, 생성, 변경 버튼은 `admin-form-sticky-actions` 안에 두되 문서 흐름 안에서 출력한다.
- 목록 화면의 검색 폼, 테이블 행의 상태 변경/삭제 폼, 툴바 폼은 페이지 폼 레이아웃을 적용하지 않는다.

체크박스 행 예시는 다음처럼 작성한다.

```php
<div class="admin-form-grid">
    <div class="admin-form-row">
        <span class="form-label">공개 회원가입 허용</span>
        <div class="admin-form-field">
            <label class="admin-form-check form-label">
                <input type="checkbox" name="allow_registration" value="1" class="form-checkbox">
                <?php echo sr_admin_choice_label_html('공개 회원가입 허용'); ?>
            </label>
        </div>
    </div>
</div>
```

회원 설정의 선택 프로필 항목처럼 한 항목에 여러 불리언 옵션이 붙는 경우에도 같은 `admin-form-row` 안에 체크박스를 나란히 둔다. 선택 프로필은 항목별로 `보이기`와 `필수입력`을 제공하며, `필수입력`은 `보이기`가 켜진 항목에만 유효하다.

## 목록 화면

목록형 화면은 공통 관리자 목록 원형을 기준으로 맞춘다.

- 사이드 메뉴에 없는 하위 흐름이나 필터성 이동 링크는 `admin-local-nav-wrap`과 `admin-local-nav`를 사용한다.
- 목록 테이블은 `admin-card card` 또는 `admin-list-card admin-card card` 섹션 안에서 `table-wrapper`와 `table`을 사용한다.
- 테이블 전체를 감싸는 일괄 작업 폼은 `admin-list-form`을 사용한다.
- 행 단위 관리 버튼은 `admin-table-actions-cell`과 `admin-row-actions` 안에 둔다.

목록 위 필터는 테이블 카드 안에서 임의의 문단으로 붙이지 않고 `form.admin-filter.ui-form-theme > .admin-filter-grid > .admin-filter-field` 구조를 사용한다. 제목이나 초기화 버튼이 필요하면 `.admin-filter-header`를 먼저 둔다. 필터가 목록 범위를 바꾸는 조건일 때는 목록 위에 두고, 화면 전체 범위를 바꾸는 조건일 때는 목록 섹션 바깥에 둔다.

저장, 삭제, 적용 같은 짧은 결과 안내는 `sr_admin_feedback_toasts($notice, $errors)`를 사용해 토스트로 출력한다. 화면 본문에 영구적으로 남아야 하는 설명과 작업 결과 피드백을 섞지 않는다.

## 대시보드

관리자 대시보드는 기본 운영 섹션과 활성 모듈의 `dashboard.php` 계약 섹션을 함께 표시한다. 각 섹션은 `data-admin-dashboard-section` 값을 가진 독립 카드로 두고, 사용자가 드래그 앤 드롭으로 순서를 바꾸면 브라우저 `localStorage`에 표시 순서가 저장된다.

모듈별 대시보드 섹션의 내부 구성은 모듈이 소유한다. 모듈은 `dashboard.php`에서 `view`로 모듈 내부 `views/*.php` 파일을 지정할 수 있고, admin 모듈은 검증된 view를 대시보드 섹션 안에 include한다. admin 모듈은 외곽 wrapper, 이동 핸들, 정렬 저장을 맡고, 모듈 view는 제목 배치, 지표 구성, 링크, 도메인별 리듬을 직접 렌더링한다. 필요한 스타일은 각 모듈의 `admin.stylesheets`로 선언한 모듈 내부 CSS에서 소유한다.

모듈은 `dashboard.php`에서 `default_visible`로 기본 노출 여부를 선언할 수 있다. 대시보드의 섹션 관리 UI는 표시/숨김을 브라우저 `localStorage`에 저장하며, 사용자가 저장한 값이 있으면 모듈 기본값보다 우선한다. 섹션을 숨기면 grid가 즉시 남은 섹션으로 재계산되고, 한 행에 남은 섹션 수에 따라 3열, 2열, 1열 전체 폭으로 보정한다. 숨긴 섹션을 다시 표시하면 대시보드 맨 마지막에 한 줄 전체를 차지하는 섹션으로 추가한다.

`view`가 없는 기존 모듈은 admin 모듈의 fallback layout으로 렌더링한다. fallback의 `table`은 기존 `항목 / 주요 수치 / 상세` 표이고, `stats`는 지표 카드형 요약이다. 알 수 없는 layout, state, emphasis 값은 admin 모듈이 안전한 기본값으로 되돌린다.

대시보드 섹션은 화면 구조를 바꾸는 관리 도구가 아니라 개인 작업 배치에 가깝다. 따라서 드래그 순서는 DB에 저장하지 않고, 모듈이 제공하는 기본 표시 순서는 `dashboard.php`의 `order` 값으로 유지한다. 섹션 이동은 섹션 전체가 아니라 섹션의 아이콘 핸들에서만 시작하며, 관리자 대시보드 본문은 최대 3열 그리드로 배치한다. 다른 섹션의 좌우에 놓을 때는 수직 고스트 라인을 표시하고 일반 폭으로 배치하며, 1열 전체 폭 섹션의 좌우에 놓으면 기존 1열 섹션과 이동 섹션을 같은 행의 2열로 전환한다. 위아래에 놓을 때는 행 사이 슬롯 중앙에 수평 고스트 라인 하나만 표시하고 한 줄 전체를 차지하는 1열 섹션으로 배치할 수 있다. 드롭 결과는 드롭 순간의 마우스 위치를 다시 해석하지 않고 마지막으로 표시된 고스트 라인의 위치를 기준으로 결정한다. 고스트 라인은 드래그 중 카드 배치를 밀지 않는 overlay로만 표시한다.
