# 릴리스 검증 기록 - YYYY-MM-DD

이 템플릿은 릴리스 후보, 큰 기능 병합, 운영 배포 전후 검증 결과를 날짜별 기록으로 남길 때 사용한다. 작성한 기록은 `docs/records/release-verification-YYYY-MM-DD.md` 또는 작업 성격이 드러나는 `docs/records/{scope}-verification-YYYY-MM-DD.md` 이름으로 저장한다.

## 대상

| 항목 | 값 |
| --- | --- |
| 실행 날짜 | YYYY-MM-DD |
| 대상 commit | `TODO` |
| 브랜치 | `TODO` |
| 작업 트리 상태 | `clean` / `dirty` |
| PHP 버전 | `TODO` |
| DB | `MySQL` / `MariaDB` / `미사용` |
| base URL | `http://127.0.0.1:PORT` / `미실행` |
| 설치 상태 | 새 설치 / 기존 로컬 DB / staging / 미설치 |
| 릴리스 zip checksum 또는 GitHub source zip 사용 여부 | SHA-256 / source zip / 미작성 |
| dry-run manifest | files=TODO, manifest-sha256=TODO / 미작성 |

## 범위

검증 대상:

- TODO

제외 또는 미실행 대상:

- TODO

## 정적 점검

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `git diff --check` | TODO |  |
| `php .tools/bin/check.php` | TODO |  |
| `php .tools/bin/check-rich-text-sanitizer.php` | TODO | sanitizer 변경 시 필수 |
| `php .tools/bin/reconcile-assets.php` | TODO | 자산 모듈 설치 환경에서 실행 |
| `php .tools/bin/ops-status.php` | TODO | 운영 지연 신호 확인 |
| `php .tools/bin/check-tool-gate-coverage.php` | TODO | 새 `check-*.php` 도구의 통합 게이트 연결 확인 |
| `php .tools/bin/check-release-verification-records.php` | TODO | 검증 기록 section, 최종 판정, 설치 DB 게이트 판정 규칙 확인 |
| `php .tools/bin/check-doc-links.php` | TODO | 문서 링크와 `.tools/bin/*.php` 명령 참조 존재 확인 |
| `php .tools/bin/check-module-status.php` | TODO | 번들 모듈 상태표와 module.php 일치, 상태 근거, 1.0 전 보강 기준 확인 |
| `php .tools/bin/check-risk-register.php` | TODO | 리스크 등록부 상태, 증거, 후속 기준 확인 |
| `php .tools/bin/check-positioning.php` | TODO | README/특장점/포지셔닝 문서의 사용 판단 기준과 과장 금지 문구 확인 |
| `php .tools/bin/check-installed-gate-status.php` | TODO | 설치 DB 게이트 상태표 도구와 문서 marker 확인 |
| `php .tools/bin/check-privacy-export-runtime.php` | TODO | quiz/survey/content/community 개인정보 export 계약의 fixture 기반 상세 답변, snapshot, 활동 데이터 포함과 타 계정 제외 확인 |
| `php .tools/bin/check-privacy-cleanup-runtime.php` | TODO | quiz/survey/content/community 개인정보 cleanup 계약의 fixture 기반 익명화와 결과 count 확인 |
| `php .tools/bin/check-admin-pagination-runtime.php` | TODO | 관리자 페이지네이션 helper의 clamp, offset, URL, HTML 상태 확인 |
| `php .tools/bin/check-community-board-copy-limits.php` | TODO | 커뮤니티 게시판 전체 복사의 동기 상한, 배치 전환, hard block, 저장소 경고 fixture 확인 |
| `php .tools/bin/check-community-board-copy-job-lock.php` | TODO | 커뮤니티 게시판 복사 batch job lock token fixture와 stage/map token 전달 marker 확인 |
| `php .tools/bin/check-asset-exchange-runtime.php` | TODO | 환전 성공 실행과 입금 원장 실패 rollback fixture 확인 |
| `php .tools/bin/check-htmlpurifier-vendor-integrity.php` | TODO | HTML Purifier vendor 버전, source reference, 라이선스, 런타임 클래스 버전 대조 |
| `php .tools/bin/check-htmlpurifier-runtime.php` | TODO | HTML Purifier autoload, cache 경로, 설정 allowlist, sanitizer canonicalizer 대조 |
| `php .tools/bin/check-ckeditor-assets.php` | TODO | CKEditor self-hosted asset, 라이선스, fallback marker, HTTP smoke marker 확인 |
| `php .tools/bin/check-browser-qa.php` | TODO | Playwright 하니스, CKEditor 브라우저 asset/fallback smoke spec, JS 문법 확인 |
| `php .tools/bin/smoke-asset-idempotency-http.php` | TODO | 설치 DB 더미 유료 대상에서 `SR_SMOKE_ALLOW_MUTATION=1`로 같은 확인 token 병렬 POST와 dedupe row count 확인 |
| `php .tools/bin/check-release-package-policy.php` | TODO | 릴리스 산출물 포함/제외 기준과 vendored dependency 기준 |
| `php .tools/bin/release-preflight.php` | TODO | Purifier 로드 상태, dependency version, cache 경로, CKEditor self-hosted asset, dry-run manifest 요약 |
| `php .tools/bin/release-installed-gate-status.php` | TODO | 설치 DB 게이트 상태표 생성. 설치 DB에서 read-only CLI 게이트(`reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run`)까지 실행하려면 `php .tools/bin/release-installed-gate-status.php --run-readonly` 사용. 설치 DB가 필요 없는 CKEditor asset/fallback browser smoke까지 상태표에서 실행하려면 base URL을 지정하고 `--run-browser-qa` 사용. 인증 smoke는 로컬/staging disposable 데이터에서 `SR_SMOKE_ALLOW_MUTATION=1`과 테스트 계정을 지정한 뒤 `--run-auth-smoke` 사용. 퀴즈 E2E smoke는 disposable 데이터와 관리자 계정을 지정한 뒤 `--run-quiz-smoke` 사용. 자산 병렬 mutation smoke는 disposable 유료 대상과 `SR_SMOKE_FORM_PATH`, `SR_SMOKE_EXPECT_DEDUPE_TABLE`, `SR_SMOKE_EXPECT_DEDUPE_KEY`를 지정한 뒤 `--run-asset-smoke` 사용. CKEditor upload/save browser smoke는 설치 DB에서 저장/업로드를 만들 수 있으므로 관리자 계정과 `SR_SMOKE_ALLOW_MUTATION=1`이 준비된 경우에만 수동 확인 대상으로 기록. 개인정보 export/cleanup smoke는 더미 계정 cleanup이 데이터를 바꿀 수 있으므로 테스트 계정과 `SR_SMOKE_ALLOW_MUTATION=1`이 준비된 경우에만 수동 확인 대상으로 기록. 성능 수동 점검은 대표 데이터가 있는 로컬/staging base URL과 `SR_PERFORMANCE_REVIEW_READY=1`이 준비된 경우에만 수동 확인 대상으로 기록. 개인정보 fixture 증거는 `--run-privacy-fixtures`, 성능 fixture 증거는 `--run-performance-fixtures`로 부분 확인하되 설치 DB smoke나 수동 점검을 대체하지 않음 |
| `php .tools/bin/release-package-dry-run.php` | TODO | 직접 제작 zip 후보 파일 목록의 필수/금지 경로 점검 |
| `php .tools/bin/release-package-dry-run.php --manifest` | TODO | 직접 제작 zip 후보 파일 집합 hash 기록 |
| 기타 점검 | TODO |  |

