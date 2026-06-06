# saanraan(산란)

산란은 빛이 흩어지고, 생명이 퍼져 나가는 일입니다.
saanraan(산란)은 PHP 웹 솔루션에서 궁금했던 구조를 직접 구현하고 검증해 보는 탐구형 코드베이스입니다.
작은 CMS를 목표로 시작했지만, 지금은 회원, 관리자, 콘텐츠, 커뮤니티, 운영 보조 기능이 실제로 어떻게 맞물리는지 실험하는 데 더 가깝습니다.

- 절차형 PHP 기반.
- 읽히는 요청 흐름과 모듈 경계 중심.
- 회원, 관리자, 업데이트, 보안 helper 같은 운영 기준선 제공.
- 현재 상태: 설치 가능한 회원 중심 모듈형 베이스로 개발 중이며, 구현해 보고 싶었던 커뮤니티/콘텐츠/포인트·금액/운영 보조 모듈이 함께 자라고 있습니다.

## 한눈에 보기

| 항목 | 내용 |
| --- | --- |
| 성격 | 저가형 PHP 웹호스팅 운영을 고려한 탐구형 회원 중심 모듈형 웹 솔루션 베이스 |
| 언어 | PHP 8.1 이상 |
| DB | MySQL 또는 MySQL 호환 DB, `pdo_mysql` 필요 |
| 프론트엔드 | Vanilla JavaScript, plain CSS |
| 기본 설치 | `core + member + admin + privacy` |
| 선택 번들 | 커뮤니티, 콘텐츠, 퀴즈, 메뉴, 로고, SEO, 배너, 팝업레이어, 알림, 포인트, 적립금, 예치금, 포인트/금액 환전, 쿠폰·이용권, CKEditor |
| 모듈 위치 | `modules/{module_key}` |
| 주요 관리자 화면 | `/admin`, `/admin/menu`, `/admin/modules`, `/admin/updates` |
| 목표 환경 | Apache 또는 Apache 호환 공유호스팅, PHP-FPM 기반 nginx |
| 보안 피드백 | `kimminsup@gmail.com` |

## 사용 판단 기준

| 기준 | 잘 맞는 방향 | 다른 선택이 나은 방향 |
| --- | --- | --- |
| 운영 규모 | 소규모 회원 기반 사이트 | 대규모 트래픽, 분산 아키텍처 |
| 배포 환경 | 공유호스팅, 단순 PHP 배포 | 상시 worker, 고성능 서버 기능 필수 |
| 개발 방식 | 파일 기반 요청 흐름 | ORM, DI, middleware 중심 개발 |
| 화면 구성 | 서버 렌더링, 단순 JS | SPA, headless API-first |
| 기능 경계 | 도메인별 모듈 분리 | 단일 코어가 모든 도메인을 직접 소유하는 구조 |

## 현재 진행 상황

산란은 아직 정식 릴리스보다 개발 베이스에 가깝습니다. 다만 단순 골격 단계는 지나, 설치/관리자/회원/콘텐츠/커뮤니티/운영 보조 흐름을 실제 파일과 DB 테이블로 검증하며 확장하는 중입니다. 기능을 적게 유지하는 것보다, 기능이 늘어나도 요청 흐름과 책임 경계를 파일에서 따라갈 수 있게 두는 일을 더 중요한 기준으로 삼습니다.

구현된 기반:

- 웹 설치와 설치 후 모듈 설치/활성화/업데이트 흐름.
- DB 기반 회원 계정, 로그인 세션, 이메일 인증, 비밀번호 재설정, 프로필, 탈퇴 처리.
- 관리자 대시보드, 관리자 메뉴, 권한, 감사 로그, 설정, 업데이트 화면.
- 개인정보 처리 요청, 개인정보 사본 제공, 모듈별 export/cleanup 계약.
- 콘텐츠와 커뮤니티 공개 화면, 관리자 화면, sitemap 후보, 회원 그룹 규칙 연동.
- 콘텐츠/커뮤니티 맥락에서 시작하는 퀴즈 응시, 채점, 결과, 보상 지급 흐름.
- 사이트 메뉴, 로고 매니저, SEO, 배너, 팝업레이어 같은 사이트 운영 모듈.
- 알림 모듈, 사이트 알림, 이메일 delivery queue, 도메인 이벤트 템플릿.
- 포인트 유효기간/만료를 포함한 포인트, 적립금, 예치금 원장과 관리자 조정/환불/회원 화면, 포인트/금액 항목 간 환전 정책과 실행 로그.
- 쿠폰·이용권 모듈과 콘텐츠/커뮤니티 유료 열람 우선 적용, 회원 탈퇴 시 쿠폰 상태 처리.
- CKEditor 5 선택 플러그인, 에디터 설정, `body_format=html` 저장, 서버 측 HTML sanitizer.
- 로컬 파일 저장과 S3 호환 저장소 helper, Apache/nginx 배포 보호 기준.

