# 이슈 #348 절차형 구조 후속 점검 기록 - 2026-06-17

## 점검 목적

절차형 PHP와 명시적 include 흐름은 유지하되, 커진 action/helper 파일이 요청 흐름 가독성을 낮추는 지점을 후속 작업 후보로 정리했다.

## 현재 큰 파일

2026-06-17 현재 줄 수 기준:

| 파일 | 줄 수 | 성격 | 우선순위 |
| --- | ---: | --- | --- |
| `modules/content/helpers.php` | 3,719 | 콘텐츠 설정, 렌더링, 관리자 보조 helper가 함께 있음. 커버 이미지와 회원 제출/작성자 신청 helper는 별도 파일로 분리함 | 높음 |
| `modules/quiz/helpers.php` | 4,606 | 퀴즈 공개/관리자/보상/스킨 helper가 함께 있음 | 높음 |
| `modules/community/helpers/posts.php` | 3,334 | 게시글 조회, 렌더링, 권한, 상태 helper가 함께 있음 | 높음 |
| `modules/notification/helpers.php` | 2,891 | 발송 상태, 외부 채널, 암호화, push endpoint, 관리자 기록 helper가 함께 있음 | 높음 |
| `modules/content/helpers/assets.php` | 2,483 | 콘텐츠 유료 접근, 권한, 자산 로그, 결제/환불 helper가 함께 있음 | 높음 |
| `modules/community/helpers/assets.php` | 2,477 | 커뮤니티 자산 정책/실행 helper가 함께 있음. 게시자 보상 관리자 조회/필터 helper는 별도 파일로 분리함 | 중간 |
| `modules/survey/helpers.php` | 2,364 | 설문 설정, 공개 조회, 문항/응답, 보상, 내보내기 helper가 함께 있음 | 높음 |
| `modules/community/helpers/boards.php` | 1,990 | 게시판/그룹 설정, 목록 조회, 저장, 표시 row helper가 함께 있음 | 중간 |
| `modules/survey/actions/admin-surveys.php` | 1,663 | 설문 저장 입력, 문항 교체, 보상 정책 저장, 감사 payload가 긴 action에 함께 있음 | 높음 |
| `modules/reaction/helpers.php` | 1,618 | 반응 대상 해석, preset/definition 관리, 기록, 알림 helper가 함께 있음 | 중간 |

## 추가 관심 파일

이슈 원문에서 언급됐지만 현재 1,500줄 기준에는 못 미치는 파일:

| 파일 | 줄 수 | 성격 | 우선순위 |
| --- | ---: | --- | --- |
| `modules/community/actions/admin-boards.php` | 1,253 | 게시판 관리자 입력, 검증, 저장, 감사 로그가 긴 문단으로 이어짐. 화면 row 보강 클로저는 helper로 분리함 | 중간 |
| `modules/community/actions/admin-settings.php` | 726 | 환경설정과 레벨 설정 처리 문단이 큼 | 낮음 |

## 분리 기준

- 숨은 dispatcher, 자동 route 등록, service provider 구조는 도입하지 않는다.
- action 파일에는 로그인/권한/CSRF, `intent` 분기, transaction 경계, redirect/view include가 보여야 한다.
- 입력 수집, 정규화, 검증, 저장 row/settings 배열 구성, 감사 로그 payload 구성은 모듈 helper로 이름을 붙인다.
- 코어 helper로 올리는 기준은 도메인 단어 없이 설명할 수 있는 primitive일 때로 제한한다.
- 도메인 정책을 가진 반복은 각 모듈 helper에 남긴다.

## 이번 처리

- 공개 레이아웃 회원 액션 row를 `member-action-rows.php` 계약으로 분리해 코어가 `reward`, `deposit`, `asset_exchange`의 업무 의미를 직접 알지 않게 했다.
- 사이트 회원전용 모드의 서비스 공개 route 판별을 `member-only-routes.php` 계약으로 분리해 코어가 `content`, `community`, `quiz`, `survey`의 공개 화면 목록을 직접 나열하지 않게 했다.
- `docs/module-guide.md`와 `docs/core-decisions.md`에 큰 관리자 action 분리 기준과 새 계약 파일 심사 기준을 추가했다.
- `modules/community/actions/admin-boards.php` 하단의 화면 표시용 게시판 row 보강 클로저를 `sr_community_admin_prepare_board_row()` helper로 분리해 action 하단이 목록/수정 대상 조회 흐름만 보이도록 줄였다.
- `modules/community/helpers/assets.php`의 게시자 보상 관리자 상태/필터/목록 조회 helper를 `modules/community/helpers/publisher-rewards.php`로 분리했다.
- `modules/content/helpers.php`의 커버 이미지 업로드/저장소/렌더링 helper를 `modules/content/helpers/cover-images.php`로 분리했다.
- `modules/content/helpers.php`의 회원 제출, 작성자 신청/권한, 작성자 보상 helper를 `modules/content/helpers/member-submissions.php`로 분리했다.

## 후속 후보

1. `modules/content/helpers.php`는 공개 렌더링, 관리자 설정/저장, 자산 정책 helper를 파일 단위로 나눌 후보를 먼저 표시한다.
2. `modules/quiz/helpers.php`는 보상/응시/관리자 설정/스킨 helper 경계를 확인한 뒤 분리한다.
3. `modules/community/helpers/posts.php`는 공개 조회/작성 권한/상태 라벨/관리자 보조 helper 경계를 확인한다.
4. `modules/notification/helpers.php`는 외부 발송 채널/secret 암호화/push endpoint/관리자 기록 helper를 나눌 후보를 표시한다.
5. `modules/content/helpers/assets.php`는 접근 권한, 자산 로그, 환불/취소, 관리자 조회 helper를 나눌 후보를 표시한다.
6. `modules/survey/actions/admin-surveys.php`의 저장 입력 수집, 문항 정규화, 보상 정책 row 구성, audit payload 문단을 `sr_survey_admin_*` helper로 분리할 수 있는지 검토한다.
7. `modules/community/actions/admin-boards.php`의 게시판 저장 입력 수집/검증/audit payload 문단을 추가로 `sr_community_admin_board_*` helper로 분리할 수 있는지 검토한다.
