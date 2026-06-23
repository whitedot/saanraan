# 모듈 상태 등급

이 문서는 산란 번들 모듈의 현재 운영 신뢰 등급을 한곳에 모아 둔다. 등급의 의미와 필요한 증거는 [검증 상태와 증거 기준](verification-status.md)을 따른다.

상태 등급은 기능 구현 여부가 아니라 릴리스 판단을 위한 검증 증거 수준이다. `beta`는 미구현이라는 뜻이 아니라, 정적 점검 외에 HTTP smoke, 브라우저 수동 점검, reconciliation, payload fixture 같은 운영 증거가 더 필요하다는 뜻이다.

## 등급 기준

| 등급 | 사용 기준 |
| --- | --- |
| `stable-candidate` | 1.0 후보에 포함할 수 있는 기반 흐름이며, 정적 점검과 기본 smoke 기준이 문서화되어 있다. |
| `beta` | 기본 구현은 있으나 고위험 도메인, 브라우저 상호작용, 동시성, 복구 절차, 수동 smoke 기록이 더 필요하다. |
| `experimental` | 구조 검증 또는 탐구 목적이 강하며, 운영 기본값으로 권장하지 않는다. |
| `planned` | 계획 문서 단계이며 1.0 안정화 범위에 새 구현을 넣지 않는다. |

## 1.0 번들 모듈