결과 로그:

```text
TODO
```

## 릴리스 후보 필수 설치 DB 게이트

릴리스 후보 판정에서는 미설치 install-mode smoke만으로 통과 처리하지 않는다. 다음 항목은 새 설치 또는 기존 로컬/staging DB에서 실행하고, 실행하지 못한 경우 최종 판정을 `조건부 통과` 또는 `판정 보류`로 낮춘다. 필수 설치 DB 게이트 표에서 하나라도 `통과`가 아니면 릴리스 후보 최종 판정을 `통과`로 기록하지 않는다. 운영 DB에서는 파괴적이거나 mutation이 있는 smoke를 실행하지 않는다.

먼저 `php .tools/bin/release-installed-gate-status.php`를 실행해 설치 DB 게이트 상태표를 만든다. 현재 CLI 사용자가 `config/config.php`를 읽을 수 있고 설치 lock이 있으면 `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run` 같은 read-only CLI 게이트는 `php .tools/bin/release-installed-gate-status.php --run-readonly`로 함께 실행할 수 있다. 관리자 read-only 화면 게이트는 `SR_SMOKE_BASE_URL`만으로 충분하지 않으며, 로컬/staging 관리자 테스트 세션을 만들 수 있도록 `SR_SMOKE_ADMIN_IDENTIFIER`와 `SR_SMOKE_ADMIN_PASSWORD`를 함께 지정해야 `수동 확인 필요` 상태로 올라간다. `SR_BROWSER_QA_BASE_URL` 또는 `SR_SMOKE_BASE_URL`이 있으면 `php .tools/bin/release-installed-gate-status.php --run-browser-qa`로 설치 DB가 필요 없는 CKEditor asset/fallback browser smoke도 상태표 안에서 실행할 수 있다. 인증 smoke는 게시글, 댓글, 쪽지 같은 데이터를 만들 수 있으므로 로컬/staging disposable 데이터에서만 `SR_SMOKE_ALLOW_MUTATION=1`, `SR_SMOKE_IDENTIFIER`, `SR_SMOKE_PASSWORD`를 함께 지정하고 `php .tools/bin/release-installed-gate-status.php --run-auth-smoke`로 실행한다. 퀴즈 E2E smoke는 퀴즈와 응시 기록을 만들 수 있으므로 로컬/staging disposable 데이터에서만 `SR_SMOKE_ALLOW_MUTATION=1`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`를 함께 지정하고 `php .tools/bin/release-installed-gate-status.php --run-quiz-smoke`로 실행한다. 자산 병렬 mutation smoke도 금액성 기록을 만들 수 있으므로 disposable 유료 대상에서만 `SR_SMOKE_ALLOW_MUTATION=1`, 테스트 계정 `SR_SMOKE_IDENTIFIER`/`SR_SMOKE_PASSWORD`, `SR_SMOKE_FORM_PATH`, `SR_SMOKE_EXPECT_DEDUPE_TABLE`, `SR_SMOKE_EXPECT_DEDUPE_KEY`와 필요한 POST payload를 지정하고 `php .tools/bin/release-installed-gate-status.php --run-asset-smoke`로 실행한다. CKEditor upload/save browser smoke는 저장/업로드 데이터를 만들 수 있으므로 `SR_SMOKE_BASE_URL`, `SR_SMOKE_ADMIN_IDENTIFIER`, `SR_SMOKE_ADMIN_PASSWORD`, `SR_SMOKE_ALLOW_MUTATION=1`이 모두 로컬/staging disposable 데이터에서 준비된 경우에만 `수동 확인 필요` 상태로 올린다. 개인정보 export/cleanup 설치 DB smoke는 더미 계정 cleanup이 데이터를 바꿀 수 있으므로 `SR_SMOKE_BASE_URL`, `SR_SMOKE_IDENTIFIER`, `SR_SMOKE_PASSWORD`, `SR_SMOKE_ALLOW_MUTATION=1`이 모두 준비된 경우에만 `수동 확인 필요` 상태로 올린다. 성능 수동 점검은 대표 데이터가 있는 로컬/staging base URL에서만 의미가 있으므로 `SR_SMOKE_BASE_URL`과 `SR_PERFORMANCE_REVIEW_READY=1`이 함께 있을 때만 느린 관리자 목록, sitemap, 개인정보 export 조회 상한, 실행 계획 확인 대상으로 기록한다. 테스트 계정 식별자와 비밀번호, 관리자 식별자와 비밀번호, 그리고 dedupe table/key는 한쪽만 있으면 각각 `incomplete`로 기록하고 smoke 실행 대상으로 보지 않는다. `php .tools/bin/release-installed-gate-status.php --run-privacy-fixtures`는 개인정보 export/cleanup SQLite 계약 fixture를 상태표 안에 `부분 확인`으로 남기지만, 로컬/staging 더미 계정으로 수행하는 설치 DB smoke를 대체하지 않는다. `php .tools/bin/release-installed-gate-status.php --run-performance-fixtures`는 성능 정책, pagination, board copy limit, survey export fixture를 `부분 확인`으로 남기지만 데이터가 있는 설치 DB에서 느린 화면과 실행 계획을 확인하는 수동 점검을 대체하지 않는다. 이 도구는 `config-mode`와 `config-owner-group`도 출력하므로, 공유호스팅 보안 권한 때문에 현재 CLI 사용자가 설치 DB를 읽지 못하는 경우에는 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 다시 점검한다. 이 도구의 출력은 아래 표를 대체하지 않고, 표의 결과와 미실행 사유를 빠뜨리지 않기 위한 보조 증거다.

| 게이트 | 결과 | 환경 | 메모 |
| --- | --- | --- | --- |
| 새 설치 또는 업데이트 적용 | TODO | 새 설치 / 기존 로컬 DB / staging | 설치 wizard, update SQL, pending update 없음 |
| `php .tools/bin/reconcile-assets.php` | TODO | 자산 모듈 설치 DB | balance row, 거래 합계, 마지막 `balance_after`, 거래별 `balance_after` 연쇄 대조 |
| `php .tools/bin/ops-status.php` | TODO | 설치 DB | queue/cron/배치 지연과 `지연 초과` 기준 확인 |
| `php .tools/bin/expire-points.php --dry-run` | TODO | 포인트 모듈 설치 DB | 만료 대상 건수/금액 preview가 원장 row를 만들지 않는지 확인 |
| `/admin/assets/reconciliation` | TODO | 설치 DB + 관리자 계정 | read-only 화면, 불일치 표시, 후속 확인 문구 |
| `/admin/operations` | TODO | 설치 DB + 관리자 계정 | read-only 화면, 허용 지연, 지연 초과 표시 |
| 인증 smoke | TODO | 로컬/staging 테스트 계정 | `smoke-community-auth.php`, 필요한 도메인별 인증 smoke |
| 퀴즈 E2E smoke | TODO | 로컬/staging 관리자 계정 | `smoke-quiz-e2e.php` 퀴즈 생성, 제출, 보상, 재응시 차단 |
| 자산/쿠폰/유료 접근권 mutation smoke | TODO | 로컬/staging 더미 데이터 | `smoke-asset-idempotency-http.php` 병렬 중복 POST, 환불/회수, 쿠폰 우선 적용 |
| 개인정보 export/cleanup smoke | TODO | 로컬/staging 더미 계정 | 사본 제공, 탈퇴/익명화, 운영 보존 데이터 표시 |
| CKEditor asset/fallback browser smoke | TODO | 브라우저 + base URL | `SR_BROWSER_QA_BASE_URL=... npm --prefix .tools/browser-qa run test:ckeditor`로 self-hosted asset 로딩, html format marker, textarea fallback 확인. 설치 DB 불필요 |
| CKEditor upload/save browser smoke | TODO | 브라우저 + 설치 DB | 업로드 adapter, 저장 HTML sanitizer, 권한별 본문 이미지 접근 |
| 성능 수동 점검 | TODO | 데이터가 있는 설치 DB | 느린 관리자 목록, sitemap, 개인정보 export 조회 상한 |

## HTTP Smoke

서버 실행 명령:

```sh
php -S 127.0.0.1:PORT -t .tools/public .tools/bin/dev-router.php
```

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:PORT php .tools/bin/smoke-http.php` | TODO |  |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:PORT SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php` | TODO | 커뮤니티 설치 환경에서 실행 |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:PORT SR_SMOKE_MEMBER_ONLY=1 php .tools/bin/smoke-http.php` | TODO | 회원 전용 모드 검증 시 실행 |

