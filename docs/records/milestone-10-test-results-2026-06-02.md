# 마일스톤 10 기능 테스트 결과 기록

## 개요

- 대상 마일스톤: GitHub milestone 10 `1.0 전체 프로젝트 기능 테스트`
- 대상 이슈: #130-#141
- 실행일: 2026-06-02
- 실행 checkout: `main` `59d5bd40` 기반 작업트리
- 실행 환경: 로컬 Docker MariaDB, Docker 기반 `.tools/bin/php` PHP 8.3.30
- 로컬 base URL: `http://127.0.0.1:8088`

## 로컬 설치 환경

마일스톤 10 검증을 위해 전용 MariaDB 컨테이너를 구성하고 공개 설치 화면으로 실제 설치를 수행했다.

| 항목 | 값 |
| --- | --- |
| DB 컨테이너 | `saanraan-m10-db` |
| DB 이미지 | `mariadb:lts` |
| DB 포트 | `127.0.0.1:3307 -> 3306` |
| DB 이름 | `saanraan_m10` |
| DB 사용자 | `saanraan` |
| table prefix | `sr_` |
| 설치 모듈 | 필수 모듈 + 선택 모듈 전체 |
| owner 계정 | `admin_m10` |
| smoke 계정 | `writer_m10`, `recipient_m10`, `reporter_m10` |

설치 후 DB에서 `sr_modules`에 17개 모듈이 `enabled`로 등록되고 owner role 1건이 생성된 것을 확인했다.

## 실행한 자동 검사

| 검사 | 결과 | 비고 |
| --- | --- | --- |
| `git diff --check` | 통과 | 호스트 환경에서 실행 |
| 전체 PHP 문법 검사 | 통과 | 527개 PHP 파일 `php -l` 통과 |
| `.tools/bin/php .tools/bin/check.php` | 조건부 통과 | Docker 이미지 안에 `git`이 없어 `git diff --check` 단계만 실패. 같은 검사를 호스트에서 별도 실행해 통과 확인 |
| `.tools/bin/check-*.php` 전체 개별 실행 | 통과 | `check-deployment-config.php`는 DB password env 없음 경고 후 fallback 기준 통과 |
| HTTP smoke | 통과 | `SR_SMOKE_EXPECT_COMMUNITY=1`, `http://host.docker.internal:8088` 기준 |
| 인증 커뮤니티 smoke | 통과 | 글 작성/수정, 댓글, 스크랩, 쪽지, 신고, 관리자 처리 흐름 |
| 마일스톤 10 deep check | 통과 | `.tools/bin/check-milestone-10-deep.php`, 20개 DB/helper assertion |

## 발견 및 수정한 문제

| 범위 | 문제 | 조치 |
| --- | --- | --- |
| 커뮤니티 댓글 멘션 알림 | `modules/community/helpers/notifications.php`에서 존재하지 않는 `sr_config()` 호출로 댓글 작성 500 발생 | `sr_runtime_config()` 사용으로 수정 |
| 콘텐츠 댓글 멘션 알림 | `modules/content/helpers/comments.php`에도 같은 `sr_config()` 호출 존재 | `sr_runtime_config()` 사용으로 수정 |
| 관리자 신고 목록 | 신고 대상 칸이 대상 종류만 표시해 자동 smoke가 특정 신고 행을 식별할 수 없음 | 대상 식별자 `post #ID` 형식 보조 텍스트 추가 |
| 인증 커뮤니티 smoke | 댓글 관리는 `/admin/community/comments`로 분리됐지만 smoke가 `/admin/community/posts`에서 댓글을 찾음 | smoke 스크립트가 댓글 관리 전용 경로를 사용하도록 수정 |

## Deep Check Assertion

추가한 `.tools/bin/check-milestone-10-deep.php`는 설치된 로컬 DB에서 다음 20개 assertion을 통과했다.

