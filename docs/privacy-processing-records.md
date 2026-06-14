# 개인정보 처리활동 기록 기준

이 문서는 `docs/privacy-contract-matrix.md`를 ROPA(개인정보 처리활동 기록) 씨앗으로 확장하기 위한 운영 기준이다. 1.0 전에는 별도 관리자 DB 화면을 만들지 않고 문서와 자동 점검으로 기준을 고정한다. 실제 export/cleanup 책임은 [개인정보 계약 매트릭스](privacy-contract-matrix.md)를 우선한다.

## 기록 컬럼

처리활동 row를 문서나 이후 관리자 화면으로 옮길 때 아래 컬럼을 유지한다.

| 컬럼 | 의미 |
| --- | --- |
| `activity_key` | `module.surface.purpose` 형태의 안정적인 key |
| `module_key` | 처리활동을 소유하는 모듈 |
| `surface` | 계정, 인증, 제출, 알림, 금액성 원장, 운영 로그처럼 운영자가 이해할 수 있는 표면 |
| `data_subjects` | 회원, 비회원 제출자, 관리자, 수신자, 제3자 등 정보주체 범위 |
| `data_categories` | 계정 식별자, 연락처, 접속/기기 hash, 동의 증적, 응답/답안 snapshot, 금액성 기록 등 |
| `processing_purpose` | 서비스 제공, 보안, 정산/권리 증빙, 알림 발송, 법적 요청 대응 등 처리 목적 |
| `lawful_basis` | 계약 이행, 동의, 정당한 이익, 법적 의무, 운영 증빙 등 프로젝트 정책상 근거 |
| `special_category_policy` | 특별범주, 연령, 고유식별자성 데이터가 없으면 `not_collected`, 가능성이 있으면 저장 금지 또는 별도 플러그인 기준 |
| `retention_basis` | 계약 이행, 법적 의무, 권리·정산 증빙, 보안 조사, 운영 감사처럼 보존이 필요한 근거 |
| `retention_period` | 관리자 보관 정책 key, 모듈 정책, 법정 보존 기간, 마스킹 시점처럼 실제 보존 기간 또는 검토 기준 |
| `retention_policy` | 삭제/익명화/운영 보존/보존기간 검토 기준 |
| `export_policy` | 사본 제공 포함, 보존형 export, 제외와 근거 |
| `cleanup_policy` | 탈퇴/익명화 때 삭제, account 연결 제거, tombstone, 운영 보존 등 |
| `processors` | 이메일, CAPTCHA, OAuth/OIDC, 결제, 본인확인, storage 같은 외부 수탁사와 재수탁사 후보 |
| `international_transfer` | 외부 처리자의 국외 처리 가능성. 확정 전에는 `review_required` |
| `storage_location` | 로컬 DB, local storage, S3, 외부 provider, 브라우저 저장소 등 |
| `access_scope` | 공개 화면, 회원 본인, 권한 있는 관리자, 배치/CLI 등 접근 범위 |
| `verification` | 연결된 체크 스크립트, smoke, 수동 확인 기준 |

## 현재 번들 모듈 처리활동 씨앗

