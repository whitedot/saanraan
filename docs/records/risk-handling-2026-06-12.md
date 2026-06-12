# 리스크 처리 기록 - 2026-06-12

이 기록은 릴리스 후보 판정이 아니라 현재 작업 환경에서 처리 가능한 리스크를 줄인 작업 메모다. 설치 DB와 테스트 계정이 필요한 게이트는 그대로 남기고, 미설치 로컬 환경에서 확인 가능한 HTTP 보호 경로, CKEditor asset/fallback 브라우저 smoke, 개인정보/성능 fixture, 상태표 JSON 구조화 증거 경로를 확인했다.

## 처리한 리스크

| 리스크 | 처리 내용 | 결과 |
| --- | --- | --- |
| R-02 HTML sanitizer/CKEditor | `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 npm --prefix .tools/browser-qa run test:ckeditor` 실행 | 통과, 4 tests passed |
| R-02 HTML sanitizer/CKEditor | 설치 DB disposable 관리자 계정용 `smoke-ckeditor-upload-save.php`와 상태표 `--run-ckeditor-upload-save-smoke` 연결 추가 | 콘텐츠 본문 이미지 업로드, 저장 HTML sanitizer, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, 저장 후 최종 본문 이미지 URL, draft 관리자 미리보기 이미지 접근, draft 페이지/이미지 비로그인 차단을 반복 실행할 하니스 확보. 유료 본문 이미지 접근 smoke는 별도 남음 |
| R-04 개인정보 export/cleanup 계약 | `release-installed-gate-status.php --run-privacy-fixtures`로 SQLite export/cleanup fixture 실행. 설치 DB disposable 계정용 `smoke-privacy-export-cleanup.php`와 상태표 `--run-privacy-smoke` 연결 추가 | fixture 부분 확인, 임시 새 설치 수동 export/withdraw cleanup 증거를 자동 하니스로 재실행할 경로 확보 |
| R-05 기존 설치 업데이트 적용 | `smoke-update-apply.php`와 상태표 `--run-update-smoke` 연결 추가. 임시 새 설치 DB에서 `coupon 2026.05.003` schema version row를 제거해 pending을 만들고 `/admin/updates` POST로 재적용 | pending 생성/해소, `sr_schema_versions` 재기록, `schema.update.completed` 감사 로그, 모듈 버전 동기화 통과 |
| R-06 커스텀 요청/보안 contract | `SR_SMOKE_BASE_URL=http://127.0.0.1:8091 php .tools/bin/smoke-http.php` 실행 | 통과, route/보안 헤더/보호 경로 확인 |
| R-08 배포 보호 | HTTP smoke에서 `config/`, `storage/installed.lock`, `database/`, `modules/*/install.sql`, `.tools/`, `.git` 등 보호 경로 403 확인 | 통과 |
| R-11 성능/캐시 기준 | `release-installed-gate-status.php --run-performance-fixtures`로 정책/베이스라인/pagination/board copy/survey export fixture 실행 | 부분 확인 |
| R-05 넓은 번들 모듈 표면 | `release-installed-gate-status.php --json --run-http-smoke --run-browser-qa --run-privacy-fixtures --run-performance-fixtures` 구조화 출력 경로 확인 | JSON 파싱 통과, unresolved_gates 12 |
| R-01 자산/쿠폰/유료 접근권 | 임시 MySQL 새 설치에서 `asset_ledger` 필수 설치 누락으로 `/admin/assets/reconciliation` 404 확인 후 설치 필수 모듈에 추가하고, `content` 유료 열람 fixture로 병렬 자산 mutation smoke 실행. `check-coupon-redemption-runtime.php`에 커뮤니티 유료 게시글 열람 쿠폰 우선 적용/환불 접근권 회수 fixture 추가. 임시 MySQL 새 설치에서 커뮤니티 paid read 실제 HTTP POST smoke 실행. 쿠폰/포인트 혼합 임시 설치 데이터로 reconciliation CLI와 관리자 read-only 게이트 대조 | 관리자 reconciliation 화면 200 확인, content paid view dedupe 0→1 통과, 커뮤니티 paid read 쿠폰 우선 적용 runtime/HTTP 통과, 혼합 데이터 reconciliation mismatch 0건과 관리자 화면 통과 |

## 수정한 사항

