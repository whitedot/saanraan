# 검증 상태와 증거 기준

이 문서는 산란의 기능 목록을 운영 신뢰로 오해하지 않도록, 현재 어떤 증거로 검증 상태를 판단할지 정리한다. 날짜별 실행 결과는 `docs/records/`에 남기고, 이 문서는 반복해서 확인할 기준을 유지한다.

## 상태 등급

기능과 모듈은 다음 등급 중 하나로 설명한다.

| 등급 | 의미 | 필요한 증거 |
| --- | --- | --- |
| `stable-candidate` | 1.0 릴리스 후보에 포함할 수 있는 상태 | 정적 점검, HTTP smoke, 필요한 수동/브라우저 점검 기록 |
| `beta` | 기본 흐름은 구현됐지만 운영 검증 기록이 부족한 상태 | 정적 점검과 제한된 smoke 또는 수동 확인 |
| `experimental` | 구조 검증 또는 탐구 목적이 강한 상태 | 구현 근거와 알려진 제한 문서 |
| `planned` | 계획 문서 단계 | 계획 문서와 1.0 제외 여부 |

README와 특장점 문서에서 기능을 나열할 때는 가능하면 이 등급이나 검증 한계를 함께 드러낸다.
`php .tools/bin/check-positioning.php`는 README, 특장점 문서, 포지셔닝 기준, 리스크 레지스터가 같은 사용 판단 기준을 유지하는지 확인하고, 대체 CMS나 완성된 플랫폼처럼 운영 성숙도를 과장하는 문구가 공개 소개 문서에 들어오지 않게 막는다.

## 기본 게이트

코드 변경 후 최소 게이트:

```sh
php .tools/bin/check.php
```

이 게이트는 PHP 문법, SQL 파일, 모듈 계약, 관리자 route, 보안/정합성 정적 기준을 확인한다. 통과는 운영 검증의 시작점이지 전체 보증이 아니다.

릴리스 후보 게이트:

```sh
php -S 127.0.0.1:<port> -t .tools/public .tools/bin/dev-router.php
SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php
```

커뮤니티 모듈이 설치된 환경을 기준으로 검증할 때:

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:<port> \
SR_SMOKE_EXPECT_COMMUNITY=1 \
php .tools/bin/smoke-http.php
```

인증 smoke는 로컬 또는 스테이징 DB와 명시적 테스트 계정이 있을 때만 실행한다.

릴리스 후보는 미설치 install-mode smoke만으로 통과 처리하지 않는다. [릴리스 검증 기록 템플릿](release-verification-template.md)의 `릴리스 후보 필수 설치 DB 게이트`를 기준으로 새 설치 또는 로컬/staging DB에서 자산 reconciliation, 운영 상태, 포인트 만료 dry-run preview, 관리자 read-only 화면, 기본 HTTP smoke, 인증 smoke, 퀴즈 E2E smoke, 자산/쿠폰/유료 접근권 mutation smoke, 개인정보 export/cleanup, CKEditor asset/fallback browser smoke, CKEditor upload/save browser smoke, 성능 수동 점검의 실행 결과 또는 미실행 사유를 남긴다. 필수 설치 DB 게이트의 행을 생략할 수 없다. 게이트가 비어 있거나 하나라도 `통과`가 아니면 최종 판정은 `통과`가 아니라 `조건부 통과` 또는 `판정 보류`로 기록한다.

`php .tools/bin/release-installed-gate-status.php`는 현재 CLI 사용자가 설치 DB 게이트를 실행할 수 있는지 read-only로 점검하고, 설치 DB 게이트 상태표를 출력한다. 상태표에는 `config-mode`와 `config-owner-group`도 포함해, 안전한 `0600` 설정 파일 때문에 현재 CLI 사용자가 설치 DB를 읽지 못하는 상황을 릴리스 기록에 구체적으로 남긴다. `gate-result-summary`와 `unresolved-gates`는 통과/부분 확인/수동 확인/미실행/환경 미준비/실패 분포와 전체 미해결 수를 함께 기록하기 위한 값이다. 날짜별 기록의 Markdown 표 전사는 `php .tools/bin/release-installed-gate-status.php --markdown-table` 출력으로 만들고, 자동화/보관용 구조화 증거는 `php .tools/bin/release-installed-gate-status.php --json` 출력으로 남긴다. CI나 릴리스 스크립트에서 미해결 게이트를 실패로 다룰 때는 `--fail-on-unresolved`를 함께 지정한다. 설치 lock과 읽을 수 있는 config가 있는 로컬/staging 환경에서는 `php .tools/bin/release-installed-gate-status.php --run-readonly`로 `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run`까지 실행해 기록할 수 있다. 기존 설치본 업데이트 적용은 disposable 로컬/staging DB에서 `SR_SMOKE_ALLOW_MUTATION=1`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`, `--run-update-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. 이 smoke는 `sr_schema_versions`의 대상 update 행을 지워 pending을 만들고 관리자 `/admin/updates` POST, 적용 이력 복원, 감사 로그, 모듈 버전 동기화를 확인한다. 현재 CLI 사용자가 공유호스팅 보안 권한 때문에 `config/config.php`를 읽지 못하면 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 `php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved`를 다시 실행한다. 로컬/staging HTTP와 관리자 계정이 준비된 경우에는 `SR_SMOKE_BASE_URL`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`를 지정한 뒤 `php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved`로 구조화 증거를 남긴다. 관리자 read-only 화면 게이트는 `SR_SMOKE_BASE_URL`만으로 수동 확인 가능 상태가 되지 않고, `SR_SMOKE_ADMIN_IDENTIFIER`와 `SR_SMOKE_ADMIN_PASSWORD`가 함께 있을 때만 관리자 세션 수동 확인 대상으로 기록된다. 같은 환경에서 `--run-admin-readonly`를 추가하면 `/admin/assets/reconciliation`과 `/admin/operations`를 로그인 세션으로 GET하고 기대 화면 문구를 상태표에 기록한다. `SR_SMOKE_BASE_URL`이 준비된 환경에서는 `php .tools/bin/release-installed-gate-status.php --run-http-smoke`로 기본 route, 보안 헤더, 보호 경로 HTTP smoke를 상태표 안에서 실행해 기록할 수 있다. `SR_BROWSER_QA_BASE_URL` 또는 `SR_SMOKE_BASE_URL`이 준비된 환경에서는 `php .tools/bin/release-installed-gate-status.php --run-browser-qa`로 설치 DB가 필요 없는 CKEditor asset/fallback browser smoke도 상태표 안에서 실행해 기록할 수 있다. 인증 smoke는 데이터를 만들 수 있으므로 `SR_SMOKE_ALLOW_MUTATION=1`, 로컬/staging 테스트 계정의 `SR_SMOKE_IDENTIFIER`/`SR_SMOKE_PASSWORD`, `--run-auth-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. 퀴즈 E2E smoke는 퀴즈와 응시 기록을 만들 수 있으므로 `SR_SMOKE_ALLOW_MUTATION=1`, 로컬/staging 관리자 테스트 계정의 `SR_SMOKE_ADMIN_IDENTIFIER`/`SR_SMOKE_ADMIN_PASSWORD`, `--run-quiz-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. 자산/쿠폰/유료 접근권 병렬 mutation smoke도 disposable 유료 대상, `SR_SMOKE_FORM_PATH`, 로컬/staging 테스트 계정, `SR_SMOKE_EXPECT_DEDUPE_TABLE`/`SR_SMOKE_EXPECT_DEDUPE_KEY`, `SR_SMOKE_ALLOW_MUTATION=1`, `--run-asset-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. 개인정보 export/cleanup 설치 DB smoke는 계정을 탈퇴/익명화하므로 로컬/staging disposable 계정, `SR_SMOKE_IDENTIFIER`/`SR_SMOKE_PASSWORD`, `SR_SMOKE_ALLOW_MUTATION=1`, `--run-privacy-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. CKEditor upload/save 설치 DB smoke도 저장/업로드 데이터를 만들 수 있으므로 로컬/staging 관리자 계정, `SR_SMOKE_ALLOW_MUTATION=1`, `--run-ckeditor-upload-save-smoke`가 모두 있을 때만 상태표 도구에서 실행한다. public-looking base URL에서 mutation smoke를 실행해야 하는 staging 환경은 `SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1`을 추가로 지정해야 한다. 성능 수동 점검은 대표 데이터가 있는 로컬/staging base URL에서만 의미가 있으므로 `SR_SMOKE_BASE_URL`이 있는 대표 데이터 환경에서 느린 관리자 목록, sitemap, 개인정보 export 조회 상한, 실행 계획을 수동 확인 대상으로 기록한다. 테스트 계정 식별자와 비밀번호, 관리자 식별자와 비밀번호, dedupe table/key 중 하나만 있으면 `incomplete`로 기록하고 smoke 실행 대상으로 보지 않는다. `--run-privacy-fixtures`는 개인정보 export/cleanup SQLite 계약 fixture를 `부분 확인`으로 기록하지만 설치 DB 더미 계정 smoke를 대체하지 않는다. `--run-performance-fixtures`는 성능 정책/베이스라인과 관련 runtime fixture를 `부분 확인`으로 기록하지만 데이터가 있는 설치 DB에서 느린 화면과 실행 계획을 확인하는 수동 점검을 대체하지 않는다.

