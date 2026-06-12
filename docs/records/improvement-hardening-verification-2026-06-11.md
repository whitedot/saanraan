# 개선 보강 검증 기록 - 2026-06-11

프로젝트 평가에서 나온 운영 신뢰성, sanitizer, 자산 정합성, 공유호스팅 운영 한계, 경쟁 대비 포지셔닝 보완 작업을 한 묶음으로 점검한 기록이다. 이 기록은 릴리스 후보 판정이 아니라 현재 개선 묶음의 정적 검증과 미실행 항목을 분리하기 위한 기준이다.

## 대상

| 항목 | 값 |
| --- | --- |
| 실행 날짜 | 2026-06-11, 2026-06-12 재검증 |
| 대상 범위 | 2026-06-11 개선 보강 시작 이후 2026-06-12 재검증까지의 누적 작업 |
| 기준 commit | 기록 파일 자체를 계속 갱신하는 개선 묶음이므로 단일 hash 대신 각 작업 단위 commit과 최종 `git log`/검증 로그로 대조 |
| 브랜치 | `main` |
| 작업 트리 상태 | `clean` |
| PHP 버전 | `PHP 8.4.12 (cli)` |
| DB | 미사용 |
| base URL | 로컬 PHP 내장 서버 재검증. 포트는 실행 시점별 가용 포트를 사용 |
| 설치 상태 | 미설치 로컬 환경 |

## 범위

검증 대상:

- HTML Purifier 우선 rich text sanitizer와 내부 fallback canonicalizer 정책
- rich text sanitizer payload fixture와 정책 문서 동기화
- CKEditor 정상 HTML fixture의 서버 canonicalize 결과 점검
- 자산 원장 reconciliation 도구와 member asset transaction 계약 점검
- read-only 관리자 자산 원장 정합성 화면
- 자산 소비 pending placeholder 기반 중복 POST 방어 순서 점검
- 자산 deadlock retry, 유료 다운로드 전달 순서, 환전 로그, 쿠폰 관리자 검증, 관리자 자산 제한 회귀 점검의 통합 게이트 편입
- 요청/cron 기반 운영 상태 점검 도구
- read-only 관리자 운영 상태 화면
- queue/cron/배치 지연 허용 범위와 `지연 초과` 운영 상태 구분
- 번들 모듈별 개인정보 계약 매트릭스와 정적 점검
- 운영 보존 개인정보의 보존 사유, 운영자 접근 범위, 1.0 전 검토 항목 고정
- 자작 보안 컴포넌트 증거표와 정적 점검
- HTTP smoke의 동적 진입점 보안 헤더 확인
- 배포 보호 문서, Apache/nginx 설정, HTTP smoke 보호 경로 동기화 점검
- 성능/캐시 베이스라인 증거표와 정적 점검
- 모듈 상태, 리스크 등록부, 포지셔닝, 외부 의존성, 보안 대응, 기여 기준, 성능 정책 문서
- README/특장점/포지셔닝 문서의 사용 판단 기준과 과장 금지 문구 점검
- 릴리스 후보 필수 설치 DB 게이트 문서화
- 새 문서 링크와 전체 정적 점검
- 미설치 로컬 환경에서 HTTP smoke install-mode 동작

제외 또는 미실행 대상:

- 설치 DB를 사용하는 자산 reconciliation 실제 대조
- 설치 DB를 사용하는 `/admin/assets/reconciliation` 실제 화면 확인
- 설치 DB를 사용하는 queue/cron 운영 지연 실제 조회
- 인증 smoke와 더미 데이터 기반 자산/커뮤니티/퀴즈/설문 mutation smoke
- 설치 DB가 필요한 CKEditor 업로드 adapter, 저장 HTML sanitizer, 관리자 화면 상호작용 확인
- HTML Purifier 포함 배포 패키지의 빌드 절차 검증

