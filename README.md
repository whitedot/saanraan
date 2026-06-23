# saanraan(산란)

산란은 빛이 흩어지고, 생명이 퍼져 나가는 일입니다.

saanraan(산란)은 저가형 PHP 웹호스팅에서도 읽고 고칠 수 있는 회원 중심 모듈형 웹 솔루션 베이스입니다. 작은 CMS를 출발점으로 삼았지만, 지금은 회원, 관리자, 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사, 자산, 운영 보조 기능이 실제 서비스 안에서 어떻게 맞물리는지 검증하는 코드베이스에 가깝습니다.

요청 흐름과 모듈 경계를 파일에서 직접 따라갈 수 있고, 필요한 기능을 운영 목적에 맞게 조립할 수 있는 구조를 우선합니다.

- 절차형 PHP, Vanilla JavaScript, plain CSS 기반.
- 코어는 요청 진입, 설정, 모듈 조회, 보안 helper, 설치/업데이트 같은 실행 기반에 집중.
- 회원, 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사, 자산, 쿠폰, 알림, 개인정보, 사이트 운영 기능은 모듈 단위로 확장.
- 현재 상태는 정식 완성품보다 1.0 릴리스 전 안정화 중인 모듈형 베이스에 가깝습니다.

## 개발 방향

saanraan의 핵심 방향은 기능을 코어에 계속 쌓는 것이 아니라, 각 도메인이 자기 모듈 안에서 정책, 화면, DB 테이블을 소유하게 하는 것입니다. 코어는 작은 실행 기반으로 남기고, 모듈은 명시적인 파일과 계약을 통해 서로 필요한 만큼만 연결합니다.

이렇게 나누는 이유는 실제 운영에서 필요한 형태로 조립하기 쉽게 하기 위해서입니다. 미리 완성된 형태로 제공할 수도 있지만, 운영자가 자기 사이트에 맞추려면 결국 커스터마이징이 필요하고, 쓰지 않는 기능의 DB 테이블이나 업로드 자산, 설정값이 남을 가능성도 커집니다. 그래서 saanraan은 기본 골격을 단순하게 유지하고, 필요한 모듈과 사이트별 확장을 선택해 붙이는 방향을 우선합니다.

구체적인 기준은 [모듈 작성 가이드](docs/module-guide.md), [핵심 설계 결정](docs/core-decisions.md), [커스터마이징과 업데이트 충돌 가이드](docs/customization-guide.md)에 정리되어 있습니다.

## 한눈에 보기

| 항목 | 내용 |
| --- | --- |
| 성격 | 저가형 PHP 웹호스팅 운영을 고려한 탐구형 회원 중심 모듈형 웹 솔루션 베이스 |
| 언어 | PHP 8.1 이상 |
| DB | MySQL 또는 MySQL 호환 DB, `pdo_mysql` 필요 |
| 프론트엔드 | Vanilla JavaScript, plain CSS |
| 기본 설치 | `core + member + admin + privacy` |
| 선택 번들 | 커뮤니티, 콘텐츠, 퀴즈, 설문, 임베드 매니저, 메뉴, 로고, SEO, 배너, 팝업레이어, 알림, 포인트, 적립금, 예치금, 포인트/금액 환전, 쿠폰·이용권, CKEditor |
| 모듈 위치 | `modules/{module_key}` |
| 주요 관리자 화면 | `/admin`, `/admin/menu`, `/admin/modules`, `/admin/updates` |
| 목표 환경 | Apache 또는 Apache 호환 공유호스팅, PHP-FPM 기반 nginx |
| 보안 제보 | [SECURITY.md](SECURITY.md), `kimminsup@gmail.com` |

## 사용 판단 기준

