# AGENTS.md

## 프로젝트 이름

이 프로젝트의 이름은 `saanraan`입니다.

데이터베이스 테이블과 공통 프로젝트 네임스페이스가 필요한 관련 식별자에는 `sr` 접두사를 사용합니다.

예:

- `sr_site_settings`
- `sr_modules`
- `sr_module_settings`
- `sr_member_accounts`

특정 호환성 이유가 없다면 `core_` 같은 일반 접두사나 `member_` 같은 모듈 전용 접두사를 데이터베이스 테이블명에 사용하지 않습니다.

## 아키텍처 원칙

- 절차형 PHP 개발에 친화적인 코드베이스를 유지합니다.
- PHP, vanilla JavaScript, plain CSS를 우선합니다.
- 저비용 공유 웹 호스팅을 지원 배포 환경으로 가정합니다.
- 회원 인증은 기본 제공 모듈이라도 하나의 모듈로 취급합니다.
- 영리한 코어보다 읽기 쉬운 코어를 우선합니다.
- 요청 흐름은 자동 등록 뒤에 숨기지 말고, 파일을 읽어 따라갈 수 있게 유지합니다.
- 코어 기능을 늘리기보다 명확한 모듈 경계를 우선합니다.
- 코어는 관리 시스템이 아니라 작은 실행 기반으로 유지합니다.
- 코어는 요청 진입점, 설치/업데이트 흐름, DB 연결, 설정 조회, 모듈 조회, 보안 헬퍼, 번역 헬퍼, 출력 슬롯, 공통 운영 헬퍼를 제공할 수 있습니다.
- 코어는 게시글, 페이지, 상품, 주문, 포인트, 쿠폰, 댓글, 카테고리, 메뉴, SEO 점수, 분석, 콘텐츠 워크플로 같은 도메인 개념을 소유하지 않습니다.
- 도메인 테이블, 도메인 관리자 화면, 도메인 권한, 도메인 정책은 해당 도메인을 소유한 모듈에 둡니다.
- 향후 커뮤니티, 커머스, 콘텐츠, 마케팅, 분석 모듈이 필요할 수 있다는 이유만으로 코어나 회원 테이블에 필드를 추가하지 않습니다.
- 여러 모듈이 같은 기능을 필요로 하면 먼저 좁은 헬퍼나 계약을 정의합니다. 진짜로 범용적이고 도메인 정책이 없을 때만 코어로 승격합니다.
- 관리자 화면은 코어와 모듈 작업을 조율할 수 있지만, 도메인별 관리는 소유 모듈에 둡니다.
- 공유 테이블을 넓히기보다 `account_id` 같은 안정적인 식별자로 연결되는 모듈 소유 확장 테이블을 우선합니다.

## 범위 통제

- 사용자가 가장 최근에 밝힌 범위를 기준으로 삼습니다. 이전의 넓은 목표를 좁히거나 정정했다면 최신 경계에서 멈춥니다.
- 사용자가 명시적으로 범위를 넓히지 않는 한, 요청된 이슈, 기능, 파일 집합, 수용 기준만 검토하고 변경합니다.
- 현재 목표에 필요하지 않은 보안, 개인정보, 정리, 리팩터링, 품질 개선은 인접해 있더라도 노트로만 남깁니다.
- 사용자가 작업이 과도하거나 비현실적이거나 목표에서 벗어났다고 말하면 자율 변경을 즉시 멈춥니다. 명확한 범위 이탈 미커밋 변경만 되돌린 뒤, 더 좁은 목표를 묻거나 기다립니다.
- 더 많은 일을 찾기보다 요청된 최종 상태를 증명하는 것을 우선합니다. 목표가 충족되고 검증되면 추가 탐색을 이어가지 말고 완료를 보고합니다.

## PHP 스타일