## 정적 점검

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `git diff --check` | 통과 | 공백 오류 없음 |
| `php .tools/bin/check.php` | 통과 | 전체 정적 점검 통과 |
| `php .tools/bin/check-rich-text-sanitizer.php` | 통과 | HTML Purifier v4.19.0 포함 경로 기준, Purifier 직접 출력 fixture, 내부 fallback 직접 경로 기준, XSS payload, `math`/`svg` namespace, `xlink:href`, `srcdoc`, `meta refresh`, `object`/`embed`, mixed-case 또는 entity/제어문자로 쪼갠 `javascript:` URL 우회 payload, `href` 없는 링크 제거, `sr_body_text_html()` html/plain 렌더링, CKEditor 정상 HTML fixture, 커뮤니티 sanitizer의 공통 `sr_sanitize_rich_text_html()` 위임 marker, content/community/popup/notification rich text 저장/출력 marker 점검 |
| `php .tools/bin/reconcile-assets.php` | 환경 미준비 | 미설치 환경이라 `saanraan is not installed.`와 exit 2 반환 |
| `php .tools/bin/check-asset-reconciliation.php` | 통과 | CLI와 관리자 reconciliation 계약, SQLite fixture 기반 `missing_balance_row`, `balance_sum_mismatch`, `last_balance_after_mismatch`, `nonzero_balance_without_transactions`, `balance_after_sequence_mismatch` 유형과 summary flag, sequence mismatch transaction id/기대값/실제값, reward/deposit 공통 원장 pair와 point 예외 점검 |
| `php .tools/bin/check-asset-idempotency.php` | 통과 | pending placeholder와 unique dedupe 기반 중복 방어 순서 점검. 콘텐츠/커뮤니티 자산 확인 token과 환전 확정 token의 no-truncation action marker 확인. SQLite fixture로 최초 claim/중복 무시/completed sticky/pending cleanup 후 재시도 가능 상태 전이, 파일 DB의 두 PDO 연결 간 중복 claim 흡수, `pcntl`이 있는 CLI 환경의 병렬 프로세스 fixture에서 같은 `dedupe_key` 성공 insert가 정확히 1개만 남는지 확인. 설치 DB 병렬 HTTP 하니스 `smoke-asset-idempotency-http.php`의 `SR_SMOKE_ALLOW_MUTATION=1` 안전장치와 문서 marker 확인 |
| `php .tools/bin/check-asset-settlement-contract.php` | 통과 | settlement 계약 marker와 runtime fixture 점검. 0원 처리, 정확 충당, 1단위 미만 ceil overpay 금지, 통화 불일치, 알 수 없는 통화 fail-closed, rounding policy snapshot 확인 |
| `php .tools/bin/check-asset-deadlock-retry.php` | 통과 | 금액성 원장 deadlock/lock wait retry marker 점검, 통합 게이트 포함 |
| `php .tools/bin/check-paid-download-delivery.php` | 통과 | 유료 파일/첨부 전달 준비가 차감보다 앞서는 순서 점검. 파일 또는 signed URL 준비 실패 시 차감 없음, 차감 실패 시 전달 없음, 성공 시 준비-차감-전달 fixture 확인 |
| `php .tools/bin/check-content-file-cleanup-runtime.php` | 통과 | SQLite fixture로 콘텐츠 삭제 시 파일 row redaction, 링크 숨김, 다운로드 로그 snapshot redaction, 링크 참조 제거, 시리즈 회차 제거, 임베드 참조 removed 전환, 저장소 cleanup 실패 미발생 확인. 저장소 쓰기 가능 환경에서는 고유 fixture 파일과 본문 이미지 실삭제까지 확인 |
| `php .tools/bin/check-content-copy-runtime.php` | 통과 | SQLite fixture로 콘텐츠 복사 시 HTML 본문 임베드 ref key 재작성, 새 owner ref 생성, 원본 ref 유지, 명시적/legacy 첨부 링크 복사, 시리즈 사본과 회차 메타데이터 복사 확인 |
| `php .tools/bin/check-community-attachment-runtime.php` | 통과 | SQLite fixture로 커뮤니티 첨부 다운로드 자산 로그 placeholder dedupe, 0원 완료 로그 settlement metadata, 첨부 다운로드 접근권 중복 방지, anonymized entitlement 무시 확인 |
| `php .tools/bin/check-quiz-reward-runtime.php` | 통과 | SQLite fixture로 퀴즈 보상 grant dedupe, 같은 시도 재호출 중복 거래 방지, 같은 퀴즈의 다른 시도 duplicate 판정, 실패 grant 재시도와 실패 상태 정리, grant 기준 원장 거래 lookup, 회수 가능액 계산, 회수 실행 확인 |
| `php .tools/bin/check-quiz-delete-runtime.php` | 통과 | SQLite fixture로 퀴즈 삭제 시 문항/선택지/결과/댓글 redaction, 응시 source snapshot과 return URL 정리, 응답/채점/결과 snapshot 정리, 보상 grant snapshot과 운영 메모 정리 확인 |
| `php .tools/bin/check-survey-response-runtime.php` | 통과 | SQLite fixture로 설문 응답 제출의 동의/연구 메타데이터/답변 snapshot, 기타 답변, 복수 선택 min/max, 무응답 선택지 배타성, 숫자 범위, 익명 중복 제한 확인 |
| `php .tools/bin/check-survey-reward-runtime.php` | 통과 | SQLite fixture로 설문 보상 grant dedupe, 같은 응답 재호출 중복 거래 방지, 같은 설문의 다른 응답 duplicate 판정, 실패 grant 재시도와 실패 상태 정리 확인 |
| `php .tools/bin/check-survey-statistics-runtime.php` | 통과 | SQLite fixture로 설문 통계의 테스트 응답 제외, 선택형 응답 단위 중복 제거, 제외 응답 배제, 숫자형 평균/최소/최대 계산 확인 |
| `php .tools/bin/check-survey-export-runtime.php` | 통과 | SQLite fixture로 설문 CSV export 타입별 상한, formula-like cell escaping, raw/analysis/codebook 필터, 테스트 응답 포함 옵션, 제외 응답 배제 확인 |
| `php .tools/bin/check-asset-exchange-logs.php` | 통과 | 환전 로그 저장값과 보정 update 기준 점검. SQLite fixture로 수수료가 있는 완료 환전 묶음 정정의 원장 반전, 정정 로그, 중복 정정 차단 확인. 관리자 로그 화면의 CSRF/edit 권한 기반 정정 action과 감사 로그 marker 확인. 통합 게이트 포함 |
| `php .tools/bin/check-asset-exchange-runtime.php` | 통과 | SQLite fixture로 실제 reward/deposit 자산 계약을 통한 환전 성공 실행의 출금/입금/수수료 원장과 완료 로그 연결, 입금 원장 저장 실패 시 출금 원장/로그 rollback 확인 |
| `php .tools/bin/check-coupon-admin-validation.php` | 통과 | 쿠폰 관리자 key/숫자/중복 검증 기준 점검, 통합 게이트 포함 |
| `php .tools/bin/check-coupon-redemption-runtime.php` | 통과 | SQLite fixture로 쿠폰 사용 dedupe, max uses 상태 전이, used issue 추가 사용 거부, 환불 시 dedupe key 격리, issue 재활성화, content/community 접근권 회수, 콘텐츠 유료 열람/다운로드의 쿠폰 우선 적용과 자산 거래/접근 로그 미생성, `content_file` 쿠폰 target contract, 쿠폰 기반 유료 다운로드 접근권 회수, 쿠폰 접근권 부분 실패 rollback 확인 |
| `php .tools/bin/check-admin-asset-limits.php` | 통과 | 관리자 자산 한도 검증 기준 점검, 통합 게이트 포함 |
| `php .tools/bin/check-privacy-contract-matrix.php` | 통과 | 번들 모듈별 개인정보 export/cleanup/보존 판단, 계약 선언, 계약 파일 반환 형태와 callable signature, cleanup array 반환 타입, 설치/update SQL의 계정 참조 컬럼과 `no_member_personal_data` 분류 충돌 방지, `operational_retained` 보존 세부 기준과 `export_retained` 고위험 필드 기준 대조 |
| `php .tools/bin/check-privacy-export-runtime.php` | 통과 | SQLite fixture로 `quiz`/`survey`/`content`/`community` export 계약을 실행해 대상 계정의 응시/응답, 상세 답변, 결과/댓글/보상 기록, 접근권, 자산 로그, 다운로드 로그, 작가 신청, 시리즈, 게시글/댓글, 신고/쪽지/스크랩/동의 증적 포함, 다른 계정 row 제외, JSON snapshot 구조화를 확인 |
| `php .tools/bin/check-privacy-cleanup-runtime.php` | 통과 | SQLite fixture로 `quiz`/`survey`/`content`/`community` cleanup 계약을 실행해 응시/응답, 보상 grant, 접근권, 다운로드 로그, 작가 신청, 닉네임, 레벨, 동의 증적, 시리즈 메타데이터, 작성자 snapshot 익명화, 다른 계정 row 유지, 결과 count 반환을 확인 |
| `php .tools/bin/check-htmlpurifier-vendor-integrity.php` | 통과 | `VERSION`, `HTMLPurifier::VERSION`, `composer.lock`, `vendor/composer/installed.json`, `installed.php`, `DEPENDENCY.md`, 라이선스 marker가 같은 package/version/source reference를 가리키는지 확인 |
| `php .tools/bin/check-htmlpurifier-runtime.php` | 통과 | 번들 autoload 우선 로드, HTML Purifier version, `storage/cache/htmlpurifier` cache 경로/쓰기 가능 여부, Purifier 설정 allowlist, Purifier 선행 정화 후 내부 canonicalizer 결과 확인 |
| `php .tools/bin/check-ckeditor-assets.php` | 통과 | CKEditor self-hosted asset JS/CSS, 라이선스 파일, README 버전, helper asset URL, 초기화 성공 시 `body_format=html`, 실패 시 textarea fallback, upload CSRF/token no-truncation marker, Node가 있는 환경의 `node --check`, 콘텐츠/커뮤니티/팝업레이어 업로드 action 보안 계약 marker, 팝업레이어 만료 임시 본문 이미지 cleanup fixture, HTTP smoke marker 확인 |
| `php .tools/bin/check-security-baseline.php` | 통과 | 세션, CSRF, 요청 contract, rate limit, token HMAC, 민감정보 마스킹, redirect/URL validator, HTTP smoke 보안 헤더 기준과 구현 marker 대조. HMAC app key 필수, 다운로드 token 목적/대상/만료 binding, rate limit count/window/HMAC subject fixture, 민감정보 문자열/metadata 마스킹, safe relative redirect와 public HTTP URL fixture 점검 |
| `php .tools/bin/check-output-helpers.php` | 통과 | 출력 helper 정책 점검. JSON 응답/header allowlist, JS JSON script-safe escaping, 다운로드/file header helper, public 외부 redirect와 trusted storage redirect의 subprocess runtime fixture, view/CKEditor config의 script context JSON helper 사용 여부 점검 |
| `php .tools/bin/check-request-contract-runtime.php` | 통과 | CSRF token 생성 안정성, 정상 POST CSRF contract 완료, 잘못된 CSRF guard 차단, POST CSRF 호출 누락 contract violation, 공개 GET 완료, 관리자 GET 로그인/권한 guard 누락 violation, 관리자 guard mark 완료, 공개 redirect 완료, 관리자 guard 누락 redirect/response 종료 차단을 subprocess fixture로 확인 |
| `php .tools/bin/check-deployment-protection.php` | 통과 | 배포 보호 문서, Apache/nginx 설정, dev-router 보호 규칙, `.env.*` 변형 차단 기준, HTTP smoke 보호 경로 marker 대조. `/config/config.php`와 `/storage/installed.lock` 직접 요청도 보호 경로에 포함 |
| `php .tools/bin/check-deployment-config.php` | 통과 | `config/config.php`는 현재 CLI 사용자에게 읽히지 않으나 `config-mode: 0600`, `config-owner-group: www-data:www-data`를 출력하고 권한 검사 통과, 내용 검사는 생략 |
| `php .tools/bin/check-performance-baseline.php` | 통과 | 관리자 목록 pagination, 쿠폰 정의/지급/사용 내역 pagination, 증가 테이블의 핵심 인덱스 marker, 동적 HTML `no-store` 헤더와 HTTP smoke marker, 직접 `Cache-Control` 헤더 allowlist, 파일 기반 HTML/cache 쓰기 후보, 캐시 경로, sitemap/export 상한, 설문 관리자 CSV export 타입별 상한과 감사 로그 metadata, 게시판 복사 limit fixture 연결 marker 점검 |
| `php .tools/bin/check-admin-pagination-runtime.php` | 통과 | 페이지 번호 파싱, 마지막 페이지 clamp, offset 계산, 배열 slice, 필터 유지 pagination URL, summary/HTML disabled 상태 fixture 확인 |
| `php .tools/bin/check-community-board-copy-limits.php` | 통과 | 커뮤니티 게시판 전체 복사의 동기 상한, 배치 전환 조건, unsupported storage/missing file/legacy token hard block, 저장소 용량 경고 fixture 확인 |
| `php .tools/bin/check-community-board-copy-job-lock.php` | 통과 | 게시판 복사 batch job lock token helper, stale/empty/completed token 거부 fixture, stage/map 처리 경로 token 전달 marker 확인 |
| `php .tools/bin/check-release-package-policy.php` | 통과 | 릴리스 산출물 포함/제외 기준, 대표 비밀/런타임 제외 샘플 self-test, 후보 파일 전체의 루트 `vendor`/`dist`/`storage`, 비밀 파일, 백업/임시 파일, DB dump, SQLite/DB 파일, SSH key, package registry token 금지 패턴 스캔, HTML Purifier vendor/license/version 기준, CKEditor self-hosted asset/license/version 기준, `release-preflight.php` 출력 형식, dry-run `--list`/`--manifest` 파일 집합 일치, 정렬/중복 없음, manifest hash 재계산 검증 |
| `php .tools/bin/release-preflight.php` | 통과 | Purifier 로드 상태, HTML Purifier version, 모듈 내부 autoload, cache 경로/쓰기 가능 여부, dependency record, CKEditor self-hosted asset version/license, release package 파일 수와 manifest hash 요약. 2026-06-12 재검증 기준 후보 파일 수는 `1490`이며, manifest hash는 기록 파일 편집으로 다시 바뀔 수 있어 최종 릴리스 후보 기록에서 고정한다 |
| `php .tools/bin/release-package-dry-run.php` | 통과 | 직접 제작 zip 후보 파일 목록에서 필수 파일 포함과 금지 경로 제외 확인. `.env.local`/`.env.production`/runtime 샘플 제외 정책도 확인한다. 2026-06-12 재검증 기준 후보 파일 수는 `1490` |
| `php .tools/bin/release-package-dry-run.php --manifest` | 통과 | 후보 파일 수와 `manifest-sha256` 출력 형식 확인. `check-release-package-policy.php`가 `--list`와 파일 집합을 대조하고 manifest body hash를 재계산한다. 현재 기록 파일도 후보에 포함되므로 실제 hash 값은 최종 릴리스 기록에서 고정 |
| `git check-ignore` | 통과 | `.env`, `.env.*`, config 임시/백업 파일, `storage/` 런타임 파일이 ignore 기준에 걸리는지 확인 |
| `php .tools/bin/ops-status.php` | 환경 미준비 | 미설치 환경이라 `saanraan is not installed.`와 exit 2 반환 |
| `php .tools/bin/check-operational-status.php` | 통과 | 운영 상태 신호, 허용 지연, 지연 초과 상태, CLI summary 출력, 관리자 화면/CLI 계약 점검. SQLite fixture로 `ok`, `warning`, `overdue`, `skipped`, `error` 판정, summary count, 안전하지 않은 table/age column 식별자, 세미콜론/SQL 주석/DDL·DML 키워드가 있는 `where` 조건 거부, notification delivery, community board copy, point expiration 일부 번들 신호의 실제 count/overdue 계산을 점검. 포인트 만료 수동 CLI 경로는 `--dry-run` 대상 규모 출력 marker와 만료 대상 지급분 하나만 `expire` 음수 거래로 차감하고 원 지급 row를 닫으며 재실행 중복 차감이 없음을 확인 |
| `php .tools/bin/check-module-status.php` | 통과 | 번들 모듈과 상태표 행 일치, 상태 값, 현재 증거, 1.0 전 보강 기준, 주요 고위험 모듈별 자동 증거 marker, 개선 기록의 기존 상태와 `module-status.md` 현재 상태 일치, `beta` 모듈별 구체 smoke/수동/reconciliation/브라우저/동시성 보강 대상 점검 |
| `php .tools/bin/check-verification-template.php` | 통과 | 릴리스 후보 필수 설치 DB 게이트와 미실행 판정 기준 점검 |
| `php .tools/bin/check-release-verification-records.php` | 통과 | 검증 기록 section, 최종 판정, 리스크 row, 릴리스 후보 필수 설치 DB 게이트 행별 결과가 모두 `통과`가 아닐 때 `통과` 판정 금지, 미해결 설치 DB 게이트의 환경/메모 누락 금지, 미실행 여부 플래그 일치, 개선 기록의 비릴리스 후보 조건부 판정 점검, 판정 규칙 self-test |
| `php .tools/bin/check-installed-gate-status.php` | 통과 | 설치 DB 게이트 상태표 도구의 출력 형식, `config-mode`/`config-owner-group` 출력, 필수 게이트 행, 포인트 만료 `--dry-run` read-only 게이트, 관리자 화면 게이트와 일반 smoke 계정의 missing/incomplete/configured 분기, `--run-readonly`, `--run-browser-qa`, `--run-auth-smoke`, `--run-quiz-smoke`, `--run-asset-smoke`, `--run-privacy-fixtures`, `--run-performance-fixtures`, 브라우저 QA 실패 상태 기록, 인증/퀴즈/자산 mutation smoke의 `SR_SMOKE_ALLOW_MUTATION=1` 차단/준비 분기, CKEditor upload/save browser smoke와 개인정보 export/cleanup smoke의 계정/mutation readiness 분기, 성능 수동 점검의 `SR_PERFORMANCE_REVIEW_READY=1` readiness 분기, 자산 smoke form path 사전조건, fixture 옵션 실행 시 개인정보/성능 게이트 `부분 확인` 출력, 성능 fixture별 exit summary, 문서 marker 확인 |
| `php .tools/bin/check-positioning.php` | 통과 | README, 특장점 문서, 포지셔닝 기준, 리스크 레지스터, 릴리스 검증 템플릿의 사용 판단 기준 marker와 대체 CMS/완성된 플랫폼/실시간 보장 같은 과장 문구 금지 기준 확인 |
| `php .tools/bin/check-doc-links.php` | 통과 | 문서 링크와 `.tools/bin/*.php` 명령 참조 존재 여부 점검 통과 |
| `php .tools/bin/check-tool-gate-coverage.php` | 통과 | 새 `check-*.php` 검증 도구가 `check.php` 통합 게이트에 연결됐는지 확인. deep QA 등 standalone 예외만 허용 |
| `php .tools/bin/check-rich-text-sanitizer-policy.php` | 통과 | Purifier 설정 경계, cache 경로, allowlist 정책 marker 점검 |
| `php .tools/bin/smoke-asset-idempotency-http.php` | 안전 거부 확인 | `SR_SMOKE_ALLOW_MUTATION=1` 없이 실행하면 usage와 함께 exit 2. 설치 DB 더미 유료 대상이 없어 병렬 mutation은 미실행 |