- `.tools/bin/release-installed-gate-status.php`
  - 실행 출력 요약을 단일 라인으로 줄일 때 UTF-8을 정리하고 문자 단위로 자르도록 수정했다.
  - JSON payload 전체도 출력 직전에 재귀적으로 UTF-8 정리하고 `JSON_INVALID_UTF8_SUBSTITUTE`를 사용하도록 보강했다.
  - JSON 인코딩 실패 시 `json_last_error_msg()`를 함께 출력해 릴리스 자동화 로그에서 원인을 확인할 수 있게 했다.
  - public-looking base URL에서 mutation smoke를 준비하려면 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`을 추가로 요구하도록 했다.
  - `--run-admin-readonly` 옵션을 추가해 관리자 계정이 준비된 로컬/staging에서 `/admin/assets/reconciliation`과 `/admin/operations`를 로그인 세션으로 GET하고 기대 화면 문구를 상태표에 기록할 수 있게 했다.
  - `--run-privacy-smoke` 옵션을 추가해 disposable 계정이 준비된 로컬/staging에서 개인정보 export JSON과 탈퇴/익명화 HTTP smoke를 상태표에 기록할 수 있게 했다.
  - `--run-update-smoke` 옵션을 추가해 disposable 로컬/staging DB에서 기존 설치본 업데이트 적용 흐름을 상태표 첫 게이트로 기록할 수 있게 했다.
  - 기존 바이트 기준 `substr()`는 한국어, 체크마크 같은 멀티바이트 문자를 중간에서 잘라 `--json --run-browser-qa` 구조화 출력이 실패할 수 있었다.
- `.tools/bin/smoke-privacy-export-cleanup.php`
  - disposable 계정으로 로그인해 `/account/privacy-export` JSON 구조와 `member` module export를 확인한 뒤 `/account/withdraw`를 POST하고 기존 세션의 `/account` 접근과 기존 자격증명 로그인이 막히는지 확인하는 설치 DB mutation smoke를 추가했다.
  - export JSON의 `exported_at` timestamp, 양수 `account_id`, `privacy_requests` 배열, 비어 있지 않은 `member` module export를 검증하도록 보강했다.
  - 계정을 탈퇴/익명화하므로 `SR_SMOKE_ALLOW_MUTATION=1`을 필수로 요구하고, public-looking base URL에서는 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 추가로 요구한다.
- `.tools/bin/smoke-update-apply.php`
  - disposable 관리자 계정으로 로그인해 `/admin/updates`를 열고, 대상 update의 `sr_schema_versions` 행을 제거해 pending 상태를 만든 뒤 `backup_confirmed=1`로 업데이트 적용 POST를 실행한다.
  - 적용 뒤 pending 해소, schema version 재기록, `schema.update.completed` 감사 로그, 모듈 버전 코드 동기화를 확인한다.
  - schema version row를 직접 조작하므로 `SR_SMOKE_ALLOW_MUTATION=1`을 필수로 요구하고, public-looking base URL에서는 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 추가로 요구한다.
- `.tools/bin/smoke-ckeditor-upload-save.php`
  - disposable 관리자 계정으로 로그인해 `/admin/content/new`의 CKEditor upload 속성을 확인하고, 본문 이미지를 multipart로 업로드한 뒤 공개 콘텐츠를 저장해 sanitizer, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, 저장 후 최종 본문 이미지 URL을 확인하는 설치 DB mutation smoke를 추가했다.
  - 같은 실행에서 별도 draft 콘텐츠도 저장해 관리자 미리보기 이미지 접근과 비로그인 draft 페이지/이미지 차단을 확인하도록 보강했다.
  - 설치 DB를 읽을 수 있는 local/staging 실행에서는 콘텐츠 에디터 설정을 CKEditor로 준비하고, 유료 공개 콘텐츠 본문 이미지가 접근권 부여 전에는 비로그인/로그인 세션 모두 차단되며 테스트 접근권 부여 뒤에는 열리는지 확인하도록 보강했다.
  - 콘텐츠와 업로드 파일을 만들기 때문에 `SR_SMOKE_ALLOW_MUTATION=1`을 필수로 요구하고, public-looking base URL에서는 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`도 추가로 요구한다.
- `.tools/bin/smoke-asset-idempotency-http.php`, `.tools/bin/smoke-community-auth.php`, `.tools/bin/smoke-quiz-e2e.php`
  - localhost/private/test 도메인이 아닌 base URL에서는 `SR_SMOKE_ALLOW_MUTATION=1`만으로 실행하지 않고 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`을 추가로 요구하도록 했다.
- `.tools/bin/check-installed-gate-status.php`
  - invalid UTF-8 환경값을 넣은 실제 `--json` 실행, public URL mutation guard, JSON payload 안전화, UTF-8 정리, 문자 단위 자르기, 실패 진단 marker를 통합 점검에 추가했다.
  - 개인정보 export/cleanup smoke와 CKEditor upload/save smoke가 직접 실행될 때도 `SR_SMOKE_ALLOW_MUTATION=1` 없이는 exit 2로 거부하고, public-looking base URL에서는 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1` 없이는 exit 2로 거부하는지 회귀 검증을 추가했다.
  - 관리자 read-only 상태표 게이트가 관리자 로그인, `/admin/assets/reconciliation`, `/admin/operations` GET, 기대 문구 확인 흐름을 관통하는지 mock fixture를 추가했다.
  - 자산 idempotency smoke 하니스가 로그인, form token 추출, 병렬 POST, status 집계 흐름을 실제 HTTP로 관통하는지 mock fixture를 추가했다. 이 fixture는 설치 DB dedupe row count 증거를 대체하지 않는다.
  - CKEditor upload/save smoke 하니스가 로그인, 업로드, 저장, 임시/저장 이미지 접근 확인과 draft 페이지/이미지 비로그인 차단 흐름을 실제 HTTP로 관통하는지 mock fixture를 추가했다.
  - 개인정보 export/cleanup smoke 하니스가 로그인, export JSON 검증, 탈퇴 redirect, 기존 세션 접근 차단, 재로그인 차단 확인 흐름을 실제 HTTP로 관통하는지 mock fixture를 추가했다.
