# 이슈 #328 작성 보관 정보와 추가 입력 항목 설계

작성일: 2026-06-13

## 목적

게시글, 댓글, 퀴즈 시도, 설문 응답처럼 사용자가 직접 제출하는 데이터가 어떤 작성자/접속/동의 정보를 보관하는지 현재 구현 기준으로 정리하고, 향후 비회원 작성과 게시판별 추가 입력 항목을 도입할 때의 저장 모델을 고정한다.

이 문서는 스키마 변경이 아니라 설계 기준이다. 실제 구현 이슈에서는 이 기준에 맞춰 설치 SQL, update SQL, 관리자 UI, 공개 UI, privacy export/cleanup, smoke test를 함께 추가한다.

## 현재 보관 정보 매트릭스

| 표면 | 대표 테이블 | 현재 작성 정책 | 작성자 보관 | 접속/동의 보관 | 개인정보 계약 |
| --- | --- | --- | --- | --- | --- |
| 커뮤니티 게시글 | `sr_community_posts` | 게시판 쓰기 정책이 `guest`이면 비회원 작성 가능, 그 외 로그인/권한 필요 | `author_account_id`, `author_public_name_snapshot`, 비회원은 `author_account_id NULL`, `guest_author_name`, `guest_password_hash`, IP/UA hash | 게시판 동의 사용 시 `sr_community_submission_consents`에 동의 snapshot, IP hash, UA hash. 게시판별 추가 입력은 `extra_values_json` snapshot과 `sr_community_post_field_values`에 저장 | community export/cleanup 포함 |
| 커뮤니티 댓글 | `sr_community_comments` | 게시판 댓글 정책이 `guest`이면 비회원 작성 가능, 그 외 로그인/권한 필요 | `author_account_id`, `author_public_name_snapshot`, 비회원은 `author_account_id NULL`, `guest_author_name`, `guest_password_hash`, IP/UA hash | 댓글 대상 동의 사용 시 `sr_community_submission_consents`에 동의 snapshot, IP hash, UA hash | community export/cleanup 포함 |
| 콘텐츠 댓글 | `sr_content_comments` | `/content/comment`에서 로그인 필수 | `author_account_id`, `author_public_name_snapshot` | 댓글별 동의 snapshot 또는 IP/UA hash 테이블 없음 | content export/cleanup 포함 |
| 퀴즈 댓글 | `sr_quiz_comments` | 스키마상 `author_account_id`는 NULL 가능, action은 로그인 필수 | `author_account_id`, `author_public_name_snapshot` | 댓글별 동의 snapshot 또는 IP/UA hash 테이블 없음 | quiz export/cleanup 포함 |
| 설문 댓글 | `sr_survey_comments` | 스키마상 `author_account_id`는 NULL 가능, action은 로그인 필수 | `author_account_id`, `author_public_name_snapshot` | 댓글별 동의 snapshot 또는 IP/UA hash 테이블 없음 | survey export/cleanup 포함 |
| 퀴즈 시도 | `sr_quiz_attempts` | 비회원 응시 가능 구조 | `account_id` NULL 가능, 출처/답변/채점/결과 snapshot | IP hash, UA hash, 시작/제출/채점/보상/만료/취소 시각 | quiz export/cleanup 포함 |
| 설문 응답 | `sr_survey_responses` | `anonymous_allowed`, `login_required`, `consent_required` 정책으로 비회원 응답 가능 구조 | `account_id` NULL 가능, 설문/답변 snapshot | 동의 snapshot JSON, IP hash, UA hash | survey export/cleanup 포함 |

현재 커뮤니티 게시글/댓글은 게시판 쓰기/댓글 정책에 따라 비회원 작성을 허용할 수 있고, 비회원 작성자는 원문 비밀번호/IP/User-Agent 대신 hash와 표시명 snapshot을 저장한다. 콘텐츠/퀴즈/설문 댓글은 작성자 snapshot 중심이고 별도 제출 동의 증적이나 접속 hash 보관 정책이 없다. 퀴즈/설문 댓글의 NULL 가능 작성자 컬럼은 실제 비회원 작성 허용이 아니라 향후 확장 여지로만 해석한다.

## 비회원 작성 저장 모델

비회원 작성은 기본 기능이 아니라 표면별 운영 옵션으로 도입한다. 먼저 커뮤니티 게시글/댓글을 기준 모델로 삼고, 콘텐츠/퀴즈/설문 댓글은 같은 요구가 명확해질 때 각 모듈의 설정과 개인정보 계약을 별도로 설계한다.

비회원 작성 허용 설정은 게시판 또는 대상 모듈 설정에 둔다. 회원 전용 기능과 충돌하지 않도록 읽기/쓰기/댓글 권한 정책에서 `guest`, `member`, `group`, `level`의 의미를 분리하고, 회원 그룹/레벨/보상/알림/멘션/자산 지급처럼 계정이 필요한 기능은 비회원 작성에서 제한한다.

비회원 작성자는 표시명을 필수로 입력하고, 원문 비밀번호는 저장하지 않는다. 수정/삭제 검증에는 password hash 또는 1회성 관리 토큰 hash를 사용한다. 이메일, 전화번호, 홈페이지 URL 같은 연락처는 기본 작성자 모델에 넣지 않고 게시판별 추가 입력 항목으로 둔다.