결과 로그 요약:

```text
git diff --check
=> exit 0

php .tools/bin/check.php
=> exit 0

php .tools/bin/release-preflight.php
=> purifier-available: yes
=> purifier-version: 4.19.0
=> purifier-autoload-path: modules/htmlpurifier/vendor/autoload.php
=> purifier-cache-dir: storage/cache/htmlpurifier
=> purifier-cache-writable: yes
=> release-package-files: 1490
=> release-package-manifest-sha256: <기록 파일 편집으로 변동, 릴리스 후보 기록에서 고정>

php -r "define('SR_ROOT', getcwd()); require 'core/helpers/output.php'; echo sr_rich_text_purifier_available() ? 'purifier=yes' : 'purifier=no';"
=> purifier=yes

php .tools/bin/reconcile-assets.php
=> saanraan is not installed.
=> exit 2

php .tools/bin/ops-status.php
=> saanraan is not installed.
=> exit 2

php .tools/bin/release-installed-gate-status.php
=> release-installed-gate-status-version: 1
=> installed-lock: present
=> config-readable: no
=> config-mode: 0600
=> config-owner-group: www-data:www-data
=> sr-is-installed: no
=> unresolved-gates: 13

php .tools/bin/release-installed-gate-status.php --markdown-table
=> 현재 CLI 사용자가 `config/config.php`를 읽지 못해 read-only 설치 DB 게이트 4개가 `환경 미준비`, base URL/계정/더미 데이터가 필요한 9개 게이트가 `미실행`인 Markdown 표를 출력
=> unresolved-gates: 13과 같은 13개 행을 아래 설치 DB 게이트 표에 전사

SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8082 php .tools/bin/release-installed-gate-status.php --run-browser-qa
=> browser-qa-base-url: http://127.0.0.1:8082
=> run-browser-qa: yes
=> gate	CKEditor asset/fallback browser smoke	result=통과
=> unresolved-gates: 12
```

