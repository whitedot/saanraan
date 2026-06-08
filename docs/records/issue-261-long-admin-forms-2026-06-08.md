# #261 긴 관리자 폼 섹션 내비게이션 계획

## 배경

#261은 관리자 등록/수정 화면 중 입력 항목과 섹션이 많아 현재 위치를 파악하기 어려운 화면에 섹션 이동 보조를 적용하는 작업이다.

기존 UI kit에는 탭 primitive가 있으므로 시각 표현은 `tab-nav-justified`, `tab-trigger-underline-justified`를 재사용한다. 다만 긴 등록/수정 폼에서는 저장 범위 혼동을 막기 위해 콘텐츠를 숨기는 `role="tab"` 패널 전환을 쓰지 않는다. 모든 섹션은 계속 렌더링하고, 상단 sticky 앵커가 섹션으로 이동한다.

## 1차 적용

2026-06-08 1차 구현에서는 `modules/community/views/admin-boards.php`의 게시판 생성/수정 화면에 적용했다.

| 섹션 | 앵커 | 범위 |
| --- | --- | --- |
| 기본 정보 | `community-board-section-basic` | key, 그룹, 이름, 상태, 스킨, 에디터 |
| SEO/OG | `community-board-section-seo` | SEO 제목/설명, OG 제목/설명/이미지 |
| 접근/작성 | `community-board-section-policy` | 읽기/쓰기/댓글 정책, 회원 그룹, 레벨, 첨부 |
| 배너 | `community-board-section-banner` | 게시판 배너 연결 |
| 팝업 | `community-board-section-popup` | 게시판 팝업 연결 |
| 포인트/금액 | `community-board-section-assets` | 작성/댓글/열람/다운로드 지급·차감 및 회원 그룹별 적용 |
| 정렬 | `community-board-section-order` | 정렬 순서 |
| 위험 작업 | `community-board-section-danger` | 수정 화면의 게시판 삭제 |
| 관리권한 | `community-board-section-managers` | 수정 화면의 게시판 관리권한 |
| 카테고리 | `community-board-section-categories` | 수정 화면의 게시판 카테고리 |

## 공통 UI 기준

- 앵커 내비게이션 wrapper는 `admin-section-nav admin-anchor-tabs tab-nav-justified`를 사용한다.
- 각 링크는 UI kit 탭 trigger인 `tab-trigger-underline-justified`를 사용한다.
- JS는 현재 스크롤 위치에 맞춰 `active`와 `aria-current="location"`만 보강한다.
- JS가 없어도 링크 href만으로 섹션 이동이 가능해야 한다.
- 섹션은 `data-admin-section-anchor`와 안정적인 `id`를 가진다.
- 긴 폼 섹션 내비게이션에는 `role="tablist"`와 `role="tab"`을 쓰지 않는다. 이 역할은 실제 tabpanel 전환에만 쓴다.

## 후속 후보

1차 동작을 확인한 뒤 다음 후보에 같은 패턴을 확대한다.

- `modules/quiz/actions/admin-quiz.php`
- `modules/community/views/admin-board-groups.php`
- `modules/content/views/admin-contents.php`
- `modules/content/views/admin-content-groups.php`

설정 화면 계열은 같은 sticky 앵커가 필요한지, 단순 섹션 헤더 강화로 충분한지 별도 판정한다.

## 검증 계획

- PHP/view 변경 후 `php .tools/bin/check.php`를 실행한다.
- 로컬 서버 실행이 가능하면 HTTP smoke를 실행한다.
- 수동 확인은 게시판 생성/수정 화면에서 데스크톱/모바일 폭의 sticky 내비게이션, 하단 sticky 저장 버튼, 관리권한/카테고리 모달 포커스 충돌 여부를 본다.

## Wiki 영향

관리자 화면 개발 가이드에 긴 등록/수정 폼 섹션 내비게이션 기준을 반영한다.