- `.tools/bin/check-tool-gate-coverage.php`
  - 새 `smoke-*.php` 실행 도구가 설치 게이트 상태표와 `docs/smoke-test.md`에 함께 연결됐는지 확인하도록 보강했다.
- `.tools/bin/seed-dummy-http.php`
  - 커뮤니티 게시판 그룹과 게시판 생성 POST에 `paid_attachment_download_publisher_reward_*` 기본값과 게시판 source 값을 포함해, 게시자 리워드 지급률 서버 검증을 실제 관리자 저장 경로와 맞췄다.
  - POST 응답의 관리자 오류뿐 아니라 공개 피드백 오류와 회원가입 오류 목록도 즉시 실패로 보고하도록 보강했다.
- `.tools/bin/check-ckeditor-assets.php`
  - 콘텐츠 본문 이미지 프록시가 임시 이미지는 편집 관리자 세션으로 제한하고, 저장 이미지는 공개/자산 접근 정책을 따르는지 정적 marker로 확인하도록 보강했다.
  - SQLite fixture로 공개 무료 콘텐츠 본문 이미지 접근, 유료 콘텐츠 비로그인 차단, 비공개 콘텐츠 비관리자 차단, 관리자 접근 허용 분기를 확인하도록 보강했다.
- `.tools/bin/check-asset-idempotency.php`, `.tools/bin/check-community-release.php`, `.tools/bin/check-quiz-consistency.php`
  - 자산 중복 POST, 커뮤니티 인증 smoke, 퀴즈 E2E smoke가 public-looking base URL에서 추가 확인 없이 실행되지 않는지 직접 실행 검증을 추가했다.
- `core/actions/install.php`, `core/views/install.php`
  - 새 설치에서 `point`, `reward`, `deposit`이 의존하고 관리자 reconciliation 화면을 제공하는 숨김 기반 모듈 `asset_ledger`가 등록되지 않던 문제를 수정했다.
  - 필수 설치 순서 안내를 `member → admin → asset_ledger → privacy`로 맞췄다.
- `.tools/bin/check-asset-reconciliation.php`
  - `asset_ledger`가 설치 필수 모듈에 포함되는지와 설치 안내 문구가 유지되는지 회귀 검증을 추가했다.
- `.tools/bin/check-coupon-redemption-runtime.php`
  - 커뮤니티 유료 게시글 열람에서 대상 쿠폰이 있으면 포인트 잔액이 있어도 쿠폰을 먼저 사용하고 자산 거래/자산 로그를 만들지 않는지 확인하는 fixture를 추가했다.
  - 같은 커뮤니티 게시글 재열람은 기존 쿠폰 접근권을 재사용하고, 쿠폰 환불 시 해당 `dedupe_key`로 부여된 커뮤니티 게시글 열람 접근권이 회수되는지 확인한다.
- `docs/smoke-test.md`
  - 상태표 JSON 출력이 멀티바이트 문자를 포함해도 `json_decode()` 가능한 구조화 증거여야 한다는 기준과 관리자 read-only 화면 자동 GET 옵션을 명시했다.

## 실행 결과