## 릴리스 후보 필수 설치 DB 게이트

이번 기록은 릴리스 후보 판정이 아니므로 필수 설치 DB 게이트를 통과로 계산하지 않는다. `php .tools/bin/release-installed-gate-status.php`가 현재 환경에서 `installed.lock`은 있으나 `config/config.php`가 현재 CLI 사용자에게 읽히지 않아 `sr-is-installed: no`라고 보고했다. `php .tools/bin/release-installed-gate-status.php --markdown-table` 출력은 같은 13개 미해결 행을 표 형태로 전사할 수 있음을 확인했다. 설치 DB, 인증 계정, 더미 데이터, 브라우저 확인이 필요한 항목은 아래처럼 미실행 또는 환경 미준비로 남긴다.

| 게이트 | 결과 | 환경 | 메모 |
| --- | --- | --- | --- |
| 새 설치 또는 업데이트 적용 | 미실행 | 미설치 로컬 환경 | 설치 wizard 화면은 HTTP smoke install-mode로만 확인 |
| `php .tools/bin/reconcile-assets.php` | 환경 미준비 | 미설치 로컬 환경 | `saanraan is not installed.`와 exit 2 반환 |
| `php .tools/bin/ops-status.php` | 환경 미준비 | 미설치 로컬 환경 | `saanraan is not installed.`와 exit 2 반환 |
| `php .tools/bin/expire-points.php --dry-run` | 환경 미준비 | 미설치 로컬 환경 | `saanraan is not installed.`와 exit 2 반환 |
| /admin/assets/reconciliation | 미실행 | 설치 DB + 관리자 계정 없음 | HTTP smoke에서 진입점 200만 확인 |
| /admin/operations | 미실행 | 설치 DB + 관리자 계정 없음 | HTTP smoke에서 진입점 200만 확인 |
| 인증 smoke | 안전 거부 확인 | 설치 DB + 테스트 계정 없음 | `smoke-community-auth.php`는 `SR_SMOKE_ALLOW_MUTATION=1` 없이 실행하면 exit 2. 실제 커뮤니티 데이터 생성은 미실행 |
| 퀴즈 E2E smoke | 안전 거부 확인 | 설치 DB + 관리자 계정 없음 | `SR_SMOKE_ALLOW_MUTATION=1` 없이 실행하면 exit 2. 실제 퀴즈 생성/응시는 미실행 |
| 자산/쿠폰/유료 접근권 mutation smoke | 미실행 | 설치 DB + 더미 데이터 없음 | SQLite fixture만 확인 |
| 개인정보 export/cleanup smoke | 미실행 | 설치 DB + 더미 계정 없음 | SQLite fixture와 계약 매트릭스만 확인 |
| CKEditor asset/fallback browser smoke | 통과 | 설치 DB 없는 로컬 브라우저 환경 | 2026-06-12 재검증에서 `SR_BROWSER_QA_BASE_URL=http://127.0.0.1:8081 npm --prefix .tools/browser-qa run test:ckeditor`로 4 tests passed. self-hosted asset 로딩, `body_format=html` marker, textarea fallback, upload adapter request contract 확인 |
| CKEditor upload/save browser smoke | 미실행 | 브라우저 + 설치 DB 없음 | 업로드 adapter, 저장 HTML sanitizer, 권한별 본문 이미지 접근은 설치 DB 필요 |
| 성능 수동 점검 | 미실행 | 데이터가 있는 설치 DB 없음 | 정적 marker와 pagination fixture만 확인 |