결과 로그:

```text
TODO
```

## 인증 Smoke

운영 DB에서는 실행하지 않는다. 로컬 또는 staging 테스트 계정과 더미 데이터만 사용한다.

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `php .tools/bin/smoke-community-auth.php` | TODO | 로컬/staging disposable 데이터에서 `SR_SMOKE_ALLOW_MUTATION=1`과 테스트 계정 필요 |
| `php .tools/bin/smoke-quiz-e2e.php` | TODO | 로컬/staging disposable 데이터에서 `SR_SMOKE_ALLOW_MUTATION=1`로 퀴즈 더미 데이터 생성 |
| 기타 인증 smoke | TODO |  |

결과 로그:

```text
TODO
```

## 브라우저/수동 점검

| 영역 | 결과 | 확인 내용 |
| --- | --- | --- |
| 설치/업데이트 | TODO |  |
| 관리자 권한/CSRF | TODO |  |
| 콘텐츠/커뮤니티 | TODO |  |
| 자산/쿠폰/유료 접근권 | TODO |  |
| CKEditor/HTML sanitizer | TODO | `npm --prefix .tools/browser-qa run test:ckeditor` asset/fallback, 업로드 adapter, 저장 HTML sanitizer |
| 개인정보 export/cleanup | TODO |  |
| queue/cron/배치 작업 | TODO |  |