```text
SR_SMOKE_BASE_URL=http://127.0.0.1:8091 php .tools/bin/smoke-http.php
=> saanraan HTTP smoke checks completed.
=> route, CKEditor asset, 보호 경로 403 확인

SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 npm --prefix .tools/browser-qa run test:ckeditor
=> 4 passed

SR_SMOKE_BASE_URL=http://127.0.0.1:8091 SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 php .tools/bin/release-installed-gate-status.php --json --run-http-smoke --run-browser-qa --run-privacy-fixtures --run-performance-fixtures
=> result_summary: 통과=2, 부분 확인=2, 수동 확인 필요=0, 미실행=6, 환경 미준비=4, 실패=0
=> unresolved_gates: 12
=> json_decode 확인 통과

sudo -n -u www-data id
=> sudo: a password is required

runuser -u www-data -- id
=> runuser: may not be used by non-root users

임시 새 설치 smoke:

GET / 후 CSRF 포함 POST 설치
=> HTTP 302 Location: /login?next=/admin
=> config/config.php yes, storage/installed.lock yes, install-failed.json no
=> asset_ledger, point, reward, deposit enabled

SR_SMOKE_BASE_URL=http://127.0.0.1:8093 SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8093 SR_SMOKE_IDENTIFIER=admin SR_SMOKE_PASSWORD=12341234 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/release-installed-gate-status.php --json --run-readonly --run-http-smoke --run-browser-qa --run-auth-smoke --run-quiz-smoke --run-privacy-fixtures --run-performance-fixtures
=> result_summary: 통과=7, 부분 확인=2, 수동 확인 필요=4, 미실행=1, 환경 미준비=0, 실패=0
=> unresolved_gates: 7

관리자 세션 GET /admin/assets/reconciliation
=> HTTP 200, title=자산 원장 정합성

관리자 세션 GET /admin/operations
=> HTTP 200, title=운영 상태

env SR_SMOKE_BASE_URL=http://127.0.0.1:1 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --run-admin-readonly
=> run-admin-readonly: yes
=> /admin/assets/reconciliation, /admin/operations: 실패로 기록하되 fatal 없이 login form HTTP 0 보고

임시 새 설치 복사본에서 관리자 read-only 자동 게이트 확인:

SR_SMOKE_BASE_URL=http://127.0.0.1:8094 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --json --run-admin-readonly
=> result_summary: 통과=2, 부분 확인=0, 수동 확인 필요=3, 미실행=9, 환경 미준비=0, 실패=0
=> /admin/assets/reconciliation: 통과, HTTP 200, expected text found
=> /admin/operations: 통과, HTTP 200, expected text found

privacy_smoke 계정 POST /account/privacy-export
=> HTTP 200, JSON decode 통과, exported_at/account_id/privacy_requests/module_exports 포함

privacy_smoke 계정 POST /account/withdraw
=> HTTP 302, sr_member_accounts.status=anonymized, audit member.anonymized success

임시 새 설치 복사본에서 content paid view 자산 병렬 mutation smoke:

optional_modules[]=point, content 설치
=> account-id=1, content-id=1, dedupe-key=content.view:point:1:1

SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8095 SR_SMOKE_IDENTIFIER=admin SR_SMOKE_PASSWORD=12341234 SR_SMOKE_FORM_PATH=/content/paid-smoke SR_SMOKE_POST_PATH=/content/paid-smoke SR_SMOKE_SUCCESS_STATUSES=200,302,303 SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs SR_SMOKE_EXPECT_DEDUPE_KEY=content.view:point:1:1 php .tools/bin/smoke-asset-idempotency-http.php
=> parallel-requests: 6
=> success-count: 6
=> status-counts: {"302":6}
=> dedupe-count-before: 0
=> dedupe-count-after: 1
=> saanraan asset idempotency HTTP smoke completed.

SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8095 SR_SMOKE_IDENTIFIER=admin SR_SMOKE_PASSWORD=12341234 SR_SMOKE_FORM_PATH=/content/paid-smoke SR_SMOKE_POST_PATH=/content/paid-smoke SR_SMOKE_SUCCESS_STATUSES=200,302,303 SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs SR_SMOKE_EXPECT_DEDUPE_KEY=content.view:point:1:1 php .tools/bin/release-installed-gate-status.php --json --run-asset-smoke
=> result_summary: 통과=1, 부분 확인=0, 수동 확인 필요=5, 미실행=8, 환경 미준비=0, 실패=0
=> 자산/쿠폰/유료 접근권 mutation smoke: 통과

php .tools/bin/check-coupon-redemption-runtime.php
=> coupon redemption runtime checks completed.
=> 콘텐츠 유료 열람/다운로드와 커뮤니티 유료 게시글 열람 쿠폰 우선 적용, 자산 거래/자산 로그 미생성, 커뮤니티 쿠폰 환불 접근권 회수 확인

임시 새 설치 복사본에서 community paid read HTTP coupon-first smoke:

optional_modules[]=point, coupon, community 설치
=> reader-id=2, post-id=1, dedupe-key=community.post.read:coupon:2:1

reader 로그인 후 GET /community/post?id=1
=> confirmation-form=yes, asset_request_token 확인

reader POST /community/post
=> post-submit-redirect=yes

reader GET /community/post?id=1
=> body-visible=yes
=> confirmation-gone=yes

DB 확인
=> coupon-redemptions=1
=> community-entitlements=1
=> community-asset-logs=0
=> negative-point-transactions=0

임시 새 설치 복사본에서 쿠폰/자산 혼합 reconciliation smoke:

optional_modules[]=point, coupon, community 설치
=> reader-id=2, post-id=1, community-dedupe=community.post.read:coupon:2:1
=> community-coupon-post=yes
=> point-balance=800
=> point-transactions=2
=> negative-point-transactions=1
=> community-asset-logs=0
=> coupon-redemptions=1
=> community-entitlements=1

php .tools/bin/reconcile-assets.php --max-rows=20
=> point checked accounts=1 mismatches=0
=> reward skipped module disabled
=> deposit skipped module disabled
=> asset reconciliation completed without mismatches.

SR_SMOKE_BASE_URL=http://127.0.0.1:8098 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --json --run-admin-readonly
=> /admin/assets/reconciliation: 통과
=> /admin/operations: 통과

개인정보 export/cleanup 설치 DB smoke 하니스 연결:

php .tools/bin/smoke-privacy-export-cleanup.php
=> SR_SMOKE_ALLOW_MUTATION=1, SR_SMOKE_BASE_URL, SR_SMOKE_IDENTIFIER, SR_SMOKE_PASSWORD 없이는 실행 거부
=> export JSON 필수 key와 기본 타입, member module export 확인 후 /account/withdraw POST, 기존 세션 /account 접근과 기존 자격증명 로그인 차단 확인

SR_SMOKE_BASE_URL=https://example.com SR_SMOKE_IDENTIFIER=member SR_SMOKE_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/smoke-privacy-export-cleanup.php
=> public-looking base URL에서 SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 없이는 실행 거부

SR_SMOKE_BASE_URL=http://127.0.0.1:1 SR_SMOKE_IDENTIFIER=member SR_SMOKE_PASSWORD=12341234 php .tools/bin/smoke-privacy-export-cleanup.php
=> SR_SMOKE_ALLOW_MUTATION=1 없이는 실행 거부

SR_SMOKE_BASE_URL=http://127.0.0.1:1 SR_SMOKE_IDENTIFIER=member SR_SMOKE_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/release-installed-gate-status.php --run-privacy-smoke
=> 개인정보 export/cleanup smoke: 실패로 기록하되 smoke-privacy-export-cleanup.php exit 및 연결 실패를 상태표에 기록

php .tools/bin/check-installed-gate-status.php
=> 관리자 read-only 상태표 mock HTTP fixture 통과
=> 자산 idempotency smoke mock HTTP fixture 통과
=> 개인정보 export/cleanup smoke mock HTTP fixture 통과

mysql -N -B -e 'SELECT VERSION();'
=> ERROR 1045 (28000): Access denied for user 'lab'@'localhost' (using password: NO)

mariadb -N -B -e 'SELECT VERSION();'
=> ERROR 1045 (28000): Access denied for user 'lab'@'localhost' (using password: NO)

CKEditor upload/save 설치 DB smoke 하니스 연결:

php .tools/bin/smoke-ckeditor-upload-save.php
=> SR_SMOKE_ALLOW_MUTATION=1, SR_SMOKE_BASE_URL, SR_SMOKE_ADMIN_IDENTIFIER, SR_SMOKE_ADMIN_PASSWORD 없이는 실행 거부
=> 콘텐츠 본문 textarea의 CKEditor upload 속성 확인 후 이미지 업로드, 임시 이미지 비로그인 차단, 콘텐츠 저장, 공개 화면 sanitizer, 저장 후 공개 이미지 접근, 최종 본문 이미지 URL 확인

SR_SMOKE_BASE_URL=https://example.com SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/smoke-ckeditor-upload-save.php
=> public-looking base URL에서 SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 없이는 실행 거부

SR_SMOKE_BASE_URL=http://127.0.0.1:1 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/smoke-ckeditor-upload-save.php
=> SR_SMOKE_ALLOW_MUTATION=1 없이는 실행 거부

SR_SMOKE_BASE_URL=http://127.0.0.1:1 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/release-installed-gate-status.php --run-ckeditor-upload-save-smoke
=> CKEditor upload/save smoke: 실패로 기록하되 smoke-ckeditor-upload-save.php exit 및 연결 실패를 상태표에 기록

php .tools/bin/check-installed-gate-status.php
=> CKEditor upload/save smoke mock HTTP fixture 통과

최신 작업트리 비파괴 HTTP/브라우저 smoke 재확인:

php -S 127.0.0.1:8091 -t .tools/public .tools/bin/dev-router.php
=> PHP 8.4.12 Development Server started

SR_SMOKE_BASE_URL=http://127.0.0.1:8091 php .tools/bin/smoke-http.php
=> saanraan HTTP smoke checks completed.
=> route, CKEditor self-hosted asset, 보호 경로 403 확인

SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 npm --prefix .tools/browser-qa run test:ckeditor
=> 4 passed

SR_SMOKE_BASE_URL=http://127.0.0.1:8091 SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 php .tools/bin/release-installed-gate-status.php --json --run-http-smoke --run-browser-qa
=> result_summary: 통과=2, 부분 확인=0, 수동 확인 필요=0, 미실행=8, 환경 미준비=4, 실패=0
=> unresolved_gates: 12

SR_SMOKE_BASE_URL=http://127.0.0.1:8091 SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php
=> saanraan HTTP smoke checks completed.
=> 커뮤니티 route, 커뮤니티 보호 POST, 커뮤니티 SQL/metadata 보호 경로 확인

SR_SMOKE_BASE_URL=http://127.0.0.1:8091 SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8091 php .tools/bin/release-installed-gate-status.php --json --run-http-smoke --run-browser-qa --run-privacy-fixtures --run-performance-fixtures
=> result_summary: 통과=2, 부분 확인=2, 수동 확인 필요=0, 미실행=6, 환경 미준비=4, 실패=0
=> unresolved_gates: 12

php -l .tools/bin/check-tool-gate-coverage.php
=> No syntax errors detected

php .tools/bin/check-tool-gate-coverage.php
=> tool gate coverage checks completed.

php .tools/bin/check.php
=> saanraan checks completed.

CKEditor upload/save smoke draft 접근 정책 보강:

php -l .tools/bin/smoke-ckeditor-upload-save.php
=> No syntax errors detected

php -l .tools/bin/check-installed-gate-status.php
=> No syntax errors detected

php .tools/bin/check-installed-gate-status.php
=> installed gate status checks completed.

php .tools/bin/check.php
=> saanraan checks completed.

개인정보 export/cleanup smoke JSON 계약 보강:

php -l .tools/bin/smoke-privacy-export-cleanup.php
=> No syntax errors detected

php -l .tools/bin/check-installed-gate-status.php
=> No syntax errors detected

php .tools/bin/check-installed-gate-status.php
=> installed gate status checks completed.

릴리스 패키지 포함 확인:

ls -l .tools/bin/smoke-ckeditor-upload-save.php .tools/bin/smoke-privacy-export-cleanup.php
=> 두 smoke 스크립트 모두 executable

php .tools/bin/check-release-package-policy.php
=> release package policy checks completed.
=> smoke-privacy-export-cleanup.php, smoke-ckeditor-upload-save.php 실행권한 회귀 확인 포함

php .tools/bin/release-package-dry-run.php
=> release package dry-run checks completed. files=1493

설치 DB 게이트 재시도 경로 확인:

php -r "define('SR_ROOT', getcwd()); require 'core/helpers.php'; ..."
=> sr_is_installed=no
=> config_readable=no
=> lock_readable=yes

getfacl -p config/config.php storage/installed.lock
=> config/config.php owner/group은 www-data:www-data, mask::--- 때문에 현재 CLI 사용자는 읽을 수 없음
=> storage/installed.lock은 현재 CLI에서 읽을 수 있음

./.tools/bin/php -v
=> Docker daemon socket permission denied

docker image inspect saanraan-php:8.3-cli
=> Docker daemon socket permission denied

현재 메인 작업트리에서는 `config/config.php`를 읽을 수 없어 `.tools/bin/seed-dummy-http.php`가 설치 DB에 접근할 수 없다. 대신 현재 작업트리 복사본으로 새 임시 설치를 만들고 더미 시더를 실행했다. 기존 설치 DB 게이트는 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 다시 실행해야 한다.

임시 새 설치 복사본에서 HTTP 더미 시드 재확인:

GET / 후 CSRF 포함 POST 설치
=> installed=yes

SR_SEED_ALLOW_MUTATION=1 SR_SEED_BASE_URL=http://127.0.0.1:8103 SR_SEED_ADMIN_IDENTIFIER=admin SR_SEED_ADMIN_PASSWORD=12341234 SR_SEED_COUNT=10 SR_SEED_RUN_KEY=seed173521 php .tools/bin/seed-dummy-http.php
=> members 1 -> 11
=> member_groups 0 -> 10
=> content_groups 0 -> 10
=> content_items 0 -> 10
=> community_board_groups 0 -> 10
=> community_boards 1 -> 11
=> community_posts 0 -> 10
=> banners 0 -> 10
=> popup_layers 0 -> 10
=> coupon_definitions 0 -> 10
=> notifications 0 -> 10
=> done

임시 새 설치 복사본에서 CKEditor 유료 본문 이미지 접근 정책 재확인:

SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8105 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/smoke-ckeditor-upload-save.php
=> paid-image-access-policy: yes
=> public/draft body image checks completed

SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8105 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --json --run-ckeditor-upload-save-smoke
=> CKEditor upload/save browser smoke: 통과
=> 상태표 memo에 paid-image-access-policy: yes 포함

임시 새 설치 복사본과 HTTP 더미 데이터 10건 세트에서 성능 readiness 및 목록 후보 수동 확인:

SR_SMOKE_BASE_URL=http://127.0.0.1:8104 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --json --run-readonly --run-http-smoke --run-admin-readonly --run-browser-qa
=> 통과=7, 부분 확인=0, 수동 확인 필요=2, 미실행=5, 환경 미준비=0, 실패=0, unresolved_gates=7
=> 성능 수동 점검: 수동 확인 필요, representative data is marked ready

HTTP 응답 시간 후보:
=> /admin/content status=200 time_total=0.029655 size=175465
=> /admin/community/boards status=200 time_total=0.457036 size=115876
=> /admin/coupons status=200 time_total=0.029846 size=207315
=> /admin/members status=200 time_total=0.018971 size=140442
=> /admin/notifications status=200 time_total=0.017609 size=119932
=> /sitemap.xml status=200 time_total=0.004348 size=9008
=> /account/privacy-export admin status=200 time_total=0.178767 size=19985, privacy-json=ok modules=11

목록 후보 EXPLAIN:
=> content_admin table=sr_content_items,type=index,key=PRIMARY,rows=10
=> community_posts table=sr_community_posts,type=index,key=PRIMARY,rows=10,extra=Using where
=> coupons table=sr_coupon_definitions,type=index,key=PRIMARY,rows=10
=> notifications table=sr_notifications,type=index,key=PRIMARY,rows=10
=> members table=sr_member_accounts,type=index,key=PRIMARY,rows=11
```