## HTTP Smoke

서버 실행 명령:

```sh
php -S 127.0.0.1:<port> -t .tools/public .tools/bin/dev-router.php
```

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php` | 통과 | 미설치 install-mode 기준. 동적 진입점, CKEditor self-hosted asset, 보호 경로 403 확인 |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> SR_SMOKE_EXPECT_COMMUNITY=1 php .tools/bin/smoke-http.php` | 미실행 | 커뮤니티 설치 DB 없음 |
| `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> SR_SMOKE_MEMBER_ONLY=1 php .tools/bin/smoke-http.php` | 미실행 | 회원 전용 설치 DB 없음 |

결과 로그 요약:

```text
[ok] home or install entry GET 200
[ok] login route GET 200
[ok] password reset route GET 200
[ok] public UI kit route GET 200
[ok] admin/community/content/public static/protected path smoke checks
[ok] admin asset exchange logs entry GET 200
[ok] admin asset exchange correction action guard POST 400
[ok] CKEditor self-hosted asset GET 200
saanraan HTTP smoke checks completed.
```

## 인증 Smoke

운영 DB에서는 실행하지 않는다. 이번 기록은 미설치 로컬 환경이므로 인증 smoke를 실행하지 않았다.

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `php .tools/bin/smoke-community-auth.php` | 안전 거부 확인 | `SR_SMOKE_ALLOW_MUTATION=1` 없이 실행하면 exit 2. 설치 DB와 테스트 계정이 없어 실제 커뮤니티 데이터 생성은 미실행 |
| `php .tools/bin/smoke-quiz-e2e.php` | 안전 거부 확인 | `SR_SMOKE_ALLOW_MUTATION=1` 없이 실행하면 exit 2. 설치 DB와 더미 데이터가 없어 실제 퀴즈 생성/응시는 미실행 |
| 자산/쿠폰/유료 접근권 mutation smoke | 미실행 | 설치 DB와 더미 데이터 필요 |

## 브라우저/수동 점검

| 영역 | 결과 | 확인 내용 |
| --- | --- | --- |
| 설치/업데이트 | 미실행 | 설치 wizard 화면은 HTTP smoke install-mode로만 확인 |
| 관리자 권한/CSRF | 미실행 | 인증 세션 없음 |
| 콘텐츠/커뮤니티 | 미실행 | 설치 DB 없음 |
| 자산/쿠폰/유료 접근권 | 미실행 | 설치 DB와 더미 데이터 없음 |
| CKEditor/HTML sanitizer | 부분 확인 | 서버 sanitizer fixture, 브라우저 asset 로딩/fallback smoke, upload adapter request contract smoke 통과. 실제 서버 업로드 action과 저장 HTML smoke는 설치 DB 필요 |
| 개인정보 export/cleanup | 부분 확인 | 계약 매트릭스 정적 점검 통과, 설치 DB 기반 사본/탈퇴 smoke는 미실행 |
| queue/cron/배치 작업 | 환경 미준비 | `ops-status.php`가 미설치 환경에서 exit 2 반환 |

## 실패와 제한

| 항목 | 분류 | 판정 | 후속 |
| --- | --- | --- | --- |
| 자산 reconciliation 실제 대조 | 환경 미준비 | 미설치 환경에서 실행 불가 | 설치 로컬 DB 또는 staging에서 `php .tools/bin/reconcile-assets.php` 실행 기록 추가 |
| 관리자 자산 정합성 화면 | 환경 미준비 | 미설치 환경에서 실제 결과 확인 불가 | 설치 로컬 DB 또는 staging에서 `/admin/assets/reconciliation` 화면 확인 |
| 관리자 운영 상태 화면 | 환경 미준비 | 미설치 환경에서 실제 결과 확인 불가 | 설치 로컬 DB 또는 staging에서 `/admin/operations` 화면 확인 |
| 동시 중복 POST fixture | 부분 확인 | `check-asset-idempotency.php`가 동일 dedupe claim의 중복 흡수, pending cleanup 재시도, rollback 후 재claim, commit 후 duplicate 흡수를 SQLite fixture로 확인. 파일 DB의 두 PDO 연결 fixture로 커밋된 pending/completed claim의 cross-connection 중복 흡수도 확인. `pcntl`이 있는 CLI 환경에서는 병렬 프로세스 fixture로 같은 `dedupe_key`의 성공 insert가 정확히 1개만 남는지 확인. 실제 병렬 HTTP는 설치 DB 필요 | 같은 확인 token으로 동시 제출해 중복 원장 거래가 없는지 설치 DB에서 확인 |
| 설치 DB 병렬 HTTP 하니스 | 부분 확인 | `smoke-asset-idempotency-http.php`는 `SR_SMOKE_ALLOW_MUTATION=1`, form/post 경로, 로그인 계정, 추가 POST payload, 선택적 dedupe table/key를 받아 같은 확인 token 병렬 POST와 dedupe row count를 기록하도록 추가. 기본 실행은 exit 2로 mutation을 거부함 | 로컬/staging 더미 유료 대상에서 실제 병렬 POST 실행 기록 추가 |
| queue/cron 운영 상태 실제 조회 | 환경 미준비 | 미설치 환경에서 실행 불가 | 설치 로컬 DB 또는 staging에서 `php .tools/bin/ops-status.php`와 `php .tools/bin/expire-points.php --dry-run` 실행 기록 추가 |
| 인증 smoke | 환경 미준비 | 실행 불가 | 테스트 관리자 계정과 더미 데이터가 있는 로컬/staging DB에서 실행 |
| CKEditor 브라우저 점검 | 부분 확인 | `ckeditor-browser-smoke.spec.js`가 self-hosted CKEditor JS/CSS와 산란 loader를 실제 브라우저에서 로드하고, 초기화 성공 시 `body_format=html` hidden input 생성, 번들 로딩 실패 시 `sr-ckeditor-unavailable` fallback과 textarea 유지 확인. 같은 spec이 mock upload endpoint로 upload adapter의 image field, `csrf_token`, `upload_token`, 커뮤니티 개인정보 동의 field multipart 전송과 서버 성공/오류 JSON 처리를 확인. 실제 서버 업로드 action, 저장 HTML, 권한별 본문 이미지 접근은 설치 DB 필요 | 설치 DB에서 서버 업로드 action, 저장 HTML, 권한별 본문 이미지 접근 smoke 확인 |
| HTML Purifier vendoring | 기존 보완 항목 | `modules/htmlpurifier/vendor/` 포함 방향으로 전환되고 패키지 dry-run, `check-htmlpurifier-runtime.php`, `release-preflight.php`에서 vendor/license/version, Purifier 로드 상태, cache 경로, 후보 파일 수, manifest hash 형식을 확인함 | 릴리스 빌드 절차에서 실제 zip checksum과 Purifier 로드 상태 확인 |
| 실제 성능/실행 계획 | 미실행 | 설치 DB와 데이터 규모가 없어 정적 marker만 확인 | 느린 관리자 목록, sitemap, 개인정보 export를 설치 DB에서 수동 점검 |
| 릴리스 후보 필수 설치 DB 게이트 | 기존 보완 항목 | 이번 기록은 릴리스 후보 판정이 아니라 개선 묶음 점검이다 | 릴리스 후보 기록에서는 `release-verification-template.md`의 필수 설치 DB 게이트를 실행하거나 미실행 시 `조건부 통과`/`판정 보류`로 낮춘다 |

