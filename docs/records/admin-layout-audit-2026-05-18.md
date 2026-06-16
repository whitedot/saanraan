# 관리자 화면 레이아웃 점검 기록 - 2026-05-18

## 점검 환경

- 점검 일시: 2026-05-18 11:00 KST
- 접속 URL: `http://127.0.0.1:8087`
- 브라우저: Headless Chrome via Playwright
- 화면 폭: 데스크톱 `1440x1000`, 모바일 `390x900`
- 점검 계정: 임시 owner 계정으로 로그인 후 캡처, 점검 후 계정 삭제
- 증거 파일: `storage/layout-audit/2026-05-18-admin/`
- 자동 수집 결과: `storage/layout-audit/2026-05-18-admin/results.json`

## 점검 범위

다음 50개 관리자 GET 화면을 데스크톱/모바일 양쪽에서 확인했다.

`/admin`, `/admin/settings`, `/admin/menu`, `/admin/modules`, `/admin/updates`, `/admin/roles`, `/admin/audit-logs`, `/admin/retention`, `/admin/members`, `/admin/member-settings`, `/admin/member-groups`, `/admin/member-groups/new`, `/admin/member-group-rules`, `/admin/member-group-rules/new`, `/admin/member-group-evaluations`, `/admin/member-group-assignments`, `/admin/community/settings`, `/admin/community/levels`, `/admin/community/boards`, `/admin/community/boards/new`, `/admin/community/board-groups`, `/admin/community/board-groups/new`, `/admin/community/posts`, `/admin/community/comments`, `/admin/community/reports`, `/admin/notifications`, `/admin/notifications/new`, `/admin/notification-deliveries`, `/admin/privacy-requests`, `/admin/popup-layers`, `/admin/popup-layers/new`, `/admin/banners`, `/admin/banners/new`, `/admin/site-menus`, `/admin/site-menus/new`, `/admin/site-menu-items`, `/admin/site-menu-items/new`, `/admin/seo`, `/admin/points`, `/admin/points/adjust`, `/admin/points/balances`, `/admin/points/transactions`, `/admin/deposits`, `/admin/deposits/adjust`, `/admin/deposits/balances`, `/admin/deposits/transactions`, `/admin/rewards`, `/admin/rewards/adjust`, `/admin/rewards/balances`, `/admin/rewards/transactions`

## 확인된 레이아웃 문제

### 1. 모바일 표 화면의 열 폭이 너무 좁아 텍스트가 한 글자 단위로 줄바꿈됨

- 영향 화면:
  - `/admin/menu`
  - `/admin/community/boards`
  - `/admin/community/board-groups`
  - `/admin/community/posts`
  - `/admin/community/comments`
  - `/admin/member-group-rules`
  - `/admin/members`
  - `/admin/site-menus`
  - `/admin/site-menu-items`
  - `/admin/popup-layers`
  - `/admin/privacy-requests`
- 증거 예시:
  - `admin-menu-mobile.png`
  - `admin-community-boards-mobile.png`
  - `admin-roles-mobile.png`
- 관찰:
  - 모바일 폭에서 표를 그대로 유지하면서 각 열이 지나치게 압축된다.
  - `시스템/관리자/대시보드`, `자유게시판`, `커뮤니티/게시판/그룹` 같은 값이 세로로 쪼개져 읽기 어렵다.
  - 일부 관리 버튼 열은 화면 오른쪽 끝에서 잘려 보이거나 부분적으로만 보인다.
- 개선 방향:
  - 모바일에서는 표를 카드형 목록으로 전환하거나, 표를 명확한 가로 스크롤 영역으로 감싸고 스크롤 가능함을 시각적으로 보여준다.
  - 관리 버튼은 행 아래 액션 영역으로 분리하는 편이 자연스럽다.

### 2. 모바일 폼의 하단 고정 액션 바가 입력 필드를 덮음

- 영향 화면:
  - `/admin/banners/new`
  - `/admin/notifications/new`
  - 유사한 `form-sticky-actions` 사용 폼
- 증거 예시:
  - `admin-banners-new-mobile.png`
  - `admin-notifications-new-mobile.png`