임시 새 설치 복사본에서 기존 설치본 업데이트 적용 smoke 확인:

```text
SR_SMOKE_BASE_URL=http://127.0.0.1:8106 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/release-installed-gate-status.php --json --run-update-smoke
=> 새 설치 또는 업데이트 적용: 통과
=> update-apply-smoke-version: 1
=> target-update: coupon 2026.05.003
=> pending-created: yes
=> pending-cleared: yes
=> schema-version-recorded: yes
=> audit-completed: yes
=> module-version-synced: yes
=> 통과=1, 부분 확인=0, 수동 확인 필요=6, 미실행=7, 환경 미준비=0, 실패=0, unresolved_gates=13
```

## 남은 게이트

현재 작업트리의 기존 `config/config.php`는 현재 CLI 사용자가 읽을 수 없고, `sudo -n -u www-data`와 `runuser -u www-data`도 사용할 수 없다. 이 파일의 권한은 넓히지 않았다. 대신 `/tmp/saanraan-risk-smoke-20260612171840`에 현재 작업트리를 복사하고 별도 MariaDB DB `sr_risk_20260612171840`를 만들어 새 설치를 수행했다. 설치 후 `asset_ledger` 필수 설치, read-only CLI, 기본 HTTP, 관리자 read-only, 인증, 퀴즈, 자산 병렬 mutation, 개인정보 export/cleanup, CKEditor asset/fallback, CKEditor upload/save smoke를 상태표 안에서 실행했다.