## 리스크별 릴리스 판정 연결

이번 기록은 1.0 릴리스 후보 판정이 아니라 개선 보강 묶음의 검증 기록이다. 따라서 설치 DB, 인증 계정, 브라우저, 더미 데이터가 필요한 항목은 `통과`가 아니라 `조건부` 또는 `보류`로 남긴다.

| 리스크 | 연결된 검증 증거 | 이번 판정 | 후속 |
| --- | --- | --- | --- |
| R-01 자산/쿠폰/유료 접근권 | `check-asset-reconciliation.php`, `check-asset-idempotency.php`, `check-asset-deadlock-retry.php`, `check-paid-download-delivery.php`, `check-asset-exchange-logs.php`, `check-asset-exchange-runtime.php`, `check-asset-settlement-contract.php`, `check-coupon-redemption-runtime.php`, `check-community-attachment-runtime.php`, `check-quiz-reward-runtime.php`, `check-survey-reward-runtime.php`, `check-member-assets-transaction-contract.php` 통과. reconciliation fixture는 잔액 행 누락, 합계 불일치, 마지막 `balance_after` 불일치, 거래 없는 비영점 잔액, 거래별 `balance_after` 연쇄 불일치를 각각 검증하고, 연쇄 오류의 첫 transaction id와 기대/실제 값을 확인한다. idempotency fixture는 최초 claim, 중복 claim 무시, completed sticky, 실패 cleanup 후 재시도, rollback 후 재claim, commit 후 duplicate 흡수와 파일 DB 두 연결의 cross-connection 중복 흡수, `pcntl` 환경의 병렬 프로세스 동일 dedupe claim 단일 insert를 검증한다. `smoke-asset-idempotency-http.php`는 설치 DB에서 같은 확인 token 병렬 POST와 dedupe row count를 기록하는 하니스로 추가됐고, 기본 실행은 mutation 안전장치로 exit 2를 반환한다. paid download fixture는 준비 실패 시 차감 없음, 차감 실패 시 전달 없음, 성공 시 준비-차감-전달 순서를 검증한다. asset exchange fixture는 수수료가 있는 완료 환전 묶음 정정의 원장 반전, 정정 로그, 중복 정정 차단과 관리자 로그 정정 action marker, 실제 reward/deposit 자산 계약 기반 성공 실행의 출금/입금/수수료 원장 연결, 입금 원장 저장 실패 시 출금 원장/로그 rollback을 검증한다. settlement fixture는 0원 처리, 정확 충당, 1단위 미만 ceil overpay 금지, 통화 fail-closed를 검증한다. coupon runtime fixture는 사용 dedupe, max uses 상태 전이, 환불 dedupe key 격리, issue 재활성화, content/community 접근권 회수, 콘텐츠 유료 열람/다운로드에서 쿠폰 우선 적용 시 자산 거래와 자산 접근 로그 미생성, 쿠폰 기반 유료 다운로드 접근권 회수, 접근권 insert 실패 시 쿠폰 사용 row와 `used_count` rollback을 검증한다. community attachment fixture는 첨부 다운로드 자산 로그 placeholder dedupe, 0원 완료 로그 settlement metadata, 첨부 다운로드 접근권 중복 방지와 anonymized entitlement 무시를 검증한다. quiz/survey reward fixture는 보상 grant dedupe, 중복 지급 방지, duplicate 판정, 실패 grant 재시도와 실패 상태 정리를 검증하고, quiz fixture는 grant 기준 원장 거래 lookup, 보상 자산 회수 가능액 계산, 회수 실행까지 검증한다. `reconcile-assets.php`는 미설치 환경에서 exit 2 | 조건부 | 설치 DB에서 reconciliation, mutation smoke, `smoke-asset-idempotency-http.php` 병렬 HTTP 동시성 fixture, 관리자 정정/복구 확인 |
| R-02 HTML sanitizer/CKEditor | `check-rich-text-sanitizer.php`, `check-rich-text-sanitizer-policy.php`, `check-htmlpurifier-runtime.php`, `check-htmlpurifier-vendor-integrity.php`, `check-ckeditor-assets.php`, `check-browser-qa.php`, `check-installed-gate-status.php` 통과. `sr_rich_text_purifier_available()`이 `purifier=yes` 반환하고, Purifier 직접 fixture가 위험 태그/속성 제거, 외부 링크 `nofollow`, 상대 링크 보존, 임베드 marker data 속성 보존을 확인한다. HTML Purifier runtime fixture는 번들 autoload, version, cache 경로, 설정 allowlist를 확인하고 vendor integrity fixture는 Composer 메타데이터/source reference/라이선스 drift를 확인한다. fallback sanitizer fixture도 XSS payload, namespace/URL 우회 payload, `href` 없는 링크 제거, `sr_body_text_html()` html/plain 렌더링, 상대 URL, 임베드 marker, CKEditor 정상 HTML 정화를 확인한다. content/community/popup/notification rich text 저장/출력 marker도 함께 확인한다. CKEditor self-hosted asset, 라이선스, fallback marker, Node가 있는 환경의 `node --check`, 콘텐츠/커뮤니티/팝업레이어 업로드 action 보안 계약 marker, 팝업레이어 만료 임시 본문 이미지 cleanup fixture, HTTP smoke asset 경로를 확인한다. `ckeditor-browser-smoke.spec.js`는 실제 Chromium에서 self-hosted CKEditor JS/CSS와 산란 loader 로딩, 초기화 성공 시 `body_format=html`, 번들 로딩 실패 시 textarea fallback, mock upload endpoint 기반 upload adapter multipart field와 성공/오류 JSON 처리를 확인한다. 상태표 체커는 설치 DB 저장/업로드 데이터를 만들 수 있는 CKEditor upload/save browser smoke가 관리자 계정과 `SR_SMOKE_ALLOW_MUTATION=1`이 있을 때만 수동 확인 대상으로 올라가는지 확인한다 | 조건부 | 설치 DB에서 CKEditor 서버 업로드 action, 저장 HTML sanitizer, 권한별 본문 이미지 접근 smoke 확인 |
| R-03 공유호스팅 queue/cron/배치 | `check-operational-status.php`, `check-community-board-copy-job-lock.php`, `check-notification-runtime.php` 통과. 운영 상태 fixture가 빈 queue, 허용 지연 이내 pending, 허용 지연 초과 pending, 즉시 실패, 비활성 모듈, 안전하지 않은 식별자, 세미콜론/SQL 주석/DDL·DML 키워드가 있는 `where` 조건 거부, 누락된 선택 테이블 판정, CLI summary 출력 marker를 확인한다. 번들 신호 fixture는 notification delivery queued/failed, community board copy active/failed, point expiration due가 실제 count/overdue로 계산되는지 확인한다. 포인트 만료 fixture는 `.tools/bin/expire-points.php`의 `--dry-run` preview marker와 `sr_point_expire_due_transactions()` 경로에서 만료 차감 원장, 원 지급 row 종료, 재실행 중복 차감 방지를 확인한다. 게시판 복사 job lock fixture는 stale/empty/completed token 거부와 stage/map 처리 경로 token 전달 marker를 확인한다. 알림 runtime fixture는 이벤트 템플릿 기반 계정 알림 생성, email delivery queue 생성, 계정별/전체 알림 읽음 처리, 읽음 redirect token no-truncation action marker, 실패 delivery 재큐/취소 action marker를 확인한다. `ops-status.php`는 미설치 환경에서 exit 2 | 조건부 | 설치 DB에서 `ops-status.php`, `expire-points.php --dry-run`, `/admin/operations`, 지연/실패 row 확인 |
| R-04 개인정보 export/cleanup 계약 | `check-privacy-contract-matrix.php`, `check-privacy-export-runtime.php`, `check-privacy-cleanup-runtime.php`, `check-installed-gate-status.php` 통과. 설치/update SQL에 계정 참조 컬럼이 있는 모듈은 `no_member_personal_data`로 남을 수 없고, `operational_retained` 모듈은 세부 보존 정책 목록에 포함되어야 한다. `export_retained` 모듈은 보존 사유, 고위험 필드/연결, 1.0 전 최소화 검토 항목을 문서화해야 한다. export/cleanup 계약 파일은 include 가능해야 하며 반환 형태와 callable signature가 계약에 맞아야 한다. export fixture는 quiz/survey/content/community의 상세 답변, snapshot 구조화, 접근권/자산/다운로드/작가/시리즈/커뮤니티 활동 데이터 포함과 다른 계정 row 제외를 확인한다. cleanup 계약은 array 반환 타입을 선언하고, quiz/survey/content/community fixture는 대상 계정 익명화와 다른 계정 row 보존, 결과 count 반환을 확인한다. content/community의 SQLite column discovery 경로도 matrix 체커가 고정한다. 상태표 체커는 cleanup이 데이터를 바꾸는 점을 반영해 설치 DB 개인정보 export/cleanup smoke가 더미 계정과 `SR_SMOKE_ALLOW_MUTATION=1`이 있을 때만 수동 확인 대상으로 올라가는지 확인한다. 운영 보존 사유와 접근 범위 문서화 | 조건부 | 설치 DB 더미 계정으로 export/cleanup smoke, 운영 보존 데이터 검토 |
| R-05 넓은 번들 모듈 표면 | `check-module-status.php`, `check-verification-template.php`, `check-risk-register.php` 통과. 모듈 상태표는 번들 모듈 행 일치, 상태 값, 현재 증거, 1.0 전 보강 기준, 자산/쿠폰/콘텐츠/커뮤니티/설문/CKEditor/개인정보 같은 주요 고위험 모듈별 자동 증거 marker, `beta` 모듈별 구체 smoke/수동/reconciliation/브라우저/동시성 보강 대상을 확인한다. 로고 매니저는 favicon head link runtime fixture, 아이콘 세트 variant 선택, disabled/기간 필터 fixture를 상태표 증거로 요구한다. 임베드 매니저는 refs sync runtime fixture와 private/broken 렌더링 fixture를 상태표 증거로 요구한다. 콘텐츠는 유료 다운로드 전달 순서, 파일/시리즈/임베드 삭제 정리 runtime fixture, 복사 runtime fixture를 상태표 증거로 요구한다. 퀴즈와 설문은 보상 지급 runtime fixture를 상태표 증거로 요구하고, 퀴즈는 보상 원장 lookup, 회수 가능액 계산, 회수 실행, 관리자 보상 회수 화면/POST 계약, 삭제/source snapshot 정리 runtime fixture를 요구하며, 설문은 응답 제출, 통계, CSV export runtime fixture도 요구한다. 배너/팝업레이어는 대상 참조 점검뿐 아니라 기간/대상 조건 렌더 runtime fixture를 상태표 증거로 요구하고, 팝업레이어는 CKEditor 임시 파일 cleanup fixture도 요구한다. 상태표는 asset_ledger, point, coupon, community, quiz, privacy, ckeditor의 1.0 전 보강 기준을 `release-installed-gate-status.php` 실행 경로와 연결한다 | 조건부 | `beta` 모듈별 smoke 기록과 등급 상향 근거 누적 |
| R-06 커스텀 요청/보안 contract | `check-security-baseline.php`, `check-runtime-helpers.php`, `check-request-contract-runtime.php`, `check-admin-action-security.php`, `check-admin-navigation-runtime.php`, `check-auth-runtime.php`, `check-member-auth-policy.php` 통과. 보안 baseline fixture가 HMAC app key 필수, 다운로드 token 목적/대상/만료 binding, rate limit count/window/HMAC subject 저장, 민감정보 문자열/metadata 마스킹, safe relative redirect, HTTP/public URL validator를 확인한다. member auth policy fixture는 GET/POST no-truncation helper의 길이 경계와 배열 입력 거부를 확인하고, 로그인 식별자, 이메일 검증 token, 비밀번호 재설정 token, 가입/재설정 비밀번호와 이메일이 truncating helper로 조회/검증되지 않도록 금지 marker를 확인한다. request contract runtime fixture는 정상/잘못된 CSRF, POST CSRF 호출 누락, 공개 GET, 관리자 auth/role guard 누락과 완료 경로, 공개 redirect 완료, 관리자 guard 누락 redirect/response 종료 차단을 subprocess로 확인한다. `check-admin-action-security.php`는 raw `exit`/`die`, 직접 `Location` header, 동적 헤더 이름을 만드는 `header($value)`, allowlist 밖 직접 `header()` 호출, 직접 JSON 헤더/`echo json_encode()` 사용, 안전하지 않은 action path 탐지 self-test와 실제 action guard scan을 함께 수행한다. `check-output-helpers.php`는 `sr_json_response()`의 content type, UTF-8 대체 인코딩, 추가 응답 헤더 allowlist, 제어문자 거부, 응답 종료 marker와 `sr_js_json_encode()`의 script-safe escaping, view/CKEditor config script 직접 `json_encode()` 금지 스캔을 확인한다. admin navigation fixture는 module admin-menu와 paths.php 매칭, unsafe/missing route 필터링, 제한 관리자 첫 허용 메뉴, owner/explicit permission 경계를 확인한다. HTTP smoke 보안 헤더 확인 | 조건부 | 인증/권한 smoke와 실제 배포 보안 헤더 확인 |
| R-07 외부 의존성/vendored asset | `check-dependency-policy.php`, `check-htmlpurifier-vendor-integrity.php`, `check-htmlpurifier-runtime.php`, `check-ckeditor-assets.php`, `check-release-package-policy.php`, `release-preflight.php`, `release-package-dry-run.php` 통과. `modules/htmlpurifier/DEPENDENCY.md`, vendor 포함 확인, Purifier 로드 상태, cache 경로, fallback sanitizer fixture, `VERSION`/Composer 메타데이터/source reference/라이선스 drift 확인, CKEditor self-hosted asset version/license/manifest 포함 확인, 후보 파일 수와 manifest hash 형식 확인, 후보 파일 전체의 루트 `vendor`/`dist`/`storage`, 비밀 파일, 백업/임시 파일, DB dump, SQLite/DB 파일, SSH key, package registry token 금지 패턴 스캔 확인 | 조건부 | 릴리스 zip에서 Purifier 로드 상태와 라이선스/버전 포함 확인, CKEditor asset 로드 상태와 라이선스/버전 포함 확인 |
| R-08 배포 보호 | `check-deployment-protection.php`, `check-deployment-config.php`, HTTP 보호 경로 smoke 통과. `.env`와 `.env.*` 변형 보호 기준을 Apache/nginx/smoke에 함께 고정 | 조건부 | 실제 Apache/nginx 또는 staging 배포에서 보호 규칙 확인 |
| R-09 문서/Wiki 지연 | `check-doc-links.php` 통과. README, 구현 스냅샷, 릴리스 절차 연결 확인 | 조건부 | 1.0 배포 정리에서 Wiki DB/관리자/요청 흐름 갱신 |
| R-10 국내 CMS 대비 신뢰 증거 | `positioning.md`, `module-status.md`, `release-verification-template.md`, `risk-register.md`, `check-positioning.php`, `check-release-verification-records.php`, `check-seo-runtime.php`, `check-site-menu-seed-order.php` 연결 확인. README/특장점/포지셔닝 문서가 “대체 CMS”가 아닌 사용 판단 기준을 유지하는지 확인하고, 검증 기록이 설치 DB 필수 게이트 미해결 상태에서 `통과`를 주장하지 못하도록 점검한다. SEO runtime fixture는 sitemap URL 정규화/중복 제거/priority clamp와 robots disallow/sitemap 출력을 확인한다. 사이트 메뉴 fixture는 seed order, 메뉴 렌더링, 현재 항목/상위 항목 표시, 3단계 제한, 외부 링크 보호, URL fail-closed를 확인한다 | 조건부 | 릴리스 후보별 검증 기록 누적, 사용 판단 기준 유지 |
| R-11 성능/캐시 기준 | `check-performance-baseline.php`, `check-performance-policy.php`, `check-admin-pagination-runtime.php`, `check-community-board-copy-limits.php`, `check-survey-export-runtime.php`, `check-installed-gate-status.php` 통과. 쿠폰 정의/지급/사용 내역은 total count 기반 관리자 pagination과 view page navigation을 사용한다. 동적 HTML `Cache-Control: no-store`와 HTTP smoke marker, 직접 `Cache-Control` 헤더 allowlist, 파일 기반 HTML/cache 쓰기 후보 스캔을 확인한다. 자산 원장, 쿠폰, 알림, 개인정보 요청, 콘텐츠/커뮤니티 활동 로그, board copy job, 환전 로그의 핵심 인덱스 marker를 설치 SQL 기준으로 확인한다. 설문 관리자 CSV export는 raw 5000행, analysis 20000행, codebook 10000행 상한과 감사 로그 metadata의 실제 limit 기록을 marker로 확인하고, runtime fixture로 필터/상한/escaping 동작을 확인한다. 페이지네이션 fixture는 비정상 page 입력, 마지막 페이지 clamp, offset, 배열 slice, 필터 유지 URL, summary/HTML disabled 상태를 확인한다. 게시판 전체 복사 fixture는 동기 상한, 배치 전환, hard block, 저장소 용량 경고를 확인한다. 상태표 체커는 대표 데이터가 있는 로컬/staging base URL과 `SR_PERFORMANCE_REVIEW_READY=1`이 있을 때만 성능 수동 점검을 수동 확인 대상으로 올리는지 확인한다 | 조건부 | 설치 DB에서 느린 화면 수동 점검과 실제 실행 계획 확인 |