| 분류 | 모듈 | 상태 | 현재 증거 | 1.0 전 보강 기준 |
| --- | --- | --- | --- | --- |
| 시스템 | `admin` | `stable-candidate` | `check-admin-action-security.php`, `check-admin-navigation-runtime.php`, `check-admin-form-validation.php`, 관리자 action 보안 정적 점검, 관리자 메뉴/route runtime fixture, 관리자 폼 opt-in validation marker 점검, HTTP smoke 기준 | 브라우저 수동 점검 기록 |
| 시스템 | `antispam` | `beta` | `check-antispam-runtime.php`, 강화 산술 challenge runtime fixture, 정답 HMAC 세션 저장과 재사용 방지 fixture, honeypot/min submit marker, provider action/hostname 검증 fixture, `provider_unavailable` 한정 fallback fixture, 활성 `antispam-providers.php` 계약 로딩 fixture, 회원가입과 비회원 커뮤니티 글/댓글 적용 marker, secret masking 점검 | 로컬/staging 설치 DB에서 회원가입, 비회원 게시글, 비회원 댓글 산술 challenge mutation smoke와 실제 provider staging key 또는 provider mock endpoint 기반 브라우저 수동 smoke |
| 시스템 | `asset_ledger` | `stable-candidate` | 숨김 기반 모듈 정책 문서, 자산 모듈 lifecycle 점검, `check-asset-reconciliation.php`와 read-only 관리자 reconciliation 화면 | `release-installed-gate-status.php --run-readonly` 설치 DB reconciliation 실행 기록과 함께 확인 |
| 회원 | `member` | `stable-candidate` | 인증 런타임 점검, 회원 auth policy 점검, 기본 HTTP smoke 기준 | 인증 smoke와 관리자 회원 화면 수동 기록 |
| 회원 | `member_oauth` | `beta` | `sr_member_oauth_accounts`/`sr_member_oauth_states` 스키마, state/nonce/PKCE hash 저장과 1회 사용 helper, provider 계약 정규화, provider 설정 저장/secret 마스킹, generic OAuth2/OIDC authorization/token/userinfo adapter, mock provider callback, 기존 계정 로그인, OAuth 신규 가입 completion 동의 gate, 계정 연결/해제 UI, 개인정보 export·cleanup 계약, `check-member-oauth-runtime.php` | mock provider 기반 설치 DB HTTP smoke, 외부 provider staging 계정 smoke |
| 회원 | `point` | `beta` | `check-asset-reconciliation.php`, `check-asset-settlement-contract.php`, reward abuse 기준, read-only reconciliation CLI, 포인트 만료 dry-run fixture | `release-installed-gate-status.php --run-readonly` 원장 reconciliation 실행 기록, 설치 DB 만료 dry-run, 환불/동시성 smoke |
| 회원 | `reward` | `beta` | `check-asset-reconciliation.php`, `check-asset-settlement-contract.php`, reward abuse 기준과 회수/출금 상한 runtime fixture, read-only reconciliation CLI | 원장 reconciliation 실행 기록, 출금/회수/동시성 smoke |
| 회원 | `deposit` | `beta` | `check-asset-reconciliation.php`, `check-asset-settlement-contract.php`, 환불 신청 상한 runtime fixture, read-only reconciliation CLI | 원장 reconciliation 실행 기록, 환불/출금/동시성 smoke |
| 회원 | `asset_exchange` | `beta` | `check-asset-exchange-logs.php`, `check-asset-exchange-runtime.php`, 환전 정책/로그 점검, 완료 묶음 정정 fixture, 실행 성공/rollback runtime fixture, 실패 로그 runtime fixture, 순환 가치 증가 방지 기준 | 동시 실행, 설치 DB 수수료/실패 복구 smoke |
| 회원 | `coupon` | `beta` | `check-coupon-admin-validation.php`, `check-coupon-redemption-runtime.php`, 유료 열람/다운로드 쿠폰 우선 적용 runtime fixture, 쿠폰 접근권 부분 실패 rollback fixture, 읽기 참조 계약 점검 | `release-installed-gate-status.php` 자산/쿠폰/유료 접근권 mutation smoke, 설치 DB 쿠폰 mutation smoke |
| 사이트 | `site_menu` | `stable-candidate` | `check-site-menu-seed-order.php`, seed order 점검, 메뉴 렌더 runtime fixture, 헤더 3단계 dropdown marker, 요청 단위 메뉴 tree cache fixture, URL 안전성 fixture | 신규 설치 seed HTTP smoke, 공개/콘텐츠/커뮤니티/퀴즈 헤더 dropdown 수동 smoke |
| 사이트 | `logo_manager` | `beta` | `check-logo-manager-favicon.php`, output helper 반복 렌더링 cache fixture, favicon head link runtime fixture, 아이콘 세트 variant 선택, disabled/기간 필터 fixture, 배포 보호 기준 | 브라우저 head 출력/아이콘 세트 수동 smoke |
| 사이트 | `banner` | `beta` | `check-popup-layer-targets.php`, popup/banner 대상 참조 점검, 기간/대상 조건 렌더 runtime fixture, 관리자 일괄 상태 smoke 기준 | 출력 슬롯과 외부 참조 차단 수동 smoke |
| 사이트 | `popup_layer` | `beta` | `check-popup-layer-targets.php`, `check-ckeditor-assets.php`, 대상 참조 점검, 기간/대상 조건 렌더 runtime fixture, CKEditor 업로드와 임시 파일 cleanup fixture | 출력 슬롯, 본문 sanitizer, 브라우저 수동 smoke |
| 사이트 | `seo` | `stable-candidate` | `check-seo-runtime.php`, output helper 점검, sitemap/robots runtime fixture, sitemap/robots HTTP smoke 기준 | SEO 설정 화면 수동 smoke |
| 서비스 | `content` | `beta` | `check-paid-download-delivery.php`, `check-content-file-cleanup-runtime.php`, `check-content-copy-runtime.php`, `check-asset-idempotency.php`, scheduled scope 점검, sanitizer fixture, 파일/시리즈/임베드 삭제 정리 runtime fixture, 복사 runtime fixture | 유료 열람/다운로드 설치 DB mutation, 임베드 삽입 브라우저 smoke |
| 서비스 | `community` | `beta` | `check-community-release.php`, `check-community-privacy-consent.php`, `check-community-board-settings.php`, `check-community-board-copy-job-lock.php`, `check-community-attachment-runtime.php`, `check-storage-helpers.php`, `check-asset-idempotency.php`, sanitizer fixture, 개인정보 동의 설정/상속/snapshot runtime fixture, 게시판 운영 설정 marker/본문 길이 helper fixture, 유료 첨부 접근권 runtime fixture, 공개 목록 썸네일 helper fixture | `release-installed-gate-status.php` 인증 smoke와 자산/쿠폰/유료 접근권 mutation smoke, 게시글/댓글/쪽지/신고/유료 첨부/개인정보 동의/게시판 운영 설정 설치 DB mutation smoke |
| 서비스 | `quiz` | `beta` | `check-quiz-consistency.php`, `check-quiz-reward-runtime.php`, `check-quiz-delete-runtime.php`, privacy runtime fixture, 보상 지급/원장 lookup/회수 가능액/회수 실행 runtime fixture, 관리자 보상 회수 화면/POST 계약, 삭제/source snapshot 정리 runtime fixture, E2E smoke 도구 | `release-installed-gate-status.php` 퀴즈 E2E smoke, 브라우저 수동 smoke |
| 서비스 | `survey` | `beta` | `check-survey-consistency.php`, `check-survey-response-runtime.php`, `check-survey-reward-runtime.php`, `check-survey-statistics-runtime.php`, `check-survey-export-runtime.php`, 응답 제출 runtime fixture, CSV export runtime fixture, privacy runtime fixture, 보상 지급 runtime fixture, 통계 runtime fixture | 개인정보 설치 DB smoke, 브라우저 수동 smoke |
| 서비스 | `embed_manager` | `beta` | `check-embed-manager-contracts.php`, URL cache sync runtime fixture, private/broken 렌더링 fixture, sanitizer marker fixture | CKEditor 삽입, URL cache 동기화, broken/private 렌더링 smoke |
| 서비스 | `reaction` | `beta` | 이슈 #326 정책 결정, 설치 SQL 스키마, 공개/관리자 UI, 리액션 정의/preset/원장 조회/사용 중지 key 정리 화면, 이모지/Material/이미지 아이콘 정의 runtime fixture, `reaction-targets.php` 단건·batch 계약, 단일 선택 write/throttle/작성자 본인 제한/알림 no-op/개인정보 export·cleanup runtime fixture, module lifecycle 정적 점검 | HTTP smoke, 이미지 아이콘 업로드 브라우저 수동 점검, write 경로 동시성 smoke, 개인정보 설치 DB smoke |
| 운영 | `notification` | `beta` | `check-mention-ux.php`, `check-notification-runtime.php`, 이벤트 템플릿 runtime fixture, delivery queue fixture, 상태 전이 fixture, claim/lock/backoff/dead-letter runner fixture, Slack/Discord/Telegram 운영 push provider fixture, 회원 Telegram push endpoint 암호화 저장/endpoint 참조 queue/발송 전 재검증/연결·해제 재인증/cleanup ciphertext 제거 fixture, `.tools/bin/run-notification-deliveries.php`, 관리자 수동 실행, 웹 종료 runner 설정, 운영 상태 기준 | 설치 DB 또는 staging에서 delivery queue, 수동/CLI runner, 실패 재시도와 dead-letter, 이벤트 템플릿 smoke, Slack/Discord/Telegram mock 또는 staging provider smoke, 회원 Telegram 연결/해제와 queued delivery 취소 smoke |
| 운영 | `policy_documents` | `beta` | 설치 SQL 스키마, 기본 문서 seed, published version 조회 helper, snapshot helper, 관리자 문서/버전 화면, 안내메일 job/delivery 개인정보 export·cleanup 계약, 회원가입/커뮤니티/OAuth completion 연계 marker, `check-privacy-contract-matrix.php`, `check-policy-documents-runtime.php`, `check-member-oauth-runtime.php` | 안내메일 batch 발송 설치 DB smoke, 설치 DB 관리자 화면과 public 동의 렌더 smoke |
| 운영 | `privacy` | `stable-candidate` | `check-privacy-contract-matrix.php`, `check-privacy-export-runtime.php`의 활동 데이터/보존형 export fixture, `check-privacy-cleanup-runtime.php`, `smoke-privacy-export-cleanup.php`, smoke 기준 | `release-installed-gate-status.php --run-privacy-smoke` 개인정보 export/cleanup 설치 DB smoke 기록, 운영 보존 데이터의 실제 마스킹/보존기간 수동 검토 |
| 플러그인 | `ckeditor` | `beta` | `check-rich-text-sanitizer.php`, `check-htmlpurifier-runtime.php`, `check-ckeditor-assets.php`, `check-browser-qa.php`, `ckeditor-browser-smoke.spec.js`, `smoke-ckeditor-upload-save.php`, HTML Purifier 배치/캐시 경로, rich text sanitizer fixture, 브라우저 asset 로딩/fallback smoke, upload adapter request contract smoke, 공개/draft 본문 이미지 접근 smoke 하니스 | `release-installed-gate-status.php --run-ckeditor-upload-save-smoke` CKEditor upload/save browser smoke 실행 기록, 유료 본문 이미지 접근 smoke |
| 플러그인 | `antispam_captcha_providers` | `beta` | `check-antispam-runtime.php`, `antispam-providers.php` 계약 반환 fixture, Turnstile/hCaptcha/reCAPTCHA provider mock fixture, widget class/endpoint/script 설정, provider action/hostname 검증 토글 점검 | 실제 provider staging key 또는 provider mock endpoint 기반 브라우저 수동 smoke와 timeout/fallback 정책 smoke |
| 플러그인 | `member_oauth_providers` | `beta` | `check-member-oauth-runtime.php`, `oauth-providers.php` 계약 반환 fixture, Google/Kakao/Naver/GitHub/Apple ID authorization/token/userinfo/id_token endpoint와 중첩 claim path fixture | 실제 provider staging 계정 기반 로그인/가입/연결 브라우저 smoke |