| 기준 | 잘 맞는 방향 | 다른 선택이 나은 방향 |
| --- | --- | --- |
| 운영 규모 | 소규모 회원 기반 사이트 | 대규모 트래픽, 분산 아키텍처 |
| 배포 환경 | 공유호스팅, 단순 PHP 배포 | 상시 worker, 고성능 서버 기능 필수 |
| 개발 방식 | 파일 기반 요청 흐름 | ORM, DI, middleware 중심 개발 |
| 화면 구성 | 서버 렌더링, 단순 JS | SPA, headless API-first |
| 기능 경계 | 도메인별 모듈 분리 | 단일 코어가 모든 도메인을 직접 소유하는 구조 |

## 현재 진행 상황

산란은 아직 정식 릴리스보다 개발 베이스에 가깝습니다. 다만 단순 골격 단계는 지나, 설치/관리자/회원/콘텐츠/커뮤니티/퀴즈/설문/리액션/자산/운영 보조 흐름을 실제 파일과 DB 테이블로 검증하며 확장하는 중입니다. 기능을 적게 유지하는 것보다, 기능이 늘어나도 요청 흐름과 책임 경계를 파일에서 따라갈 수 있게 두는 일을 더 중요한 기준으로 삼습니다.

[산란 특장점 소개](docs/operator-feature-list.md)는 회원, 콘텐츠, 커뮤니티, 퀴즈, 설문, 리액션, 자산, 쿠폰, 알림, 개인정보, 사이트 운영 기능이 제공하는 주요 기능과 편의를 정리합니다. 기능 목록은 프로젝트가 어떤 방향의 모듈형 베이스인지 빠르게 이해하기 위한 소개 문서입니다.

구현된 기반:

- 웹 설치와 설치 후 모듈 설치/활성화/업데이트 흐름.
- DB 기반 회원 계정, 로그인 세션, 이메일 인증, 비밀번호 재설정, 마이페이지, 프로필, 탈퇴 처리.
- 관리자 대시보드, 관리자 메뉴, 권한, 감사 로그, 설정, 업데이트 화면.
- 개인정보 처리 요청, 개인정보 사본 제공, 모듈별 export/cleanup 계약.
- 콘텐츠와 커뮤니티 공개 화면, 관리자 화면, sitemap 후보, 회원 그룹 규칙 연동.
- 콘텐츠/커뮤니티 맥락에서 시작하는 퀴즈 응시, 채점, 결과, 보상 지급 흐름.
- 설문 공개 목록/응답, 관리자 설문/응답/통계/CSV/매뉴얼, 보상 지급 흐름.
- 사이트 메뉴, 로고 매니저, SEO, 배너, 팝업레이어 같은 사이트 운영 모듈.
- 알림 모듈, 사이트 알림, 이메일 delivery queue, 도메인 이벤트 템플릿.
- 포인트 유효기간/만료를 포함한 포인트, 적립금, 예치금 원장과 관리자 조정/환불/회원 화면, 포인트/금액 항목 간 환전 정책과 실행 로그.
- 쿠폰·이용권 모듈과 콘텐츠/커뮤니티 유료 열람 우선 적용, 회원 탈퇴 시 쿠폰 상태 처리.
- 임베드 매니저 공식 선택 모듈과 본문 임베드 참조 점검.
- CKEditor 5 선택 플러그인, 에디터 설정, `body_format=html` 저장, 서버 측 HTML sanitizer.
- 로컬 파일 저장과 S3 호환 저장소 helper, Apache/nginx 배포 보호 기준.

## 번들 모듈