## 모듈 상태 영향

이번 작업은 상태 등급 상향보다 증거 기준과 안전장치를 추가한 것이다. 설치 DB, 인증 smoke, 브라우저 수동 점검이 없으므로 `beta` 모듈을 `stable-candidate`로 올릴 근거는 아직 없다.

| 모듈 | 기존 상태 | 변경 후보 | 근거 |
| --- | --- | --- | --- |
| `ckeditor` | `beta` | `beta` 유지 | sanitizer fixture, 정책, 브라우저 asset/fallback, upload adapter request contract는 보강됐지만 설치 DB 서버 업로드 action과 저장 HTML smoke 미실행 |
| `logo_manager` | `beta` | `beta` 유지 | favicon head link runtime fixture와 아이콘 세트 선택은 보강됐지만 브라우저 head 출력 수동 smoke 미실행 |
| `embed_manager` | `beta` | `beta` 유지 | refs sync와 private/broken 렌더링 runtime fixture는 보강됐지만 CKEditor 삽입/refs 동기화 브라우저 smoke 미실행 |
| `content` | `beta` | `beta` 유지 | 유료 열람/다운로드 쿠폰 우선 적용, 파일/시리즈/임베드 삭제 정리, 복사 fixture는 보강됐지만 설치 DB mutation과 임베드 삽입 브라우저 smoke 미실행 |
| `community` | `beta` | `beta` 유지 | sanitizer와 유료 첨부 접근권 fixture는 보강됐지만 인증 smoke와 설치 DB mutation smoke 미실행 |
| `survey` | `beta` | `beta` 유지 | 응답 제출 fixture, CSV export fixture, 개인정보 fixture, 보상 지급 fixture, 통계 fixture는 보강됐지만 개인정보 설치 DB smoke와 브라우저 수동 smoke 미실행 |
| `notification` | `beta` | `beta` 유지 | 이벤트 템플릿과 delivery queue runtime fixture는 보강됐지만 설치 DB delivery 재시도 smoke 미실행 |

