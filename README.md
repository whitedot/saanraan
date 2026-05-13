# Toycore

Toycore는 토이 프로젝트처럼 가볍게 시작해 구조를 바꿔 보고 가능성을 확인하는 실험적 코어 프로젝트입니다. [G5 Codex 프로젝트](https://github.com/whitedot/g5codex)를 진행하며 얻은 경험에서 출발했으며, AI와 자동화 도구가 만든 코드도 사람이 요청 흐름과 변경 범위를 이해하고 검토할 수 있는 형태를 지향합니다.

- 절차형 PHP 기반.
- 작은 코어와 모듈 경계 중심.
- 회원, 관리자, 업데이트, 보안 helper 같은 운영 기준선 제공.
- 현재 상태: 실험적 코어 프로젝트.

## 한눈에 보기

| 항목 | 내용 |
| --- | --- |
| 성격 | 저가형 PHP 웹호스팅에서도 운영 가능한 회원 중심 모듈형 웹 솔루션 베이스 |
| 언어 | PHP 8.1 이상 |
| DB | MySQL 또는 MySQL 호환 DB, `pdo_mysql` 필요 |
| 프론트엔드 | Vanilla JavaScript, plain CSS |
| 기본 설치 | `core + member + admin + privacy` |
| 모듈 위치 | `modules/{module_key}` |
| 주요 관리자 화면 | `/admin`, `/admin/menu`, `/admin/modules`, `/admin/updates` |
| 목표 환경 | Apache 또는 Apache 호환 공유호스팅 |
| 보안 피드백 | `kimminsup@gmail.com` |

## 사용 판단 기준

| 기준 | 잘 맞는 방향 | 다른 선택이 나은 방향 |
| --- | --- | --- |
| 운영 규모 | 소규모 회원 기반 사이트 | 대규모 트래픽, 분산 아키텍처 |
| 배포 환경 | 공유호스팅, 단순 PHP 배포 | 상시 worker, 고성능 서버 기능 필수 |
| 개발 방식 | 파일 기반 요청 흐름 | ORM, DI, middleware 중심 개발 |
| 화면 구성 | 서버 렌더링, 단순 JS | SPA, headless API-first |
| 기능 경계 | 도메인별 모듈 분리 | 페이지 빌더, 플러그인 마켓형 CMS |

## 현재 구현 범위

- 웹 설치.
- 회원/관리자 기준선.
- DB 기반 로그인 세션과 클라우드 런타임 대응.
- 모듈 설치, 활성화, 업데이트.
- 감사 로그, 개인정보 처리 요청, 보관 정리.
- 커뮤니티, SEO, 메뉴, 배너, 팝업레이어, 알림.
- 포인트, 예치금, 적립금 회원 연계 모듈.
- 파일 저장 S3 연동과 운영 HTTPS 설정 검증.

## 주요 문서

| 목적 | 문서 |
| --- | --- |
| 설계 결정 | [핵심 설계 결정](docs/core-decisions.md) |
| 모듈 개발 | [모듈 작성 가이드](docs/module-guide.md), [모듈 배치와 업데이트 기준](docs/module-update-policy.md) |
| 보안 | [Toycore 보안 모델](docs/security-model.md), [보안 체크리스트](docs/security-checklist.md), [DB 접근 정책](docs/database-access-policy.md) |
| 배포와 릴리스 | [배포 보호 기준](docs/deployment-protection.md), [릴리스 절차](docs/release-process.md) |
| 검증 | [스모크 테스트 기준](docs/smoke-test.md) |
| 예제 | [sample_module](examples/sample_module/README.md) |

## 보안 피드백

- 보안 취약점 또는 민감한 운영 위험: `kimminsup@gmail.com`
- 공개 이슈 등록 전 사전 제보 권장.

## 라이선스

- [MIT License](LICENSE)