| 모듈 | 주요 처리활동 | ROPA 보강 기준 |
| --- | --- | --- |
| `admin` | 관리자 권한, 감사 로그, 보존/정리 화면, 운영 알림 처리 | actor account, IP/UA, metadata redaction, 보존기간과 관리자 접근 범위를 분리한다. |
| `antispam` | 산술 challenge, CAPTCHA provider 검증, 선택적 remote IP 전달 | DB 회원 귀속 데이터는 없지만 provider script와 remote IP 전달은 쿠키/외부 처리자 inventory에 포함한다. |
| `antispam_captcha_providers` | Turnstile, hCaptcha, reCAPTCHA provider 계약 | 국외 처리와 외부 processor 후보로 기록하고, 동의 전 script 차단 필요 여부를 #151에서 결정한다. |
| `asset_exchange` | 환전 로그와 정정 증빙 | 금액성 운영 증빙으로 export_retained, account 연결과 정정 가능 기간을 기록한다. |
| `asset_ledger` | 자산 원장 primitive와 reconciliation 조회 | 자기 테이블은 없지만 금액성 모듈의 처리활동 verification과 관리자 조회 표면에 포함한다. |
| `banner` | 배너 설정, 클릭 dedupe hash | `click_key_hash`는 account/session/IP/UA에서 파생될 수 있는 가명성 dedupe 데이터다. 기본 보관일은 180일이며 `/admin/retention`의 배너 클릭 hash 보관일로 정리한다. |
| `ckeditor` | 에디터 asset, 업로드 adapter, sanitizer | 본문 개인정보는 소유 모듈에 귀속하고, 업로드/임시 파일 접근 정책은 소유 모듈 처리활동에 연결한다. |
| `community` | 게시글, 댓글, 쪽지, 신고, 스크랩, 시리즈, 접근권, 보상/자산 로그 | 작성자/상대방/신고자/접속 hash/동의 증적/금액성 로그를 표면별로 나누고 제3자 식별자 export 마스킹을 기록한다. |
| `content` | 콘텐츠, 댓글, 작가 신청, 유료 접근권, 다운로드/자산 로그 | 작성자와 신청자, 유료 접근권, 다운로드/자산 로그의 export_cleanup과 금액성 보존 경계를 구분한다. |
| `coupon` | 쿠폰 지급, 사용, 환불 기록 | 권리성 증빙으로 보존하며 환불 처리자와 메모, 만료 후 표시 최소화 기준을 기록한다. |
| `deposit` | 예치금 잔액/원장, 환불 신청 계좌 | 현금성 증빙과 계좌정보 마스킹 시점, 처리자 접근 범위를 기록한다. |
| `embed_manager` | 본문 embed reference와 작성자 메타데이터 | 본문 소유 모듈 cleanup 이후 orphan reference의 작성자 연결 제거 여부를 검토 항목으로 둔다. |
| `logo_manager` | 로고 배치와 변경 작성자 | 설정 변경 책임 추적은 운영 보존으로 두되 감사 로그 대체 가능성을 검토한다. |
| `member` | 계정, 인증, 프로필, 닉네임, 동의, 그룹, 세션/token | 계정 원천 소유자이며 다른 모듈 cleanup 조정자다. 동의 철회와 정정권 전파의 시작점으로 기록한다. |
| `member_oauth` | OAuth/OIDC state, provider subject hash, email snapshot, 계정 연결 | 외부 provider processor, 국외 처리 가능성, profile snapshot 최소화, 연결 해제 cleanup을 기록한다. |
| `notification` | 사이트 알림, read, delivery, push endpoint, 운영 알림 | site/email/push/external recipient와 ciphertext, 재발송 가능 기간, 탈퇴 후 tombstone 기준을 기록한다. |
| `point` | 포인트 잔액, 원장, 만료 소비 매핑 | 금액성 증빙으로 보존하고 만료 source/consume 연결과 탈퇴 후 식별자 노출 최소화를 기록한다. |
| `policy_documents` | 약관/방침 버전, 동의 문서, 변경 안내메일 delivery | 정책 문서 version snapshot과 안내메일 delivery의 export/cleanup, 수신자 보존 기준을 기록한다. |
| `popup_layer` | 팝업 설정과 닫기 쿠키 | 회원 귀속 DB 데이터는 없지만 브라우저 기능 쿠키 inventory에 포함한다. |
| `privacy` | 개인정보 요청 기록, requester, admin note, handler, export 조정 | 관리자 메모 최소화, 민감정보 redaction, 처리 상태와 실제 조치의 정합성을 기록한다. |
| `quiz` | 퀴즈, 시도, 답안 snapshot, 결과, 댓글, 보상 grant, IP/UA hash | 답안/결과 snapshot과 접속 hash, 보상 grant, 댓글 작성자 cleanup을 표면별로 기록한다. |
| `reaction` | 계정별 target reaction 원장과 알림 연결 | 리액션 원장 삭제 cleanup, target owner 알림 no-op 조건, target resolver 접근 정책을 기록한다. |
| `reward` | 적립금 잔액/원장, 출금 신청 계좌 | 금액성 증빙과 출금 계좌 마스킹 시점, 처리자 접근 범위를 기록한다. |
| `seo` | sitemap/robots/default meta 설정 | 현재 회원 귀속 개인정보 없음. 콘텐츠별 SEO 개인정보는 소유 모듈 책임이다. |
| `site_menu` | 메뉴 구조와 링크 자산 | 현재 회원 귀속 개인정보 없음. 연결 대상 개인정보는 소유 모듈 책임이다. |
| `survey` | 설문, 응답, 답변/metadata/consent snapshot, 댓글, 보상 grant, IP/UA hash | 응답 동의, 익명 허용, 답변 snapshot, 민감정보 입력 가능성, 보상 grant cleanup을 표면별로 기록한다. |

