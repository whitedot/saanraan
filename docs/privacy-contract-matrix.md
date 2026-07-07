# 개인정보 계약 매트릭스

이 문서는 번들 모듈이 회원 귀속 데이터에 대해 어떤 개인정보 계약을 가져야 하는지 고정한다. 목적은 모든 모듈에 무조건 `privacy-cleanup.php`를 붙이는 것이 아니라, 사본 제공 대상, 탈퇴/익명화 정리 대상, 운영 증빙 보관 대상을 분리해 누락을 보이게 만드는 것이다.

`account_id`, `author_account_id`, `created_by_account_id`, `processed_by_account_id`, `handled_by_account_id`처럼 회원 계정과 연결되는 컬럼이나 `email`, `recipient`, `phone`, `birth_date`, `ip_hash`, `user_agent_hash`, `provider_subject_hash`, 동의/답변/metadata snapshot처럼 개인 식별 가능성이 있는 컬럼을 설치 SQL 또는 update SQL 기준으로 추가하면 이 문서를 먼저 갱신하고 `.tools/bin/check-privacy-contract-matrix.php`를 통과시켜야 한다.

## 상태 값

| 상태 | 의미 |
| --- | --- |
| `export_cleanup` | 회원 사본 제공과 탈퇴/익명화 cleanup 계약이 모두 필요하다. |
| `export_retained` | 회원 사본 제공은 필요하지만 원장, 권리, 신청, 알림처럼 운영 증빙으로 보관한다. 탈퇴 처리는 별도 자산/보존 계약 또는 계정 상태 익명화로 처리한다. |
| `export_owner` | 회원 계정의 원천 소유자다. 사본 제공을 제공하고 다른 모듈 cleanup 계약을 실행한다. |
| `coordinator_direct` | 개인정보 처리 조정자다. 자기 계약 파일이 아니라 조정 함수에서 직접 포함한다. |
| `operational_retained` | 관리자 설정, 감사성 작성자, 참조 작성자처럼 운영 메타데이터로 보관한다. 1.0 전 보존 정책 검토 대상이다. |
| `no_member_personal_data` | 현재 설치 SQL 또는 update SQL 기준 회원 귀속 개인정보를 저장하지 않는다. |

## 번들 모듈 기준