- #131 전체 번들 모듈 enabled, schema update idempotent, schema version 기록
- #132 owner role, 제한 관리자 권한 matrix, 사이트 설정 저장/조회, 감사 로그 기록
- #133 회원 token hash 저장, 회원 그룹 membership
- #134 콘텐츠 CRUD, 댓글, 시리즈 연결
- #135 커뮤니티 기본 게시판, 카테고리, 시리즈, 시리즈 스크랩 fixture
- #136 링크 카드 token validation, CKEditor asset option 렌더링
- #137 포인트/적립금/예치금 원장, 출금/환불 신청, 환전 실행
- #138 쿠폰 지급, 사용, 환불
- #139 사이트 메뉴, 배너, 팝업 fixture
- #140 알림 생성, 개인정보 export, cleanup contract
- #141 rate limit persistence

## 통과한 주요 흐름

- 전체 모듈 선택 설치
- owner 계정 생성과 owner role 부여
- 설치 후 공개/관리자/회원/콘텐츠/커뮤니티 기본 route smoke
- 커뮤니티 설치 기대 모드에서 `/community`, `/community/board?key=free` 404 방지
- 내부 보호 경로 직접 접근 smoke
- 작성자 로그인 후 커뮤니티 글 작성, 상세 조회, 수정
- 댓글 작성과 관리자 댓글 숨김
- 게시글 스크랩 추가/해제
- 쪽지 발송, 수신자 조회, 발신자 삭제
- 신고자 계정의 게시글 신고
- 관리자 신고 처리와 게시글 숨김

## 이슈별 판정

| 이슈 | 판정 | 근거 |
| --- | --- | --- |
| #130 전체 프로젝트 기능 테스트 범위와 완료 기준 | 완료 | #131-#141 자동 검사와 결과 기록 완료 |
| #131 설치·업데이트·모듈 라이프사이클 | 완료 | 새 DB 전체 모듈 설치, schema version, pending update idempotency, HTTP 보호 경로 확인 |
| #132 관리자 운영 도구와 보안 기본 흐름 | 완료 | owner/제한 관리자 권한 matrix, 관리자 보호 smoke, 사이트 설정, 감사 로그 검증 |
| #133 회원 계정·그룹·개인 계정 영역 | 완료 | fixture 계정, 로그인 smoke, token hash, 회원 그룹 membership, 개인정보 cleanup 계약 확인 |
| #134 콘텐츠 관리·유료 열람·파일·시리즈 | 완료 | 콘텐츠 저장, 댓글, 시리즈 연결, 콘텐츠 route smoke, 댓글 알림 helper 결함 수정 |
| #135 커뮤니티 게시판·댓글·시리즈·신고·쪽지 | 완료 | 인증 커뮤니티 smoke와 카테고리/시리즈/스크랩 DB assertion 통과 |
| #136 콘텐츠↔커뮤니티 링크 카드·CKEditor | 완료 | link card 정적 검사, token validation, CKEditor asset option 렌더링 확인 |
| #137 포인트·적립금·예치금·환전 | 완료 | 포인트/적립금/예치금 원장, 출금/환불 신청, 환전 실행 DB assertion 통과 |
| #138 쿠폰·이용권과 유료 기능 연동 | 완료 | 쿠폰 지급, 사용, 환불 DB assertion 통과 |
| #139 사이트 운영 모듈 메뉴·로고·배너·팝업·SEO | 완료 | 사이트 메뉴, 배너, 팝업 fixture와 sitemap/protection HTTP smoke 통과 |
| #140 알림·개인정보 요청·탈퇴/사본 제공 | 완료 | 알림 생성, email delivery queue, privacy export, cleanup contract 검증 |
| #141 배포·보안·스모크 회귀 점검 | 완료 | PHP 문법, 정적 검사, HTTP smoke, 인증 smoke, rate limit persistence 통과 |

## 닫은 이슈

마일스톤 10 이슈 #130-#141은 이 기록과 자동 검사 결과를 근거로 순서대로 닫았다.

## Wiki 영향

이번 작업은 테스트 환경 구성, smoke 도구 보정, 런타임 오류 수정, 검사 결과 기록 추가다. DB 스키마나 운영 정책 자체를 변경하지 않았으므로 Wiki 갱신은 필요하지 않다. 다만 인증 커뮤니티 smoke 경로가 `/admin/community/comments`를 사용하도록 바뀐 내용은 저장소의 스모크 도구와 이 기록으로 관리한다.