- 요청 흐름은 절차형 PHP로 읽기 쉽게 유지합니다.
- 숨겨진 dispatch 흐름보다 직접적인 `if` / `elseif` 요청 분기나 명시적인 `include` 파일을 우선합니다.
- 기본 라우팅 모델로 `sr_route()` 같은 route registration API를 사용하지 않습니다.
- 모듈이 라우팅 가능한 핸들러를 노출한다면, 허용된 method/path와 handler 매핑을 반환하는 평범한 배열 파일을 우선하고 명시적으로 검증 후 include합니다.
- PHP short tag나 short echo tag를 사용하지 않습니다.
- `<?= ... ?>` 대신 `<?php echo ...; ?>`를 사용합니다.
- `echo <<<HTML` 같은 heredoc 문자열로 전체 HTML 레이아웃을 렌더링하지 않습니다.
- 뷰 출력은 PHP를 닫고 일반 HTML을 작성하되, 값이 필요한 곳에만 작은 `<?php echo ...; ?>` 블록을 사용하는 방식을 우선합니다.
- 사용자 입력 또는 변수 값을 출력하기 전에 escape합니다.

## UI 날짜와 시간 표시

- public/admin UI에서 작성/생성 시각은 빠른 스캔에 도움이 되는 경우 `며칠 전`, `몇시간 전`, `방금 전` 같은 한국어 상대 시간으로 표시합니다.
- 원래의 정확한 시각은 마크업에 보존하고, `<time>`의 `title` 속성처럼 hover 또는 click으로 볼 수 있게 합니다.
- 가능하면 정확한 시각을 escape하고 machine-readable 형태로 유지합니다. 예: `<time datetime="...">상대 시간</time>`.

## Public UI와 레이아웃

- public 모듈 화면에서 관리자가 레이아웃을 선택할 수 있다면, 현재 화면의 모듈이 아니라 선택된 `layout_key` 제공자가 소유한 layout shell stylesheet를 로드합니다. 예를 들어 `content.*` 레이아웃을 쓰는 community 화면은 `/modules/content/assets/layout.css`를 로드해야 합니다.
- 모듈 public 화면이 common public layout 안에서 렌더링된다면 `/assets/layout.css`, header 마크업, footer 마크업, public layout script는 common layout이 소유합니다. 모듈은 선택된 layout shell 안에서 필요한 reset, UI kit, module body, skin 스타일만 추가합니다.
- 모듈 layout shell의 CSS, 마크업, JavaScript는 네임스페이스를 맞춥니다. 예를 들어 `quiz.*` 레이아웃은 `quiz-layout-*` selector와 `/modules/quiz/assets/layout.css`를 사용하고, `common.*`은 common `public-layout-*` shell을 사용합니다.
- public layout을 제공하는 모듈은 public layout, UI kit, module, skin asset에서 `public-*`, `public-ui-*`, `sr-public-*` class/data 네임스페이스를 사용하지 않습니다. `content-*`, `community-*`, `quiz-*`, `survey-*` 같은 모듈 네임스페이스를 사용합니다.
- public layout을 제공하는 모듈은 layout JavaScript와 module/page JavaScript를 별도 파일로 유지합니다. layout template은 `/modules/{provider}/assets/layout.js`를 소유하고, public 모듈 화면은 layout context를 통해 `/modules/{module_key}/assets/module.js`를 추가합니다.
- UI kit이 `card`, `card-body`, `card-img-top`, button, badge, tab, dropdown, form control class 같은 시맨틱 컴포넌트를 이미 제공한다면 그 컴포넌트를 직접 사용합니다. 모듈 CSS는 화면 레이아웃, grid 배치, 모듈 고유 텍스트 구조, UI kit이 소유하지 않는 상태만 담당합니다.
- public/admin UI에서 저장, 수정, 삭제, 발송, 신고, 차감 실패 같은 action 결과 메시지는 화면 본문에 일반 `<p>`나 `<ul>`로 고정 표시하지 말고 토스트 메시지로 표시합니다. 토스트 표면은 별도 장식 컴포넌트를 새로 만들기보다 UI kit의 `alert`, `alert-success`, `alert-danger`, `alert-warning`, `alert-info`, `alert-removable` 같은 alert 컴포넌트를 사용하고, 위치와 모듈 네임스페이스만 모듈 CSS에서 담당합니다.
- action 결과 메시지를 보여주는 POST form은 성공과 validation failure 모두에서 Post/Redirect/Get을 우선합니다. 실패 시 입력값을 유지해야 한다면 짧은 세션 flash로 오류와 필요한 입력값을 넘기고 GET 화면에서 토스트로 표시해, 새로고침이 form 재전송을 일으키지 않게 합니다.

## 테마 토큰