## 1.0 제외 계획

| 계획 | 상태 | 기준 문서 |
| --- | --- | --- |
| 본인확인 플러그인 | `planned` | [본인확인 플러그인 계획](plans/identity-verification-plugin-plan.md) |
| 회원 마이그레이션 | `planned` | [회원 마이그레이션 계획](plans/member-migration-plan.md) |
| 결제 플러그인 | `planned` | [결제 플러그인 계획](plans/payment-plugin-plan.md) |

## 갱신 기준

- 새 모듈을 번들에 추가하면 이 문서에 상태 등급과 보강 기준을 추가한다.
- 각 번들 모듈 행의 `현재 증거`와 `1.0 전 보강 기준`은 비어 있으면 안 된다. `beta` 행은 구체적인 smoke, 수동 점검, reconciliation, 브라우저/업로드 점검, 동시성/복구 확인처럼 1.0 전에 줄일 증거 공백을 명시한다.
- `beta`를 `stable-candidate`로 올릴 때는 날짜별 검증 기록 또는 릴리스 후보 기록을 근거로 남긴다.
- 고위험 영역의 미실행 smoke는 실패가 아니라 미실행 사유와 후속 보완 항목으로 기록한다.
- 등급 문서의 모듈 목록은 `.tools/bin/check-module-status.php`와 `php .tools/bin/check.php`가 누락을 확인한다.
