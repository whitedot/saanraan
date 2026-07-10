# Saanraan 보안 모델

산란은 비즈니스 도메인을 소유하지 않는 절차형 PHP 솔루션 베이스다. 대신 설치, 회원 인증, 관리자 권한, 감사 로그, 개인정보 사본 제공, 업데이트 같은 운영 도메인을 코어 기준선으로 제공하고, helper, 정적 검사, dispatch contract 세 층으로 그 기준선을 받친다.

이 문서는 모듈 작성자가 산란에서 무엇을 제공받고, 무엇을 직접 책임져야 하는지 구분하기 위한 기준이다.

## 참고 기준

산란의 최종 설계 판단은 [핵심 설계 결정](core-decisions.md)을 우선하지만, 보안 세부 기준은 다음 공개 기준과 충돌하지 않는 방향으로 검토한다.

- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [European Commission GDPR principles](https://commission.europa.eu/law/law-topic/data-protection/rules-business-and-organisations/principles-gdpr_en)
- [European Data Protection Board: GDPR FAQ](https://www.edpb.europa.eu/sme-data-protection-guide/faq-frequently-asked-questions/answer/what-gdpr_en)
- [European Data Protection Board: individuals' rights](https://www.edpb.europa.eu/sme-data-protection-guide/respect-individuals-rights_en)

## 1. 작은 약속을 지킨다

산란의 보안 모델은 자동 보안을 약속하지 않는다.

```text
산란은 운영·보안 기준선의 호출 누락을 런타임에서 잡는다.
비즈니스 정책의 의미 판단은 모듈이 명시적으로 책임진다.
```

이 구분을 다음처럼 부른다.

```text
call-site contract: 필요한 helper가 요청 흐름에서 호출되었는지 확인하는 계약
semantic contract: 어떤 계정이 어떤 대상에 어떤 작업을 할 수 있는지 판단하는 계약
```

코어는 call-site contract를 보강한다. semantic contract는 모듈 action과 helper가 책임진다.

예를 들어 관리자 POST action에서 `sr_require_csrf()`, `sr_member_require_login()`, `sr_admin_require_permission()` 또는 `sr_admin_require_owner()` 호출이 빠지면 코어가 잡을 수 있다. 하지만 어떤 메뉴에서 `view`, `edit`, `delete` 중 어느 권한을 요구할지, 게시글 작성자가 자기 글만 수정할 수 있는지, 주문 상태를 어떤 순서로 바꿀 수 있는지는 해당 모듈의 정책이다.

## 1-1. 콘텐츠 보안 정책

기본 CSP는 자체 출처를 기준으로 한다. 외부 스타일/폰트와 연결 출처는 현재 관리자 폰트 로딩과 브라우저 개발자 도구의 보조 요청에 필요한 `https://cdn.jsdelivr.net`을 허용한다. YouTube URL 임베드를 위해 frame 출처는 자체 출처와 `https://www.youtube-nocookie.com`, `https://www.youtube.com`만 허용한다. 본인확인 시작 응답은 외부 provider POST 전송이 필요하므로 `form-action`에 한해 KCP 테스트/운영 gateway와 KG이니시스 본인확인 origin을 추가 허용한다. CKEditor 플러그인의 기본 운영값은 저장소에 포함된 `modules/ckeditor/vendor/ckeditor5/` 파일을 쓰는 직접 호스팅이다. 선택 CDN 모드는 공식 CDN의 스크립트와 스타일을 불러올 수 있도록 `https://cdn.ckeditor.com`을 허용한다. 외부 스크립트, 연결 출처, frame 출처를 추가할 때는 UI 렌더링에 필요한지, 자체 호스팅으로 대체할 수 있는지, 운영 화면 전체에 주는 영향을 함께 검토한다.

공개 모듈 theme와 skin은 저장소 내부 PHP view로 실행되며 별도 sandbox가 아니다. 새 theme나 skin을 배포하는 것은 저장소 코드를 배포하는 것과 같은 승인 수준으로 다룬다. 공개 asset은 `modules/{module_key}/theme/{theme_key}/assets/`처럼 허용된 정적 asset 디렉터리에서만 직접 열리고, PHP view 파일과 모듈 내부 파일은 웹에서 직접 열리지 않아야 한다. 자세한 계약은 [공개 모듈 테마 기준](public-module-themes.md)을 따른다.

CKEditor HTML 저장은 서버 helper가 허용 태그/속성 기반으로 정화한다. 클라이언트 에디터가 보낸 HTML을 신뢰하지 않으며, `script`, event handler, inline style, `iframe`, `object`, `embed`, `form`, `javascript:` URL, data URL 이미지는 허용하지 않는다. 공통 rich text sanitizer는 `script`, `style`, `iframe`, `object`, `embed`, `form`, `meta` 같은 hard-drop 컨테이너를 먼저 제거한다. HTML Purifier가 배치되어 있으면 그 다음 Purifier를 1차 정화 엔진으로 사용하고, 산란의 제한 allowlist와 출력 형식을 맞추기 위해 내부 fallback canonicalizer를 한 번 더 통과시킨다. HTML Purifier가 없는 공유호스팅 배포에서는 같은 hard-drop 제거 뒤 내부 DOM 기반 sanitizer를 fallback으로 사용하되, 이는 보조 경로이며 payload fixture로 계속 검증한다. 커뮤니티 게시글 helper는 모듈 경계 wrapper만 유지하고 실제 정화는 공통 rich text sanitizer에 위임하므로 콘텐츠, 커뮤니티, 알림 본문이 같은 hard-drop 제거와 canonicalizer 경로를 공유한다. 콘텐츠, 커뮤니티 게시글, 팝업레이어 복사 경로도 기존 저장 HTML을 신뢰하지 않고 새 레코드 저장 전과 본문 이미지 URL을 다시 정화한다. URL 임베드 helper는 저장 HTML에 script, iframe, object, embed, event handler, inline style, 전용 marker를 남기지 않고, 안전한 URL/링크만 공개 렌더링 시점에 `url-embed-targets.php` resolver와 renderer로 해석한다.

콘텐츠와 커뮤니티의 plain text 본문 URL 자동 링크 설정은 `http://`와 `https://` 절대 URL만 대상으로 한다. 출력 helper는 링크 앞뒤의 일반 텍스트를 계속 escape하고, URL도 `sr_is_http_url()` 검증을 통과한 값만 `rel="nofollow noopener noreferrer"` 링크로 출력한다. `새 탭` 설정을 켰을 때만 이 자동 생성 링크에 `target="_blank"`를 추가한다. Markdown/HTML 본문은 두 설정의 변환을 거치지 않고 Markdown renderer 또는 기존 sanitizer와 저장된 링크 정책을 따른다.

자동등록방지 외부 CAPTCHA provider 계약은 `endpoint`와 widget `script_url`에 HTTPS 공개 URL만 허용한다. provider 응답 검증을 위해 서버가 POST를 보내기 직전에는 endpoint host가 공개망 주소로 해석되는지 다시 확인해 `file://`, localhost, 사설망/예약망 IP, 비 HTTPS endpoint를 provider 검증 경로에서 배제한다.

CKEditor 본문 이미지는 다운로드/첨부 파일과 분리된 화면 소유 모듈의 로컬 저장 경로가 소유한다. 콘텐츠, 커뮤니티, 팝업레이어 upload endpoint는 로그인/관리자 권한, CSRF, 업로드 token을 확인하고, 저장된 본문 HTML에서 정화 후 남은 소유 모듈 프록시 URL만 서버가 보존 대상으로 인정한다. 비공개, 권한 제한, 유료 열람, 미발행 콘텐츠와 게시글의 본문 이미지 프록시는 소유 모듈 접근 정책 또는 관리자 권한을 다시 확인해야 하며, 클라이언트 hidden 값이나 공개 URL 문자열만으로 접근을 허용하지 않는다. 관리자 설정형 rich textarea에는 공통 업로드를 자동 활성화하지 않고, 소유 모듈이 안정적인 setting subject key와 삭제 정책을 정의한 경우에만 upload endpoint를 붙인다.

삭제된 글과 댓글을 운영 보존 상태로 남기는 경우에도 본문, 제목, 작성자 snapshot, 멘션, 신고, 알림, 자산 처리 로그는 개인정보와 운영 증빙으로 취급한다. 공개 화면에서 제외됐다는 사실만으로 개인정보 cleanup이나 사본 제공 대상에서 제외하지 않는다. 물리 삭제로 전환하는 모듈은 감사 로그만으로 충분한지, 본문/제목/작성자 snapshot을 얼마나 남길지, 자식 댓글과 관련 신고/보상/첨부 파일을 어떻게 정리할지 먼저 정의해야 한다.

커뮤니티 게시판별 개인정보 수집 및 이용동의는 회원가입 동의가 아니라 게시글 작성/수정, 댓글 작성, 첨부 업로드 제출 행위의 증적이다. 동의 설정은 `policy_documents` 모듈이 활성화되어 있고 게시된 정책 문서가 있을 때만 전역 기본값과 게시판별 설정에서 켤 수 있으며, 모듈 또는 게시 문서가 준비되지 않으면 관리자 UI와 서버 저장 검증이 동의 사용을 막는다. 첨부 업로드 대상에는 게시글 저장 시의 일반 이미지/파일 첨부와 CKEditor 본문 이미지 임시 업로드가 포함되며, AJAX 업로드 endpoint도 동의 POST 값을 서버에서 검증한다. 커뮤니티 모듈은 제출 당시 동의 제목, 본문, 버전을 `sr_community_submission_consents`에 snapshot으로 저장하고 IP/User-Agent는 원문이 아니라 해시로만 남긴다. 개인정보 사본 제공에는 회원 계정과 연결된 동의 증적과 IP/User-Agent 해시를 포함하고, 탈퇴 또는 익명화 cleanup에서는 `account_id`, IP 해시, User-Agent 해시를 비운다.

커뮤니티 게시글 임시저장 action은 로그인과 CSRF를 통과한 POST JSON 요청만 받는다. 작성 draft는 게시판 쓰기 권한을, 수정 draft는 게시글 수정 권한을 다시 확인하고, 권한이 없으면 저장하지 않는다. 서버 draft는 로그인 회원의 작성/수정 본문과 선택형 form state만 저장하며 비회원 글, 댓글, 파일 input 값은 저장하지 않는다. 같은 탭 장애 복구용 브라우저 버퍼는 `sessionStorage`로 한정하고 `localStorage`를 쓰지 않는다. 임시 본문 이미지 URL은 현재 세션 token이 더 이상 유효하지 않으면 복원 시 본문에서 제거 안내 문구로 대체한다. draft 본문과 form state는 개인정보 사본 제공 대상이고, 탈퇴/익명화 cleanup에서는 해당 회원 draft를 삭제한다.

비회원 게시글/댓글 작성을 허용하는 경우에도 작성자 표시명, 수정/삭제 검증 수단, IP/User-Agent hash, 동의 snapshot, 보관 기간 cleanup을 한 묶음으로 설계한다. 원문 비밀번호, IP, User-Agent는 저장하지 않고 hash 또는 검증 token hash만 저장한다. 회원 사본 제공과 비회원 제출 증적은 검색/검증 기준이 다르므로 export/cleanup 정책을 분리하며, 게시판별 추가 입력 항목이 개인정보이면 동의 문구와 보관 기간을 필드 정의에 함께 둔다. 세부 설계와 후속 범위는 GitHub 이슈에서 추적한다.

커뮤니티 게시글과 댓글은 `deleted` 상태 전환 시 원문 제거를 기본값으로 둔다. 게시글 삭제는 제목, 본문, 작성자 표시 snapshot, SEO/OG 원문, 본문 URL 임베드 캐시, 본문 이미지 파일을 제거하고 첨부 파일 원본명과 저장소 참조를 마스킹한다. 댓글 삭제는 댓글 본문과 작성자 표시 snapshot을 제거한다. 삭제 후 원문 복구는 지원하지 않으며, 운영 목록에는 삭제 상태와 최소 식별자만 남긴다.

콘텐츠 모듈도 관리자 삭제와 댓글 삭제에서 원문 제거를 기본값으로 둔다. 콘텐츠 삭제는 제목, 요약, 본문, SEO, 커버 이미지, 본문 이미지, 본문 URL 임베드 캐시 파생 상태, revision 원문, 회원 제출 원문, 다운로드 로그 snapshot, 다운로드 첨부 파일 원본명과 저장소 참조를 제거하거나 마스킹한다. 콘텐츠 댓글 삭제는 댓글 본문과 작성자 표시 snapshot을 제거한다. 삭제 상태 콘텐츠는 저장 또는 일괄 상태 변경으로 공개, 초안, 숨김 상태로 복구할 수 없다.

퀴즈와 설문은 `deleted_at` 삭제 시 문제지/설문지 원문과 응시·응답 snapshot을 마스킹한다. 퀴즈 삭제는 퀴즈 제목/설명, 문항/선택지/결과 원문, 댓글 원문과 작성자 표시 snapshot, 응시 answer/scoring/result snapshot, 주관식 답변, 보상 snapshot을 제거한다. 설문 삭제는 설문 설명과 조사 설계 원문, 문항/선택지 원문, 댓글 원문과 작성자 표시 snapshot, 응답/답변 snapshot, 주관식/기타/숫자 답변, 보상 snapshot을 제거한다.

콘텐츠·퀴즈·설문은 일반 삭제와 영구 물리 삭제를 2단계로 구분한다. 일반 삭제는 원문 redaction과 소유 저장소 파일 삭제 시도를 맡고, 영구 삭제는 이미 redaction된 본체 row와 매트릭스상 삭제 대상 live 하위 row만 제거한다. 영구 삭제는 소유 파일을 처음 삭제하는 단계가 아니며, cleanup failure/pending row와 결제·자산·보상·감사 같은 운영 증빙 로그는 본체 row 없이도 조회 또는 재시도 가능해야 한다. 테이블별 삭제/보존 기준은 [이슈 #404 삭제 상태와 영구 삭제 계약](records/issue-404-delete-state-permanent-delete-contract-2026-07-05.md)을 따른다.

회원 자산 알림은 자산 거래나 쿠폰 상태 변경이 성공한 뒤에만 생성한다. 회원 보안 알림은 이메일 인증 완료, 비밀번호 변경/재설정 완료, 2차 인증 변경, OAuth 연결/해제처럼 계정 보안 작업이 성공한 뒤에만 생성한다. 알림 모듈이 비활성화되어 있거나 알림 템플릿이 없으면 no-op으로 처리하고, 포인트/적립금/예치금 원장 처리나 쿠폰 지급/사용/수동 환불 처리와 회원 보안 작업 자체를 실패시키지 않는다. 포인트/적립금 만료 소비 매핑처럼 특정 회원 계정 ID에 연결되는 자산 보조 기록은 해당 모듈의 개인정보 사본 제공에 포함한다. 외부 채널은 이벤트 템플릿 또는 포인트/적립금/예치금/쿠폰 알림/메일 관리 화면의 케이스별 알림 채널과 회원 보안 알림 채널에 따라 발송 작업 queue를 만들며, 환전 알림도 각 자산 모듈의 케이스별 채널 설정으로 저장한다. 기본 자산 알림과 회원 보안 알림은 사이트 알림으로 둔다. 포인트 회원 알림 케이스의 기본값은 사용 안 함이고, 적립금/예치금/쿠폰 회원 알림 케이스는 기존 동작 보존을 위해 기본값을 사용으로 둔다.

회원 대상 외부 push endpoint는 원문을 delivery나 개인정보 export에 복사하지 않는다. 알림 모듈은 Telegram chat ID와 Slack/Discord 개인 webhook URL 같은 endpoint 원문을 app key 기반 암호문과 fingerprint로 분리해 저장하고, delivery에는 `endpoint:{id}` 내부 참조만 남긴다. Slack/Discord webhook URL은 전송 권한을 가진 credential로 취급한다. 가역 비밀값 암호화와 fingerprint는 코어의 `sr_secret_at_rest_*()` helper를 사용하며, helper는 app key에 바인딩된 `sr2` envelope만 만들고 app key가 없으면 fallback key 없이 실패한다. 회원의 외부 endpoint 연결과 해제는 `/account/notifications`에서 현재 비밀번호 재확인과 CSRF를 통과해야 하며, 성공 시 회원 인증 로그, 감사 로그, 사이트 보안 알림을 남긴다. 같은 provider/endpoint fingerprint를 다른 계정에 중복 연결할 수 없고, 계정별 provider 활성 endpoint는 5개를 넘길 수 없다. runner는 전송 직전에 endpoint 소유 계정, 활성 상태, 개인 수신처 타입, 알림 audience/account, provider 설정과 복호화 가능 여부를 다시 확인하며, 연결 해제나 복호화 실패처럼 provider 문제가 아닌 경우에는 재시도하지 않고 delivery를 취소한다. 외부 push payload는 사이트 알림 본문 전체를 복사하지 않고 제목, 짧은 안내, 내부 링크만 보낸다. 회원이 endpoint를 해제하거나 개인정보 cleanup이 실행되면 endpoint row는 보존 식별자만 남기고 `endpoint_ciphertext`를 비운 disabled 상태로 전환한다.

회원 정보수정 화면인 `/account`는 로그인 세션만으로 본문을 보여주지 않는다. GET 진입 시 현재 비밀번호 재확인을 요구하고, 확인 직후 한 번의 화면 진입을 허용한 뒤 그 화면에서 생성한 nonce가 있는 저장 POST만 처리한다. 재확인 실패는 회원 재인증 rate limit과 인증 로그에 포함한다.

회원 로그인 세션은 member 모듈 설정 `session_lifetime_seconds`가 정하는 활동 기준 유효시간을 따른다. 기본값은 86400초이고, 관리자 UI는 1800~2592000초 범위를 서버에서 검증한다. 로그인 또는 세션 회전 시 `sr_member_sessions.expires_at`을 저장하고, 유효 기간 안에 페이지 이동이나 새로고침이 있으면 `last_seen_at`과 `expires_at`을 현재 활동 시점 기준으로 다시 연장한다. 저장된 `expires_at`이 이미 지난 세션은 활동으로 되살리지 않고 무효 처리하며, cleanup은 만료된 row와 오래된 revoked row를 삭제한다. 설정 helper가 없거나 설정 조회만 실패하면 86400초로 후퇴하되, 세션 row 조회 실패는 로그인 세션을 무효로 처리한다.

회원 2차 인증은 로그인 완료 지점 앞의 gate로 다룬다. 비밀번호 로그인, 가입 직후 자동 로그인, OAuth 기존 계정 로그인, OAuth completion 자동 로그인은 모두 `sr_member_login_or_start_mfa()`를 거친다. 운영자 회원 설정의 `mfa_login_mode`(`required`, `optional`, `disabled`)와 `mfa_login_providers_json`이 로그인 MFA의 상위 gate이며, 새 설치 기본 정책은 `disabled`다. 새 설치의 provider 후보는 이메일 인증 코드와 인증 앱 OTP지만, 정책이 꺼져 있으면 로그인 MFA와 신규 TOTP 등록은 중지된다. `optional`은 회원이 허용 provider의 factor를 활성화했거나 이메일 코드처럼 계정에 바로 적용할 수 있는 provider가 있는 경우에만 로그인 challenge를 시작하고, `required`는 허용 provider가 있는 회원에게 로그인 MFA를 강제하며 등록형 factor 해제를 막는다. `required`에서 인증 앱 OTP처럼 회원별 등록이 필요한 provider가 허용되어 있는데 해당 회원의 활성 factor가 없으면, 로그인 세션 생성 직후 또는 보호 화면 진입 시 `/mypage/security`로 보내 등록을 완료하게 한다. 이메일 provider는 계정 이메일로 짧은 만료 코드를 발송하고, 세션 challenge에는 코드 원문 대신 account id와 코드에 대한 HMAC hash 및 만료시각만 저장한다. 기본 provider는 `member-mfa-providers.php` 계약의 `email`과 `totp`이며, SMS나 다른 OTP 방식은 같은 계약 파일을 제공하는 모듈이 활성화되면 운영자 허용 목록에 들어올 수 있다. 활성 TOTP factor는 `sr_member_mfa_factors`에서 조회하고, pending challenge는 `sr_account_id`와 분리된 세션 값으로만 저장한다. `/mypage/security`는 운영자가 TOTP 로그인을 허용한 경우에만 비밀번호가 있는 계정에서 현재 비밀번호 재확인을 통과한 뒤 pending TOTP factor를 만들고, 등록용 QR 이미지, 수동 입력 secret, `otpauth://` URI를 1회 flash로 보여준다. 사용자가 인증 앱의 첫 코드를 제출하면 pending factor를 active로 전환하고, 같은 time step 코드를 즉시 로그인에 재사용할 수 없도록 `last_used_step`을 함께 기록한다. 이 활성화 트랜잭션은 기존 미사용 백업 코드를 폐기하고 새 백업 코드 묶음을 `sr_hmac_hash()` 기반 hash로 저장하며, 원문 백업 코드는 session flash로 한 번만 보여준다. `/login/mfa` POST는 challenge 생성 시 허용된 provider와 현재 운영자 허용 provider를 다시 교차 확인한 뒤, 이메일 코드, app key 기반 secret-at-rest 암호문을 복호화한 TOTP 코드, 또는 미사용 백업 코드를 검증한 뒤에만 `sr_member_login()`을 호출한다. `/mypage/security`의 백업 코드 재발급과 2차 인증 해제는 CSRF와 재인증을 요구한다. 비밀번호가 있는 계정은 현재 비밀번호를 확인하고, 비밀번호가 없는 OAuth-only 계정은 현재 TOTP 코드 또는 미사용 백업 코드로 재확인한다. 백업 코드 재발급은 기존 미사용 코드를 revoked로 전환하고 새 코드를 1회 flash로 보여주며, 2차 인증 해제는 활성/pending TOTP factor와 남은 미사용 백업 코드를 닫는다. 단, `required` 정책에서는 회원이 2차 인증을 해제할 수 없다. TOTP는 `last_used_step` 조건부 업데이트로 같은 time step 재사용을 막고, 백업 코드는 `status = 'unused'` 조건부 업데이트로 재사용을 막는다. MFA factor secret ciphertext, fingerprint, recovery code hash는 개인정보 사본 제공에 포함하지 않고, factor 상태/표시 메타데이터와 recovery code 상태별 개수만 제공한다. 탈퇴와 관리자 withdrawn/anonymized 전환은 다른 모듈 cleanup 계약을 실행하기 전에 회원 모듈 소유 MFA factor와 recovery code row를 직접 삭제한다. 관리자 차단(`suspended`)은 복구 가능한 이용 제한이므로 소셜 로그인 연결을 유지하지만 세션은 폐기한다. 관리자 탈퇴(`withdrawn`)와 익명화(`anonymized`)는 세션, 프로필, MFA, 최신 동의를 정리하고 설치된 모듈의 `privacy-cleanup.php`를 실행한다. 관리자 탈퇴는 자동 익명화를 예약하거나 실행하지 않으며, 익명화가 필요하면 운영자가 별도 `anonymized` 전환을 수동으로 실행한다. 탈퇴/익명화 회원은 회원 그룹 규칙 재평가 대상에서 제외한다. 관리자 화면은 전환 확인 단계에서 회원의 현재 보유 자산을 경고로 보여주지만 본인 탈퇴의 자산 정산 요청을 대신 실행하지 않는다. 전환 성공 후 보유 자산이 있거나 조회가 실패한 경우에는 계정 ID, 공개 해시, 자산 요약과 기존 자산 관리자 화면 후속 조회 링크를 1회 안내한다. `member_oauth` cleanup은 연결 row의 `revoked_at`을 채우고 이메일/이름 스냅샷을 비워 탈퇴·익명화 계정의 소셜 로그인을 해제한다.

본인확인은 선택 모듈 `identity_verification`이 소유한다. 코어와 `member` 테이블은 실명, 휴대폰, CI/DI, 본인확인 상태 컬럼을 갖지 않고, provider 설정과 외부 응답 원문도 보관하지 않는다. attempt의 state/nonce는 원문 대신 HMAC hash로 저장하며, return/callback은 저장된 state hash와 provider별 거래 참조를 대조한다. 성공 결과는 `sr_identity_verification_results`에 최소 요약과 CI/DI/이름/휴대폰 HMAC hash만 남기고, 계정 연결은 `sr_identity_verification_links`에서 purpose별로 관리한다. 회원가입 입력 자동 채움에 필요한 이름/휴대폰 원문은 DB에 저장하지 않고 본인확인 직후 브라우저 `sessionStorage`와 첫 가입 화면 렌더링용 PHP 세션 스냅샷으로만 전달한다. 이 PHP 세션 스냅샷은 state token HMAC key 아래에 짧게 보관하고 읽는 즉시 제거하며, 가입 완료 판정은 PHP 세션이 아니라 현재 가입 폼의 짧은 return token을 `sr_identity_verification_results`와 대조해 결정한다. 회원 프로필의 기본 휴대폰 번호 항목은 `member` 테이블 컬럼이 아니라 운영자 정의 프로필 값으로 저장되며, 본인확인 완료 시 해당 항목이 있으면 인증된 휴대폰 번호를 채우고 필수/선택 여부는 프로필 항목 설정을 따른다. 생년월일과 성인 여부는 본인확인 환경설정의 생년월일 사용이 켜진 경우에만 가입 본인확인 결과로 채우고 잠그며, 성인 열람/참여 정책은 이 설정이 켜져 있고 해당 성인 purpose를 처리할 provider가 준비된 경우에만 관리자 설정으로 저장할 수 있다. 회원, 커뮤니티, 콘텐츠, 퀴즈, 설문, 적립금, 예치금, 자산 환전의 관리자 본인확인 요구 설정은 본인확인 사용 여부와 목적별 provider 준비 상태를 포함하는 `sr_identity_verification_available($pdo, $purpose)` 판정을 따라 비활성화된다. 가입 화면은 인증 완료 URL의 token 파라미터를 즉시 주소창에서 제거하므로 새로고침이나 `/register` 재진입은 본인확인 완료 상태를 유지하지 않는다. 계정 보안, 로그인 2차 인증, 탈퇴처럼 이미 로그인한 계정 작업은 목적별 PHP 세션 결과 helper를 사용할 수 있다. 제출 시에는 저장된 HMAC hash와 다시 대조한다. 같은 CI가 여러 활성 계정에 연결되지 않도록 `sr_identity_verification_identity_locks`에 CI HMAC hash unique 락을 두며, 회원가입은 계정 생성 트랜잭션 안에서 락 점유와 결과 연결까지 끝나야 성공한다. CI/DI/이름/휴대폰 hash 값은 중복 비교와 내부 감사 경계에서만 쓰고 관리자 화면과 개인정보 export에는 원문 hash를 표시하지 않는다. KCP와 KG이니시스 provider는 HTTPS endpoint와 provider 암복호화/결과조회 절차를 사용해야 하며, provider 라이브러리나 상점 설정이 없으면 성공 처리하지 않는다.

관리자 운영 알림은 회원 사이트 알림과 별도 테이블에 저장하고 관리자 shell과 `/admin/admin-notifications`에서만 노출한다. action URL은 `/admin/...` 내부 상대 경로만 허용하며, 헤더 요약과 목록 조회, 상태 변경 POST는 알림에 저장된 권한 path/action을 현재 관리자 권한으로 다시 검증한다. 알림 모듈이 비활성화되어 있으면 신고, 신청, 개인정보 요청 같은 원래 업무 처리는 실패하지 않는다. 보존 정리는 열린 운영 알림을 삭제하지 않고, 처리됨/보관됨 상태만 알림 보관일 기준으로 읽음/확인 기록과 함께 정리한다.

적립금 출금 신청과 예치금 환불 신청은 회원 공개 POST에서만 접수하며, CSRF와 로그인 확인을 거친다. 적립금 출금 신청은 적립금 환경설정에서 출금 신청을 사용하도록 켠 뒤 지정한 회원 그룹 중 하나에 속한 회원만 가능하고, 예치금 환불 신청은 예치금 환경설정에서 환불 신청을 사용하도록 켠 뒤 지정한 회원 그룹 중 하나에 속한 회원만 가능하다. 지정 그룹이 없으면 회원이 출금 또는 환불 신청을 할 수 없고, 신청 사용을 끄면 회원 화면 폼과 직접 POST를 모두 막는다. 신청 계좌 정보는 각 자산 모듈의 신청 테이블에 보관하고 개인정보 사본 제공에 포함한다. 대기 중인 신청 금액은 신청 가능액에서 제외하고, 관리자 완료 처리 시 원장 차감을 같은 DB transaction 안에서 다시 검증한다. 관리자는 완료 또는 거부 시 처리 메모를 남겨야 하며, 완료 처리만 원장 `withdraw` 거래를 만든다. 개별 거부는 별도 모달에서 거부 사유를 받아 처리한다. 관리자 일괄처리는 현재 필터와 검색 조건에 맞는 대기 신청만 서버에서 다시 조회해 처리하고, 한 번에 100건을 초과하면 거부한다.

쿠폰·이용권은 회원에게 귀속되는 권리성 자산이다. 쿠폰 종류는 쿠폰 모듈이 소유하고, 지급/사용/수동 환불/탈퇴 상태 변경 기록은 쿠폰 모듈의 개인정보 사본 제공에 포함한다. 콘텐츠와 커뮤니티는 쿠폰 테이블에 직접 접근하지 않고 helper를 통해 대상 열람권 사용 가능 여부와 사용 처리를 요청한다.

## 2. 세 층의 기준선

산란은 운영·보안 기준선을 한 helper에만 맡기지 않는다.

```text
1. helper
   - CSRF, 로그인, 관리자 권한, redirect, 오류 응답, 감사 로그 같은 공통 도구 제공

2. 정적 검사
   - .tools/bin/check.php와 세부 검사 도구가 action 파일의 누락과 위험한 패턴 확인

3. dispatch contract
   - index.php가 action include 전후로 요청 계약을 만들고 런타임에서 호출 누락 확인
```

이 구조는 프레임워크식 middleware 체인을 만들기 위한 것이 아니다. action 파일은 여전히 절차형 PHP 파일이고, 요청 흐름은 `index.php`, 모듈 `paths.php`, action 파일을 열어 추적한다.

세션, CSRF, 요청 contract, rate limit, token HMAC, 민감정보 마스킹처럼 산란이 직접 제공하는 보안 컴포넌트의 구현 위치와 자동 점검 연결은 코드와 관련 GitHub 이슈에서 유지한다. 이는 감사를 통과했다는 증명서가 아니라, 자작 기준선이 어떤 파일과 check에 걸려 있는지 보이게 하기 위한 관리 방식이다.

## 3. 요청 흐름과 contract

설치 후 일반 요청은 다음 흐름을 따른다.

```text
index.php
-> core/request-bootstrap.php의 요청 준비 함수 호출
-> sr_request_method(), sr_request_path()로 method/path 정규화
-> 설치/사이트 설정/session/locale 로드와 자동 정리, 점검 모드 확인
-> 회원 전용 모드가 켜져 있고 비로그인 요청이면 공개 route 접근 정책 확인
-> path가 / 이고 site.home_path가 /가 아니며 현재 사용할 수 있는 내부 경로이면 redirect
-> 초기화면 경로를 사용할 수 없으면 public layout/theme 기본 홈페이지 include
-> GET /ui-kit이면 public layout의 ui_kit view를 include
-> GET /content/ui-kit, /community/ui-kit, /quiz/ui-kit, /survey/ui-kit이면 해당 모듈이 활성 상태인지 확인한 뒤 모듈 UI kit action include
-> 활성 모듈의 paths.php 배열 읽기
-> METHOD /path와 일치하는 action 파일 검증
-> 회원 전용 모드가 켜져 있으면 실제 match된 공개 route 접근 정책 확인
-> sr_start_request_contract(...)
-> action include
-> sr_enforce_request_contract('after_action')
```

`core/request-bootstrap.php`는 보안 검사를 우회하는 숨은 부팅 지점이 아니라, `index.php`가 호출 순서를 드러내는 요청 준비 함수 모음이다.

contract는 현재 요청의 method, 정규화된 path, module key, action 파일, 관리자 요청 여부를 저장한다.

런타임 확인 기준:

- `POST` action은 `sr_require_csrf()`를 호출해야 한다.
- `/admin`과 `/admin/...` action은 `sr_member_require_login()`을 호출해야 한다.
- `/admin`과 `/admin/...` action은 `sr_admin_require_permission()` 또는 `sr_admin_require_owner()`를 호출해야 하며, 관리자 내부 리다이렉트도 이 검사를 통과한 뒤 실행해야 한다.
- 새로 만들기/수정 폼처럼 상태 변경으로 이어지는 관리자 화면은 GET 진입 시에도 해당 메뉴의 `edit` 권한을 요구한다.
- 인증, 권한, CSRF guard가 요청을 막은 경우에는 의도된 차단으로 기록한다.
- 검사 누락 상태로 action이 끝나거나 응답 종료 지점에 도달하면 contract 위반으로 처리한다.

금액성 자산 차감, 쿠폰 사용, 접근권 생성처럼 회원 잔액이나 권리 상태를 바꾸는 공개 요청은 `GET`에서 실행하지 않는다. 콘텐츠 유료 열람, 콘텐츠 파일 다운로드, 커뮤니티 유료 열람, 커뮤니티 첨부 다운로드는 `GET`에서 로그인과 기존 접근권 또는 1회성 확인 세션만 확인하고, 실제 차감과 접근권 기록은 CSRF가 포함된 `POST` 확인 요청에서만 처리한다.

회원 전용 모드는 `site.member_only_enabled` 사이트 설정으로 켠다. 점검 모드가 켜져 있으면 로그인 완료와 관리자 진입에 필요한 `/login`, `/login/mfa`, `/logout`, `/admin` 계열 route, 관리자 shell에서 쓰는 `/logo-manager/image` 로고 표시 파일을 제외하고 점검 503이 우선한다. 회원 전용 guard는 `/`, `/ui-kit`, `index.php`가 직접 처리하는 활성 모듈 UI kit, 활성 모듈 `paths.php`에서 실제 match된 공개 서비스 route에만 적용해 없는 경로나 비활성 모듈 경로를 로그인 화면으로 바꾸지 않는다. 비로그인 `GET`/`HEAD` 공개 화면은 `/login?next=...`로 redirect하고, 공개 서비스 모듈의 `POST`, 파일, 다운로드, 배너 클릭 같은 상태 변경 또는 비HTML endpoint는 403으로 막는다. `/login`, `/login/mfa`, `/register`, 비밀번호 재설정, 이메일 인증, `POST /logout`, 관리자와 계정 route, 정적 asset, 로고/배너/SEO 이미지 같은 공개 표시 파일은 예외로 둔다. 회원 전용 모드에서 `robots.txt`는 `Disallow: /`, `sitemap.xml`은 빈 urlset을 반환한다.

`sr_member_require_login()`은 비로그인 요청을 `/login`으로 보낼 때 현재 내부 경로와 쿼리스트링을 `next` 값으로 전달한다. 로그인 action은 명시적인 안전한 내부 `next` 값이나 같은 호스트 referrer를 사용할 수 있으면 로그인 성공 후 그 위치로 보내고, 사용할 수 없거나 `/login`, `/login/mfa`, `/logout` 같은 인증 흐름 경로이면 `/`로 보낸다. `next`는 내부 상대 URL만 허용하고 encoded slash/backslash, 제어문자, dot segment를 포함한 path는 기본 `/`로 접는다.

비밀번호 로그인, 가입 직후 자동 로그인, OAuth 기존 계정 로그인, OAuth completion 자동 로그인은 모두 `sr_member_login()`을 직접 호출하기 전에 `sr_member_login_or_start_mfa()`를 통과한다. 2차 인증 challenge는 `sr_account_id`와 분리된 `sr_member_mfa_challenge` 세션 값에 account id, 1차 인증 방식, 허용 provider 목록, 만료 시각, 재검증된 next path를 저장하며, 검증 전에는 `sr_account_id`나 member session token을 만들지 않는다. 활성 TOTP factor 판정과 로그인 MFA TOTP 검증은 `sr_member_mfa_factors`를 기준으로 동작하며, 검증 성공 뒤 challenge에 저장된 next path를 다시 `sr_member_safe_next_path()`로 접어 redirect한다. TOTP 등록은 `/mypage/security`의 pending factor 생성과 첫 코드 활성화로 처리하고, 등록용 QR 이미지와 수동 secret/URI를 한 번만 표시하며, 활성화 직후 백업 코드를 생성해 한 번만 표시한다. `/login/mfa`는 TOTP code와 백업 코드 소비를 모두 처리한다. 계정 보안 화면의 백업 코드 재발급과 2차 인증 해제는 현재 비밀번호 또는 OAuth-only 계정의 현재 TOTP/백업 코드 재확인 뒤 PRG로 처리하되, 운영자 `required` 정책에서는 회원 해제를 거부한다.

contract 위반은 낮은 층에서 직접 로그를 남기고 평문 500 응답으로 종료한다. 이 실패 처리 안에서는 `sr_render_error()`나 `sr_redirect()` 같은 상위 helper를 다시 호출하지 않는다.

`sr_render_error()`는 원본 예외를 먼저 로그에 남긴 뒤 request contract를 검사하고 응답 상태를 설정한다. 관리자 레이아웃처럼 일부 출력이 이미 시작된 뒤 예외가 발생한 경우에도 `http_response_code()` 경고가 원본 예외 로그를 가리지 않아야 한다. 404, 500 같은 시스템 에러 페이지는 공개 레이아웃을 사용하지 않고 사이트명, 공개 메뉴, 카피라이트 없이 상태와 안내만 표시한다.

## 4. 허용된 응답 종료 지점

action 파일에서 직접 `exit` 또는 `die`를 호출하지 않는다. 응답을 끝내야 한다면 다음 helper 중 하나를 사용한다.

```text
sr_redirect()
sr_render_error()
sr_finish_response()
```

`header('Location: ...')`도 action에서 직접 호출하지 않는다. 내부 redirect는 `sr_redirect()`를 통과해야 안전한 상대 URL 검증과 contract 검사를 함께 받는다. 사용자나 관리자가 입력한 외부 URL로 redirect해야 하는 경우에는 `sr_redirect_external()`을 사용하며, 대상 URL은 public network 검증을 통과해야 한다. S3 public URL이나 presigned URL처럼 서버 설정과 저장소 helper가 만든 URL은 `sr_redirect_trusted_external()`을 사용하되, 대상 origin은 런타임 저장소 설정에서 파생된 allowlist를 통과해야 한다. OAuth authorization endpoint처럼 저장소가 아닌 서버 생성 외부 URL은 호출부가 provider endpoint origin을 명시 allowlist로 넘긴다.

허용되는 예:

```text
header('Content-Type: ...') 같은 allowlisted 응답 메타 제어
http_response_code() 단독 호출
sr_redirect(), sr_render_error(), sr_finish_response()
```

금지되는 예:

```text
exit, die 직접 호출
header('Location: ...') 직접 호출
header($dynamicHeader)처럼 헤더 이름을 동적으로 만드는 호출
http_response_code(...); exit; 패턴
```

정적 검사는 `paths.php`에 등록된 action 파일에서 이 패턴을 확인한다. action에서 직접 호출하는 `header()`는 `Content-Type`, `Content-Length`, `Content-Disposition`, `Cache-Control`, `ETag`, `Last-Modified`, `Pragma`, `X-Content-Type-Options`, `Content-Security-Policy` 같은 응답 메타 헤더 리터럴로 시작해야 하며, redirect는 내부 URL의 `sr_redirect()`, public 외부 URL의 `sr_redirect_external()`, 서버 생성 저장소 URL의 `sr_redirect_trusted_external()` 중 하나를 사용한다. 다운로드 streaming 응답은 직접 `Content-Disposition`과 `Content-Length`를 조립하지 않고 `sr_send_download_headers()`와 `sr_download_content_disposition()`을 사용한다. 이미지와 본문 이미지 proxy streaming 응답은 직접 `Content-Type`, `Content-Length`, `nosniff`, cache header를 반복하지 않고 `sr_send_file_headers()`를 사용한다.

`sr_request_contract_mark()`와 `sr_request_contract_guard_blocked()`는 코어와 공통 guard helper가 사용하는 낮은 층의 함수다. action 파일은 이 함수를 직접 호출하지 않고 `sr_require_csrf()`, `sr_member_require_login()`, `sr_admin_require_permission()`, `sr_admin_require_owner()` 같은 공개 helper를 통과한다. 외부 본인확인 provider가 POST로 호출하는 return/callback은 `sr_identity_verification_require_provider_response()`를 통과한 뒤 state token, provider reference, provider 결과 조회로 검증한다.

## 5. 정규화된 path 기준

인증, 권한, 요청 계약 판단은 정규화된 request key를 기준으로 한다.

```text
$method = sr_request_method();
$path = sr_request_path();
$routeKey = $method . ' ' . $path;
```

action이나 helper가 권한 판단을 위해 `$_SERVER['REQUEST_URI']` 원문을 직접 해석하지 않는다. 공유호스팅 fallback처럼 URL 표현을 추가로 지원하더라도, 최종 권한 판단은 같은 정규화된 method/path 값으로 수렴해야 한다.

## 6. paths.php는 무엇이고 무엇이 아닌가

`paths.php`는 URL 요청을 action 파일로 연결하는 단순 배열이다. 산란은 요청 매핑을 사용하지만, 프레임워크식 라우팅 시스템으로 확장하지 않는다.

| 항목 | 산란 기준 |
| --- | --- |
| 등록 API | `sr_route()` 같은 등록 함수 없음 |
| 컨트롤러 클래스 | 없음 |
| 자동 스캔/리플렉션 | 없음 |
| 서비스 프로바이더 | 없음 |
| middleware 체인 | 없음 |
| 라우트 모델 바인딩 | 없음 |
| 요청 흐름 | `index.php`와 `paths.php`, action include로 추적 |

코어는 매핑 파일을 읽고 안전한 action 파일인지 검증한다. action 안에서 어떤 데이터를 읽고, 어떤 정책을 적용하고, 어떤 view를 include할지는 모듈이 명시적으로 작성한다.

## 7. 배포 보호와의 관계

dispatch contract는 애플리케이션 요청 흐름 안의 기준선이다. `config/`, `storage/`, `database/`, `core/`, `modules/`, `docs/`, `.tools/` 같은 내부 파일이 웹에서 직접 열리지 않도록 막는 배포 보호를 대체하지 않는다.

운영 환경에서는 루트 `index.php`만 공개 진입점으로 사용해야 한다. 이 조건을 만족하지 못하는 호스팅에는 운영 설치하지 않는다.