`php .tools/bin/check-release-verification-records.php`는 날짜별 검증 기록의 section, 리스크 row, 최종 판정, 릴리스 후보 필수 설치 DB 게이트 판정 규칙을 확인한다. 리스크별 릴리스 판정 연결 표의 증거, 판정, 후속 칸은 비워 두거나 `TODO`로 남길 수 없다. 릴리스 후보 기록이 설치 DB 게이트를 모두 `통과`로 해결하지 않은 상태에서 `통과`를 주장하거나, 비릴리스 개선 기록이 최종 판정을 과장하면 통합 게이트에서 실패해야 한다.

## 고위험 영역별 증거

### 자산, 쿠폰, 유료 접근권

필요한 증거:

- 잔액 row와 거래 원장의 합계가 일치하는지 확인하는 reconciliation 절차
- 같은 참조 대상에 중복 지급, 중복 차감, 중복 쿠폰 사용이 생기지 않는지 확인하는 fixture
- 환불, 회수, 접근권 취소가 원거래 수량과 상태를 넘지 않는지 확인하는 smoke
- lock wait timeout, deadlock retry, duplicate request를 분리해 기록한 테스트
- 실패 후 관리자나 운영자가 어떤 화면 또는 명령으로 불일치를 발견하고 정정하는지에 대한 문서

현재 기준:

- `docs/smoke-test.md`는 자산, 쿠폰, 유료 열람, settlement 기반 복합 차감의 smoke 기준을 포함한다.
- `.tools/bin/check-asset-deadlock-retry.php`, `.tools/bin/check-paid-download-delivery.php`, `.tools/bin/check-asset-exchange-logs.php`, `.tools/bin/check-asset-exchange-runtime.php`, `.tools/bin/check-coupon-admin-validation.php`, `.tools/bin/check-admin-form-validation.php`, `.tools/bin/check-admin-asset-limits.php`, `.tools/bin/check-reward-abuse-standards.php`, `.tools/bin/check-asset-settlement-contract.php` 같은 정적/정합성 점검은 `php .tools/bin/check.php` 통합 게이트에 포함된다. `check-admin-form-validation.php`는 공통 관리자 opt-in 폼 검증 helper와 사이트 메뉴, 쿠폰, 배너 대표 폼의 `data-sr-validate-form`/`data-validation-message`/조건부 필수 marker를 확인한다. settlement 계약 점검은 정확 충당/ceil overpay 금지/통화 불일치 실패 fixture와 함께 콘텐츠/커뮤니티 자산 입력이 사용자 입력 순서와 무관하게 결정적 차감 순서로 정규화되는지도 확인한다. 유료 다운로드 전달 점검은 파일 또는 signed URL 준비 실패 시 차감이 일어나지 않고, 차감 실패 시 전달하지 않으며, 성공 시 준비-차감-전달 순서를 유지하는 fixture를 포함한다. 환전 로그 점검은 완료 환전 묶음 정정 helper를 SQLite fixture로 실행해 수수료가 있는 정정의 원장 반전, 정정 로그, 중복 정정 차단을 확인하고, 관리자 로그 화면의 CSRF/edit 권한 기반 정정 action과 감사 로그 marker를 확인한다. 환전 runtime 점검은 실제 reward/deposit 자산 계약을 통해 성공 실행의 양쪽 원장/수수료/로그 연결, 입금 원장 저장 실패 시 출금 원장/로그가 남지 않는 rollback, 실패 로그가 잔액과 원장을 바꾸지 않고 요청 금액/실패 사유/처리자 증거를 남기는지 확인한다.
- `.tools/bin/check-tool-gate-coverage.php`는 새 `check-*.php` 검증 도구가 통합 게이트에 연결됐는지 확인한다. 오래 걸리는 deep QA 등 의도적으로 독립 실행하는 도구만 standalone 목록으로 예외 처리한다. 새 `smoke-*.php` 도구는 설치 게이트 상태표인 `release-installed-gate-status.php`와 `docs/smoke-test.md`에 함께 연결됐는지도 확인한다.
- `.tools/bin/check-coupon-redemption-runtime.php`는 SQLite fixture로 쿠폰 사용 dedupe, max uses 상태 전이, 환불 시 dedupe key 격리, issue 재활성화, content/community 접근권 회수를 확인한다. 콘텐츠 유료 열람, 콘텐츠 유료 다운로드, 커뮤니티 유료 게시글 열람 경로는 포인트 잔액이 있어도 쿠폰을 먼저 적용하고, 쿠폰 접근권 부여 시 자산 거래와 자산 접근 로그를 만들지 않는지 확인한다. 다운로드 파일용 `content_file` 쿠폰 target contract의 검색, 상태 점검, 관리자 URL과 쿠폰 기반 유료 다운로드 접근권 회수도 같은 fixture에서 확인한다. 커뮤니티 유료 열람 쿠폰 환불은 같은 `dedupe_key`로 부여된 게시글 열람 접근권을 회수하는지 확인한다. 접근권 insert 실패를 trigger로 주입해 콘텐츠/커뮤니티 쿠폰 사용 row와 `used_count`가 함께 rollback되는 부분 실패 fixture도 포함한다.
- `.tools/bin/check-content-file-cleanup-runtime.php`는 콘텐츠 삭제 시 파일 row redaction, 다운로드 링크 숨김, 다운로드 로그 snapshot redaction, 링크 참조 제거, 시리즈 회차 제거, 임베드 참조 removed 전환, 저장소 cleanup 실패 미발생을 SQLite fixture로 확인한다. 로컬 저장소 쓰기 권한이 있는 환경에서는 고유 fixture 파일과 본문 이미지를 실제로 만들고 삭제 여부까지 확인한다.
- `.tools/bin/check-content-copy-runtime.php`는 콘텐츠 복사 시 HTML 본문 임베드 ref key 재작성, 새 owner ref 생성, 원본 ref 유지, 명시적/legacy 첨부 링크 복사, 시리즈 사본과 회차 메타데이터 복사를 SQLite fixture로 확인한다.
- `.tools/bin/check-community-attachment-runtime.php`는 커뮤니티 첨부 다운로드 자산 로그 placeholder의 dedupe, 0원 완료 로그 settlement metadata, 첨부 다운로드 접근권 중복 방지, anonymized entitlement 무시를 SQLite fixture로 확인한다.
- `.tools/bin/check-reward-abuse-standards.php`는 보상/자산 abuse 기준의 정적 marker와 함께 SQLite fixture로 적립금 회수 잠금 검증 경로, 회수 가능 잔액 상한, 적립금 pending 출금 신청 차감, 출금 완료 거래 생성과 중복 완료 차단, 예치금 pending 환불 신청 차감, 환불 완료 거래 생성과 중복 완료 차단을 확인한다.
- `.tools/bin/check-quiz-reward-runtime.php`는 퀴즈 보상 지급 시 grant dedupe, 같은 시도 재호출 중복 거래 방지, 같은 퀴즈의 다른 시도 duplicate 판정, 실패 grant 재시도와 실패 상태 정리, grant에서 원장 거래를 다시 찾는 lookup 계약, 보상 자산 회수 가능액 계산과 회수 실행을 SQLite fixture로 확인한다. `check-quiz-consistency.php`는 `/admin/quiz/attempts` 보상 grant 표시, 회수 모달, 회수 POST 계약 marker도 확인한다.
- `.tools/bin/check-quiz-delete-runtime.php`는 퀴즈 삭제 시 문항/선택지/결과/댓글 redaction, 응시 source snapshot과 return URL 정리, 응답/채점/결과 snapshot 정리, 보상 grant snapshot과 운영 메모 정리를 SQLite fixture로 확인한다.
- `.tools/bin/check-survey-response-runtime.php`는 설문 응답 제출 시 동의/연구 메타데이터/답변 snapshot, 기타 답변, 복수 선택 min/max, 무응답 선택지 배타성, 숫자 범위, 익명 중복 제한을 SQLite fixture로 확인한다.
- `.tools/bin/check-survey-reward-runtime.php`는 설문 보상 지급 시 grant dedupe, 같은 응답 재호출 중복 거래 방지, 같은 설문의 다른 응답 duplicate 판정, 실패 grant 재시도와 실패 상태 정리를 SQLite fixture로 확인한다.
- `.tools/bin/check-survey-statistics-runtime.php`는 설문 통계 계산 시 테스트 응답 제외, 선택형 통계의 응답 단위 중복 제거, 제외 응답 배제, 숫자형 평균/최소/최대 계산을 SQLite fixture로 확인한다.
- `.tools/bin/check-survey-export-runtime.php`는 설문 CSV export의 타입별 상한, formula-like cell escaping, raw/analysis/codebook 필터, 테스트 응답 포함 옵션, 제외 응답 배제를 SQLite fixture로 확인한다.
- `.tools/bin/check-member-assets-transaction-contract.php`는 `member-assets.php`의 `transaction_function`이 외부 PDO transaction에 동참하는 패턴과 `transaction_lookup_function` 제공 여부를 확인한다.
- `.tools/bin/reconcile-assets.php`는 설치된 환경에서 `point`, `reward`, `deposit`의 balance row, 거래 합계, 마지막 거래 `balance_after`, 거래별 `balance_after` 연쇄를 read-only로 비교한다.
- `/admin/assets/reconciliation`은 같은 reconciliation 기준을 관리자 화면에서 보여준다.
- `.tools/bin/check-asset-reconciliation.php`는 SQLite fixture로 `missing_balance_row`, `nonzero_balance_without_transactions`, mismatch truncation, summary flag를 실제 계산해 확인한다.
- 공통 `sr_ledger_create_transaction()` helper의 table pair allowlist는 현재 `reward`와 `deposit`에 한정한다. `point`는 만료/소진 필드인 `expires_at`, `expires_remaining`을 함께 다루므로 자체 `sr_point_insert_ledger_transaction()` 경로를 유지하되, reconciliation 대상에는 계속 포함한다.
- 콘텐츠/커뮤니티 금액성 소비는 현재 전용 claim 테이블 대신 `dedupe_key` unique가 있는 pending log placeholder를 먼저 insert하고, 원장 거래 성공 후 completed로 전환하는 구조를 claim 경계로 사용한다.
- `.tools/bin/check-asset-idempotency.php`는 pending log placeholder, unique dedupe key, 거래 생성 전 placeholder insert, 성공 시 completed 전환, 실패 시 pending placeholder 삭제, rollback 후 재claim, commit 후 duplicate 흡수 경로가 유지되는지 확인한다. 콘텐츠/커뮤니티 자산 확인 token과 환전 확정 token은 허용 길이를 넘으면 truncate 검증하지 않고 거부하는 action marker도 확인한다. 같은 파일 DB를 여는 두 PDO 연결 fixture로 커밋된 pending/completed claim이 다른 연결의 중복 insert를 흡수하는지도 확인한다. `pcntl`이 있는 CLI 환경에서는 병렬 프로세스 fixture로 여러 프로세스가 같은 `dedupe_key`를 동시에 claim할 때 성공 insert가 정확히 1개만 남는지 확인한다.
- `.tools/bin/smoke-asset-idempotency-http.php`는 설치 DB와 더미 유료 대상이 준비된 로컬/staging에서 같은 확인 token을 병렬 HTTP POST로 제출하는 mutation smoke 하니스다. 기본 실행은 거부하고 `SR_SMOKE_ALLOW_MUTATION=1`을 요구하며, 대상 form의 `csrf_token`/`asset_request_token`, 추가 POST payload, 성공으로 볼 HTTP status allowlist, 선택적 dedupe table/key를 받아 병렬 응답과 dedupe row count를 기록한다. dedupe table/key를 주면 기본적으로 fresh key에서 실행 전 0개, 실행 후 정확히 1개를 요구하고, 전체 병렬 응답 중 최소 1개는 성공 status여야 하며 모든 병렬 응답은 허용 status 안에 있어야 한다. `check-installed-gate-status.php`는 mock HTTP fixture로 로그인, form token 추출, 병렬 POST, status 집계 하니스 경로를 점검하지만, dedupe row count 증거는 설치 DB smoke에서만 인정한다. 이 도구는 운영 DB에서 실행하지 않는다.
- `.tools/bin/check-asset-settlement-contract.php`는 settlement plan fixture로 0원 처리, 정확 충당, 1단위 미만 ceil overpay 금지, 통화 불일치, 알 수 없는 통화 fail-closed, rounding policy snapshot을 확인한다.
- 정적 점검과 SQLite/`pcntl` 병렬 claim fixture는 실제 설치 DB의 HTTP 요청 경합 전체를 증명하지 않으므로, 1.0 전에는 `smoke-asset-idempotency-http.php` 같은 확인 token 병렬 HTTP 동시 제출을 실행해 중복 원장 거래가 없는지 확인하고 설치 DB 기반 관리자 정정 실행 확인을 별도 보완 대상으로 둔다.