## 외부 처리자와 국외이전 후보

| 후보 | 관련 모듈 | 기준 |
| --- | --- | --- |
| 이메일 발송 provider | `member`, `notification`, `policy_documents`, `privacy` | 인증/정책 안내/알림/개인정보 요청 회신에 쓰일 수 있으므로 recipient, delivery status, 재발송 기간을 기록한다. |
| CAPTCHA provider | `antispam`, `antispam_captcha_providers` | provider script 로딩과 remote IP 전달 옵션은 쿠키 동의와 국외 처리 검토 대상이다. |
| OAuth/OIDC provider | `member_oauth` | provider subject, email/profile snapshot, scopes, 국외 처리 가능성을 기록한다. |
| 결제/본인확인 provider | 선택 플러그인 | 원문 고유식별자 저장 금지, 결과 token/hash/snapshot 최소화, export 제외 근거를 플러그인 계약에 요구한다. |
| S3 호환 storage | `ckeditor`, `content`, `community`, `logo_manager`, 향후 파일 소유 모듈 | 파일 원문 저장 위치와 signed URL 접근 범위, 삭제 실패 재시도 기준을 기록한다. |
| 외부 알림 채널 | `notification` | Slack/Discord/Telegram 등 운영 채널 recipient와 endpoint는 회원 알림과 분리해 기록한다. |

## 특별범주·연령·고유식별자성 데이터 기준

기본 번들 모듈은 특별범주 개인정보, 주민등록번호 같은 고유식별자 원문, CI/DI 원문, 성인 인증 원문 결과를 저장하지 않는다. 회원 선택 프로필의 `birth_date`는 연령성 개인정보로 분류하고 사본 제공에 포함하되, 성인 인증이나 본인확인 원문 결과로 쓰지 않는다. 퀴즈와 설문은 운영자가 민감한 답변을 수집할 수 있는 자유 입력 표면을 제공하므로, 기본 정책은 운영자 안내와 export/cleanup 계약으로 관리하고 원문 민감정보 수집을 권장하지 않는다.

| 표면 | 기본 기준 | 후속 구현 기준 |
| --- | --- | --- |
| 회원 선택 프로필 | `member`의 `birth_date`는 선택 프로필의 연령성 개인정보이며 `privacy-export.php`에 포함한다. | 성인 인증이나 법정대리인 확인이 필요하면 birth date 원문 재사용이 아니라 별도 선택 플러그인의 최소 결과 snapshot 기준을 따른다. |
| 본인확인/성인 인증 | 번들 기본 모듈은 원문 신원정보를 저장하지 않는다. | 선택 플러그인은 provider 원문 응답, 주민등록번호, CI/DI 원문, 이름/휴대폰 원문 저장을 금지하고 HMAC hash 또는 최소 결과 snapshot만 저장한다. |
| OAuth/OIDC profile | `member_oauth`는 provider subject hash와 최소 email snapshot만 보관한다. | scope를 추가하면 profile 원문 저장 금지, export 포함 범위, cleanup 기준을 먼저 갱신한다. |
| CAPTCHA 검증 | `antispam`은 기본 DB 개인정보를 저장하지 않는다. | remote IP 전달을 켜거나 외부 provider script를 로딩하면 processor/국외이전 후보와 쿠키 동의 inventory에 포함한다. |
| 퀴즈/설문 답변 | 답안/응답 snapshot은 사본 제공과 cleanup 대상이다. | 건강, 정치, 종교, 아동/연령, 고유식별자성 질문을 운영자가 추가하는 경우 별도 동의 문구와 보존/삭제 기준을 문서화해야 한다. |
| 관리자 메모/감사 metadata | 제3자 개인정보와 민감정보 입력을 금지하고 redaction 기준을 적용한다. | #157에서 입력 가이드, 목록/상세/export 노출 범위, audit metadata 중복 노출을 점검한다. |

선택 플러그인이나 후속 모듈이 특별범주·연령·고유식별자성 데이터를 다뤄야 하면 `special_category_policy`를 `not_collected`로 둘 수 없다. 해당 모듈은 수집 목적, 원문 저장 금지 여부, 최소 snapshot, export 제외 근거, cleanup 또는 보존기간, 관리자 원문 노출 금지, 설치 DB smoke 기준을 함께 제공해야 한다.