운영 상태 점검:

```sh
php .tools/bin/ops-status.php
```

## 실패와 제한

| 항목 | 분류 | 판정 | 후속 |
| --- | --- | --- | --- |
| TODO | 회귀 / 환경 미준비 / 기존 보완 항목 / 미실행 | TODO | TODO |

분류 기준:

- 회귀: 현재 변경 또는 대상 commit에서 새로 깨진 동작
- 환경 미준비: DB, 계정, 권한, 서버 설정, 외부 서비스 부재로 실행하지 못한 항목
- 기존 보완 항목: 이미 문서화된 리스크 또는 1.0 전 보완 항목
- 미실행: 시간, 범위, 안전성 문제로 이번 기록에서 실행하지 않은 항목

## 리스크별 릴리스 판정 연결

[프로젝트 리스크 레지스터](risk-register.md)의 각 항목이 이번 검증 기록에서 어떤 증거로 판정됐는지 연결한다. `open` 또는 `mitigating` 항목이 실행 증거 없이 남아 있으면 최종 판정을 `통과`로 기록하지 않는다.

| 리스크 | 연결된 검증 증거 | 이번 판정 | 후속 |
| --- | --- | --- | --- |
| R-01 자산/쿠폰/유료 접근권 | reconciliation, mutation smoke, 동시성 fixture, 환전 rollback fixture, 관리자 정정/복구 확인 | TODO | TODO |
| R-02 HTML sanitizer/CKEditor | sanitizer fixture, Purifier 로드 상태, fallback sanitizer fixture, `check-browser-qa.php`, `ckeditor-browser-smoke.spec.js`, CKEditor asset/fallback browser smoke, CKEditor upload/save browser smoke | TODO | TODO |
| R-03 공유호스팅 queue/cron/배치 | `ops-status.php`, `expire-points.php --dry-run`, `/admin/operations`, board copy job lock fixture, 지연/실패 row 확인 | TODO | TODO |
| R-04 개인정보 export/cleanup 계약 | privacy matrix, export/cleanup smoke, 운영 보존 데이터 검토 | TODO | TODO |
| R-05 넓은 번들 모듈 표면 | module status, `check-module-status.php`, `release-installed-gate-status.php`, asset_ledger/point/coupon/community/quiz/privacy/ckeditor 설치 게이트 연결, beta smoke 기록, 등급 상향 근거 | TODO | TODO |
| R-06 커스텀 요청/보안 contract | security baseline, request contract runtime, admin action security, 인증/권한 smoke, 보안 헤더 | TODO | TODO |
| R-07 외부 의존성/vendored asset | dependency policy, `modules/htmlpurifier/DEPENDENCY.md`, vendor integrity, release package dry-run, dry-run manifest, Purifier 로드 상태, CKEditor self-hosted asset, fallback sanitizer fixture | TODO | TODO |
| R-08 배포 보호 | deployment protection, HTTP 보호 경로 smoke, Apache/nginx 확인 | TODO | TODO |
| R-09 문서/Wiki 지연 | README, 구현 스냅샷, 릴리스 절차, Wiki 갱신 여부 | TODO | TODO |
| R-10 국내 CMS 대비 신뢰 증거 | positioning, 릴리스 검증 기록 누적, 사용 판단 기준 | TODO | TODO |
| R-11 성능/캐시 기준 | performance baseline, pagination fixture, board copy limit fixture, 느린 화면 수동 점검, 실행 계획/인덱스 검토 | TODO | TODO |

## 모듈 상태 영향

[모듈 상태 등급](module-status.md)을 바꿀 근거가 생겼는지 기록한다.

| 모듈 | 기존 상태 | 변경 후보 | 근거 |
| --- | --- | --- | --- |
| TODO | TODO | 변경 없음 / `stable-candidate` 후보 / `beta` 유지 | TODO |

## 판정

최종 판정:

- 통과 / 조건부 통과 / 실패 / 판정 보류

릴리스 또는 배포 가능 여부:

- TODO

릴리스 후보 필수 설치 DB 게이트 미실행 여부:

- 없음 / 있음

후속 작업:

- TODO