### HTML sanitizer와 CKEditor

필요한 증거:

- 허용 태그와 속성 목록
- CKEditor가 생성하는 정상 HTML fixture
- XSS payload 제거 fixture
- 임베드 매니저 marker가 허용 범위를 벗어나지 않는지 확인하는 fixture
- plain textarea fallback과 `body_format=html` 저장 경계 확인

현재 기준:

- [Rich Text Sanitizer 정책](rich-text-sanitizer-policy.md)은 공통/커뮤니티 HTML 허용 목록과 차단 기준을 문서화한다.
- `docs/smoke-test.md`는 CKEditor 활성화, fallback, 악성 HTML 정화, 본문 이미지 프록시 접근 정책을 smoke 기준으로 둔다.
- `.tools/bin/check-rich-text-sanitizer.php`는 공통 rich text sanitizer와 커뮤니티 게시글 sanitizer의 payload fixture를 검사한다. 기본 XSS payload 외에 `math`/`svg` namespace, `xlink:href`, `srcdoc`, `meta refresh`, `meta` 내부 텍스트 제거, `object`/`embed`, mixed-case 또는 entity/제어문자로 쪼갠 `javascript:` URL 우회가 결과에 남지 않는지도 확인한다. 커뮤니티 게시글 sanitizer가 별도 구현을 두지 않고 공통 `sr_sanitize_rich_text_html()`로 위임해 hard-drop 컨테이너 제거, Purifier 1차 정화, fallback canonicalizer 경로를 공유하는지도 marker로 확인한다. 콘텐츠, 커뮤니티 게시글, 팝업레이어 복사 경로가 기존 HTML을 새 레코드에 쓰기 전과 본문 이미지/임베드 참조 재작성 후 다시 sanitizer에 통과시키는지도 marker로 확인한다.
- 같은 점검은 CKEditor 정상 HTML fixture도 검사한다. 문단, 제목, 인용, 목록, 링크, 본문 이미지는 보존하고 CKEditor 내부 class, 보조 `data-*`, inline style, 링크 `target`과 클라이언트 `rel` 값은 제거해야 한다.
- `.tools/bin/check-ckeditor-assets.php`는 self-hosted asset, helper URL, textarea fallback marker와 함께 Node가 있는 로컬 환경에서 `node --check modules/ckeditor/assets/saanraan-ckeditor.js`를 실행한다. 또한 콘텐츠/커뮤니티/팝업레이어 CKEditor 업로드 textarea 속성과 업로드 action의 POST, 로그인, CSRF, 관리자 권한 또는 게시판/동의 검증, upload token no-truncation 입력, 임시 파일 cleanup marker를 확인하고, 콘텐츠 본문 이미지 프록시가 임시 이미지는 편집 관리자 세션으로 제한하고 저장 이미지는 공개/자산 접근 정책을 따르는지 marker와 SQLite access fixture로 확인하며, 팝업레이어 만료 임시 본문 이미지 cleanup fixture를 실행한다.
- `.tools/browser-qa/tests/ckeditor-browser-smoke.spec.js`는 설치 DB 없이 실행 가능한 CKEditor asset/fallback browser smoke와 upload adapter request contract smoke다. `SR_BROWSER_QA_BASE_URL=... npm --prefix .tools/browser-qa run test:ckeditor`로 실행하며, self-hosted CKEditor JS/CSS와 산란 loader를 실제 브라우저에서 로드해 textarea가 `srEditorReady=1`과 `body_format=html`로 표시되는지, 번들 로딩 실패 시 `sr-ckeditor-unavailable` class가 붙고 textarea fallback이 유지되는지 확인한다. 같은 spec은 mock endpoint로 upload adapter가 이미지 field, `csrf_token`, `upload_token`, 커뮤니티 개인정보 동의 field를 multipart 요청에 포함하고 서버 성공/오류 JSON을 올바르게 처리하는지도 확인한다. 실제 콘텐츠 관리자 업로드 action, 콘텐츠 본문 영역 기준 저장 HTML sanitizer, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, 저장 후 최종 본문 이미지 URL, draft 콘텐츠 관리자 미리보기 이미지 접근, draft 페이지/이미지 비로그인 차단은 `.tools/bin/smoke-ckeditor-upload-save.php`와 상태표 `--run-ckeditor-upload-save-smoke`로 설치 DB에서 확인한다. `check-installed-gate-status.php`는 mock HTTP fixture로 이 smoke 하니스가 로그인, 업로드, 저장, 이미지 접근 확인 흐름을 관통하는지도 점검한다. 유료 콘텐츠의 본문 이미지 접근은 별도 브라우저/수동 smoke로 대조한다.
- 공통 rich text sanitizer는 hard-drop 컨테이너를 먼저 제거하고, HTML Purifier가 있으면 Purifier를 1차 정화 엔진으로 사용하며, 이후 내부 DOM canonicalizer를 한 번 더 통과한다. Purifier가 없으면 같은 hard-drop 제거 뒤 내부 DOM sanitizer로 fallback한다. 두 경로 모두 같은 payload fixture를 통과해야 한다.
- `.tools/bin/check-htmlpurifier-runtime.php`는 번들 autoload 우선 로드, `HTMLPurifier::VERSION`, `storage/cache/htmlpurifier` 캐시 경로, Purifier 설정 allowlist, hard-drop 제거와 Purifier 1차 정화 후 내부 canonicalizer 결과를 런타임 fixture로 확인한다.
- fallback sanitizer는 보안 검증 대상이므로, 외부에 설명할 때 검증된 라이브러리 수준의 보증으로 표현하지 않는다.
- HTML Purifier 배치 방식과 라이선스 기록은 [외부 의존성 배치 기준](dependency-policy.md)을 따른다.
- 릴리스 후보 기록에서는 Purifier 로드 상태만이 아니라 `release-package-dry-run.php --manifest`의 files count와 manifest hash를 함께 남겨, 공유호스팅 배포 산출물에 `modules/htmlpurifier/vendor/`가 실제로 포함됐는지 확인한다.
- `php .tools/bin/release-preflight.php`는 Purifier 로드 상태, HTML Purifier version, 모듈 내부 autoload, cache 경로/쓰기 가능 여부, release package 파일 수, manifest hash를 read-only로 요약한다.
- `php .tools/bin/release-package-dry-run.php`와 `.tools/bin/check-release-package-policy.php`는 루트 `vendor/`, `dist/`, `storage/`, 비밀 파일, 백업/임시 파일, DB dump, SQLite/DB 파일, SSH key, package registry token 파일이 릴리스 후보에 들어오지 않는지 전체 후보 파일 목록을 검사한다. 릴리스에 포함하기로 한 모듈 내부 vendor는 허용하되, 모듈 내부의 비밀/덤프/키 파일은 제외 대상으로 유지한다.