| 모듈 | 상태 | `privacy-export.php` | `privacy-cleanup.php` | 근거 |
| --- | --- | --- | --- | --- |
| `admin` | `operational_retained` | 없음 | 없음 | 관리자 권한과 감사성 운영 데이터는 회원 계정과 연결될 수 있으나, 현재는 관리자 운영 보존 데이터로 분류한다. 감사 로그는 본인 사본 제공 기본 범위에서 제외하고 보존/조사 권한과 redaction 기준으로 관리한다. |
| `antispam` | `no_member_personal_data` | 없음 | 없음 | 자동등록방지 설정, 산술 challenge 세션, 외부 provider 검증 요청만 다루며 현재 설치 SQL 또는 update SQL 기준 회원 귀속 개인정보를 저장하지 않는다. |
| `antispam_captcha_providers` | `no_member_personal_data` | 없음 | 없음 | Turnstile, hCaptcha, reCAPTCHA provider 계약만 제공하며 설정 저장과 검증 정책은 `antispam` 모듈이 소유한다. |
| `asset_exchange` | `export_retained` | 제공 | 없음 | 회원별 환전 이력과 실행자 메타데이터는 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `asset_ledger` | `export_cleanup` | 제공 | 제공 | 공통 원장 점검 helper와 보상 회수 실패 큐를 제공한다. `sr_asset_recovery_failures`는 실패한 회수 작업의 account/actor 연결과 금액을 저장하며, 사본 제공과 탈퇴/익명화 cleanup 계약은 `asset_ledger`가 소유한다. |
| `banner` | `no_member_personal_data` | 없음 | 없음 | 배너 설정은 회원 귀속 데이터가 아니다. 클릭 dedupe hash는 account/session/IP/UA에서 파생될 수 있는 가명성 운영 데이터로 분리해 보관일 정책에 따른다. |
| `ckeditor` | `no_member_personal_data` | 없음 | 없음 | 에디터 asset과 설정만 제공하며 본문 데이터는 화면 소유 모듈이 저장한다. |
| `community` | `export_cleanup` | 제공 | 제공 | 게시글, 댓글, post draft, 스크랩, 신고, 신고 자동 임시 조치 이력, 계정 guard 이력/현재 상태, 동의 증적, 접근권, 보상 로그 등 회원 활동 데이터와 IP/UA hash를 가진다. 보상 미회수 운영 기준은 `asset_ledger` 공통 큐가 소유하며, community export/cleanup은 호환 기간의 legacy `sr_community_asset_recovery_failures` row만 정리한다. |
| `content` | `export_cleanup` | 제공 | 제공 | 작성자, 신청자, 댓글, 유료 접근권, 다운로드 로그, 자산 처리 로그 등 회원 활동 데이터를 가진다. |
| `coupon` | `export_retained` | 제공 | 없음 | 회원 쿠폰 지급, 사용, 환불 기록은 권리성 자산과 운영 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `deposit` | `export_retained` | 제공 | 없음 | 예치금 잔액, 원장, 환불 신청 계좌 정보는 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `logo_manager` | `operational_retained` | 없음 | 없음 | 로고 운영 메타데이터의 작성자 계정 연결은 운영 보존 데이터로 분류한다. |
| `markdown_editor` | `no_member_personal_data` | 없음 | 없음 | Markdown parser/style profile과 declaration-only CSS 설정만 제공하며 본문 데이터는 화면 소유 모듈이 저장한다. |
| `message` | `export_cleanup` | 제공 | 제공 | 회원 간 쪽지 본문과 발신/수신 방향, 회원별 수신 설정을 소유한다. export에서는 상대 계정 ID를 노출하지 않고 `message_direction`과 masked counterparty role로 제공한다. cleanup은 회원별 수신 설정을 삭제하고 양쪽 사본이 모두 삭제된 자기 자신 대상 row만 물리 정리한다. |
| `member` | `export_owner` | 제공 | 소비 | 계정, 인증, 기본/추가 프로필, 동의, 그룹 멤버십, MFA factor/recovery code 저장소를 소유하고 탈퇴/익명화 시 회원 소유 MFA row를 직접 삭제한 뒤 설치 모듈의 cleanup 계약을 실행한다. 추가 프로필 값은 항목별 export 정책에 따라 사본 제공 범위를 정하고, MFA export는 factor 표시 메타데이터와 recovery code 상태별 개수만 제공한다. |
| `member_oauth` | `export_cleanup` | 제공 | 제공 | OAuth provider 연결 증적과 최소 profile snapshot을 회원 계정에 연결하며, provider subject 원문은 저장하지 않고 HMAC hash와 hash prefix 표시값만 둔다. 탈퇴/익명화 시 연결을 해제하고 snapshot을 제거한다. |
| `member_oauth_providers` | `no_member_personal_data` | 없음 | 없음 | Google, Kakao, Naver, GitHub, Apple ID OAuth provider 계약만 제공하며 설정 저장, state, 계정 연결, profile snapshot은 `member_oauth` 모듈이 소유한다. |
| `identity_verification` | `export_cleanup` | 제공 | 제공 | 외부 본인확인 attempt/result/link를 소유한다. state/nonce와 CI/DI/이름/휴대폰은 HMAC hash로 저장하고 provider 원문 응답, 주민등록번호, CI/DI 원문은 저장하지 않는다. 탈퇴/익명화 시 계정 연결을 revoke하고 attempt/result account 연결을 제거한다. |
| `identity_kcp` | `no_member_personal_data` | 없음 | 없음 | NHN KCP 휴대폰 본인확인 provider 계약만 제공하며 설정 저장, 시도, 결과, 계정 연결은 `identity_verification` 모듈이 소유한다. |
| `identity_inicis` | `no_member_personal_data` | 없음 | 없음 | KG이니시스 통합인증 본인확인 provider 계약만 제공하며 설정 저장, 시도, 결과, 계정 연결은 `identity_verification` 모듈이 소유한다. |
| `notification` | `export_retained` | 제공 | 제공 | 회원 알림과 읽음 상태, 대상 회원의 site/email delivery는 사본 제공 대상이다. 운영 알림과 발송 이력은 보존 정책으로 다루되, 회원 push endpoint secret은 탈퇴/익명화 cleanup에서 disabled tombstone으로 전환하고 ciphertext를 제거한다. |
| `payment_ledger` | `export_cleanup` | 제공 | 제공 | 결제 record는 account_id와 주문/콘텐츠/커뮤니티 같은 subject 참조, payable/settlement 금액, 결제 item snapshot을 저장한다. 회원 사본 제공 대상이며 탈퇴/익명화 시 record의 account 연결을 제거한다. |
| `point` | `export_retained` | 제공 | 없음 | 포인트 잔액, 원장, 만료 소비 매핑은 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `policy_documents` | `export_cleanup` | 제공 | 제공 | 약관/방침 변경 안내메일 delivery가 회원 계정과 연결되며, 탈퇴/익명화 시 계정 연결을 제거한다. |
| `popup_layer` | `no_member_personal_data` | 없음 | 없음 | 팝업 설정과 노출 정책은 현재 회원 귀속 데이터가 아니다. |
| `privacy` | `coordinator_direct` | 소비 | 없음 | 관리자 전용 개인정보 요청 대응 기록과 사본 제공을 보조한다. `sr_privacy_export_data()`가 대응 기록을 직접 포함하고 다른 모듈 export 계약을 수집한다. |
| `quiz` | `export_cleanup` | 제공 | 제공 | 응시, 답안 snapshot, 결과, 댓글, 보상 grant, IP/UA hash를 가진다. |
| `reaction` | `export_cleanup` | 제공 | 제공 | 계정별 target reaction 원장을 가지며, 탈퇴/익명화 시 해당 계정의 reaction record를 삭제한다. |
| `reward` | `export_cleanup` | 제공 | 제공 | 적립금 잔액과 원장, 출금 신청 금액/상태/처리자 증빙은 사본 제공과 보존 대상이다. 탈퇴/익명화 cleanup은 출금 신청의 은행명, 계좌번호, 예금주, 요청자/관리자 free-text note를 빈 값으로 정리한다. |
| `seo` | `no_member_personal_data` | 없음 | 없음 | SEO 설정과 sitemap 정책은 현재 회원 귀속 데이터가 아니다. |
| `site_menu` | `no_member_personal_data` | 없음 | 없음 | 메뉴 구조는 현재 회원 귀속 데이터가 아니다. |
| `survey` | `export_cleanup` | 제공 | 제공 | 설문 응답, 답변, 댓글, 보상 grant, IP/UA hash를 가진다. |