## 쿠키와 브라우저 저장소 inventory

현재 번들 기준으로 공개 화면에서 확인된 쿠키와 브라우저 저장소 표면은 기능·보안 목적에 한정한다. 마케팅, 행동 분석, 광고, 제3자 추적 script는 기본 번들에 포함하지 않는다. 따라서 `/privacy/cookie-settings`의 선택 항목은 현재 비필수 브라우저 저장소로 확인된 팝업 닫기 쿠키만 표시하고, 새 비필수 저장소가 추가될 때 이 inventory와 동의 gate를 함께 확장한다.

| 표면 | 저장 위치 | 목적 | 동의/차단 기준 | 검증 |
| --- | --- | --- | --- | --- |
| PHP session cookie | browser cookie | 로그인, CSRF, 관리자/회원 세션 유지 | 필수 보안 저장소로 분류하고 `/privacy/cookie-settings`의 필수 쿠키 그룹에 항상 사용 항목으로 표시한다. `HttpOnly`, `SameSite=Lax`, HTTPS 설정 시 `Secure`를 사용한다. | `core/helpers/runtime.php`, `.tools/bin/check-security-baseline.php`, `modules/privacy/views/cookie-settings.php` |
| 쿠키 설정 상태 | browser cookie `sr_cookie_consent` | 기능성 저장소 항목별 허용 여부를 저장한다. | 기본/콘텐츠/커뮤니티/퀴즈 공개 layout의 `privacy` 쿠키 설정 배너에서 `관리`, `거절`, `동의` 중 하나를 선택한다. `관리`는 `/privacy/cookie-settings` 설정 페이지로 이동해 항목을 선택하고 저장한다. 로그인한 회원은 `/account/privacy-requests`의 쿠키 동의 관리에서 같은 설정 페이지로 다시 이동할 수 있다. 거절은 기능성 저장소 동의 철회로 처리한다. 이 쿠키 자체는 같은 선택을 반복해서 묻지 않기 위한 필수 설정 저장소로 분류하고 `/privacy/cookie-settings`의 필수 쿠키 그룹에 표시한다. 현재 항목형 값은 `items:popup_dismissal`이며, 기존 `functional` 값은 호환을 위해 모든 기능성 항목 허용으로 읽는다. | `modules/privacy/helpers.php`, `modules/privacy/actions/cookie-consent.php`, `modules/privacy/actions/cookie-settings.php`, `modules/privacy/views/cookie-settings.php`, `modules/privacy/views/account-privacy-requests.php` |
| 팝업 닫기 쿠키 | browser cookie `sr_popup_layer_{id}_dismissed` | 방문자가 선택한 기간 동안 같은 팝업을 다시 보지 않게 한다. | 기능성 선택 저장소다. `sr_cookie_consent`가 `popup_dismissal` 항목을 허용할 때만 저장하고, 보관 기간은 팝업별 `dismiss_cookie_days`이며 0이면 쿠키를 만들지 않는다. 쿠키 path는 루트 배포에서는 `/`, 하위 경로 배포에서는 `sr_base_path()`를 따른다. | `modules/popup_layer/assets/saanraan-popup-layer.js`, `modules/popup_layer/helpers.php` |
| CAPTCHA provider script | 외부 script와 provider cookie 가능성 | 공개 제출/회원가입 자동등록방지 | 보안 목적 provider 표면이다. Turnstile, hCaptcha, reCAPTCHA를 활성화하면 외부 처리자와 국외이전 후보에 포함한다. 마케팅/분석 목적 script와 함께 쓰면 사전 동의 gate를 추가해야 한다. | `modules/antispam/helpers.php`, `modules/antispam_captcha_providers/antispam-providers.php` |
| CAPTCHA remote IP | provider 검증 POST payload | provider의 위험도 판단 보조 | 기본값은 `verify_remote_ip_enabled = false`다. 켜면 개인정보 처리활동 기록과 개인정보 안내문에 remote IP 전달을 반영한다. | `modules/antispam/module.php`, `modules/antispam/views/admin-settings.php` |
| 커뮤니티 제출 동의 | DB row, POST checkbox | 게시글/댓글/첨부 업로드 시 개인정보 수집 동의 증적 | 브라우저 저장소에 유지하지 않고 제출 시 `community_privacy_consent_accepted`를 서버에서 검증한다. | `modules/community/helpers/privacy-consents.php`, export/cleanup runtime |