### 공유호스팅 queue, cron, 배치 작업

필요한 증거:

- 요청 기반 자동 정리와 cron 실행의 차이 설명
- 마지막 실행 시각, 지연 작업, 실패 작업 확인 방법
- 브라우저 종료, timeout, lock 만료, 재시도 takeover 상황에서 중복 실행이 생기지 않는지 확인한 기록
- production 데이터에서 파괴적 smoke를 실행하지 않는 기준

현재 기준:

- `docs/smoke-test.md`는 고부하 관리자 작업, 작업 테이블형 lock token, 대상 단위 dedupe, query snapshot drift 기준을 포함한다.
- `docs/operational-status.md`는 queue/cron/배치 지연 신호와 read-only 운영 점검 기준을 정리한다.
- `.tools/bin/ops-status.php`는 설치된 환경에서 주요 지연/실패 count, 허용 지연, 가장 오래된 시각과 status별 summary를 출력한다.
- `/admin/operations`는 같은 기준을 관리자 화면에서 read-only로 보여주며, 허용 지연을 넘긴 항목은 `지연 초과`로 구분한다.
- `.tools/bin/check-output-helpers.php`는 공개 레이아웃 후보, 로고 위치 후보, output slot renderer metadata가 같은 요청 안에서 반복 contract 조회를 줄이는지 SQLite counting fixture로 확인한다. output slot cache는 HTML, callable, resource가 아니라 `module_key`, `contract_name`, `contract_version`, `contract_file` metadata만 저장해야 한다.
- `.tools/bin/check-operational-status.php`는 지연 신호, 허용 지연 표기, `overdue` 상태 marker, CLI row/summary 출력 형식, 관리자 route/view 연결을 확인한다. 운영 상태 점검 정의의 `table`/`age_column` 안전 식별자 검사와 `where` 조건의 세미콜론, SQL 주석, DDL/DML 키워드 거부도 SQLite fixture로 확인하며, notification delivery, community board copy, point expiration 일부 번들 신호가 실제 count/overdue로 계산되는지도 fixture로 확인한다. 포인트 만료 수동 CLI 경로는 SQLite fixture에서 만료 대상 지급분 하나만 `expire` 음수 거래로 차감하고, 원 지급 row의 `expires_remaining`/`expired_at`을 닫으며, 두 번째 실행이 중복 차감하지 않는지도 확인한다.
- `.tools/bin/check-notification-runtime.php`는 알림 delivery 수동 상태 변경 helper를 SQLite fixture로 실행해 실패/취소 delivery 재시도 시 provider message ID, 오류, 시도 시각을 비우고, 수동 성공 표시는 시도 시각과 오류 정리를 남기며, stale `before_status`나 `site` delivery 수동 변경은 row를 바꾸지 않는지 확인한다. 또한 queued delivery claim, lock timeout takeover, retry backoff, 최대 시도 후 `dead` 전환, recipient masking, web/manual/CLI batch limit 계산을 fixture로 확인한다. 운영 알림 dedupe 이벤트가 다시 발생하면 기존 계정별 읽음 기록을 초기화해 관리자 상단 안읽음 배지가 다시 뜨는지도 확인한다.
- 같은 점검은 `slack_webhook`, `discord_webhook`, `telegram_bot` 외부 푸시 채널을 허용 채널과 회원 알림 생성 채널에서 분리하고, 관리자 운영 알림의 provider별 delivery queue, 공통 claim/process dispatch, 실패 정책, provider 응답 판정, secret masking marker를 확인한다. 실제 provider 호출과 브라우저 설정 저장 smoke는 로컬 또는 staging에서 별도 기록한다.
- `.tools/bin/check-site-menu-seed-order.php`는 사이트 메뉴 seed order, 공개 렌더링, 현재 항목/상위 항목 표시, 3단계 제한, 외부 링크 보호, URL fail-closed를 확인한다. 하위 메뉴가 있는 항목의 `sr-site-menu-item-has-children`, `aria-haspopup`, `aria-expanded`, `aria-controls`와 헤더 dropdown CSS/JS marker도 확인한다. 같은 요청에서 동일 `menu_key` 렌더링이 반복될 때 enabled item tree 조회가 반복되지 않고, `sr_site_menu_clear_runtime_cache()` 호출 후 다음 렌더링이 새 tree를 조회하는지도 SQLite fixture로 확인한다.
- `.tools/bin/check-community-board-copy-job-lock.php`는 게시판 복사 작업의 `lock_token` 검증 helper와 stage/map 처리 경로의 token 전달 marker를 확인하고, stale token과 종료 상태 job이 lock assertion을 통과하지 못하는지 SQLite fixture로 확인한다.
- `.tools/bin/check-community-privacy-consent.php`는 커뮤니티 개인정보 수집 및 이용동의의 전역 기본값 normalization, 게시판 그룹 설정 상속, 게시판 설정 override, 미동의 서버 거부, 비회원 `account_id = NULL` 동의 snapshot, 제목/버전/IP hash 저장을 SQLite fixture로 확인한다. 정적 마커는 환경설정, 게시판 그룹, 게시판 개별 설정 UI와 저장 action, 공개 게시글/수정/댓글/첨부 업로드 검증 연결, 게시글/댓글 관리자 목록 동의 증적 표시를 함께 확인한다.
- `.tools/bin/check-community-board-settings.php`는 게시판 그룹/게시판 운영 설정 key, 관리자 저장 action, 공개 목록 페이지당 글 수/기본 정렬/본문 요약 적용, 게시글·댓글 본문 길이 검증, 댓글 수 기반 게시글 수정·삭제 잠금 marker를 확인한다. 런타임 fixture는 정렬 key normalization, HTML 본문 plain length 계산, 본문 요약 자르기 helper가 기대값을 유지하는지 확인한다.
- 공유호스팅에서 실시간 실행을 보장하지 않는다는 한계는 README와 운영 문서에서 함께 설명해야 한다.