- 다크 모드는 시각적 사후 보정이 아니라 1급 상태로 취급합니다. public/admin UI 색상을 변경할 때는 `data-color-scheme="light"`와 `data-color-scheme="dark"` 모두에서 foreground, border, focus, icon, interactive 상태의 가독성을 유지해야 합니다.
- public/admin UI의 색상, 표면, border, icon, focus, hover/active/selected 상태를 만들거나 변경할 때는 사용자가 별도로 요청하지 않아도 항상 라이트 모드와 다크 모드 대응을 함께 작업합니다.
- 테마 인식 UI에는 `--sr-text`, `--sr-muted`, `--sr-muted-strong`, `--sr-border`, `--sr-border-soft`, `--sr-surface`, `--sr-surface-muted`와 danger, warning, success, info 같은 상태 토큰을 우선합니다. public themed surface에 나타나는 요소에서 모듈 CSS는 `--text-strong`, `--text-muted`, `--color-body-color`, `--color-default-*` 같은 light-only UI kit 토큰을 `--sr-*`보다 우선하지 않습니다.
- fallback이 필요하면 theme-aware 토큰을 먼저, UI kit/static 토큰을 뒤에 둡니다. 예: `color: var(--sr-text, var(--text-strong, var(--color-default-900)));`.

## 배경 처리

- 원하지 않는 배경을 제거할 때는 "요청되지 않은 모듈 표면 채움"과 "테마 의미"를 구분합니다. 보이는 배경을 제거한다는 이유만으로 `color-scheme`, `data-color-scheme`, 다크 모드 저장값, 테마 변수를 비활성화하거나 강제로 덮어쓰지 않습니다.
- `card`, dropdown, modal, form control, badge, selected-control surface처럼 UI kit 시맨틱 컴포넌트가 소유한 표면은 유지합니다. 모듈 전용 장식 채움은 명시적인 컴포넌트, 의미 상태, 요청된 디자인에 속하지 않을 때만 제거합니다.
- avatar fill, notification badge, validation/status message, modal backdrop, sticky-header stuck 상태는 의미적 또는 기능적 배경색을 유지할 수 있습니다.
- sticky header는 요청된 디자인이 다르게 말하지 않는 한 기본 unstuck 상태를 투명하게 유지하고, stuck 상태에서만 surface background를 적용합니다.

## 문서화

- 동작, 기능, 데이터베이스 스키마, 관리자 화면, 모듈 계약, 요청 흐름, 보안/개인정보 정책, 배포 가정, 운영 절차를 변경하면 같은 작업 항목에서 관련 저장소 문서를 업데이트합니다.
- 최소한 DB specification, administrator screen field guide, developer guides, request flow, module development guide, security/privacy guide, testing guide, deployment guide, troubleshooting guide에 영향이 있는지 확인합니다.
- 현재는 GitHub Wiki를 운영 문서의 정본으로 사용하지 않습니다. 1.0 배포 전 문서 기준은 저장소 `docs/`와 루트 안내 문서입니다.
- 시각적 변경, CSS-only 변경, 내부 구현 변경은 운영자 동작, public contract, configuration, schema, request flow, deployment assumptions를 바꾸지 않는다면 문서 업데이트가 필요하지 않습니다. 유용할 때 최종 응답이나 커밋 본문에 문서 생략 판단을 언급합니다.
- repository docs는 초기 계획이 아니라 현재 구현과 맞춥니다.

## 검증과 스모크 테스트

