# #260 관리자 독립 form 조사 및 안내 계획

## 배경

#260은 관리자 등록/수정 페이지 안에 기본 저장과 별도로 실행되는 POST form 또는 모달 form이 함께 노출될 때 저장 범위를 오해할 수 있는지 조사하는 작업이다.

이번 라운드에서는 바로 공통 미저장 변경 경고를 넣지 않는다. 화면별 독립 action의 성격과 기본 저장 form과의 관계를 먼저 확인한 뒤, 위험도가 큰 화면부터 문구, 도움말, 확인문, 배치 조정으로 처리한다.

## 분류 기준

| 분류 | 기준 | 기본 처리 방향 |
| --- | --- | --- |
| 기본 저장 | 등록/수정 화면의 주 데이터 저장 | sticky 저장 버튼과 필수/도움말/서버 검증 정합성 유지 |
| 보조 저장 | 같은 화면에서 별도 도메인 레코드나 하위 설정을 저장 | form/action을 기본 저장과 분리하고, 모달 제목과 도움말에 기본 저장과 별도 동작임을 표시 |
| 삭제/상태 변경 | 삭제, 회수, 상태 변경처럼 즉시 반영되는 작업 | 위험 작업 확인문과 권장 실행 시점 안내 강화 |
| 실행 작업 | 정리 재시도, 동기화, 평가처럼 운영 작업을 실행 | 저장 작업이 아니라 실행 작업임을 버튼/도움말에 표시 |
| lookup/search | 회원/참조 검색처럼 값을 찾는 보조 UI | 서버 POST 저장 form과 별도 표기, 저장 범위 경고 대상에서 제외 |

## 1차 조사표