### 자작 보안 컴포넌트

필요한 증거:

- 세션 쿠키, DB 세션 hash, CSRF, 요청 contract, rate limit, token HMAC, 민감정보 마스킹 기준
- 각 기준이 어느 구현 파일과 어느 자동 점검에 묶여 있는지 확인할 수 있는 문서
- POST/action 누락을 잡는 정적 점검과 dispatch contract
- 실제 배포에서 보안 헤더와 보호 경로가 적용되는 HTTP smoke

현재 기준:

- [보안 베이스라인 증거표](security-baseline-evidence.md)는 자작 보안 컴포넌트의 구현 위치와 자동 점검 연결을 정리한다.
- `.tools/bin/check-member-auth-policy.php`는 로그인 식별자, 이메일 검증 token, 비밀번호 재설정 token, 가입/재설정 비밀번호와 이메일이 허용 길이를 넘으면 truncate lookup이 아니라 거부 경로로 처리되는지 확인한다. 같은 점검은 `sr_get_string_without_truncation()`과 `sr_post_string_without_truncation()`의 길이 경계와 배열 입력 거부 fixture도 실행한다. `.tools/bin/check-notification-runtime.php`는 알림 읽음 redirect token도 truncate lookup을 쓰지 않는지 확인한다.
- `.tools/bin/check-member-oauth-runtime.php`는 OAuth state/nonce/PKCE 원문 DB 비저장, callback용 PKCE verifier 세션 임시 저장소 1회 회수, authorization URL PKCE parameter, state 1회 사용, link state account binding, provider subject HMAC과 hash prefix 표시값, provider 설정 저장/secret 마스킹 marker, 연결/해제 helper, 마지막 로그인 수단 해제 차단, generic provider callback과 completion의 policy document 동의 gate marker를 SQLite fixture와 정적 marker로 확인한다. 실제 외부 provider 계정과 브라우저 완료 흐름은 설치 DB가 있는 로컬 또는 staging smoke에서 별도 기록한다.
- `.tools/bin/check-policy-documents-runtime.php`는 새 published version 게시 시 기존 published version이 보관 처리되는지, 미래 effective published version이 현재 공개/관리자 선택 version으로 노출되지 않는지, version 단위 안내메일 job이 idempotent한지, 안내메일 batch가 정지/탈퇴 등 비활성 계정 delivery를 `skipped`로 닫아 job이 영구 queued로 남지 않는지 SQLite fixture로 확인한다.
- `.tools/bin/check-security-baseline.php`는 증거표와 핵심 helper, 기존 보안 점검기의 marker가 함께 유지되는지 확인한다.
- `.tools/bin/check-runtime-helpers.php`는 rate limit fixture로 같은 window 안의 count 증가, 만료 row 제외, 만료 후 재증가 시 1로 재시작, HMAC key/subject hash 저장, 잘못된 bucket/subject 입력 무시를 SQLite에서 확인한다.
- `.tools/bin/check-auth-runtime.php`, `.tools/bin/check-admin-action-security.php`, `.tools/bin/check-request-contract-runtime.php`, `.tools/bin/check-runtime-helpers.php`, `.tools/bin/check-upload-helpers.php`가 세부 동작과 누락 패턴을 점검한다. request contract runtime fixture는 CSRF와 관리자 auth/role guard 누락뿐 아니라 `sr_redirect()`와 `sr_finish_response()`가 contract를 통과하기 전 관리자 응답을 끝내지 못하는지도 확인한다. 관리자/action 보안 점검은 raw `exit`/`die`, 직접 `Location` header뿐 아니라 동적 헤더 이름을 만드는 `header($value)` 호출도 거부하고, 직접 `header()` 호출은 allowlisted 응답 메타 헤더 리터럴로 시작해야 한다. JSON action 응답은 직접 `Content-Type: application/json` 헤더와 `echo json_encode()`를 쓰지 않고 `sr_json_response()`를 통과해야 한다.
- `.tools/bin/check-antispam-runtime.php`는 자동등록방지 모듈의 강화 산술 challenge 변형, 정답 HMAC 세션 저장, 성공/실패/만료 후 challenge 폐기, honeypot, 최소 제출 시간, provider failure policy, `provider_unavailable`에만 허용되는 `fallback_math`, provider action/hostname 검증 토글, 활성 `antispam-providers.php` 계약 로딩, `antispam_captcha_providers` 플러그인의 Turnstile/hCaptcha/reCAPTCHA 응답 fixture, reCAPTCHA 최소 점수, secret masking, 회원가입과 비회원 커뮤니티 글/댓글 적용 marker를 확인한다. 실제 provider key와 브라우저 widget 동작은 설치 DB가 있는 로컬 또는 staging smoke에서 별도 기록해야 하며, 운영 DB에서 mutation smoke를 실행하지 않는다.
- `.tools/bin/check-output-helpers.php`는 HTML view와 CKEditor config script의 JS 값 주입이 직접 `json_encode()`가 아니라 `sr_js_json_encode()`를 쓰는지 정적 스캔한다. helper fixture는 `</script>`, quote, ampersand, invalid UTF-8 입력이 script-safe JSON으로 encode되는지도 확인한다. 응답 헤더 fixture는 `sr_json_response()`와 `sr_send_file_headers()`의 추가 헤더 allowlist가 redirect, CRLF injection, malformed cache-control, 따옴표 없는 content-disposition, 비숫자 content-length, 비정규 content-type, nosniff/pragma 외 값을 거부하는지도 확인한다.
- `.tools/bin/smoke-http.php`는 동적 진입점 응답에서 `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, 기본 `Content-Security-Policy`가 실제로 전송되는지 확인한다.
- 이 정적 점검은 외부 보안 감사나 실제 브라우저/배포 헤더 검증을 대체하지 않는다.

### 배포 보호

필요한 증거:

- Apache `.htaccess`, nginx 샘플 설정, 배포 보호 문서가 같은 내부 차단 경로를 말하는지 확인
- HTTP smoke가 `config/`, `core/`, `database/`, `docs/`, `examples/`, `storage/`, `modules/`, `.git/`, `.tools/`, `.env.*`, root metadata 직접 노출을 검사하는지 확인
- `config/config.php`가 웹에서 차단되고 파일 권한이 group/other에 열려 있지 않은지 확인

현재 기준:

- [배포 보호 기준](deployment-protection.md)은 Apache/nginx/공유호스팅 차단 기준을 정리한다.
- `.tools/bin/check-deployment-protection.php`는 배포 보호 문서, `.htaccess`, nginx 샘플, HTTP smoke 보호 경로가 같은 기준을 유지하는지 확인한다. `.env`뿐 아니라 `.env.local` 같은 `.env.*` 변형도 보호 범위에 포함하며, HTTP smoke는 `/config/config.php`와 `/storage/installed.lock` 같은 실제 민감 파일 경로도 직접 요청한다.
- `.tools/bin/check-deployment-config.php`는 `config/config.php` 권한과 DB 비밀번호 설정을 확인한다. 현재 CLI 사용자가 안전한 `0600` 설정 파일을 읽을 수 없으면 `config-mode`와 `config-owner-group`을 출력한 뒤 권한 검사만 통과시키고 내용 검사는 건너뛴다.
- HTTP smoke는 보호 경로의 원문 노출 여부를 확인한다. 실제 Apache/nginx 운영 배포에서는 같은 smoke를 운영 base URL 또는 staging base URL에서 다시 실행해 기록한다.

### 성능과 캐시

필요한 증거:

- 공유호스팅 기준에서 허용하는 캐시와 금지하는 캐시의 구분
- 관리자 대형 목록의 페이지네이션 유지 여부
- 증가 테이블의 핵심 인덱스 유지 여부
- sitemap, 개인정보 export 같은 read-only 수집 출력의 조회 상한
- 관리자 CSV export의 타입별 조회 상한과 감사 로그 metadata
- `storage/cache/` 경로 사용 범위와 배포 보호 기준

현재 기준:

- [성능과 캐시 기준](performance-policy.md)은 요청 단위 메모리 캐시, 정적 asset 캐시, HTML/개인정보/권리 상태 캐시 금지 기준을 정리한다.
- [성능 베이스라인 증거표](performance-baseline-evidence.md)는 관리자 목록, 인덱스 안전선, 캐시 경로, sitemap/export 상한, 관리자 CSV export 상한의 정적 기준을 정리한다.
- `.tools/bin/check-performance-baseline.php`는 주요 관리자 목록의 pagination marker, 증가 테이블의 핵심 인덱스 marker, 동적 HTML `no-store` 헤더와 HTTP smoke marker, 다운로드 `Cache-Control` 정규화 runtime fixture, 직접 `Cache-Control` 헤더 allowlist, 파일 기반 HTML/cache 쓰기 후보, `storage/cache` 허용 참조, sitemap/export `LIMIT 1000` marker, 설문 관리자 CSV export 타입별 상한과 감사 로그 metadata marker를 확인한다. 설문 CSV export의 실제 필터/상한/escaping 동작은 `.tools/bin/check-survey-export-runtime.php`가 fixture로 확인한다.
- `.tools/bin/check-storage-helpers.php`는 S3 공개 URL/서명 URL 설정 검증과 함께 `sr_thumbnail_*` helper, S3/local source version 산출, `storage/cache/thumbnails` 캐시 파일명 보호 규칙, GD가 있는 환경의 module-key 썸네일 생성/재사용/삭제 fixture를 확인한다.
- `.tools/bin/check-admin-pagination-runtime.php`는 페이지 번호 파싱, 마지막 페이지 clamp, offset 계산, 배열 slice, 필터 유지 pagination URL, summary/HTML disabled 상태를 fixture로 확인한다.
- `.tools/bin/check-community-board-copy-limits.php`는 커뮤니티 게시판 전체 복사의 동기 상한, 배치 전환 조건, hard block 조건, 저장소 용량 경고를 fixture로 확인한다.
- 정적 점검은 실제 DB 실행 계획이나 운영 트래픽 부하를 증명하지 않으므로, 릴리스 후보에서는 느린 화면 수동 점검 기록을 남긴다.

### 개인정보와 모듈 계약

필요한 증거:

- 개인정보 export/cleanup 계약 제공 여부
- 계정 탈퇴 또는 사본 제공 시 각 모듈의 누락 여부를 확인하는 정적 점검
- 임베드, 읽기 참조, 회원 그룹, 자산 계약 파일과 `module.php` 선언의 일치 여부

현재 기준:

- `php .tools/bin/check.php`는 여러 계약 점검을 묶어 실행한다.
- [개인정보 계약 매트릭스](privacy-contract-matrix.md)는 번들 모듈별 사본 제공, 탈퇴/익명화 cleanup, 운영 보존 판단을 분리한다.
- `.tools/bin/check-privacy-contract-matrix.php`는 매트릭스의 상태와 `module.php` 계약 선언, 실제 `privacy-export.php`/`privacy-cleanup.php` 파일 존재, 계약 파일 반환 형태와 callable signature, `install.sql`의 계정 연결 컬럼과 식별자성 컬럼 문서화, ROPA 처리활동 씨앗의 26개 번들 모듈 row와 필수 컬럼 marker, `operational_retained` 모듈의 보존 사유/운영자 접근 범위/1.0 전 검토 항목, `export_retained` 모듈의 보존 사유/고위험 필드/1.0 전 검토 항목을 대조한다.
- 같은 점검은 `updates/*.sql`도 함께 훑어 update로 새 `*_account_id`, email/recipient, 연락처, 생년월일, hash, snapshot 계열 참조가 생겼는데 개인정보 매트릭스 상태가 따라오지 않는 경우를 막는다.
- `.tools/bin/check-privacy-export-runtime.php`는 SQLite fixture로 `quiz`, `survey`, `content`, `community` export 계약과 `asset_exchange`, `coupon`, `deposit`, `notification`, `point`, `reward` 보존형 export 계약을 실행한다. 대상 계정의 상세 답변, snapshot, 접근권, 자산 로그, 다운로드 로그, 작가 신청, 시리즈, 댓글/게시글, 신고/쪽지/스크랩/동의 증적이 포함되고 다른 계정 row가 제외되는지 확인한다. 보존형 fixture는 금액성 원장, 쿠폰 환불, 환불/출금 계좌, 알림 delivery, 포인트 만료 소비 매핑 같은 운영 증빙 필드가 대상 계정 기준으로 포함되는지 확인한다.
- `.tools/bin/check-privacy-cleanup-runtime.php`는 SQLite fixture로 `quiz`, `survey`, `content`, `community`, `notification`, `policy_documents` cleanup 계약을 실행한다. 대상 계정의 응시/응답, 보상 grant, 접근권, 다운로드 로그, 작가 신청, 닉네임, 레벨, 동의 증적, 시리즈 메타데이터, 댓글/게시글 작성자 snapshot이 익명화되고 다른 계정 row가 유지되는지 확인한다. notification push endpoint ciphertext 제거와 policy_documents 안내메일 delivery 계정 연결 제거도 함께 확인한다.
- `.tools/bin/smoke-privacy-export-cleanup.php`는 로컬/staging disposable 계정으로 로그인해 `/account/privacy-export` JSON의 기본 구조, `exported_at` timestamp, 양수 `account_id`, `privacy_requests` 배열, 비어 있지 않은 `member` module export를 확인한 뒤 `/account/withdraw`를 POST하고 기존 세션의 `/account` 접근과 기존 자격증명 로그인이 막히는지 확인하는 설치 DB mutation smoke다. `check-installed-gate-status.php`는 mock HTTP fixture로 이 smoke 하니스가 로그인, export JSON 검증, 탈퇴 redirect, 기존 세션 접근 차단, 재로그인 차단 확인 흐름을 관통하는지도 점검한다. 계정을 탈퇴/익명화하므로 운영 DB에서 실행하지 않는다.
- 모듈이 늘어날수록 계약 구현 매트릭스를 유지해야 하며, 누락은 단순 TODO가 아니라 개인정보 또는 운영 정합성 리스크로 다룬다.

## 날짜별 기록 기준

실행 결과는 [릴리스 검증 기록 템플릿](release-verification-template.md)을 복사해 다음 형식으로 `docs/records/`에 남긴다.

```text
docs/records/release-verification-YYYY-MM-DD.md
```

기록할 항목:

- 실행 날짜와 대상 commit
- PHP 버전, DB 종류, 실행 base URL
- `php .tools/bin/check.php` 결과
- sanitizer를 바꾼 경우 `.tools/bin/check-rich-text-sanitizer.php` 결과
- 필요한 경우 `php .tools/bin/reconcile-assets.php` 결과
- 릴리스 후보 필수 설치 DB 게이트 결과
- 리스크별 릴리스 판정 연결 결과
- HTTP smoke 결과
- 브라우저 또는 수동 smoke 결과
- 실행하지 못한 검사의 사유
- 실패가 현재 변경의 회귀인지, 환경 미준비인지, 이미 문서화된 기존 보완 항목인지 구분
- 모듈 상태 등급 변경 근거가 있는지 여부
- `php .tools/bin/check-release-verification-records.php`가 날짜별 기록을 받아들이는지 여부

## 1.0 전 보완 대상

1.0 릴리스 후보 전까지 다음 항목을 우선 보완한다.

- 설치 DB에서 `release-installed-gate-status.php --run-readonly --fail-on-unresolved`를 실행해 `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run` 결과를 날짜별 기록에 남긴다. 현재 CLI 사용자가 `config/config.php`를 읽지 못하면 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 다시 실행한다.
- 로컬/staging 관리자 계정으로 `/admin/assets/reconciliation`과 `/admin/operations` read-only 화면을 실제 데이터와 대조한다.
- 로컬/staging disposable 계정과 더미 유료 대상으로 `smoke-community-auth.php`, `smoke-quiz-e2e.php`, `smoke-asset-idempotency-http.php`를 실행해 인증 흐름, 퀴즈 생성/응시/보상, 병렬 중복 POST, dedupe row count를 기록한다.
- 로컬/staging disposable 계정으로 `smoke-privacy-export-cleanup.php`를 실행해 개인정보 export JSON과 탈퇴/익명화 흐름을 기록한다.
- 로컬/staging base URL과 관리자 계정이 준비되면 `SR_SMOKE_BASE_URL`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`를 지정하고 `release-installed-gate-status.php --json --fail-on-unresolved` 출력으로 구조화 증거를 남긴다.
- 설치 DB에서 CKEditor 서버 업로드 action, 저장 HTML sanitizer, 임시 이미지 비로그인 차단, 저장 후 공개 이미지 접근, draft 페이지/이미지 비로그인 차단을 smoke로 확인하고, 유료 콘텐츠의 본문 이미지 접근은 별도 브라우저/수동 smoke로 대조한다.
- 설치 DB의 대표 데이터로 느린 관리자 목록, sitemap, 개인정보 export의 실행 시간과 실행 계획/인덱스 상태를 수동 점검한다.
- 릴리스 후보 검증 기록에서 필수 설치 DB 게이트가 하나라도 미해결이면 최종 판정을 `통과`가 아니라 `조건부 통과` 또는 `판정 보류`로 남긴다.
