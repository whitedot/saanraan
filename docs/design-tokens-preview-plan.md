# 디자인 토큰 미리보기 정밀 구현 계획

관리자 `디자인 토큰` 화면은 `assets/common.css`에 정의된 토큰과 공통 UI 클래스를 빠짐없이 확인하는 내부 점검 페이지로 둔다. 화면의 목적은 새 UI를 추가하기 전에 이미 정의된 디자인 토큰과 시맨틱 클래스를 확인하고, 관리자 화면에서 임의 스타일을 늘리지 않도록 기준점을 제공하는 것이다.

## 기준 자료

- 1차 기준은 `whitedot/chmedical.git`의 `docs/ui-kit`이다.
- 원격 저장소 접근에 인증이 필요하면 현재 개발 서버의 `/home/lab/www/ui-kit` 체크아웃을 임시 기준으로 삼는다.
- 비교 대상은 현재 프로젝트의 `assets/common.css`와 관리자 미리보기 화면 `/admin/design-tokens`이다.

`/home/lab/www/ui-kit` 기준으로 확인 가능한 UI Kit 구성은 다음 파일이다.

- `README.md`
- `index.php`
- `ui-buttons.php`
- `ui-badges.php`
- `form-elements.php`
- `form-validation.php`
- `ui-cards.php`
- `tables-static.php`
- `ui-tabs.php`
- `ui-modals.php`
- `ui-dropdowns.php`
- `ui-alerts.php`
- `ui-sidebar.php`
- `css/common.css`

## 현재 차이 확인

현재 `assets/common.css`는 원본 UI Kit의 주요 클래스 체계를 이어받되 saanraan 관리자 화면을 위해 일부 클래스를 추가한 상태다.

- 원본 기준 클래스는 현재 `assets/common.css`에 모두 포함되어 있다.
- 현재 프로젝트에는 관리자 폼, 토스트, 접근성 보조, 인라인 버튼 등 프로젝트 추가 클래스가 있다.
- 토큰은 원본의 Tailwind 내부 `--tw-*` 계열과 일부 기본 토큰이 줄어들었고, 프로젝트에서 `--radius-lg`, `--text-xl`, `--text-2xl` 계열을 추가했다.
- 따라서 미리보기는 원본 복제본이 아니라 `원본 UI Kit 기준 + saanraan 추가 항목`을 구분해 보여야 한다.

## 구현 방향

### 1. common.css 분석 고도화

단순 정규식 추출 결과를 그대로 나열하지 않고 다음 항목으로 분리한다.

- CSS custom property 토큰
- `:root` 기본값
- `[data-theme=dark]` 재정의값
- `@property --tw-*` 내부 속성
- 컴포넌트 클래스
- 상태 클래스와 조합 클래스

각 항목에는 가능한 경우 다음 상태를 붙인다.

- `원본 동일`
- `saanraan 추가`
- `원본 대비 누락`
- `값 변경`

### 2. 화면 정보 구조

`/admin/design-tokens` 상단에는 전체 요약을 둔다.

- 기준 CSS 경로
- 현재 CSS 경로
- 토큰 수
- 클래스 수
- 원본 대비 추가 수
- 원본 대비 누락 수
- 원본 대비 값 변경 수

본문은 UI Kit 원본 페이지 구조를 따라 다음 섹션으로 나눈다.

- 색상
- 타이포그래피
- 간격과 레이아웃
- 모서리
- 그림자
- 모션
- 버튼
- 배지
- 폼 컨트롤
- 유효성 및 피드백
- 카드
- 테이블과 페이지네이션
- 탭과 내비게이션
- 드롭다운
- 모달과 오버레이
- 알림과 토스트
- 유틸리티

### 3. 실제 렌더링 기준

미리보기는 `common.css`에 있는 시맨틱 클래스를 실제 조합 형태로 보여준다.

- 버튼은 `btn` 기본 클래스와 `btn-solid-*`, `btn-outline-*`, `btn-soft-*`, `btn-ghost-*`, `btn-surface-*` 계열을 조합해 렌더링한다.
- 배지는 `badge`, `badge-label` 등 실제 사용 조합을 표시한다.
- 폼은 `form-input`, `form-select`, `form-textarea`, `form-checkbox`, `form-radio`, `form-switch`, `form-range`, 파일 입력을 모두 표시한다.
- 탭은 `tab-nav-*`, `tab-trigger-*` 계열만 사용한다.
- 모달은 `modal-*`, `hs-overlay` 계열 구조를 원본 UI Kit와 같은 의미로 표시한다.
- 토스트와 관리자 피드백은 기존 `admin-flash-message-*`와 `data-admin-toast` 속성 체계를 기준으로 표시한다.

미리보기 페이지 자체의 배치에만 `admin-design-tokens-*` 클래스를 사용하고, 공통 컴포넌트 모양을 새 클래스로 재해석하지 않는다.

### 4. 원본 대조 방식

원본 UI Kit CSS와 현재 `assets/common.css`를 비교하는 내부 도우미를 둔다.

- 원본 경로가 존재하면 자동 비교한다.
- 원본 경로가 없으면 현재 `assets/common.css` 기준 목록만 표시하고 원본 비교 상태는 숨긴다.
- 비교 데이터는 화면 렌더링 직전에 PHP에서 생성해 view에 전달한다.
- 외부 네트워크 접근이나 GitHub 인증이 필요한 동작은 관리자 화면 요청 중 수행하지 않는다.

### 5. 관리자 문서와 운영 기준

`docs/admin-ui-guide.md`에는 다음 기준을 유지한다.

- 공통 UI를 새로 만들기 전에 `/admin/design-tokens`에서 기존 토큰과 클래스를 확인한다.
- 탭, 버튼, 토스트, 모달처럼 `common.css`에 시맨틱 클래스가 있는 UI는 기존 클래스를 먼저 사용한다.
- 관리자 전용 레이아웃만 `modules/admin/assets/admin.css`에 둔다.

## 검증 계획

구현 후 다음 순서로 확인한다.

1. `php -l modules/admin/actions/design-tokens.php`
2. `php -l modules/admin/views/design-tokens.php`
3. `php .tools/bin/check.php`
4. `/admin/design-tokens` 데스크톱 화면 확인
5. `/admin/design-tokens` 모바일 화면 확인
6. 버튼, 탭, 토스트, 모달이 `common.css`의 기존 시맨틱 클래스로 렌더링되는지 확인
7. 원본 UI Kit 경로가 없는 환경에서도 화면이 깨지지 않는지 확인

## 커밋 기준

정밀 구현은 기능 단위로 나누어 커밋한다.

1. 원본 대조 데이터 수집 로직
2. 디자인 토큰 화면 정보 구조 개편
3. 컴포넌트별 실제 렌더링 미리보기 보강
4. 관리자 UI 문서 보완

각 커밋 메시지는 한국어 Conventional Commits 형식을 사용한다.