## 판정

최종 판정:

- 조건부 통과

릴리스 또는 배포 가능 여부:

- 현재 변경 묶음은 정적 점검과 미설치 HTTP smoke 기준으로 통과했다.
- 이 기록만으로 1.0 릴리스 후보 또는 운영 배포 가능 판정을 내리지는 않는다.

릴리스 후보 필수 설치 DB 게이트 미실행 여부:

- 있음

후속 작업:

- HTML Purifier와 CKEditor asset 포함 배포 빌드 절차에서 vendor/license/version 포함과 `release-preflight.php` 출력값을 확인한다.
- 설치 로컬 DB에서 `reconcile-assets.php`, `ops-status.php`, `expire-points.php --dry-run`, 인증 smoke를 실행한다.
- 설치 로컬 DB에서 `/admin/assets/reconciliation`과 `/admin/operations` 화면을 확인한다.
- 로컬/staging 더미 유료 대상에서 `smoke-asset-idempotency-http.php` 병렬 mutation smoke와 dedupe row count를 기록한다.
- 설치 DB에서 CKEditor 업로드 adapter, 저장 HTML sanitizer, 권한별 본문 이미지 접근 smoke를 날짜별 기록으로 남긴다.
- 설치 DB 대표 데이터로 느린 관리자 목록, sitemap, 개인정보 export의 실행 시간과 실행 계획/인덱스 상태를 기록한다.