## 마일스톤 12 재기준화 기준

마일스톤 12의 기존 GDPR 후속 이슈(#151-#161)는 2026-06-02 생성 당시 번들 모듈 17개를 전제로 분리되었다. 현재 번들 모듈은 32개이며, 이후 추가된 `antispam`, `antispam_captcha_providers`, `asset_ledger`, `payment_ledger`, `member_oauth`, `member_oauth_providers`, `identity_verification`, `identity_kcp`, `identity_inicis`, `policy_documents`, `quiz`, `reaction`, `survey`, `markdown_editor`, `message`까지 포함해 개인정보 표면을 판단한다.

| 표면 | 현재 포함 모듈 | 주요 개인정보성 필드와 처리 표면 | 마일스톤 12 연결 |
| --- | --- | --- | --- |
| 쿠키와 브라우저 저장소 | `core`, `member`, `popup_layer`, `admin`, `antispam`, `antispam_captcha_providers`, `banner` | 세션 쿠키, 팝업 닫기 쿠키, 공개 화면 색상 모드 `localStorage`, 관리자 UI `localStorage`, CAPTCHA provider script와 remote IP 전달 옵션, 배너 클릭 dedupe hash | #151, #152, #160 |
| 계정 원천과 인증 | `member`, `member_oauth`, `member_oauth_providers`, `identity_verification`, `identity_kcp`, `identity_inicis`, `message` | 계정 식별자/email hash, 세션/token hash, MFA factor secret ciphertext/fingerprint/recovery code hash, OAuth provider subject hash, email snapshot, state/nonce/code verifier hash, 본인확인 CI/DI/이름/휴대폰 HMAC hash, 가입 동의 snapshot, 쪽지 수신 설정, OAuth/본인확인 provider 계약 | #158, #159, #160, #161 |
| 정책 문서와 동의 | `member`, `policy_documents`, `community`, `survey` | 회원 동의 증적, 정책 문서 버전 snapshot, 안내메일 delivery account 연결, 제출/응답 동의 snapshot | #151, #158, #160, #161 |
| 사용자 제출과 활동 | `community`, `content`, `quiz`, `survey`, `reaction` | 작성자/응답자/시도자 account 연결, 댓글/쪽지/스크랩/리액션, answer/metadata snapshot, IP/UA hash, 제3자 식별자 | #155, #158, #159, #161 |
| 금액성 원장과 권리 | `asset_ledger`, `payment_ledger`, `asset_exchange`, `point`, `reward`, `deposit`, `coupon`, `content`, `community`, `quiz`, `survey` | 잔액/거래/환불/출금/쿠폰/결제 기록/유료 접근권/보상 grant/보상 미회수 기록, account 연결과 created/processed/refunded actor | #156, #158, #160, #161 |
| 알림과 외부 발송 | `notification`, `policy_documents`, `member_oauth`, `message`, `community`, `content`, `quiz`, `survey`, `reaction` | site/email delivery recipient, push endpoint ciphertext와 masked recipient, 정책문서 안내메일, 쪽지 도착 알림, 멘션/댓글/리액션 알림 | #154, #158, #160, #161 |
| 운영 보존과 감사 | `admin`, `privacy`, `logo_manager`, `notification` | 관리자 권한, 감사 로그 actor/metadata, 개인정보 요청 requester/admin note/handler, 로고 변경 작성자, 운영 알림 처리자 | #153, #157, #158, #160 |

### 이슈별 추가 모듈 영향

| 이슈 | 현재 기준에서 반드시 포함할 추가 모듈/표면 | 선행 판단 |
| --- | --- | --- |
| #151 쿠키 동의 관리 | `core`, `antispam`, `antispam_captcha_providers`, `popup_layer`, `admin`, `banner`, `member`, `community`, `privacy` | 로그인 세션 쿠키와 CSRF/session state는 필수 보안 저장소로 두고, 공개 화면 색상 모드 `localStorage`는 사용자가 직접 고른 표시 설정 저장소로 안내한다. `privacy`의 공개 쿠키 설정 배너가 `sr_cookie_consent`로 기능성 저장소 항목별 허용 여부를 기록한다. 현재 기능성 항목은 `popup_dismissal`이며, 팝업 닫기 쿠키는 이 항목 동의가 있을 때만 저장한다. CAPTCHA provider script와 remote IP 전달은 보안 목적 외부 처리자 표면으로 기록하고, 마케팅/분석 script를 추가하려면 사전 동의 gate와 항목별 설정을 먼저 구현한다. |
| #152 배너 클릭 hash | `banner`, `member` | `click_key_hash`가 account, session hash, IP/UA hash에서 파생될 수 있으므로 가명성 dedupe 데이터로 분류한다. 기본 보관일은 180일이며 `/admin/retention`의 배너 클릭 hash 보관일로 조정하고, 배너별 총 클릭 수는 유지하되 오래된 dedupe hash는 정리한다. 배너 복사에서는 집계 클릭 수만 선택 복사하고 dedupe hash row는 복제하지 않는다. |
| #153 전역 감사 로그 | `admin`, `privacy`, `logo_manager`, 전체 관리자 action | 감사 로그는 본인 export 기본 범위에서 제외하고 운영 보존 데이터로 둔다. actor account, IP/UA, metadata는 저장 전 sanitize와 표시 전 redaction을 적용하며, `/admin/retention`의 관리자 작업 로그 보관일로 정리한다. |
| #154 알림 delivery recipient | `notification`, `policy_documents`, `member_oauth`, `message`, `reaction`, `community`, `content`, `quiz`, `survey` | site delivery와 대상 회원 email delivery는 export에 포함하고 다른 회원 email recipient는 제외한다. push endpoint delivery는 masked recipient로만 제공한다. 탈퇴/익명화 시 push endpoint ciphertext는 제거하고, 정책문서 안내메일 delivery는 account 연결을 제거한다. 일반 delivery retention은 알림 보관일을 따른다. |
| #155 쪽지 상대방 식별자 | `message`, `member` | 쪽지 export는 본문과 상태, 발신/수신 방향(`sent`/`received`)만 제공하고 raw `sender_account_id`/`recipient_account_id`는 제외한다. 상대방은 `masked_sender`/`masked_recipient` 역할값으로만 표시하며 runtime fixture가 이 계약을 검증한다. |
| #156 자산 로그 account_id 보존 | `asset_ledger`, `payment_ledger`, `asset_exchange`, `point`, `reward`, `deposit`, `coupon`, `content`, `community`, `quiz`, `survey` | 금액성 증빙 row는 모듈별 보존 계약에 따라 처리한다. 콘텐츠/커뮤니티 접근권과 다운로드 이력처럼 서비스 접근 상태를 나타내는 row는 연결 제거 대상이고, 자산 차감/지급/환불/정정 원장은 환불·정산·분쟁 대응을 위해 account id와 실행 snapshot을 보존한다. `payment_ledger`는 공통 결제 묶음의 사본 제공 대상이며 탈퇴/익명화 시 account 연결을 제거한다. |
| #157 관리자 메모 redaction | `privacy`, `admin` | privacy 요청의 `admin_note`는 입력 가이드로 제3자 개인정보, 주민등록번호, 원문 연락처, 비밀번호, token 저장을 금지하고 저장/export 단계에서 명백한 이메일·휴대폰·주민등록번호·secret류를 redaction한다. 감사 metadata는 `sr_audit_metadata_sanitize()`와 관리자 화면 표시 redaction을 따른다. |
| #158 권리 요청 전파 | `member`, `policy_documents`, `member_oauth`, `message`, `community`, `content`, `quiz`, `survey`, `reaction`, `notification`, 금액성 모듈 | 정정권, 처리 제한, 동의 철회가 쪽지 수신, 공개 노출, 알림, 리액션, 보상, 금액성 원장에 미치는 범위를 모듈별로 나눈다. |
| #159 특별범주/연령/고유식별자성 | `member`, `member_oauth`, `identity_verification`, `identity_kcp`, `identity_inicis`, `antispam_captcha_providers`, `quiz`, `survey` | 회원 선택 프로필의 생년월일과 성인여부는 연령성 개인정보로 export에 포함하고, 회원 추가 프로필 항목, 외부 provider profile, 연령/성인 인증, 설문/퀴즈 응답에서 민감정보가 생길 수 있는 경우 원문 저장 금지와 export 제외 기준을 둔다. |
| #160 ROPA 확장 | 전체 32개 번들 모듈 | processor/수탁사, 국외이전, 처리 위치, 처리 목적, 보존 기간 컬럼을 현재 모듈 표면에 맞춰 확장한다. |
| #161 conformance 자동화 | 전체 32개 번들 모듈 | 이 문서, 설치/update SQL, 계약 선언, export/cleanup/runtime fixture, smoke readiness를 한 경로에서 실패로 보고한다. |

## 보강 기준

- 새 번들 모듈을 추가하면 위 표에 먼저 상태를 추가한다.
- 게시글, 댓글, 응시, 응답처럼 사용자가 직접 제출하는 데이터의 작성자/접속/동의 보관 기준은 [이슈 #328 설계 기준](plans/issue-328-author-submission-data-design.md)의 매트릭스를 함께 갱신한다. 비회원 작성을 열거나 게시판별 추가 입력 항목을 저장하면 계정 연결 개인정보와 비회원 제출 증적의 export/cleanup 또는 보관 기간 cleanup 책임을 분리해 문서화한다.
- `export_cleanup` 모듈은 `module.php`의 `contracts.provides`에 `privacy-export.php`와 `privacy-cleanup.php`를 모두 선언하고 실제 파일을 둔다. `.tools/bin/check-privacy-contract-matrix.php`는 계약 파일을 include해 `privacy-export.php`가 배열 또는 `(PDO $pdo, int $accountId): array` callable을 반환하고, `privacy-cleanup.php`가 최소 `(PDO $pdo, int $accountId): array` callable을 반환하는지 확인한다.
- `.tools/bin/check-privacy-contract-matrix.php`는 `install.sql`뿐 아니라 `updates/*.sql`도 함께 훑어 `*_account_id`, `email`, `recipient`, `phone`, `birth_date`, `ip_hash`, `user_agent_hash`, `provider_subject_hash`, 동의/답변/metadata snapshot 계열 컬럼이나 참조가 생겼는데 매트릭스 상태가 `no_member_personal_data`로 남는 경우를 차단한다.
- `.tools/bin/check-privacy-export-runtime.php`는 SQLite fixture로 `quiz`, `survey`, `content`, `community` export 계약과 `asset_ledger`, `asset_exchange`, `coupon`, `deposit`, `notification`, `payment_ledger`, `point`, `reward` 보존형 export 계약을 실행한다. 퀴즈/설문은 상세 답변과 JSON snapshot 구조화를 확인하고, 콘텐츠/커뮤니티는 접근권, 자산 로그, 다운로드 로그, 작가 신청, 시리즈, 댓글/게시글, post draft, 신고/쪽지/스크랩/동의 증적이 대상 계정 기준으로 포함되며 다른 계정 row가 섞이지 않는지 확인한다. 보존형 export fixture는 보상 회수 실패 큐, 결제 record/item, 금액성 원장, 쿠폰 환불, 환불/출금 계좌, 알림 delivery, 포인트/적립금 만료 소비 매핑처럼 운영 증빙으로 남기는 고위험 필드가 대상 계정 기준으로 export되고 다른 계정 row가 섞이지 않는지 확인한다.
- `.tools/bin/check-privacy-cleanup-runtime.php`는 SQLite fixture로 `asset_ledger`, `quiz`, `survey`, `content`, `community`, `notification`, `payment_ledger`, `policy_documents`, `reward` cleanup 계약을 실행한다. asset_ledger는 보상 회수 실패 큐의 account 연결, dedupe key, operation context를 익명화하는지 확인하고, 퀴즈/설문은 응시/응답, 보상 grant, 댓글 작성자 snapshot을 확인하고, 콘텐츠/커뮤니티는 접근권, 다운로드 로그, 작가 신청, 닉네임, 레벨, 동의 증적, 시리즈 메타데이터, 작성자 snapshot이 대상 계정에 대해서만 익명화되고 post draft는 삭제되며 다른 계정 row가 유지되는지 확인한다. notification은 push endpoint row를 disabled tombstone으로 전환하고 ciphertext를 비우는지, payment_ledger는 결제 record의 account 연결 제거와 item account 참조 익명화를 수행하는지, policy_documents는 안내메일 delivery의 대상 account 연결만 제거하는지 확인한다. reward는 출금 신청의 계좌정보와 요청자/관리자 note를 비우되 금액, 상태, 처리자, 거래 연결은 보존하는지 확인한다. 이 fixture는 설치 DB에서의 전체 개인정보 smoke를 대체하지 않고, 계약 반환 형태와 핵심 익명화 동작의 회귀를 막는 최소 런타임 증거다.
- `export_retained` 모듈은 `privacy-export.php`를 제공해야 한다. cleanup이 없거나 일부 secret/부가 데이터만 정리하는 이유는 보존 정책, 회원 자산 정리 계약, 운영 증빙 중 하나로 설명한다.
- `operational_retained` 모듈은 사본 제공 제외가 확정됐다는 뜻이 아니다. 1.0 전 보존 정책 검토와 운영자 안내 대상이다.
- `privacy` 모듈은 `privacy-export.php`를 제공하지 않고 소비한다. 자기 요청 이력은 `sr_privacy_export_data()`에서 직접 포함한다.
- 콘텐츠·퀴즈·설문 영구 삭제는 개인정보 cleanup을 대체하지 않는다. 삭제/보존/익명화 후 보존 테이블 배정과 본체 row 부재 상태의 export 안정성 기준은 [이슈 #404 삭제 상태와 영구 삭제 계약](records/issue-404-delete-state-permanent-delete-contract-2026-07-05.md)을 따른다.

## 쪽지 상대방 식별자 기준

대상 회원이 보낸 쪽지와 받은 쪽지는 `message_direction`으로 방향을 구분하고, 상대방은 `counterparty_role` 및 `masked_sender` 또는 `masked_recipient` 역할값으로만 표시한다. `sender_account_id`와 `recipient_account_id` 같은 raw account id 제외 기준은 `message` 모듈의 export runtime fixture로 검증한다.

## 운영 보존 세부 기준

`operational_retained`와 `export_retained`는 "삭제하지 않는다"는 편의 문구가 아니라, 왜 남기는지와 누가 볼 수 있는지를 문서화해야 하는 상태다. 신규 모듈이 이 상태를 선택하면 아래 표에 보존 사유, 운영자 접근 범위, 1.0 전 검토 항목을 추가한다.

| 모듈 | 보존 사유 | 운영자 접근 범위 | 1.0 전 검토 항목 |
| --- | --- | --- | --- |
| `admin` | 관리자 권한, 감사 로그, 운영 설정 변경의 책임 추적 | 소유자와 권한 있는 관리자 화면, 감사 로그 조회 권한 | 감사 로그는 본인 export 기본 범위에서 제외한다. 탈퇴 계정 표시명을 공개 식별자 또는 익명 label로 낮출 수 있는지 검토 |
| `logo_manager` | 로고 변경 이력과 운영 설정 변경 책임 추적 | 사이트 설정 권한이 있는 관리자 화면 | 단순 설정 변경자 ID를 감사 로그로 충분히 대체할 수 있는지 검토 |

`export_retained` 모듈은 사본 제공 대상이므로 `privacy-export.php`를 유지한다. 탈퇴/익명화 시에는 거래 원장, 쿠폰 권리, 환불/출금 신청, 알림 delivery처럼 운영상 보관해야 하는 행을 임의 삭제하지 않는다. 대신 export 결과, 관리자 화면, 운영 문서에서 보존 사유를 설명하고, 표시명/연락처/계좌정보처럼 재식별성이 높은 필드는 업무 종료 후 별도 마스킹 또는 보존기간 정책을 둘 수 있는지 1.0 전 검토한다. `reward`처럼 상태는 `export_cleanup`이지만 원장 row 일부 필드를 보존하는 모듈도 이 표에 보존 필드와 cleanup 필드를 함께 적는다.

| 모듈 | 보존 사유 | 고위험 필드/연결 | 1.0 전 검토 항목 |
| --- | --- | --- | --- |
| `asset_exchange` | 환전 실행 증빙, 정정 로그, 중복 환전 방지 | `account_id`, `created_by_account_id`, 환전 묶음 ID와 자산 원장 reference | 완료 환전 정정 후 보존 기간과 관리자 조회 범위 |
| `coupon` | 쿠폰 지급/사용/환불 권리 증빙과 중복 사용 방지 | `account_id`, `issued_by_account_id`, `refunded_by_account_id`, 쿠폰 사용 reference, 발급 시점 claim/가격/자산 reference 스냅샷, 사용 시점 가격/target 스냅샷, 공개 발급 campaign/source 로그 | 만료/환불 완료 쿠폰의 표시명, 메모, 관리자 접근 범위 |
| `deposit` | 현금성 예치금 원장, 환불 신청, 처리 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, 은행명/계좌번호/예금주 | 환불 완료 후 계좌정보 마스킹 시점과 보존 기간 |
| `notification` | 회원 알림 제공 이력, delivery 실패 추적, 운영 알림 읽음 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, delivery destination/metadata, push endpoint ciphertext | 대상 회원 delivery만 export하고 다른 회원 recipient는 제외한다. 발송 완료/실패 delivery의 주소 마스킹과 재발송 가능 기간, 탈퇴/익명화 시 push endpoint ciphertext 제거 |
| `point` | 포인트 원장, 만료 소비 매핑, 환불/회수 증빙 | `account_id`, `created_by_account_id`, 만료 source/consume transaction 연결 | 탈퇴 후 회원 식별자 노출 최소화와 오래된 만료 소비 매핑 보존 기간 |
| `reward` | 적립금 원장, 출금 신청, 회수/처리 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, 출금 신청 금액/상태/거래 연결. 은행명/계좌번호/예금주와 요청자/관리자 note는 탈퇴/익명화 cleanup에서 빈 값 처리 | 세무 snapshot을 추가할 때 법정 보존 clock과 은행 PII cleanup 경계를 필드 단위로 재확인 |