| 우선 | 화면 | 파일 | form/action | intent 또는 경로 | 분류 | 저장/실행 대상 | 기본 저장과 동시 노출 | 권한/CSRF/검증 | 안내 필요 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 게시판 생성/수정 form | `create`, `update` | 기본 저장 | 게시판 설정, 권한/자산/정책 설정 | 예 | `admin-boards.php` action에서 CSRF, edit 권한, intent 검증 | 기준 form |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 목록/수정 화면 삭제 form | `delete_board` | 삭제/상태 변경 | 게시판과 하위 설정/카테고리 삭제 | 예 | CSRF, delete 권한, 확인문 일부 존재 | 높음. 수정 중 값이 함께 저장되지 않음을 삭제 영역에 명시 |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 저장소 정리 재시도 | `retry_storage_cleanup_failure` | 실행 작업 | 삭제 후 남은 파일 정리 | 목록 영역 중심 | CSRF, delete 권한 | 중간. 저장이 아닌 운영 실행임을 표시 |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 매니저 부여/회수 form | `board_manager_grant`, `board_manager_revoke` | 보조 저장 | 게시판 매니저 관계 | 수정 화면에서 함께 노출 | CSRF, edit 권한, 별도 intent | 높음. 게시판 설정 저장과 별개임을 모달/버튼 주변에 표시 |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 카테고리 생성/수정/삭제 form | `category_create`, `category_update`, `category_delete` | 보조 저장/삭제 | 게시판 카테고리 | 수정 화면에서 함께 노출 | CSRF, edit 권한, 카테고리 검증 | 높음. 카테고리 변경과 게시판 저장 분리 안내 필요 |
| 1 | 게시판 관리 | `modules/community/views/admin-boards.php` | 회원 lookup form | JS 검색 form | lookup/search | 매니저 선택 보조 검색 | 모달 내부 | 검색 action은 edit 권한 | 낮음. 저장 경고 대상 제외 |
| 1 | 사이트 설정 | `modules/admin/views/settings.php` | 공용 아이콘 설정 modal form | `icon_settings` | 보조 저장 | 공용 아이콘 key override | 사이트 설정 form과 같은 화면 | CSRF, edit 권한, 별도 intent | 높음. 모달 저장과 사이트 설정 저장을 실제로 분리하고 범위 안내 필요 |
| 1 | 콘텐츠 관리 | `modules/content/views/admin-contents.php` | 콘텐츠 저장 form | `/admin/content/save` | 기본 저장 | 콘텐츠 본문, 파일, 시리즈, 자산 설정 | 예 | save action에서 CSRF, edit 권한 | 기준 form |
| 1 | 콘텐츠 관리 | `modules/content/views/admin-contents.php` | 콘텐츠 복사 modal form | `/admin/content/copy` | 보조 저장 | 초안 복사본, 선택 시 시리즈 복사 | 수정 화면 sticky action과 목록에 노출 | CSRF, edit 권한 | 높음. 현재 수정 중인 값이 복사되지 않는다는 안내 필요 |
| 1 | 콘텐츠 관리 | `modules/content/views/admin-contents.php` | 콘텐츠 삭제 form | `/admin/content/delete` | 삭제/상태 변경 | 콘텐츠 삭제 | 목록 영역 중심 | CSRF, delete action | 중간. 목록 삭제 안내 확인 필요 |
| 1 | 콘텐츠 그룹 관리 | `modules/content/views/admin-content-groups.php` | 그룹 생성/수정 form | `create_group`, `update_group` | 기본 저장 | 콘텐츠 그룹 설정 | 예 | CSRF, edit 권한, intent 검증 | 기준 form |
| 1 | 콘텐츠 그룹 관리 | `modules/content/views/admin-content-groups.php` | 그룹 삭제 form | `delete_group` | 삭제/상태 변경 | 그룹과 연결 콘텐츠/파일 삭제 | 수정 화면에서 별도 삭제 영역 노출 | CSRF, delete 권한, 확인문 존재 | 낮음. 이번 범위에서는 별도 저장 안내 대상에서 제외 |
| 1 | 콘텐츠 그룹 관리 | `modules/content/views/admin-content-groups.php` | 저장소 정리 재시도 | `retry_storage_cleanup_failure` | 실행 작업 | 삭제 후 남은 파일 정리 | 목록 영역 중심 | CSRF, delete 권한 | 중간. 운영 실행 안내 필요 |
| 2 | 사이트 메뉴 | `modules/site_menu/views/admin-site-menus.php` | 메뉴/항목 저장 modal form, 순서 저장 form | `save_menu`, `save_item`, `save_item_order` | 보조 저장 | 메뉴 정보, 항목 정보, 목록 정렬 | 같은 관리 화면 | CSRF, edit 권한, intent 검증 | 높음. 모달 저장과 순서 적용이 서로의 입력값을 함께 저장하지 않음을 안내 필요 |
| 2 | 회원 그룹 관리 | `modules/member/views/admin-groups.php` | 그룹 저장 form | `save_group` | 기본 저장 | 회원 그룹 | 목록/모달 계열 | CSRF, edit 권한, intent 검증 | 기준 form |
| 2 | 회원 그룹 관리 | `modules/member/views/admin-groups.php` | 수동 배정/해제 form | `grant_manual`, `revoke_manual` | 보조 저장/삭제 | 회원 그룹 membership | 그룹 화면과 함께 노출 | CSRF, edit/delete 권한 | 높음. 그룹 저장과 배정 작업 분리 안내 반영 |
| 2 | 회원 그룹 관리 | `modules/member/views/admin-groups.php` | 규칙 저장/평가 form | `save_rule`, `evaluate_group` | 보조 저장/실행 작업 | 그룹 규칙, 평가 실행 | 규칙 화면 계열 | CSRF, edit 권한 | 높음. 저장된 규칙 기준으로 평가하고 작성 중인 규칙 입력값은 함께 저장하지 않음을 안내 반영 |
| 2 | 커뮤니티 레벨 설정 | `modules/community/views/admin-settings.php` | 레벨 최소 점수 저장/회원 레벨 재계산 | `save_level_definitions`, `recalculate_levels` | 보조 저장/실행 작업 | 레벨 최소 점수, 회원 레벨 재계산 | 같은 레벨 설정 화면 | CSRF, edit 권한, 확인문 | 높음. 재계산이 작성 중인 최소 점수 입력값을 함께 저장하지 않음을 안내 필요 |
| 2 | 관리자 권한 | `modules/admin/views/roles.php` | 권한 추가/회수 form | `add_permission`, `revoke_permission` | 보조 저장/삭제 | 관리자 권한 | 권한 화면에서 모달/인라인 노출 | owner 권한, CSRF | 중간. 기본 저장 form은 없으나 독립 작업 안내 필요 |

## 우선순위

1. 게시판 관리
   - 기본 저장 form과 매니저/카테고리/삭제 form이 같은 수정 화면에 함께 노출된다.
   - 첫 구현 라운드에서 버튼 주변 도움말과 모달 설명을 보강하기 좋다.

2. 콘텐츠 관리
   - 콘텐츠 복사처럼 현재 수정 중인 값과 복사본 저장 범위를 오해할 수 있는 작업은 별도 라운드에서 확인한다.
   - 콘텐츠 그룹 삭제는 이번 범위의 별도 저장 안내 대상에서 제외한다.

3. 회원 그룹/권한 관리
   - 보조 action이 많지만 기본 저장 form과 완전히 같은 문맥인지 화면별로 더 나눠야 한다.

4. 배너/팝업레이어 관리
   - 수정 화면에 복사 버튼이 있지만, 이번 1차 작업의 핵심인 "등록/수정 페이지 안의 하위 설정 별도 저장"과는 성격이 다르다.
   - 이번 구현에서는 제외하고, 복사/삭제 action 안내 정책을 별도 라운드에서 판단한다.

## 구현 원칙

