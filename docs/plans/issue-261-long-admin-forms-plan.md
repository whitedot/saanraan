# #261 긴 관리자 폼 섹션 내비게이션 계획

## 배경

#261은 관리자 등록/수정 화면 중 입력 항목과 섹션이 많아 현재 위치를 파악하기 어려운 화면에 섹션 이동 보조를 적용하는 작업이다.

기존 UI kit에는 탭 primitive가 있으므로 시각 표현은 `tab-nav-justified`, `tab-trigger-underline-justified`를 재사용한다. 다만 긴 등록/수정 폼에서는 저장 범위 혼동을 막기 위해 콘텐츠를 숨기는 `role="tab"` 패널 전환을 쓰지 않는다. 모든 섹션은 계속 렌더링하고, 상단 sticky 앵커가 섹션으로 이동한다.

## 적용 내역

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

2026-06-08 후속 구현에서는 게시판 외에도 섹션 수와 반복 이동 가능성이 큰 폼을 더 낙관적으로 검토해 다음 화면으로 확대했다.

| 화면 | 파일 | 적용 섹션 |
| --- | --- | --- |
| 게시판 그룹 생성/수정 | `modules/community/views/admin-board-groups.php` | 기본 정보, 작성 기본값, 배너, 팝업, 포인트/금액, 위험 작업 |
| 콘텐츠 생성/수정 | `modules/content/views/admin-contents.php` | 기본 정보, 본문/이미지, 공개 설정, SEO, 유료 열람, 완료 버튼, 배너/팝업, 파일 |
| 콘텐츠 그룹 생성/수정 | `modules/content/views/admin-content-groups.php` | 기본 정보, 작성 기본값, 배너/팝업, 유료 열람, 완료 버튼, 파일, 회원 제출, 위험 작업 |
| 퀴즈 생성/수정 | `modules/quiz/actions/admin-quiz.php` | 기본 정보, 채점/결과, 결과 규칙, 공개/응시, 문제 목록, 보상 정책, 연결 대상 |
| 회원 설정 | `modules/member/views/admin-settings.php` | 가입/인증, 스킨, 프로필, 가입 약관, 로그인 제한, 가입 제한, 비밀번호, 이메일 |
| 커뮤니티 설정 | `modules/community/views/admin-settings.php` | 레벨 기본값, 쪽지 정책, 자산/과금, 공개 화면 |
| 퀴즈 설정 | `modules/quiz/views/admin-settings.php` | 공개 화면, 새 퀴즈, 기본 보상, 목록/연결 |
| 알림 설정 | `modules/notification/views/admin-notification-settings.php` | 메일 환경, SMTP, HTTP API |

## 공통 UI 기준

- 앵커 내비게이션 wrapper는 `sticky-tabs anchor-tabs tab-nav-justified`를 사용한다.
- 각 링크는 UI kit 탭 trigger인 `tab-trigger-underline-justified`를 사용한다.
- 탭 trigger 글자 크기는 UI kit 공통에서 14px 수준으로 맞춘다.
- JS는 현재 스크롤 위치에 맞춰 `active`와 `aria-current="location"`만 보강한다.
- JS가 없어도 링크 href만으로 섹션 이동이 가능해야 한다.
- 섹션은 `data-admin-section-anchor`와 안정적인 `id`를 가진다.
- 긴 폼 섹션 내비게이션에는 `role="tablist"`와 `role="tab"`을 쓰지 않는다. 이 역할은 실제 tabpanel 전환에만 쓴다.

## 남은 후보

이번 확대 적용 뒤에도 다음 화면은 별도 검토 후보로 남긴다.

- 배너, 팝업레이어, 설문처럼 모달 편집 비중이 큰 관리 화면
- 에셋 환전 설정처럼 섹션 수가 적어 sticky 앵커보다 헤더 정리만으로 충분할 수 있는 화면
- 커뮤니티 레벨 정의처럼 목록 테이블 중심이거나 별도 작업 버튼이 핵심인 화면

## 검증 계획

- PHP/view 변경 후 `php .tools/bin/check.php`를 실행한다.
- 로컬 서버 실행이 가능하면 HTTP smoke를 실행한다.
- 수동 확인은 적용 화면의 데스크톱/모바일 폭 sticky 내비게이션, 하단 sticky 저장 버튼, 모달 편집 화면 포커스 충돌 여부를 본다.

## 문서 영향

관리자 화면 개발 기준에 긴 등록/수정 폼 섹션 내비게이션 기준을 반영한다.