```
php -S 127.0.0.1:8099 -t /tmp/saanraan-risk-smoke-20260612171840/.tools/public /tmp/saanraan-risk-smoke-20260612171840/.tools/bin/dev-router.php
=> 새 설치 POST 302 /login?next=/admin, config/config.php mode 0600, storage/installed.lock 생성

SR_SMOKE_BASE_URL=http://127.0.0.1:8099 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 php .tools/bin/release-installed-gate-status.php --json --run-readonly --run-http-smoke --run-admin-readonly --run-browser-qa
=> 통과=7, 수동 확인 필요=1, 미실행=6, 환경 미준비=0, 실패=0

SR_SMOKE_BASE_URL=http://127.0.0.1:8099 SR_SMOKE_IDENTIFIER=risk171938m06 SR_SMOKE_PASSWORD='SaanraanQA1!' SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_FORM_PATH=/content/risk-paid-asset-smoke SR_SMOKE_POST_PATH=/content/risk-paid-asset-smoke SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs SR_SMOKE_EXPECT_DEDUPE_KEY='content.view:point:7:16' php .tools/bin/smoke-asset-idempotency-http.php
=> parallel-requests=6, success-count=6, status-counts={"302":6}, dedupe-count-before=0, dedupe-count-after=1

SR_SMOKE_BASE_URL=http://127.0.0.1:8099 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=12341234 SR_SMOKE_IDENTIFIER=risk171938m07 SR_SMOKE_PASSWORD='SaanraanQA1!' SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_FORM_PATH=/content/risk-paid-asset-smoke-2 SR_SMOKE_POST_PATH=/content/risk-paid-asset-smoke-2 SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs SR_SMOKE_EXPECT_DEDUPE_KEY='content.view:point:8:17' php .tools/bin/release-installed-gate-status.php --json --run-readonly --run-http-smoke --run-admin-readonly --run-browser-qa --run-auth-smoke --run-quiz-smoke --run-asset-smoke --run-privacy-smoke --run-ckeditor-upload-save-smoke --run-privacy-fixtures --run-performance-fixtures
=> 통과=12, 부분 확인=1, 수동 확인 필요=1, 미실행=0, 환경 미준비=0, 실패=0, unresolved_gates=2
```