- 코드 변경 후 완료를 보고하기 전에 관련 자동 검사를 실행합니다. PHP를 사용할 수 있고 문서 전용 변경이 아니라면 최소한 `php .tools/bin/check.php`를 실행합니다.
- 문서 전용 변경은 최소한 `git diff --check`를 실행하고, 렌더링될 Markdown 구조 또는 diff에서 깨진 heading, list, link, 실수로 노출된 credential이 없는지 확인합니다.
- 로컬 커밋이나 코드 변경이 포함된 working tree를 검토할 때는 자동 검사와 smoke-test 상태를 포함합니다. 검사나 smoke test를 건너뛰었다면 구체적인 이유를 적습니다.
- 로컬 또는 staging base URL이 있거나, 비밀값/프로덕션 데이터 없이 로컬 PHP built-in server를 안전하게 시작할 수 있으면 HTTP smoke test를 기본 후속 검증으로 취급합니다. 사용 가능한 포트로 `php -S 127.0.0.1:<port> -t .tools/public .tools/bin/dev-router.php`를 실행한 뒤 `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php`를 실행합니다.
- 대상 환경에 community 모듈이 설치되어 있어야 한다면 HTTP smoke test에 `SR_SMOKE_EXPECT_COMMUNITY=1`을 사용합니다.
- `php .tools/bin/smoke-community-auth.php` 같은 인증 smoke test는 로컬 또는 staging 데이터베이스에서만 실행하고, 명시적인 smoke-test credential이 있을 때만 실행합니다. 이 테스트는 데이터를 생성하고 수정하므로 production에서 실행하지 않습니다.
- 이 파일에 실제, 공유, release-sensitive credential을 저장하지 않습니다. 임시 인증 smoke-test credential은 커밋된 agent 지침 밖의 local 또는 staging 전용 노트에 둡니다.
- reward reclaim이나 기타 admin-only workflow를 검증하려면 필요한 경우 local/staging dummy data를 만듭니다. destructive 또는 mutating smoke test에 production data를 사용하지 않습니다.
- smoke-test 실패는 missing local environment, unavailable credentials, 이미 문서화된 기존 이슈가 명확한 원인일 때를 제외하고 실제 finding으로 취급합니다. 그 구분을 최종 응답에 기록합니다.
- 다크 모드에 민감한 CSS 변경 후에는 screenshot이나 static search에만 의존하지 말고 computed style을 검증합니다. 최소한 관련 rendered screen 또는 focused browser fixture를 light/dark 모드 모두에서 확인하고 body/background, main text, muted text, links, borders, active/hover 또는 selected 상태의 대비가 충분한지 확인합니다.
- Playwright를 사용할 수 없다면 Chrome 같은 설치된 headless browser와 실제 CSS 파일을 로드하는 임시 HTML fixture로 대표 selector의 `getComputedStyle()`을 확인합니다. 이 fallback을 사용했다면 보고합니다.

## Git과 GitHub 작업

- 사용자가 명시적으로 커밋을 요청하지 않는 한 커밋하지 않습니다.
- 사용자가 명시적으로 push를 요청하지 않는 한 push하지 않습니다.
- 사용자가 명시적으로 이슈 작업을 요청하지 않는 한 GitHub issue를 열거나, 다시 열거나, 닫거나, 상태를 변경하지 않습니다.
- 사용자가 명시적으로 comment 또는 issue update를 요청하지 않는 한 GitHub issue comment를 추가하지 않습니다.
- GitHub issue나 issue comment를 만들 때는 본문에 명확한 Markdown 줄바꿈과 문단/목록/섹션 사이 빈 줄을 사용합니다. 여러 생각이 포함된 본문을 빽빽한 한 줄로 제출하지 않습니다.
- GitHub issue comment가 한 문장보다 길다면 inline shell string 대신 body file 등 여러 줄을 안전하게 보존하는 방식을 사용합니다.
- 사용자가 코드 구현이나 수정을 요청하면 working tree 변경과 검증 요청으로 취급합니다. 커밋, push, issue 상태 변경은 별도 명시 요청을 묻거나 기다립니다.

## 커밋 메시지

- 커밋 메시지는 한국어로 작성합니다.
- 형식은 `type: message`를 사용합니다.
- type은 Conventional Commits 스타일의 공통 type만 사용합니다.
  - `feat`: 사용자에게 보이는 기능 또는 capability 추가
  - `fix`: 버그 수정 또는 동작 보정
  - `docs`: 문서 전용 변경
  - `chore`: 저장소 유지관리, tooling, housekeeping
  - `refactor`: 동작 변경 없는 내부 구조 변경
  - `test`: 테스트 전용 변경
  - `style`: formatting-only 변경
  - `perf`: 성능 개선
  - `build`: build 또는 dependency 변경
  - `ci`: CI 설정 변경
  - `revert`: 이전 커밋 되돌리기
- `core`, `member`, `admin`, `install` 같은 project-area prefix를 type으로 사용하지 않습니다.
- 필요하면 영향받은 영역을 한국어 메시지나 본문에 넣습니다.
- 커밋이 GitHub issue를 처리한다면 제목에 `#26`처럼 이슈 번호를 포함합니다.
- 제목은 간결하게 쓰고 실제 변경을 설명합니다.
- 여러 파일이나 동작이 영향을 받는 non-trivial 변경에는 한국어 본문을 추가합니다.

예:

- `docs: 루트 진입점 배포 기준 정리`
- `feat: 회원 로그인 실패 기록 정책 보완`
- `fix: 설치 상태 확인 조건 수정`
- `chore: 로컬 개발 도구 설정 정리`

## 관리자 폼 검증

- 관리자 save/update form을 만들거나 변경할 때 이 규칙을 적용합니다.
- 필수 관리자 필드의 기준은 server-side validation입니다. HTML `required`, disabled button, JavaScript-only check만을 유일한 보호로 삼지 않습니다.
- 관리자 필드가 저장/수정에 필수라면 가능한 경우 visible `(필수)`, browser/front-end validation, server-side POST validation 세 층을 맞춥니다.
- 관리자 폼 label은 필드명만 둡니다. URL 동작, 허용 형식, 단위, 업로드 제한, 자동 동작 같은 설명은 label에 괄호로 붙이지 말고 control 아래 `.admin-form-help`에 둡니다.
- `*_key`, `module_key`, `menu_key` 같은 관리자 key text input은 `data-admin-key-input`과 일치하는 browser attribute로 lowercase letters, digits, `_`를 강제하고, 저장 action에서 다시 normalize한 뒤 domain validation을 수행합니다. hyphen을 허용하는 public slug는 별도 필드 유형으로 취급합니다.
- 보이는 control이 hidden POST field만 파생한다면 실제 hidden POST 결과도 server-side에서 검증합니다. 예를 들어 `1차/2차/권한` 선택을 `permission_keys[]`로 바꾸는 picker는 비어 있거나 잘못된 `permission_keys[]` payload를 server-side에서 거부해야 합니다.
- 조건부 필수 필드는 짧은 `(필수)` label만 표시합니다. 조건이 바뀔 때 해당 label과 browser validation을 토글하고, 같은 조건을 server-side에서도 강제합니다. reference type/reference ID, target/match type/subject ID, policy/member-group 선택, terminal status/admin note 같은 paired field가 여기에 포함됩니다.
- confirmation phrase가 필요한 destructive 또는 high-impact modal form은 submit 전에 modal 내부에서 `setCustomValidity`, visible `.validation-error-note`, `aria-invalid`, server-side POST validation으로 검증해야 합니다. confirmation phrase 오류를 최종 POST error flash에만 의존하지 않습니다.
- form이 unsaved field value를 inline으로 보존할 필요가 없다면, 관리자 POST save action은 success와 validation failure 모두에 Post/Redirect/Get을 사용합니다. browser refresh가 form을 재제출하지 않도록 flash result helper를 사용합니다.
- search filter, lookup-only control, helper selector는 해당 값이 save action에 직접 필수인 경우가 아니라면 required로 표시하지 않습니다.

## 관리자 고부하 작업

- 많은 row, file, external request, reward/asset을 건드릴 수 있는 관리자 작업을 만들거나 변경할 때 이 규칙을 적용합니다.
- bulk delete, recursive file operation, large data copy, recalculation, export, external delivery retry는 잠재적으로 고부하 관리자 작업으로 취급합니다.
- 고부하 관리자 action 실행 전 현재 대상 수 또는 가능한 최선의 추정치를 보여주고, 실제 결과가 실행 시점에 달라질 수 있음을 설명하며, destructive action에는 명시적 확인을 요구합니다.
- 하나의 unbounded web request보다 bounded batch를 우선합니다. 작업이 많은 row나 file을 건드릴 수 있다면 request당 처리 수를 제한하고, 처리된 수를 보고하며, 남은 작업을 계속하는 방법을 안내합니다.
- 장시간 또는 multi-step 작업은 valid submit 후 submit button을 비활성화하고 진행 중 label을 보여줘 운영자가 같은 작업을 실수로 중복 제출하지 않게 합니다.
- modal이 추정치를 표시하거나 button을 비활성화하거나 client-side validation을 수행하더라도 server-side limit과 validation을 authoritative하게 유지합니다.

## 코어 결정

- 구현 계획이 모호할 때 `docs/core-decisions.md`를 최상위 decision log로 취급합니다.
- token 원문이 아니라 token hash를 저장합니다.
- SEO value decision은 모듈에 둡니다. 코어는 output slot과 helper만 제공합니다.
- GDPR 지원은 최소 member/core foundation과 선택적 privacy/admin workflow로 나눕니다.
