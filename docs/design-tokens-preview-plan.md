# UI-KIT 정적 변환 계획

루트 `/ui-kit`의 PHP 기반 UI-KIT을 개발자가 바로 참고할 수 있는 정적 HTML/CSS/JS 묶음으로 변환해 `assets/ui-kit` 아래에 둔다. 목적은 관리자 라우트나 PHP include 없이, 운영에서 공개 정적 파일로 허용된 `assets/` 경로에서 UI-KIT 구조와 컴포넌트 예시를 확인하는 것이다.

## 산출물

- 정적 HTML: `assets/ui-kit/*.html`
- UI-KIT 전용 CSS: `assets/ui-kit/css/ui-kit.css`, `assets/ui-kit/css/ui-guide.css`
- 공통 CSS: `assets/common.css`
- UI-KIT JS: `assets/ui-kit/js/common.js`, `assets/ui-kit/js/ui-kit/*.js`
- 이미지: `assets/ui-kit/images/*`

## 변환 규칙

- 원본 `/ui-kit/*.php`를 PHP 실행 결과 HTML로 렌더링한다.
- 내부 페이지 링크는 `.php`에서 `.html`로 바꾼다.
- `css/common.css`는 복사하지 않고 프로젝트 공통 파일인 `../common.css`를 참조한다.
- `css/ui-kit.css`, `css/ui-guide.css`, `js/`, `images/`는 `assets/ui-kit` 아래로 복사한다.
- footer의 PHP `filemtime()` 기반 query string은 제거하고 정적 JS 경로만 남긴다.
- `docs/`는 배포 보호 기준상 직접 접근이 차단되므로, 브라우저 접근용 산출물은 `assets/ui-kit` 아래에 둔다.

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

개발자는 `/assets/ui-kit/index.html` 또는 필요한 개별 HTML 파일을 브라우저에서 열어 확인한다.

## 검증 계획

1. `/assets/ui-kit/index.html`을 브라우저에서 연다.
2. 각 사이드바 링크가 `.html` 페이지로 이동하는지 확인한다.
3. `../common.css`, `css/ui-kit.css`, `css/ui-guide.css`가 로드되는지 확인한다.
4. 드롭다운, 모달, 탭, 테마 토글, 모바일 사이드바 JS가 동작하는지 확인한다.
5. `docs/` 직접 접근 차단은 그대로 유지한다.