후속 모듈이 `localStorage`, `sessionStorage`, IndexedDB, analytics cookie, pixel script, A/B testing script를 추가하면 이 inventory와 ROPA processor/국외이전 후보를 먼저 갱신한다. 비필수 저장소는 렌더링 전에 동의 상태를 확인해야 하며, 거부 상태에서도 필수 보안 흐름이 동작해야 한다. 기능성 저장소는 `sr_privacy_cookie_consent_allows('popup_dismissal')`처럼 항목별 gate 또는 같은 의미의 클라이언트 gate를 거친다.

## 권리 요청 전파 기준

`privacy` 모듈의 처리 요청 유형은 `access`, `rectification`, `erasure`, `restriction`, `portability`, `objection`, `withdrawal`이다. 관리자 화면의 상태값은 요청 처리 기록의 상태이며, 각 모듈 데이터가 자동으로 바뀌었다는 뜻이 아니다. 요청을 `completed`로 종결하려면 요청자 확인, 처리 자료 또는 처리 결과 확인, 처리 내용 메모가 함께 남아야 한다.

| 요청 유형 | 기본 처리 기준 | 모듈별 전파 기준 |
| --- | --- | --- |
| `access` 열람 | `privacy-export.php` 계약과 보존형 export를 수집해 사본을 제공한다. | `member`, `member_oauth`, `policy_documents`, `notification`, `community`, `content`, `quiz`, `survey`, `reaction`, 금액성 모듈 export를 포함한다. |
| `rectification` 정정 | 계정 원천 데이터는 `member` 관리자/회원 화면에서 정정하고, 원문 신원정보는 재검증을 유도한다. | 게시글/댓글/응답/원장은 과거 작성 시점 snapshot을 임의 수정하지 않는다. 표시명 snapshot 정정은 운영자가 공개 오표시와 증빙 보존을 비교해 판단한다. |
| `erasure` 삭제 | 회원 탈퇴/익명화 cleanup 계약을 우선 사용한다. | 운영 증빙, 금액성 원장, 정책문서 delivery, 감사 로그처럼 보존 사유가 있는 row는 account 연결 제거, tombstone, 마스킹, 보존기간 만료 후 정리 중 하나로 처리한다. |
| `restriction` 처리 제한 | 계정 상태, 공개 노출, 알림 발송, 신규 처리 중단이 필요한지 분리한다. | `member` 계정 정지/보류, `notification` 발송 중단, `community`/`content`/`quiz`/`survey` 공개 노출 제한, `reaction` 신규 write 제한은 별도 모듈 정책으로 처리한다. 금액성 원장은 정산/환불 가능성을 해치지 않는다. |
| `portability` 이동권 | 산란이 보관하는 구조화 가능한 사본만 제공한다. | provider 원문, CI/DI 원문, 외부 처리자 내부 로그는 제공하지 않는다. export JSON은 다른 계정 row가 섞이지 않아야 한다. |
| `objection` 처리 반대 | 정당한 이익이나 운영 목적 처리에 대한 중단 가능성을 검토한다. | 마케팅/분석/비필수 알림, 리액션/추천/통계 반영은 중단 후보로 두되, 보안/정산/법적 의무 기록은 보존 사유를 메모한다. |
| `withdrawal` 동의 철회 | 회원 마케팅 수신 동의와 쿠키/추적 동의, 커뮤니티/설문 제출 동의를 분리한다. | `member`의 `marketing` 동의 철회 기록, #151 쿠키 consent 설정, `/account/privacy-requests`의 기능성 쿠키 철회, `community` 제출 동의의 신규 제출 차단, `survey` 응답 철회 정책을 각각 처리한다. 과거 필수 동의의 증적은 보존할 수 있다. |

권리 요청 전파는 자동 일괄 변경보다 모듈 소유 정책을 우선한다. 여러 모듈에 반복되는 동작이 확인되면 먼저 좁은 helper 또는 계약을 추가하고, 코어로 올리는 것은 도메인 정책이 사라진 뒤에만 검토한다.

## 감사 로그 개인정보 기준

감사 로그는 관리자 작업, 보안 이벤트, 정리 실행, 권한 변경, 모듈 업데이트처럼 운영 책임을 추적하기 위한 `operational_retained` 데이터다. actor account, actor type, target, result, IP, user agent, message, metadata를 포함할 수 있으므로 공개 회원 사본 제공의 기본 범위에는 넣지 않는다. 특정 요청에서 감사 로그 제공이 필요하다고 판단하면 운영자가 별도로 검토하고, 제3자와 관리자 개인정보를 redaction한 요약만 제공한다.