- 관찰:
  - `목록 / 저장`, `목록 / 알림 등록` 고정 바가 화면 중간에 떠 있는 상태로 캡처되며 현재 입력 영역을 가린다.
  - 실제 모바일 스크롤 중에도 사용자가 작성 중인 필드와 도움말을 가릴 가능성이 높다.
- 개선 방향:
  - 모바일에서는 sticky 액션 바 높이만큼 폼 하단 여백을 충분히 확보한다.
  - 긴 폼에서는 액션 바를 카드 하단의 일반 버튼 영역으로 바꾸거나, 접힘/축소 상태를 제공한다.

### 3. 데스크톱의 일부 대형 표가 카드 폭을 넘거나 오른쪽 액션이 잘림

- 영향 화면:
  - `/admin/modules`
  - `/admin/audit-logs`
- 증거 예시:
  - `admin-modules-desktop.png`
  - `admin-audit-logs-desktop.png`
- 관찰:
  - `/admin/modules`는 설치된 모듈 표가 오른쪽으로 밀려 `상태 변경` 버튼 일부가 화면 끝에서 잘린다.
  - `/admin/audit-logs`는 메타 JSON 열 때문에 표가 매우 넓어지고, 메시지와 메타 열이 세로로 길게 깨져 전체 행 높이가 과도하게 커진다.
- 개선 방향:
  - 긴 열은 `max-width`, 말줄임, 상세 보기 확장으로 분리한다.
  - JSON 메타는 기본 표에서는 요약만 보이고, 클릭/상세 패널에서 원문을 보여주는 방식이 낫다.
  - 모듈 표의 액션 열은 고정 폭을 확보하거나 행별 보조 줄로 분리한다.

### 4. 필터 폼이 데스크톱에서 지나치게 좁고 남는 공간이 큼

- 영향 화면:
  - `/admin/audit-logs`
- 증거 예시:
  - `admin-audit-logs-desktop.png`
- 관찰:
  - 로그 검색 폼이 카드의 왼쪽 좁은 열에만 쌓여 있고, 오른쪽 대부분이 빈 공간이다.
  - 이어지는 표는 열이 많아 복잡한데 필터는 세로로 길어져 화면 밀도가 어색하다.
- 개선 방향:
  - 데스크톱에서는 필터 필드를 2-4열 그리드로 배치한다.
  - 초기화 버튼과 조회 버튼의 위치를 같은 액션 줄에 정리한다.

## 정상으로 보인 부분

- 모든 점검 대상 GET 화면은 HTTP 200으로 응답했다.
- 데스크톱/모바일 모두 문서 전체 수준의 수평 스크롤은 대부분 발생하지 않았다.
- 대시보드, 설정류, 포인트/적립금/예치금 조회류 화면은 큰 겹침 없이 읽을 수 있었다.

## 기타 메모

- Playwright 콘솔 수집에서 로그인 직후 `/login?next=%2Fadmin` favicon 계열 404로 보이는 리소스 오류 1건이 있었으나, 관리자 레이아웃 문제와 직접 관련은 없어 보인다.
- 이 문서는 레이아웃 점검 결과 기록이며 코드 동작 변경은 없다.

## 2026-05-18 수정 반영

- 모바일 표는 좁은 열에 내용을 강제로 줄바꿈하지 않고 카드 내부 가로 스크롤로 탐색하도록 조정했다.
- 모바일 폼의 `form-sticky-actions`는 입력 영역을 덮지 않도록 일반 흐름으로 내려오게 했다.
- 감사 로그 필터는 데스크톱에서 여러 열을 쓰고 태블릿/모바일에서는 한 열로 접히도록 조정했다.
- 감사 로그의 메시지와 메타 JSON은 기본 표 폭을 과도하게 밀지 않도록 메시지 폭을 제한하고 메타 원문은 펼침 영역에 넣었다.
- 대표 화면 재확인 증거: `storage/layout-audit/2026-05-18-admin-after-fix/`

## 2026-05-21 수정 반영

- 등록, 수정, 설정 화면의 제출 영역은 공통 `form-sticky-actions` 기준으로 다시 하단 스티키 동작을 사용한다.
- 모바일에서도 스티키 동작은 유지하고, 버튼 폭과 액션 배치만 모바일 기준으로 보정한다.