| 분류 | 모듈 | 기능 요약 |
| --- | --- | --- |
| 시스템 | `admin` | 관리자 대시보드, 권한, 메뉴, 모듈 관리, 업데이트 |
| 시스템 | `asset_ledger` | 숨김 기반 잔액 처리 primitive, 자산 모듈 자동 준비 |
| 회원 | `member` | 회원 계정, 인증, 마이페이지, 프로필, 회원 그룹, 탈퇴 |
| 회원 | `point`, `reward`, `deposit`, `asset_exchange` | 포인트/적립금/예치금 잔액과 거래 원장, 포인트/금액 항목 간 환전 |
| 회원 | `coupon` | 쿠폰·이용권 종류, 회원 지급, 사용 이력, 열람권 우선 적용 |
| 사이트 | `site_menu`, `logo_manager`, `content`, `banner`, `popup_layer`, `seo` | 사이트 메뉴, 로고, 콘텐츠, 출력 슬롯, 검색 노출 보조 |
| 서비스 | `community` | 게시판, 댓글, 신고, 스크랩, 쪽지, 레벨/회원 그룹 규칙 |
| 서비스 | `quiz` | 퀴즈 응시, 채점, 콘텐츠/커뮤니티 연계, 보상 지급 |
| 서비스 | `survey` | 설문 작성, 응답, 통계, CSV, 보상 지급 |
| 서비스 | `embed_manager` | 본문 임베드 참조 점검과 marker/refs 동기화 |
| 운영 | `notification`, `privacy` | 알림, 발송 작업, 개인정보 요청/사본 제공 |
| 플러그인 | `ckeditor` | CKEditor 5 에셋 로딩과 textarea 강화 |

## 개발과 기여

대표 점검 명령:

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n 1 php -l
php .tools/bin/check.php
```

변경 후에는 영향 범위에 맞는 자동 점검을 실행하고, 필요한 경우 브라우저나 HTTP 흐름도 함께 확인합니다. 작업 기준은 [기여 기준](CONTRIBUTING.md)과 [기여자 작업 기준](docs/contribution-guide.md)을 따릅니다.

## 주요 문서

| 목적 | 문서 |
| --- | --- |
| 문서 구분 | [저장소 문서 안내](docs/README.md) |
| 특장점 소개 | [산란 특장점 소개](docs/operator-feature-list.md) |
| 사용 판단 | [산란 포지셔닝 기준](docs/positioning.md) |
| 설계 결정 | [핵심 설계 결정](docs/core-decisions.md) |
| 설치와 배포 | [배포 보호 기준](docs/deployment-protection.md), [nginx 샘플 설정](docs/deployment/nginx-saanraan.conf) |
| 모듈 개발 | [모듈 작성 가이드](docs/module-guide.md), [모듈 배치와 업데이트 기준](docs/module-update-policy.md), [커스터마이징과 업데이트 충돌 가이드](docs/customization-guide.md) |
| 관리자 UI | [관리자 UI 작성 기준](docs/admin-ui-guide.md), [관리자 목록 컬럼 기준](docs/admin-list-columns.md) |
| 기여 | [기여 기준](CONTRIBUTING.md), [기여자 작업 기준](docs/contribution-guide.md) |
| 보안과 정책 | [SECURITY.md](SECURITY.md), [산란 보안 모델](docs/security-model.md), [보안 체크리스트](docs/security-checklist.md), [보안 제보와 처리 기준](docs/security-response-policy.md), [DB 접근 정책](docs/database-access-policy.md), [외부 의존성 배치 기준](docs/dependency-policy.md) |
| 개인정보와 본문 | [개인정보 처리활동 기록 기준](docs/privacy-processing-records.md), [Rich Text Sanitizer 정책](docs/rich-text-sanitizer-policy.md) |
| 예제 | [sample_module](examples/sample_module/README.md) |

구현 상태를 설명하는 DB 명세, 관리자 화면별 항목 설명, 개발자 가이드는 GitHub Wiki를 우선합니다. 저장소의 주요 문서 안내는 프로젝트 소개, 사용 방법, 기여 방법, 배포와 보안 기준 중심으로 유지합니다.

## 보안 제보

- 보안 취약점 또는 민감한 운영 위험: [SECURITY.md](SECURITY.md), `kimminsup@gmail.com`
- 공개 이슈 등록 전 사전 제보 권장.

## 라이선스

- [MIT License](LICENSE)