| 항목 | 기준 |
| --- | --- |
| 저장 전 처리 | `sr_audit_metadata_sanitize()`로 password, token, secret, credential, bearer, authorization 계열 값과 명백한 이메일·휴대폰·주민등록번호 형태를 마스킹한다. |
| 표시 전 처리 | `/admin/audit-logs`는 `sr_admin_audit_log_display_message()`와 `sr_admin_audit_log_display_metadata()`를 거쳐 민감 문자열을 다시 마스킹한다. |
| export 기준 | `privacy-export.php` 기본 수집 대상에서 제외한다. 본인 요청이라도 운영 책임 추적, 다른 관리자/회원/제3자 식별자, 보안 조사 맥락이 섞일 수 있기 때문이다. |
| retention 기준 | `/admin/retention`의 관리자 작업 로그 보관일(`audit_logs_days`)을 따른다. |
| metadata 입력 기준 | action은 원문 비밀번호, token 원문, provider 원문 응답, 주민등록번호/CI/DI 원문, 계좌번호 전체, 제3자 연락처를 metadata에 넣지 않는다. 필요한 경우 hash, count, 상태, target id, masked value만 저장한다. |
| 접근 범위 | 소유자와 감사 로그 조회 권한이 있는 관리자 화면으로 제한한다. |

## 알림 delivery recipient 기준

알림 delivery recipient는 회원 알림 제공 이력과 외부 발송 실패 추적에 필요하지만, 다른 회원이나 외부 운영 채널 식별자가 섞일 수 있다. export와 cleanup은 notification 원장 보존과 recipient 최소화를 분리한다.

| 항목 | 기준 |
| --- | --- |
| site delivery export | 대상 회원에게 연결된 알림 또는 전체 알림의 site delivery는 사본 제공에 포함한다. |
| email delivery export | 대상 계정의 현재 email과 일치하는 recipient만 포함하고 다른 회원 email recipient는 제외한다. |
| push endpoint export | 대상 계정 소유 endpoint id 형태의 delivery recipient는 `recipient_masked`로 대체하고 endpoint ciphertext는 export하지 않는다. 대상 계정 소유 endpoint로 확인되지 않는 endpoint delivery는 제외한다. |
| external admin channel | Slack/Discord/Telegram 같은 운영 채널은 회원 알림 export에 섞지 않는다. 대상 회원 알림과 연결된 endpoint reference도 masked 값만 제공한다. |
| 탈퇴/익명화 cleanup | `notification` cleanup은 회원 push endpoint를 disabled tombstone으로 바꾸고 `endpoint_ciphertext`를 비운다. `policy_documents` cleanup은 안내메일 delivery의 `account_id` 연결을 제거한다. |
| retention | 일반 알림, delivery, read 기록은 `/admin/retention`의 알림 보관일(`notifications_days`)을 따른다. 재발송 가능 기간이 끝난 delivery는 오래 보존하지 않는다. |
| 관리자 화면 | `/admin/notification-deliveries`는 recipient를 masked 표시하고, 검색/필터는 권한 있는 관리자에게만 제공한다. |

## 커뮤니티 쪽지 상대방 식별자 기준

커뮤니티 쪽지는 대상 회원의 대화 내역이지만 항상 상대방 회원 식별자가 함께 걸린다. 사본 제공은 대상 회원의 본문과 상태 확인권을 우선하고, 제3자 account id는 기본 export에서 제외한다.

| 항목 | 기준 |
| --- | --- |
| export 포함 | 대상 회원이 보낸 쪽지와 받은 쪽지를 모두 포함한다. 본문, 상태, 읽음/삭제 시각, 생성/수정 시각은 대상 회원의 대화 내역으로 제공한다. |
| 방향 표시 | `message_direction`은 대상 회원 기준 `sent` 또는 `received`로 제공한다. |
| 상대방 표시 | 상대방은 원 account id나 표시명 snapshot 대신 `counterparty_role`의 `masked_sender` 또는 `masked_recipient` 역할값으로만 제공한다. |
| 원 식별자 제외 | `sender_account_id`와 `recipient_account_id`는 `community` export 결과에 포함하지 않는다. |
| cleanup | 탈퇴/익명화는 쪽지 원문 보존과 양쪽 삭제 상태를 별도 정책으로 다루되, export 계약에서는 상대방 raw 식별자를 다시 노출하지 않는다. |
| verification | `.tools/bin/check-privacy-export-runtime.php`는 보낸 쪽지와 받은 쪽지 fixture를 함께 만들고 방향값, 상대방 역할값, raw account id 제외를 확인한다. |