- 전역 미저장 변경 경고는 바로 도입하지 않는다.
- 먼저 보조 action 옆에 "현재 작성 중인 기본 form은 저장되지 않음"을 표시할 수 있는 화면부터 처리한다.
- 기본 저장 form 안에 보조 저장 modal form이 포함되어 있으면 HTML과 서버 intent를 분리해 실제 동작도 독립 저장으로 맞춘다.
- lookup/search form은 저장 경고 대상에서 제외한다.
- 삭제/상태 변경/실행 작업은 확인문과 도움말을 분리한다.
- 서버의 CSRF, 권한, intent 검증은 각 form 단위로 유지한다.

## 다음 구현 라운드 제안

첫 구현 라운드는 `modules/community/views/admin-boards.php`에 한정한다.

- 게시판 매니저/카테고리 modal에 "게시판 기본 설정 저장과 별도로 반영됩니다." 도움말 추가
- 게시판 수정 화면 삭제 영역에 "위 저장 버튼으로 작성 중인 변경사항은 삭제 실행 전에 저장되지 않습니다." 안내 추가

## 1차 구현 반영

2026-06-08 1차 구현에서 다음 안내를 반영했다.

- 게시판 수정 화면의 위험 작업, 관리권한, 카테고리 영역에 기본 게시판 저장과 별도로 반영된다는 도움말을 추가했다.
- 게시판 관리권한 부여 모달과 카테고리 추가/수정 모달에 현재 수정 중인 게시판 입력값이 함께 저장되지 않는다는 안내를 추가했다.
- 사이트 설정 화면의 공용 아이콘 설정 모달을 사이트 설정 form 밖의 독립 form으로 분리하고, 서버 저장 intent도 `icon_settings`로 분리했다.
- 공용 아이콘 설정 모달에 모달 저장 버튼이 공용 아이콘 설정만 저장하고 사이트 설정 form 입력값은 함께 저장하지 않는다는 안내를 추가했다.
- 포인트 수동 만료와 보관 정책 정리 실행처럼 기본 저장 화면 주변에서 즉시 처리되는 작업에는 현재 form 입력값이 함께 저장되지 않는다는 안내를 추가했다.

## 2차 구현 반영

2026-06-08 2차 구현에서 다음 안내를 반영했다.

- 콘텐츠 복사 모달에 복사본은 이미 저장된 원본 콘텐츠 기준으로 만들며, 열려 있는 수정 form 입력값은 함께 저장하거나 복사하지 않는다는 안내를 추가했다.

## 3차 구현 반영

2026-06-08 3차 구현에서 다음 안내를 반영했다.

- 커뮤니티 레벨 설정 화면에 레벨 설정 저장은 최소 점수만 저장하고, 회원 레벨 재계산은 저장과 별도로 실행되며 작성 중인 최소 점수 입력값을 함께 저장하지 않는다는 안내를 추가했다.

## 4차 구현 반영

2026-06-08 4차 구현에서 다음 안내를 반영했다.

- 사이트 메뉴 화면의 메뉴/항목 저장 모달과 순서 적용 form에 각 저장 버튼이 자기 대상만 저장하며 다른 입력값은 함께 저장하지 않는다는 안내를 추가했다.

## 5차 구현 반영

2026-06-08 5차 구현에서 다음 안내를 반영했다.

- 회원 그룹 수동 배정 모달에 회원 그룹 정보 저장과 별도로 회원 배정만 바로 반영하며 열려 있는 그룹 등록/수정 모달 입력값은 함께 저장되지 않는다는 안내를 추가했다.
- 회원 그룹 규칙 평가 모달에 저장된 규칙 기준으로 평가하며 열려 있는 규칙 등록/수정 모달 입력값은 함께 저장되지 않는다는 안내를 추가했다.
- 공통 미저장 변경 JavaScript는 도입하지 않았다.

## 검증 계획

- 문서만 변경하는 라운드는 PHP 자동 검사를 생략할 수 있다.
- PHP/view 변경이 들어가는 구현 라운드부터 `php .tools/bin/check.php`를 실행한다.
- 로컬 서버 실행이 안전하면 `php -S 127.0.0.1:<port> -t .tools/public .tools/bin/dev-router.php` 후 `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php`를 실행한다.

## Wiki 영향

관리자 안내 패턴이 구현되어 Wiki를 함께 갱신했다.

- `관리자-화면-개발-가이드.md`: 기본 저장 form과 독립 POST action이 함께 보일 때 보조 action의 저장 범위를 안내하고, modal form을 기본 form과 실제로 분리하는 기준을 추가했다.
- `관리자-화면별-항목-설명서.md`: 사이트 공용 아이콘 설정, 사이트 메뉴 저장 범위, 회원 그룹 수동 배정/규칙 평가, 콘텐츠 복사, 커뮤니티 레벨 재계산, 게시판 관리권한/카테고리 안내 기준을 반영했다.