비회원 작성에도 IP/User-Agent 원문은 저장하지 않고 hash만 저장한다. 보관 목적, 보관 기간, cleanup 기준을 해당 모듈 문서와 개인정보 처리 안내에 명시한다. 커뮤니티 게시판 동의가 켜진 경우 `sr_community_submission_consents.account_id`는 NULL을 허용하고, subject와 action, 동의 문구 snapshot, IP/UA hash만으로 제출 증적을 남길 수 있어야 한다.

관리자 목록과 privacy export/cleanup은 회원 작성과 비회원 작성을 구분해 보여준다. 회원 요청의 사본 제공은 계정 연결 데이터만 대상으로 하되, 비회원 권리 요청을 지원하려면 별도 검증 기준과 검색 키를 먼저 설계한다. 탈퇴/익명화 cleanup은 회원 계정 연결과 작성자 snapshot을 정리하고, 비회원 hash/추가 입력 필드는 보관 기간 기반 cleanup 대상으로 분리한다.

## 게시판별 추가 입력 항목 모델

게시판별 운영 입력은 커뮤니티 모듈이 소유한다. `sr_community_posts`, core 테이블, member 테이블을 넓히지 않고 확장 정의 테이블과 값 테이블로 분리한다.

구현 테이블:

| 테이블 | 목적 | 주요 필드 후보 |
| --- | --- | --- |
| `sr_community_board_field_definitions` | 게시판별 추가 입력 정의 | `board_id`, `field_key`, `label`, `field_type`, `is_required`, `visibility`, `show_on_view`, `show_in_admin`, `sort_order`, `validation_json`, `privacy_purpose`, `export_policy`, `cleanup_policy`, `status`, `created_at`, `updated_at` |
| `sr_community_post_field_values` | 게시글별 추가 입력값 | `post_id`, `field_key`, `label_snapshot`, `field_type_snapshot`, `visibility_snapshot`, `show_on_view_snapshot`, `show_in_admin_snapshot`, `privacy_purpose_snapshot`, `export_policy_snapshot`, `cleanup_policy_snapshot`, `value_text`, `value_json`, `created_at`, `updated_at` |
| `sr_community_comment_field_values` | 댓글별 추가 입력값이 필요할 때의 별도 값 테이블 | `comment_id`, `field_key`, `value_text`, `value_json`, `created_at`, `updated_at` |

`field_key`는 관리용 key이므로 소문자 영문, 숫자, `_`만 허용한다. 공개 slug처럼 hyphen을 허용하는 필드와 구분한다. 필수/조건부 필드는 UI 표시, 브라우저 속성, 서버 POST 검증을 모두 맞추며 서버 검증을 최종 기준으로 둔다.

필드 정의에는 공개 출력, 관리자 목록 표시, 검색/필터 포함, CSV/export 포함, 개인정보 처리 목적, 보관/cleanup 정책을 분리해 저장한다. 잘못된 key, 중복 key, 빈 라벨, 잘못된 유형/정책, 선택지 누락은 조용히 항목을 버리지 않고 게시판/게시판 그룹 저장 오류로 처리한다. 현재 커뮤니티 게시글 목록은 `show_in_admin` snapshot이 켜진 추가 입력값만 관리자 목록에 표시하고, 추가 입력 검색도 값 테이블의 `show_in_admin_snapshot=1` 값만 대상으로 한다. 제출값은 text/select 1000자, textarea 5000자를 넘거나 배열 payload이면 잘라 저장하지 않고 서버에서 거부한다. 개인정보 사본 제공은 `export_policy_snapshot=include` 값 테이블 행과 `export_policy=include` 게시글 snapshot만 포함하며, export 정책이 없던 legacy snapshot은 `include`로 간주한다. cleanup은 `cleanup_policy_snapshot`이 `retain`이 아닌 값 기준으로 처리해 빈 정책이 남은 legacy 값도 익명화한다. 게시글 `extra_values_json` snapshot에는 관리자 표시, export 정책, cleanup 정책을 함께 남기며, cleanup 정책이 없던 legacy snapshot은 `anonymize`로 간주한다.

관리자 게시판 생성/수정 화면은 추가 입력 항목을 보조 목록과 추가/수정 모달로 편집하고, 최종 저장값은 기존 게시판 저장 form의 `extra_fields_json`에 반영한다. 모달의 적용은 화면 입력값만 바꾸며 실제 DB 반영은 게시판 저장 action에서 서버 정규화와 검증을 통과한 뒤 이루어진다.

댓글 추가 입력이 필요해지면 게시글 값 테이블을 재사용하지 않는다. 댓글과 게시글은 권한, 수정/삭제, 공개 범위, 개인정보 요청 맥락이 다르므로 별도 값 테이블을 우선한다. 여러 subject를 하나의 공용 value 테이블에 담는 방식은 인덱스, FK, cleanup 검증이 어려워지는 경우가 많으므로 반복 요구가 쌓인 뒤 검토한다.

## 구현 수용 기준

- DB 명세, 관리자 화면 항목 설명서, 모듈 개발 가이드, 보안/개인정보 가이드, 테스트 가이드 또는 smoke 문서를 변경 범위에 맞게 갱신한다.
- `php .tools/bin/check.php`를 실행한다.
- 스키마를 추가하면 privacy contract matrix와 fixture check를 함께 보강한다.
- 공개 또는 관리자 작성 UI를 추가하면 로컬 PHP built-in server로 HTTP smoke test를 실행한다.
- 비회원 작성 저장/수정/삭제는 CSRF, rate limit, CAPTCHA 또는 자동등록방지 이슈와 연결한다.
- production 데이터가 아닌 local/staging 더미 데이터로 비회원 작성, 동의 snapshot, export/cleanup, 관리자 검색을 확인한다.
