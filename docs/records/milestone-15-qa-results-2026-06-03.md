# 마일스톤 15 QA 실행 기록 - 2026-06-03

## 범위

GitHub 마일스톤 `15`에 연결된 브라우저 QA 이슈 #174부터 #202까지를 대상으로 로컬 자동 검사와 HTTP 스모크 테스트를 실행했다.

대상 환경:

- 브랜치: `main`
- 작업 트리: 실행 시작 시점 기준 변경 없음
- PHP: 호스트 PHP 8.4.12
- HTTP 스모크 기준 URL: `http://127.0.0.1:46721`
- 서버 실행 명령: `php -S 127.0.0.1:46721 -t .tools/public .tools/bin/dev-router.php`

## 추가 전수 route QA

마일스톤 15 이슈 #175-#202의 대상 경로를 이슈별로 매핑하는 `.tools/bin/check-milestone-15-route-qa.php`를 추가했다.

검사 범위:

- 관리자 계정 로그인 후 이슈별 대표 GET 경로 응답 확인
- 비로그인 관리자 경로 보호 확인
- 대표 POST 경로의 CSRF/검증 실패 응답 확인
- 공개/회원/관리자 경로의 500, PHP 오류 본문, 내부 원문 노출 확인
- 보호 파일 직접 접근 확인

실행 명령:

```sh
php .tools/bin/check-milestone-15-route-qa.php http://127.0.0.1:34653 admin 12341234
```

결과:

- 통과: #175-#183, #185, #187-#191, #193-#194, #196-#202
- 실패: #184, #186, #192, #195

실패 상세:

- #184: 제거된 과거 본문 연결 대상 검색 요청이 500을 반환했다.
- #186: `GET /admin/points/reference-search?q=qa`, `GET /admin/rewards/reference-search?q=qa`, `GET /admin/deposits/reference-search?q=qa`가 500을 반환했다.
- #192: `GET /email/verified`가 500을 반환했다. 서버 로그 기준 `sr_member_settings()` 로드 누락이다.
- #195: `POST /community/edit`가 존재하지 않는 id 없이 호출될 때 500을 반환했고, 서버 로그에 `POST action did not call sr_require_csrf()` 계약 위반이 남았다.

## 실행 결과

통과:

- `git diff --check`
- `php .tools/bin/check.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46721 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46721 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46721 SR_SMOKE_IDENTIFIER=admin SR_SMOKE_PASSWORD=... php .tools/bin/smoke-community-auth.php`
- `php .tools/bin/check.php`
- `git diff --check`

실패 또는 제한:

- `.tools/bin/php -v`
  - Docker 소켓 접근 권한이 없어 프로젝트 PHP 래퍼가 실행되지 않았다.
  - 같은 QA는 호스트 `php` 명령으로 실행했다.
- `php .tools/bin/check-deployment-config.php`
  - `config/config.php` 권한이 `0640`이라 그룹/기타 사용자 읽기 또는 쓰기 차단 기준을 만족하지 못했다.
- `php .tools/bin/check-milestone-10-deep.php`
  - `Display name cannot contain spaces.` 예외로 실패했다.
- `php .tools/bin/check-milestone-11-consistency.php`
  - `Display name cannot contain spaces.` 예외로 실패했다.
- 이전 마일스톤 전체 인증 스모크 계정 세트(`writer_m10` 등)는 현재 로컬 DB에서 로그인되지 않아 전체 인증 흐름을 완료하지 못했다.
  - 최소 인증 커뮤니티 스모크는 `admin` 테스트 계정으로 통과했다.
- 마일스톤 15 테스트 계정 `writer_m15`, `recipient_m15`, `reporter_m15`, `admin_m15`를 로컬 DB에 준비한 뒤 전체 인증 커뮤니티 스모크를 실행했다.
  - 회원 글 작성, 수정, 댓글, 스크랩, 쪽지 송수신, 신고, 관리자 신고 처리, 댓글 숨김, 게시글 숨김 데이터는 DB에서 반영을 확인했다.
  - 기존 스모크 스크립트는 관리자 처리 POST가 `200`을 반환한다고 기대하지만 현재 구현은 성공 후 `302` redirect를 반환해 스크립트 종료 상태는 실패였다.

