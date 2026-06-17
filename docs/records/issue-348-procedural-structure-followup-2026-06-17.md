# 이슈 #348 절차형 구조 후속 점검 기록 - 2026-06-17

## 점검 목적

절차형 PHP와 명시적 include 흐름은 유지하되, 커진 action/helper 파일이 요청 흐름 가독성을 낮추는 지점을 후속 작업 후보로 정리했다.

## 현재 큰 파일

2026-06-17 현재 줄 수 기준:

| 파일 | 줄 수 | 성격 | 우선순위 |
| --- | ---: | --- | --- |
| `modules/content/helpers.php` | 4,807 | 콘텐츠 설정, 렌더링, 자산, 관리자 보조 helper가 함께 있음 | 높음 |
| `modules/quiz/helpers.php` | 4,606 | 퀴즈 공개/관리자/보상/스킨 helper가 함께 있음 | 높음 |
| `modules/community/helpers/posts.php` | 3,334 | 게시글 조회, 렌더링, 권한, 상태 helper가 함께 있음 | 높음 |
| `modules/community/helpers/assets.php` | 2,570 | 커뮤니티 자산 정책/실행 helper가 함께 있음 | 중간 |
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

## 후속 후보

1. `modules/community/actions/admin-boards.php`의 게시판 저장 입력 수집/검증/audit payload 문단을 추가로 `sr_community_admin_board_*` helper로 분리할 수 있는지 검토한다.
2. `modules/content/helpers.php`는 공개 렌더링, 관리자 설정/저장, 자산 정책 helper를 파일 단위로 나눌 후보를 먼저 표시한다.
3. `modules/quiz/helpers.php`는 보상/응시/관리자 설정/스킨 helper 경계를 확인한 뒤 분리한다.
4. `modules/community/helpers/posts.php`는 공개 조회/작성 권한/상태 라벨/관리자 보조 helper 경계를 확인한다.