## 자산 로그 account_id 보존 기준

금액성 자산 로그는 포인트, 적립금, 예치금, 쿠폰, 환전, 콘텐츠/커뮤니티 과금, 퀴즈/설문 보상처럼 권리·정산·환불·정정 근거가 되는 `export_retained` 데이터다. 탈퇴/익명화 cleanup은 공개 표시와 서비스 접근 상태를 정리하되, 원장성 row의 account 연결과 실행 snapshot을 자동 삭제하지 않는다. 단, 콘텐츠/커뮤니티 자산 처리 중 생성된 `log_status = 'pending'` placeholder는 아직 권리·정산 증빙 row가 아니므로 세션 보관 기준에 맞춰 관리자 보관 정책에서 정리할 수 있다.

| 표면 | 보존 기준 | cleanup 기준 |
| --- | --- | --- |
| `point`, `reward`, `deposit` 원장 | 잔액, 거래 유형, 금액, reference, 처리 시각은 사본 제공과 운영 보존 대상이다. | 모듈 cleanup을 제공하지 않고 원장 보존으로 처리한다. |
| `coupon`, `asset_exchange` 로그 | 지급/사용/환불/환전 묶음과 실패 사유는 권리성 증빙으로 유지한다. | 상태 정정은 반대 거래나 정정 row로 남기며 원거래 account 연결을 제거하지 않는다. |
| `content` 자산 로그 | `sr_content_asset_access_logs`, `sr_content_asset_action_logs`, `sr_content_author_reward_logs`의 completed 원장성 row는 account id와 settlement snapshot을 유지한다. | `sr_content_access_entitlements`와 `sr_content_file_download_logs`는 접근 상태/다운로드 이력 최소화를 위해 account 연결을 제거할 수 있다. 오래된 pending placeholder는 보관 정책의 미완료 로그 정리 대상이다. |
| `community` 자산 로그 | `sr_community_asset_logs`, `sr_community_publisher_reward_logs`의 completed 원장성 row는 downloader/publisher account id와 settlement snapshot을 유지한다. | `sr_community_access_entitlements`는 접근권 상태이므로 account 연결과 source reference를 제거할 수 있다. 오래된 pending placeholder는 보관 정책의 미완료 로그 정리 대상이다. |
| `quiz`, `survey` 보상 grant | 보상 지급 dedupe와 provider reference는 중복 지급 방지와 권리 확인에 필요하다. | 현재 cleanup은 grant를 익명화하되 dedupe key를 `anonymized:*` 형태로 바꿔 재연결을 끊는다. 금액성 원장 자체는 자산 모듈 보존 기준을 따른다. |
| verification | export runtime은 대상 계정의 금액성 row만 제공하는지 확인하고, cleanup runtime은 콘텐츠/커뮤니티 자산 로그 account id가 정리 중 유지되는지 확인한다. | 설치 DB smoke에서는 탈퇴 후 공개 UI 노출 제거와 원장 조회 가능성을 별도로 확인한다. |

## 관리자 메모 redaction 기준

관리자 메모는 개인정보 처리 요청의 판단 근거와 처리 결과를 남기기 위한 운영 기록이다. 요청자 본문과 달리 내부 판단을 위한 필드이므로 제3자 개인정보, 주민등록번호, 원문 연락처, 비밀번호, token, provider 원문 응답을 남기지 않는다.

| 표면 | 기준 |
| --- | --- |
| 입력 가이드 | `/admin/privacy-requests`의 생성/상태 변경 메모 도움말은 처리 근거와 결과만 남기도록 안내한다. |
| 저장 전 처리 | `sr_admin_privacy_request_admin_note_sanitize()`가 secret류 문자열, 이메일, 휴대폰 번호, 주민등록번호 형태를 redaction한다. |
| export 처리 | 개인정보 요청 export와 계정 전체 export의 `privacy_requests.admin_note`는 저장된 기존 row라도 같은 redaction helper를 거쳐 제공한다. |
| 감사 metadata | 감사 로그 metadata는 `sr_audit_metadata_sanitize()`로 저장 전 처리하고 `/admin/audit-logs` 표시 전에도 `sr_admin_audit_log_display_metadata()`를 거친다. |
| 한계 | 자동 redaction은 명백한 패턴만 줄인다. 운영자는 제3자 사연, 민감정보, provider 원문 응답을 메모에 붙여넣지 않는 것을 원칙으로 한다. |

