# 개인정보 계약 매트릭스

이 문서는 번들 모듈이 회원 귀속 데이터에 대해 어떤 개인정보 계약을 가져야 하는지 고정한다. 목적은 모든 모듈에 무조건 `privacy-cleanup.php`를 붙이는 것이 아니라, 사본 제공 대상, 탈퇴/익명화 정리 대상, 운영 증빙 보관 대상을 분리해 누락을 보이게 만드는 것이다.

`account_id`, `author_account_id`, `created_by_account_id`, `processed_by_account_id`, `handled_by_account_id`처럼 회원 계정과 연결되는 컬럼을 설치 SQL 또는 update SQL 기준으로 추가하면 이 문서를 먼저 갱신하고 `.tools/bin/check-privacy-contract-matrix.php`를 통과시켜야 한다.

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
| `admin` | `operational_retained` | 없음 | 없음 | 관리자 권한과 감사성 운영 데이터는 회원 계정과 연결될 수 있으나, 현재는 관리자 운영 보존 데이터로 분류한다. |
| `antispam` | `no_member_personal_data` | 없음 | 없음 | 자동등록방지 설정, 산술 challenge 세션, 외부 provider 검증 요청만 다루며 현재 설치 SQL 또는 update SQL 기준 회원 귀속 개인정보를 저장하지 않는다. |
| `antispam_captcha_providers` | `no_member_personal_data` | 없음 | 없음 | Turnstile, hCaptcha, reCAPTCHA provider 계약만 제공하며 설정 저장과 검증 정책은 `antispam` 모듈이 소유한다. |
| `asset_exchange` | `export_retained` | 제공 | 없음 | 회원별 환전 이력과 실행자 메타데이터는 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `asset_ledger` | `no_member_personal_data` | 없음 | 없음 | 공통 원장 점검 helper와 화면만 제공하고 자기 회원 귀속 테이블을 만들지 않는다. |
| `banner` | `no_member_personal_data` | 없음 | 없음 | 배너 설정과 노출 정책은 현재 회원 귀속 데이터가 아니다. |
| `ckeditor` | `no_member_personal_data` | 없음 | 없음 | 에디터 asset과 설정만 제공하며 본문 데이터는 화면 소유 모듈이 저장한다. |
| `community` | `export_cleanup` | 제공 | 제공 | 게시글, 댓글, 스크랩, 쪽지, 신고, 동의 증적, 접근권, 보상 로그 등 회원 활동 데이터와 IP/UA hash를 가진다. |
| `content` | `export_cleanup` | 제공 | 제공 | 작성자, 신청자, 댓글, 유료 접근권, 다운로드 로그, 자산 처리 로그 등 회원 활동 데이터를 가진다. |
| `coupon` | `export_retained` | 제공 | 없음 | 회원 쿠폰 지급, 사용, 환불 기록은 권리성 자산과 운영 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `deposit` | `export_retained` | 제공 | 없음 | 예치금 잔액, 원장, 환불 신청 계좌 정보는 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `embed_manager` | `operational_retained` | 없음 | 없음 | 본문 참조의 작성자 메타데이터를 보관한다. 본문 자체 개인정보는 소유 모듈의 export/cleanup 책임이다. |
| `logo_manager` | `operational_retained` | 없음 | 없음 | 로고 운영 메타데이터의 작성자 계정 연결은 운영 보존 데이터로 분류한다. |
| `member` | `export_owner` | 제공 | 소비 | 계정, 인증, 프로필, 동의, 그룹 멤버십을 소유하고 탈퇴/익명화 시 설치 모듈의 cleanup 계약을 실행한다. |
| `notification` | `export_retained` | 제공 | 없음 | 회원 알림과 읽음 상태는 사본 제공 대상이다. 운영 알림과 발송 이력은 보존 정책으로 다룬다. |
| `point` | `export_retained` | 제공 | 없음 | 포인트 잔액, 원장, 만료 소비 매핑은 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `popup_layer` | `no_member_personal_data` | 없음 | 없음 | 팝업 설정과 노출 정책은 현재 회원 귀속 데이터가 아니다. |
| `privacy` | `coordinator_direct` | 소비 | 없음 | 관리자 전용 개인정보 대응 기록과 사본 제공을 보조한다. `sr_privacy_export_data()`가 대응 기록을 직접 포함하고 다른 모듈 export 계약을 수집한다. |
| `quiz` | `export_cleanup` | 제공 | 제공 | 응시, 답안 snapshot, 결과, 댓글, 보상 grant, IP/UA hash를 가진다. |
| `reaction` | `export_cleanup` | 제공 | 제공 | 계정별 target reaction 원장을 가지며, 탈퇴/익명화 시 해당 계정의 reaction record를 삭제한다. |
| `reward` | `export_retained` | 제공 | 없음 | 적립금 잔액, 원장, 출금 신청 계좌 정보는 금액성 증빙으로 사본 제공 대상이며 보관 대상이다. |
| `seo` | `no_member_personal_data` | 없음 | 없음 | SEO 설정과 sitemap 정책은 현재 회원 귀속 데이터가 아니다. |
| `site_menu` | `no_member_personal_data` | 없음 | 없음 | 메뉴 구조는 현재 회원 귀속 데이터가 아니다. |
| `survey` | `export_cleanup` | 제공 | 제공 | 설문 응답, 답변, 댓글, 보상 grant, IP/UA hash를 가진다. |