보완 중인 부분:

- 관리자 쿠폰 지급 내역과 사용 내역은 각각 `/admin/coupons/issues`, `/admin/coupons/redemptions`에서 분리해 관리합니다.
- 본인확인, 회원 마이그레이션, 결제 플러그인은 계획 문서 단계이며 1.0 범위에서는 새 구현을 잠급니다.
- 브라우저 수동 점검과 운영 데이터 기반 검증은 계속 필요한 상태입니다.
- Wiki 구현 명세는 1.0 배포 정리 때 맞추되, 그 전까지는 저장소의 임시 구현 스냅샷을 함께 봅니다.

## 번들 모듈

| 분류 | 모듈 | 상태 |
| --- | --- | --- |
| 시스템 | `admin` | 관리자 대시보드, 권한, 메뉴, 모듈 관리, 업데이트 |
| 회원 | `member` | 회원 계정, 인증, 프로필, 회원 그룹, 탈퇴 |
| 회원 | `point`, `reward`, `deposit`, `asset_exchange` | 포인트/적립금/예치금 잔액과 거래 원장, 포인트/금액 항목 간 환전 |
| 회원 | `coupon` | 쿠폰·이용권 종류, 회원 지급, 사용 이력, 열람권 우선 적용 |
| 사이트 | `site_menu`, `logo_manager`, `content`, `banner`, `popup_layer`, `seo` | 사이트 메뉴, 로고, 콘텐츠, 출력 슬롯, 검색 노출 보조 |
| 서비스 | `community` | 게시판, 댓글, 신고, 스크랩, 쪽지, 레벨/회원 그룹 규칙 |
| 서비스 | `quiz` | 퀴즈 응시, 채점, 콘텐츠/커뮤니티 연계, 보상 지급 |
| 운영 | `notification`, `privacy` | 알림, 발송 작업, 개인정보 요청/사본 제공 |
| 플러그인 | `ckeditor` | CKEditor 5 에셋 로딩과 textarea 강화 |

## 개발과 검증

대표 점검 명령:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n 1 php -l
php .tools/bin/check.php
```

현재 `php -l` 전체 문법 검사와 `php .tools/bin/check.php` 통합 정책 점검은 통과하는 상태입니다. 변경 후에는 두 명령을 다시 실행하고, HTTP/브라우저 스모크 점검이 필요한 변경인지 함께 판단합니다.

## 주요 문서

| 목적 | 문서 |
| --- | --- |
| 문서 구분 | [저장소 문서 기준](docs/README.md) |
| 1.0 범위 | [1.0 범위 잠금 기준](docs/1.0-scope.md) |
| 구현 스냅샷 | [1.0 전 구현 스냅샷](docs/implementation-snapshot.md) |
| 설계 결정 | [핵심 설계 결정](docs/core-decisions.md) |
| 모듈 개발 | [모듈 작성 가이드](docs/module-guide.md), [모듈 배치와 업데이트 기준](docs/module-update-policy.md) |
| 보안 | [산란 보안 모델](docs/security-model.md), [보안 체크리스트](docs/security-checklist.md), [DB 접근 정책](docs/database-access-policy.md) |
| 배포와 릴리스 | [배포 보호 기준](docs/deployment-protection.md), [릴리스 절차](docs/release-process.md) |
| 검증 | [스모크 테스트 기준](docs/smoke-test.md), [수동 화면 점검 체크리스트](manual-check.md) |
| 예제 | [sample_module](examples/sample_module/README.md) |

구현 상태를 설명하는 DB 명세, 관리자 화면별 항목 설명, 개발자 가이드는 GitHub Wiki를 우선합니다. 저장소의 `docs/`는 설계 결정, 정책, 점검 기준, 구현 전 계획 문서를 보관합니다.

## 보안 피드백

- 보안 취약점 또는 민감한 운영 위험: `kimminsup@gmail.com`
- 공개 이슈 등록 전 사전 제보 권장.

## 라이선스

- [MIT License](LICENSE)