## 마일스톤 12 conformance 자동화 기준

마일스톤 12의 통합 판정은 `php .tools/bin/check.php`를 기준으로 한다. 개별 점검은 빠른 회귀 확인용으로 실행할 수 있지만, 릴리스 전에는 통합 게이트가 아래 경로를 모두 포함해야 한다.

| 점검 | 역할 |
| --- | --- |
| `check-retention-targets.php` | 감사 로그, 알림, 배너 클릭 hash 같은 보존/정리 대상과 삭제 SQL 안전 경계를 확인한다. |
| `check-privacy-contract-matrix.php` | 26개 번들 모듈 분류, 계약 선언, 설치/update SQL 계정·식별자 컬럼, ROPA 문서 marker, 쿠키/브라우저 저장소 inventory, 통합 게이트 연결을 확인한다. |
| `check-privacy-export-runtime.php` | SQLite fixture로 활동 데이터와 보존형 원장이 대상 계정 기준으로 export되고 다른 계정 row가 섞이지 않는지 확인한다. |
| `check-privacy-cleanup-runtime.php` | SQLite fixture로 탈퇴/익명화 cleanup이 공개 노출 데이터와 secret을 줄이고, 보존 원장은 유지하는지 확인한다. |
| `check-privacy-request-admin-note.php` | 개인정보 대응 기록 관리자 메모가 저장/export sanitization에서 명백한 이메일, 휴대폰, 주민등록번호형 값과 secret류를 원문으로 남기지 않는지 확인한다. |
| `check-doc-links.php` | 개인정보 계약, 처리활동 기록, 개발자 가이드, 실행 명령 링크가 깨지지 않았는지 확인한다. |

`export_policy`, `cleanup_policy`, `lawful_basis` 정책 값에 `pending`, `TODO`, `TBD`, `미정`, `미확정`을 남기면 실패한다. 운영 상태값으로 쓰는 `pending`은 허용하지만, 개인정보 정책 근거와 export/cleanup 판정은 문서와 계약에서 확정된 값으로만 남긴다.

설치 DB smoke는 `.tools/bin/check.php`를 대체하지 않는다. 로컬 또는 스테이징 DB와 mutation 허용 조건이 준비된 때 `release-installed-gate-status.php --run-privacy-smoke` 또는 관련 smoke를 추가 실행하고, 실패하면 환경 문제인지 실제 개인정보 계약 회귀인지 구분해 기록한다.

## 운영 기준

- 새 모듈이 `account_id`, `author_account_id`, `created_by_account_id`, `processed_by_account_id`, `handled_by_account_id`, `recipient`, `email`, `phone`, `birth_date`, `ip_hash`, `user_agent_hash`, `provider_subject_hash`, `consent_snapshot_json`, `answer_snapshot_json`, `metadata_snapshot_json` 같은 개인정보성 필드를 추가하면 이 문서와 [개인정보 계약 매트릭스](privacy-contract-matrix.md)를 함께 갱신한다.
- 처리활동 row는 `processing_purpose`, `lawful_basis`, `retention_basis`, `retention_period`를 함께 적어 목적과 보존 근거가 분리되도록 한다.
- 외부 provider script를 공개 화면에 추가하면 쿠키/브라우저 저장소 inventory와 processor/국외이전 후보에 포함한다.
- 특별범주, 연령, 고유식별자성 데이터는 기본 번들 모듈에서 원문 저장하지 않는다. 필요하면 본인확인 같은 선택 플러그인이 원문 저장 금지와 결과 최소 snapshot 기준을 문서화해야 한다.
- `export_retained`와 `operational_retained`는 삭제하지 않는다는 뜻이 아니다. 보존 사유, 접근 범위, 보존기간 또는 마스킹 시점을 기록해야 한다.
- 설치 DB smoke가 필요한 처리활동은 `docs/release-verification-template.md`와 `docs/smoke-test.md`의 개인정보 export/cleanup smoke 기준에 연결한다.