## 보강 기준

- 새 번들 모듈을 추가하면 위 표에 먼저 상태를 추가한다.
- 게시글, 댓글, 응시, 응답처럼 사용자가 직접 제출하는 데이터의 작성자/접속/동의 보관 기준은 [이슈 #328 설계 기록](records/issue-328-author-submission-data-design-2026-06-13.md)의 매트릭스를 함께 갱신한다. 비회원 작성을 열거나 게시판별 추가 입력 항목을 저장하면 계정 연결 개인정보와 비회원 제출 증적의 export/cleanup 또는 보관 기간 cleanup 책임을 분리해 문서화한다.
- `export_cleanup` 모듈은 `module.php`의 `contracts.provides`에 `privacy-export.php`와 `privacy-cleanup.php`를 모두 선언하고 실제 파일을 둔다. `.tools/bin/check-privacy-contract-matrix.php`는 계약 파일을 include해 `privacy-export.php`가 배열 또는 `(PDO $pdo, int $accountId): array` callable을 반환하고, `privacy-cleanup.php`가 최소 `(PDO $pdo, int $accountId): array` callable을 반환하는지 확인한다.
- `.tools/bin/check-privacy-contract-matrix.php`는 `install.sql`뿐 아니라 `updates/*.sql`도 함께 훑어 `*_account_id` 계열 컬럼이나 참조가 생겼는데 매트릭스 상태가 `no_member_personal_data`로 남는 경우를 차단한다.
- `.tools/bin/check-privacy-export-runtime.php`는 SQLite fixture로 `quiz`, `survey`, `content`, `community` export 계약과 `asset_exchange`, `coupon`, `deposit`, `notification`, `point`, `reward` 보존형 export 계약을 실행한다. 퀴즈/설문은 상세 답변과 JSON snapshot 구조화를 확인하고, 콘텐츠/커뮤니티는 접근권, 자산 로그, 다운로드 로그, 작가 신청, 시리즈, 댓글/게시글, 신고/쪽지/스크랩/동의 증적이 대상 계정 기준으로 포함되며 다른 계정 row가 섞이지 않는지 확인한다. 보존형 export fixture는 금액성 원장, 쿠폰 환불, 환불/출금 계좌, 알림 delivery, 포인트 만료 소비 매핑처럼 운영 증빙으로 남기는 고위험 필드가 대상 계정 기준으로 export되고 다른 계정 row가 섞이지 않는지 확인한다.
- `.tools/bin/check-privacy-cleanup-runtime.php`는 SQLite fixture로 `quiz`, `survey`, `content`, `community` cleanup 계약을 실행한다. 퀴즈/설문은 응시/응답, 보상 grant, 댓글 작성자 snapshot을 확인하고, 콘텐츠/커뮤니티는 접근권, 다운로드 로그, 작가 신청, 닉네임, 레벨, 동의 증적, 시리즈 메타데이터, 작성자 snapshot이 대상 계정에 대해서만 익명화되며 다른 계정 row가 유지되는지 확인한다. 이 fixture는 설치 DB에서의 전체 개인정보 smoke를 대체하지 않고, 계약 반환 형태와 핵심 익명화 동작의 회귀를 막는 최소 런타임 증거다.
- `export_retained` 모듈은 `privacy-export.php`를 제공해야 한다. cleanup이 없는 이유는 보존 정책, 회원 자산 정리 계약, 운영 증빙 중 하나로 설명한다.
- `operational_retained` 모듈은 사본 제공 제외가 확정됐다는 뜻이 아니다. 1.0 전 보존 정책 검토와 운영자 안내 대상이다.
- `privacy` 모듈은 `privacy-export.php`를 제공하지 않고 소비한다. 자기 요청 이력은 `sr_privacy_export_data()`에서 직접 포함한다.

## 운영 보존 세부 기준

`operational_retained`와 `export_retained`는 "삭제하지 않는다"는 편의 문구가 아니라, 왜 남기는지와 누가 볼 수 있는지를 문서화해야 하는 상태다. 신규 모듈이 이 상태를 선택하면 아래 표에 보존 사유, 운영자 접근 범위, 1.0 전 검토 항목을 추가한다.

| 모듈 | 보존 사유 | 운영자 접근 범위 | 1.0 전 검토 항목 |
| --- | --- | --- | --- |
| `admin` | 관리자 권한, 감사 로그, 운영 설정 변경의 책임 추적 | 소유자와 권한 있는 관리자 화면, 감사 로그 조회 권한 | 탈퇴 계정 표시명을 공개 식별자 또는 익명 label로 낮출 수 있는지 검토 |
| `embed_manager` | 본문 참조 동기화와 참조 작성자 추적 | 참조를 소유한 콘텐츠/커뮤니티/퀴즈/설문 관리자 화면과 디버그성 운영 점검 | 본문 소유 모듈 cleanup 이후 orphan ref의 작성자 연결을 제거할지 검토 |
| `logo_manager` | 로고 변경 이력과 운영 설정 변경 책임 추적 | 사이트 설정 권한이 있는 관리자 화면 | 단순 설정 변경자 ID를 감사 로그로 충분히 대체할 수 있는지 검토 |

`export_retained` 모듈은 사본 제공 대상이므로 `privacy-export.php`를 유지한다. 탈퇴/익명화 시에는 거래 원장, 쿠폰 권리, 환불/출금 신청, 알림 delivery처럼 운영상 보관해야 하는 행을 임의 삭제하지 않는다. 대신 export 결과, 관리자 화면, 운영 문서에서 보존 사유를 설명하고, 표시명/연락처/계좌정보처럼 재식별성이 높은 필드는 업무 종료 후 별도 마스킹 또는 보존기간 정책을 둘 수 있는지 1.0 전 검토한다.

| 모듈 | 보존 사유 | 고위험 필드/연결 | 1.0 전 검토 항목 |
| --- | --- | --- | --- |
| `asset_exchange` | 환전 실행 증빙, 정정 로그, 중복 환전 방지 | `account_id`, `created_by_account_id`, 환전 묶음 ID와 자산 원장 reference | 완료 환전 정정 후 보존 기간과 관리자 조회 범위 |
| `coupon` | 쿠폰 지급/사용/환불 권리 증빙과 중복 사용 방지 | `account_id`, `issued_by_account_id`, `refunded_by_account_id`, 쿠폰 사용 reference | 만료/환불 완료 쿠폰의 표시명, 메모, 관리자 접근 범위 |
| `deposit` | 현금성 예치금 원장, 환불 신청, 처리 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, 은행명/계좌번호/예금주 | 환불 완료 후 계좌정보 마스킹 시점과 보존 기간 |
| `notification` | 회원 알림 제공 이력, delivery 실패 추적, 운영 알림 읽음 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, delivery destination/metadata | 발송 완료/실패 delivery의 주소 마스킹과 재발송 가능 기간 |
| `point` | 포인트 원장, 만료 소비 매핑, 환불/회수 증빙 | `account_id`, `created_by_account_id`, 만료 source/consume transaction 연결 | 탈퇴 후 회원 식별자 노출 최소화와 오래된 만료 소비 매핑 보존 기간 |
| `reward` | 적립금 원장, 출금 신청, 회수/처리 증빙 | `account_id`, `created_by_account_id`, `processed_by_account_id`, 은행명/계좌번호/예금주 | 출금 완료 후 계좌정보 마스킹 시점과 보존 기간 |