| 게이트 | 현재 상태 | 필요한 환경 |
| --- | --- | --- |
| 새 설치 또는 업데이트 적용 | 별도 임시 DB에서 새 설치 성공. `--run-update-smoke`로 기존 설치본 update pending 생성/적용/이력/감사 로그/버전 동기화 통과 | 릴리스 후보 또는 실제 staging DB에서 같은 smoke 재실행 |
| `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run` | 별도 임시 설치에서 모두 통과 | 대표 데이터가 있는 로컬/staging에서 재실행 |
| /admin/assets/reconciliation, /admin/operations | `--run-admin-readonly`로 임시 새 설치에서 두 화면 HTTP 200과 기대 문구 확인 | 대표 데이터가 있는 로컬/staging에서 CLI 결과와 화면 요약 대조 |
| 인증 smoke, 퀴즈 E2E smoke | 임시 새 설치에서 인증 smoke와 퀴즈 E2E smoke 통과 | 쪽지/신고까지 포함하려면 recipient/reporter/admin smoke 계정 추가 |
| 자산/쿠폰/유료 접근권 mutation smoke | 임시 새 설치 content paid view에서 병렬 POST 6건, dedupe 0→1 통과. 상태표 `--run-asset-smoke`에서도 통과. SQLite runtime으로 콘텐츠/커뮤니티 쿠폰 우선 적용과 환불/회수 계약 확인. 임시 새 설치 community paid read 실제 HTTP POST로 쿠폰 사용, 접근권, 자산 미차감 확인. 임시 새 설치 혼합 데이터에서 포인트 거래와 쿠폰 접근권을 함께 만든 뒤 reconciliation mismatch 0건 및 관리자 화면 통과 확인 | 대표 규모 로컬/staging 데이터에서 운영자 reconciliation 대조와 성능 수동 점검 |
| 개인정보 export/cleanup 설치 DB smoke | 임시 새 설치에서 export JSON 구조, member module export, 탈퇴/익명화, 기존 세션 접근 차단, 기존 자격증명 로그인 차단 통과. 상태표 `--run-privacy-smoke`에서도 통과 | 운영 보존 데이터 표시와 모듈별 실제 row 포함/익명화 수동 대조 |
| CKEditor upload/save browser smoke | 임시 새 설치에서 upload/save smoke 통과. 정상 레이아웃 `<script src>` 때문에 sanitizer 검사가 오탐하지 않도록 smoke 하니스를 본문 영역 기준으로 보정했고, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, 유료 본문 이미지 접근권 전후 차단/허용, draft 관리자 미리보기 이미지 접근, draft 페이지/이미지 비로그인 차단을 확인 | 대표 규모 local/staging에서 같은 smoke 재실행 |
| 성능 수동 점검 | static/SQLite fixture는 부분 확인으로 통과. 임시 새 설치 + HTTP 더미 데이터 10건 세트에서 상태표 readiness가 `수동 확인 필요`로 올라가고, 주요 관리자 목록/sitemap/privacy export 응답 시간과 목록 후보 EXPLAIN을 기록 | 대표 규모 로컬/staging DB에서 느린 관리자 목록, sitemap, 개인정보 export 조회 상한, 실행 계획 수동 확인 |