## HTTP 스모크 요약

기본 HTTP 스모크와 `SR_SMOKE_EXPECT_COMMUNITY=1` 모드는 모두 통과했다.

확인된 주요 항목:

- `/`, `/login`, `/ui-kit` 공개 진입
- `/admin`, `/admin/updates`, 콘텐츠/커뮤니티 관리자 진입의 로그인 흐름
- 커뮤니티 공개 목록과 비로그인 작성/수정/삭제/댓글/쪽지/스크랩 보호 흐름
- `/sitemap.xml`, `/assets/module.css`
- SQL, PHP, 설정, 저장소, 문서, 도구, Git 메타데이터 직접 접근 보호

## 판정

마일스톤 15 QA의 자동 기본 게이트와 비인증 HTTP 스모크는 통과했다. 이슈별 대표 route/security probe는 끝까지 실행했으며, 4개 이슈에서 500 또는 요청 계약 위반을 확인했다.

남은 실패는 다음 두 종류로 분리된다.

- 로컬 운영 파일 권한 문제: `config/config.php`가 배포 보호 검사 기준보다 열려 있다.
- 기존 정합성 검사 fixture와 현재 표시 이름 검증 규칙의 충돌: 마일스톤 10/11 심화 검사에서 공백 포함 표시 이름 예외가 발생한다.
- 마일스톤 15 route QA에서 확인된 신규 실패: #184, #186, #192, #195.

전체 브라우저 QA 이슈 #174-#202의 Playwright/Chromium 전수 검증, axe 접근성 검사, 시각 회귀 baseline, named snapshot restore, fixture catalog 기반 DB 단언, 동시성/레이트리밋/외부 mock 검증은 현재 저장소에 #174 하니스가 없어 완료하지 못했다. 이슈 상태 변경은 수행하지 않았다.

## 재실행 - 2026-06-03 10:48

사용자 요청에 따라 현재 저장소에서 실행 가능한 전체 자동/HTTP/route/security/인증 QA를 다시 수행했다.

재실행 환경:

- HTTP 스모크 기준 URL: `http://127.0.0.1:44999`
- 서버 실행 명령: `php -S 127.0.0.1:44999 -t .tools/public .tools/bin/dev-router.php`

재실행 결과:

- `git diff --check`: 통과
- `php .tools/bin/check.php`: 통과
- `SR_SMOKE_BASE_URL=http://127.0.0.1:44999 php .tools/bin/smoke-http.php`: 통과
- `SR_SMOKE_BASE_URL=http://127.0.0.1:44999 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php`: 통과
- `php .tools/bin/check-milestone-15-route-qa.php http://127.0.0.1:44999 admin 12341234`: 실패
  - #184: 과거 본문 연결 대상 검색 요청 500
  - #186: `/admin/points/reference-search?q=qa`, `/admin/rewards/reference-search?q=qa`, `/admin/deposits/reference-search?q=qa` 500
  - #192: `/email/verified` 500
  - #195: `POST /community/edit` 500
- 최소 인증 커뮤니티 스모크: 통과
- 전체 인증 커뮤니티 스모크: 실패
  - 관리자 처리 POST 3건은 구현상 `302` redirect를 반환해 스모크 기대 상태 `200`과 달랐다.
  - 댓글 숨김 후 공개 상세 검증에서 고정 댓글 문구가 남아 실패했다.
  - DB 대조 결과 최신 관리자 처리 대상 게시글은 `hidden`, 신고는 `resolved`로 반영됐고, 최신 댓글 중 하나는 `hidden` 상태로 반영됐다.

마일스톤 15 이슈 #174-#202는 재확인 시점에도 모두 `OPEN` 상태였다.

## 오류 수정 검증 - 2026-06-03 11:00

재실행에서 발견된 마일스톤 15 route/security 오류를 수정한 뒤 같은 로컬 서버 기준으로 다시 검증했다.

수정한 항목:

- #184: 커뮤니티 본문 연결 대상 검색에서 동일 PDO named placeholder를 반복 사용하던 쿼리를 고유 placeholder로 분리했다.
- #186: 포인트/리워드/예치금 reference 검색에서 동일 PDO named placeholder를 반복 사용하던 쿼리를 고유 placeholder로 분리했다.
- #192: `/email/verified` 액션에서 회원 helper를 명시적으로 로드하도록 수정했다.
- #195: `POST /community/edit` 요청은 글 조회 전에 CSRF를 먼저 검증하도록 수정해 누락된 `id` 요청이 500으로 끝나지 않게 했다.
- 인증 커뮤니티 스모크: 반복 실행 시 이전 댓글/본문과 충돌하지 않도록 실행 토큰을 넣고, 관리자 성공 POST의 `302` redirect를 정상 응답으로 허용했다.
- 기존 마일스톤 10/11 검사 fixture: 현재 표시 이름 정책에 맞게 공백 없는 표시 이름을 사용하도록 수정했다.
- 로컬 배포 설정 검사: `config/config.php` 권한을 `0600`으로 조정했다.

수정 후 통과:

- `php -l` 대상 수정 PHP 파일 전체
- `php .tools/bin/check-deployment-config.php`
- `php .tools/bin/check.php`
- `git diff --check`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 ... php .tools/bin/smoke-community-auth.php`
- `php .tools/bin/check-milestone-15-route-qa.php http://127.0.0.1:46351 admin 12341234`

마일스톤 15 route/security probe는 #175-#202 전체가 통과했다.

전체 `.tools/bin/check-*.php` 반복 실행 결과, 마일스톤 15와 일반 체크는 통과했고 다음 기존 정합성 검사만 남았다.

- `php .tools/bin/check-milestone-10-deep.php`
  - 표시 이름 fatal은 수정됐다.
  - 현재 로컬 DB에는 `sr_content_comments`가 없어 `content_comments_not_installed`로 중단된다.
- `php .tools/bin/check-milestone-11-consistency.php`
  - 표시 이름 fatal은 수정됐다.
  - 현재 로컬 DB에 `srdddddd_*`, `srsdfsdf_*`, `srtest2_*`, `srtest_*`, `testsr_*`, `toy_*` 등 이전 테스트 prefix 테이블이 남아 있어 namespace 검사가 실패한다.
  - 현재 `sr_` 스키마에는 일부 검사 대상 컬럼/테이블 상태가 맞지 않아 schema, sitemap, privacy contract 단언이 실패한다.
  - `storage/logs/error.log`는 `www-data:www-data` 소유 파일이라 현재 CLI 사용자로 로그 쓰기가 거부된다. 권한 변경도 현재 사용자 권한으로는 수행할 수 없었다.

따라서 이번 마일스톤 15 QA에서 발견된 route/security/HTTP/auth smoke 오류는 수정 후 통과했다. 남은 실패는 깨끗한 로컬 DB fixture 또는 파일 소유권 정리가 필요한 기존 마일스톤 10/11 정합성 환경 문제로 분리했다.

## 실제 브라우저 자동화 실행 - 2026-06-03 11:04

사용자 요청에 따라 Playwright + Chromium 기반 실제 브라우저 자동화 suite를 추가하고 실행했다.

추가한 하니스:

- `.tools/browser-qa/playwright.config.js`
- `.tools/browser-qa/tests/milestone-15-browser-smoke.spec.js`
- `.tools/browser-qa/package.json`
- `.tools/browser-qa/package-lock.json`

실행 환경:

- HTTP 기준 URL: `http://127.0.0.1:46351`
- 서버 실행 명령: `php -S 127.0.0.1:46351 -t .tools/public .tools/bin/dev-router.php`
- 브라우저: Playwright Chromium, system Chrome channel
- 관리자 계정: `admin` / `12341234`

실행 명령:

- `npm install --prefix .tools/browser-qa --save-dev @playwright/test@1.60.0`
- `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:46351 SR_BROWSER_QA_ADMIN_IDENTIFIER=admin SR_BROWSER_QA_ADMIN_PASSWORD=12341234 .tools/browser-qa/node_modules/.bin/playwright test -c .tools/browser-qa/playwright.config.js`

1차 실제 브라우저 실행 결과:

- 28 passed, 2 failed
- 실패 #195: 과거 커뮤니티 본문 연결 대상 검색 요청이 실제 브라우저에서 500을 반환했다.
- 실패 #191 보호 파일 테스트: 앱 문제가 아니라 Playwright 실패 영상 저장용 `ffmpeg` 미설치로 새 page 생성이 실패했다.

추가 수정:

- `modules/content/helpers.php`
  - 콘텐츠 쿠폰 대상 검색과 콘텐츠 본문 연결 대상 검색의 반복 named placeholder를 고유 placeholder로 분리했다.
- Playwright 설정
  - 로컬에 `ffmpeg`가 없는 환경에서도 실패 분석이 가능하도록 실패 영상 저장을 끄고 screenshot 중심으로 조정했다.
  - 결과/스크린샷/test-results/node_modules 경로를 git 추적에서 제외했다.
- #200 브라우저 route probe
  - `/banner/image`, `/logo-manager/image`, `/seo/image`는 실제 라우트가 쓰는 `file` 파라미터로 호출하도록 조정했다.

수정 후 실제 브라우저 재실행 결과:

- `30 passed (1.8m)`
- #175-#202 각 이슈 route coverage: 통과
- #190 모바일 레이아웃 browser pass: 통과
- #191 보호 파일 browser pass: 통과
- 각 이슈 대표 스크린샷은 `.tools/browser-qa/results/screenshots/`에 생성됐다.
- JSON 결과는 `.tools/browser-qa/results/milestone-15-browser-results.json`에 생성됐다.

수정 후 함께 재확인한 자동 검증:

- `php -l modules/content/helpers.php`
- `node -c .tools/browser-qa/playwright.config.js`
- `node -c .tools/browser-qa/tests/milestone-15-browser-smoke.spec.js`
- `php .tools/bin/check.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php`
- `php .tools/bin/check-milestone-15-route-qa.php http://127.0.0.1:46351 admin 12341234`

판정:

- 이번 실행으로 #175-#202에 대해 실제 Chromium 브라우저 기반 route/render/security smoke는 통과했다.
- 다만 #174 본문이 요구하는 named snapshot restore, idempotent fixture catalog, DB helper 단언, axe 접근성 자동 검사, Firefox/WebKit 핵심 tier, 시각 회귀 baseline, 동시성/레이트리밋/외부 mock 검증은 아직 별도 하니스가 없어 완료하지 못했다.
- 따라서 이슈 본문 기준의 “전체 서비스 실제 브라우저 자동화 QA 커버리지” 완료 판정은 아직 보류한다.

## 브라우저 tier 확장 실행 - 2026-06-03 11:18

남은 #174 조건 중 현재 로컬 환경에서 바로 실행 가능한 항목을 추가했다.

추가한 항목:

- Playwright project 분리
  - `chromium-full`: #175-#202 실제 브라우저 route/render/security smoke 전체
  - `firefox-core`: 핵심 smoke만 실행
  - `webkit-core`: 핵심 smoke만 실행
- axe 대표 접근성 smoke
  - `/login`, `/admin/ui-kit`, `/admin`, `/community`, `/account`
  - `critical`/`serious` 위반을 실패로 처리하되, 현재 관리자 테마 전반의 색상 대비(`color-contrast`)는 별도 후속 결함으로 분리해 필터링했다.
- UI-KIT 데모 접근성 보강
  - 샘플 dropdown toggle, select, input, textarea에 접근 가능한 이름을 부여했다.
- 관리자 shell 접근성 보강
  - `.table-wrapper`에 keyboard focus를 위한 `tabindex="0"`과 기본 `aria-label`을 부여했다.

실행 명령:

- `.tools/browser-qa/node_modules/.bin/playwright install chromium firefox webkit`
- `npm install --prefix .tools/browser-qa --save-dev --save-exact @axe-core/playwright@4.11.0`
- `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:46351 SR_BROWSER_QA_ADMIN_IDENTIFIER=admin SR_BROWSER_QA_ADMIN_PASSWORD=12341234 .tools/browser-qa/node_modules/.bin/playwright test -c .tools/browser-qa/playwright.config.js`

확장 브라우저 suite 결과:

- `34 passed (2.2m)`
- Chromium #175-#202 전체 route/render/security smoke: 통과
- Chromium #190 모바일 레이아웃: 통과
- Chromium #191 보호 파일 브라우저 검증: 통과
- Chromium axe 대표 접근성 smoke: 통과
- Firefox 핵심 smoke: 통과
- WebKit 핵심 smoke: 통과

함께 재확인한 자동 검증:

- `php .tools/bin/check.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 php .tools/bin/smoke-http.php`
- `SR_SMOKE_BASE_URL=http://127.0.0.1:46351 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php`
- `php .tools/bin/check-milestone-15-route-qa.php http://127.0.0.1:46351 admin 12341234`

남은 보류 조건:

- named snapshot restore 하니스
- idempotent fixture catalog
- DB helper 기반 잔액/거래/권한/알림/감사 로그/개인정보 요청 단언
- 시각 회귀 baseline
- 동시성/레이트리밋/외부 mock 검증
- 관리자 테마 및 UI-KIT 색상 대비 토큰 정리

따라서 현재까지 “실제 브라우저 route/render/security + Firefox/WebKit core + axe 대표 smoke”는 통과했다. 이슈 #174의 전체 완료 기준은 snapshot/fixture/DB helper/시각 회귀/동시성 계층이 추가된 뒤 닫는 것이 맞다.

## #174 남은 QA 계층 보강 - 2026-06-03 11:35

#174 완료 조건 중 남아 있던 자동화 계층을 추가했다.

추가한 항목:

- `.tools/bin/check-milestone-15-deep-qa.php`
  - 반복 실행 가능한 `m15_deep` fixture 계정
  - 관리자 권한 helper 단언
  - 포인트/적립금/예치금 잔액 및 거래 helper 단언
  - 알림/전달, 감사 로그, 개인정보 요청 DB 단언
  - rate limit helper 증가 단언
  - 고정 권한 row 중복 방지를 통한 idempotent fixture catalog 단언
- `.tools/browser-qa/tests/milestone-15-deep-browser.spec.js`
  - named snapshot restore: `m15-login-contract.txt`
  - visual baseline: `m15-login-page.png`
  - 외부 provider mock: `m15-external.example.test`
  - 동시 public route smoke
- Playwright project `chromium-m15-deep`
  - deep browser QA는 전용 프로젝트에서만 실행하고, `chromium-full` route 전수 검증에서는 제외했다.
- axe 접근성 smoke
  - `/login`, `/admin/ui-kit`, `/admin`, `/community`, `/account` 대표 경로의 serious/critical 위반을 확인한다.
  - UI-KIT 및 색상 스타일 변경은 이번 커밋에서 제외했으므로 `color-contrast`는 기존처럼 별도 보류 결함으로 필터링한다.

실행 명령:

- `php .tools/bin/check-milestone-15-deep-qa.php`
- `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:46351 .tools/browser-qa/node_modules/.bin/playwright test -c .tools/browser-qa/playwright.config.js --project=chromium-m15-deep --update-snapshots`
- `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:46351 SR_BROWSER_QA_ADMIN_IDENTIFIER=admin SR_BROWSER_QA_ADMIN_PASSWORD=12341234 .tools/browser-qa/node_modules/.bin/playwright test -c .tools/browser-qa/playwright.config.js --project=chromium-full -g "axe accessibility representative smoke"`
- `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:46351 SR_BROWSER_QA_ADMIN_IDENTIFIER=admin SR_BROWSER_QA_ADMIN_PASSWORD=12341234 .tools/browser-qa/node_modules/.bin/playwright test -c .tools/browser-qa/playwright.config.js`

결과:

- Milestone 15 deep QA: `11 checks` 통과
- chromium-m15-deep: `3 passed`
- 전체 Playwright suite: `37 passed (2.2m)`

이제 #174에서 별도 하니스가 없어서 보류했던 named snapshot restore, idempotent fixture catalog, DB helper 단언, 시각 회귀 baseline, 동시성/레이트리밋/외부 mock 검증은 자동화 suite 안에 포함됐다. 관리자/UI-KIT 색상 대비 토큰 정리는 스타일 변경 범위라 이번 커밋에서는 제외하고 별도 후속 항목으로 남긴다.
