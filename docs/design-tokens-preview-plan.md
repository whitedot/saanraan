# UI-KIT 정적 변환 계획

루트 `/ui-kit`의 PHP 기반 UI-KIT을 개발자가 바로 참고할 수 있는 정적 HTML/CSS/JS 묶음으로 변환해 `assets/ui-kit` 아래에 둔다. 목적은 관리자 라우트나 PHP include 없이, 운영에서 공개 정적 파일로 허용된 `assets/` 경로에서 UI-KIT 구조와 컴포넌트 예시를 확인하는 것이다.

## 산출물

- 정적 HTML: `assets/ui-kit/*.html`
- 관리자 UI 레이어: `assets/admin-ui.css`
- 사용자 UI 레이어: `assets/public-ui.css`
- UI-KIT 전용 CSS: `assets/ui-kit/css/ui-kit.css`, `assets/ui-kit/css/ui-guide.css`
- 공통 CSS: `assets/common.css`
- UI-KIT JS: `assets/ui-kit/js/common.js`, `assets/ui-kit/js/ui-kit/*.js`
- 이미지: `assets/ui-kit/images/*`

## 변환 규칙

- 원본 `/ui-kit/*.php`를 PHP 실행 결과 HTML로 렌더링한다.
- 내부 페이지 링크는 `.php`에서 `.html`로 바꾼다.
- `css/common.css`는 복사하지 않고 프로젝트 공통 파일인 `../common.css`를 참조한다.
- `../admin-ui.css`, `../public-ui.css`를 함께 로드해 관리자/사용자 레이어가 독립적으로 진화할 수 있게 한다.
- `css/ui-kit.css`, `css/ui-guide.css`, `js/`, `images/`는 `assets/ui-kit` 아래로 복사한다.
- footer의 PHP `filemtime()` 기반 query string은 제거하고 정적 JS 경로만 남긴다.
- `docs/`는 배포 보호 기준상 직접 접근이 차단되므로, 브라우저 접근용 산출물은 `assets/ui-kit` 아래에 둔다.

## 맥락 구분 규칙

UI-KIT의 자산 구분과 항목은 원본 구조를 유지한다.

- `UI Components`: Buttons, Cards, Alerts, Badges, Modals, Dropdowns, Tabs, Sidebar
- `Forms`: Elements, Validation
- `Tables`: Static Tables
- `Icons`: Tabler Icons, Lucide Icons

대신 각 페이지 본문에서 다음 맥락을 구분한다.

- `Base`: `common.css`의 공통 토큰과 원형 컴포넌트
- `Admin`: `admin-ui.css`의 관리자 운영 화면 조합
- `Public`: `public-ui.css`의 사용자 화면 조합
- `Skin`: 각 스킨이 자기 범위 안에서 최종 표현을 조정하는 예시

처음부터 모든 컴포넌트를 완전히 분리 구현하지 않고, 페이지 상단에 맥락 안내를 두고 버튼, 폼, 테이블처럼 차이가 큰 항목부터 실제 비교 예시를 추가한다.

## 포함 페이지

- `index.html`
- `ui-buttons.html`
- `ui-cards.html`
- `ui-alerts.html`
- `ui-badges.html`
- `ui-modals.html`
- `ui-dropdowns.html`
- `ui-tabs.html`
- `ui-sidebar.html`
- `form-elements.html`
- `form-validation.html`
- `tables-static.html`
- `icons-tabler.html`
- `icons-lucide.html`

## 구현 상태

2026-05-14 기준으로 루트 `/ui-kit`을 `assets/ui-kit` 아래 정적 파일로 변환했다.
또한 `admin-ui.css`, `public-ui.css`를 추가하고 UI-KIT 페이지에서 Base/Admin/Public/Skin 맥락을 확인할 수 있게 했다.

개발자는 `/assets/ui-kit/index.html` 또는 필요한 개별 HTML 파일을 브라우저에서 열어 확인한다.

## 검증 계획

1. `/assets/ui-kit/index.html`을 브라우저에서 연다.
2. 각 사이드바 링크가 `.html` 페이지로 이동하는지 확인한다.
3. `../common.css`, `../admin-ui.css`, `../public-ui.css`, `css/ui-kit.css`, `css/ui-guide.css`가 로드되는지 확인한다.
4. 각 페이지 상단에서 Base/Admin/Public/Skin 맥락 안내가 보이는지 확인한다.
5. Buttons, Forms, Tables에서 실제 맥락별 예시가 보이는지 확인한다.
6. 드롭다운, 모달, 탭, 테마 토글, 모바일 사이드바 JS가 동작하는지 확인한다.
7. `docs/` 직접 접근 차단은 그대로 유지한다.