## 판정

이번 작업은 R-02, R-06, R-08의 설치 DB 불필요 검증 증거를 보강했고, 임시 새 설치 DB로 `asset_ledger` 필수 설치 누락을 수정해 관리자 reconciliation 화면과 read-only CLI 게이트를 확인했다. R-02는 `smoke-ckeditor-upload-save.php`와 상태표 `--run-ckeditor-upload-save-smoke`를 연결하고, 임시 새 설치에서 서버 업로드, 저장 sanitizer, 임시/공개 본문 이미지 접근, 유료 본문 이미지 접근권 전후 차단/허용, draft 본문 이미지 접근 차단을 성공 확인했다. R-04는 fixture와 임시 설치 export/withdraw cleanup으로 보강했고, 같은 흐름을 반복 실행할 수 있도록 `smoke-privacy-export-cleanup.php`와 상태표 `--run-privacy-smoke`를 추가한 뒤 임시 새 설치에서 통과시켰다. R-05 기존 설치본 업데이트 적용은 `smoke-update-apply.php`와 상태표 `--run-update-smoke`를 추가하고, 임시 새 설치에서 pending 생성/적용/이력/감사 로그/버전 동기화를 통과시켰다. R-11은 fixture 수준 부분 확인에 더해 임시 새 설치 + HTTP 더미 데이터 10건 세트에서 성능 readiness 상태표, 관리자 목록/sitemap/privacy export 응답 시간, 목록 후보 EXPLAIN을 기록했다. R-01은 content paid view 기준 병렬 mutation smoke에서 dedupe row가 0개에서 1개로만 증가하는 것을 확인했고, runtime fixture로 콘텐츠/커뮤니티 쿠폰 우선 적용과 환불/회수 접근권 정리를 보강했다. 임시 새 설치 community paid read HTTP POST에서도 쿠폰 사용 1건, 커뮤니티 접근권 1건, 자산 로그 0건, 음수 포인트 거래 0건을 확인했다. 추가로 포인트 grant/use와 커뮤니티 쿠폰 접근권이 섞인 임시 설치 데이터에서 reconciliation mismatch 0건과 관리자 read-only 화면 통과를 확인했다. 다만 대표 규모 로컬/staging 데이터 기반 운영자 reconciliation 대조, 대표 규모 성능 수동 점검, 릴리스 후보/staging DB의 전체 smoke 재실행은 아직 남아 있으므로 전체 리스크 처리는 완료가 아니다.
