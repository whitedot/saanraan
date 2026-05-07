# 문서 분류

이 문서는 `docs/` 아래 Markdown 문서의 종류와 현재 용도를 정리한다.

## 기준 문서

| 문서 | 종류 | 용도 |
| --- | --- | --- |
| `core-decisions.md` | 핵심 결정 로그 | 구현 판단이 갈릴 때 우선하는 설계 결정 |
| `current-implementation-status.md` | 현재 상태 | 코드 기준 구현 범위와 검증 기준 |
| `implemented-features.md` | 기능 목록 | 사용자 관점에서 확인 가능한 구현 기능 |
| `external-module-support-plan.md` | 외부 모듈 지원 계획 | 외부 모듈 제작자 문서와 도구 개선 진행 상태 |
| `external-module-quickstart.md` | 외부 모듈 시작 | 모듈 폴더를 만들고 zip으로 업로드하는 첫 절차 |
| `module-checklist.md` | 모듈 확인표 | 외부 모듈 zip 배포 전 확인 항목 |
| `module-ci-quickstart.md` | 모듈 자동 점검 | GitHub Actions 기반 자동 점검을 짧게 설명 |
| `module-guide.md` | 작성 규칙 | 모듈/플러그인 작성 방식과 파일 역할 |
| `module-ci-template.yml` | 모듈 CI 템플릿 | 외부 모듈 프로젝트용 GitHub Actions 호환성 검사 예시 |
| `distributions.json` | 배포 조합 정책 | minimal/standard/ops 포함 모듈과 기본 선택 모듈 기준 |
| `module-repository-strategy.md` | 배포 전략 | 모듈을 `modules/{module_key}` 폴더 기준으로 다루는 저장 위치 기준 |
| `module-update-and-source-plan.md` | 모듈 운영 | 모듈 배치, zip 업로드, 설치/업데이트 책임 기준 |
| `legacy-maintainer-improvement-plan.md` | 운영 개선 | 레거시 운영자 관점의 개선 순서와 완료 기준 |
| `database-access-policy.md` | DB 접근 규약 | PDO 사용, prepared statement, raw SQL 허용 범위 |

## 설계 문서

| 문서 | 종류 | 용도 |
| --- | --- | --- |
| `erd-basic-environment.md` | 데이터 구조 | 현재 설치 SQL 기준 테이블 구조와 관계 |
| `install-plan.md` | 설치 흐름 | 웹 설치, 필수/선택 모듈 설치, 실패 재시도 기준 |
| `update-plan.md` | 업데이트 흐름 | SQL 파일 기반 스키마 업데이트와 복구 기준 |
| `runtime-ops-plan.md` | 운영 정책 | 실행 모드, 오류 화면, 로그, 복구 marker 기준 |
| `operations-modules-plan.md` | 운영 모듈 | 메뉴, 배너, 알림, 관리자 작업 로그의 모듈화 기준 |
| `audit-log-plan.md` | 감사 로그 | 감사 로그 기록 대상과 보관 기준 |
| `security-checklist.md` | 보안 점검 | 기능 추가와 리뷰 시 확인할 보안 항목 |
| `admin-post-action-rules.md` | 관리자 작성 규칙 | 관리자 POST action의 권한, 검증, 메시지, 감사 로그 기준 |

## 도메인 계획

| 문서 | 종류 | 용도 |
| --- | --- | --- |
| `admin-plan.md` | 관리자 모듈 | 관리자 화면, 권한, 관리 기능 기준 |
| `member-plan.md` | 회원 모듈 | 인증, 세션, 프로필, 개인정보 관련 회원 기준 |
| `privacy-gdpr-plan.md` | 개인정보 | 개인정보 요청, 내보내기, 보관 정리 기준 |
| `seo-plan.md` | SEO | 코어와 SEO 모듈의 역할 분리 기준 |
| `i18n-plan.md` | 다국어 | locale 결정, 번역 파일, fallback 기준 |
| `entry-request-plan.md` | 요청 흐름 | 루트 진입점, 홈, 모듈 path 처리 기준 |
| `cache-plan.md` | 캐시 | 캐시를 선택 최적화 계층으로 두는 기준 |

## 운영 문서

| 문서 | 종류 | 용도 |
| --- | --- | --- |
| `local-development.md` | 로컬 개발 | PHP 내장 서버, 기본 점검 스크립트, 문법 검사 명령 |
| `smoke-test.md` | 스모크 테스트 | 배포 전 최소 HTTP 점검과 수동 검증 시나리오 |
| `release-process.md` | 릴리스 절차 | 배포 zip 생성과 릴리스 전후 확인 기준 |
| `deployment-protection.md` | 배포 보호 | 내부 디렉터리 직접 접근 차단 기준 |
| `deployment-examples.md` | 배포 예시 | PHP 내장 서버, Apache, Nginx, 공유호스팅 예시 |
| `shared-hosting-install.md` | 저가형 호스팅 설치 | FTP/SFTP 기반 호스팅 설치 순서와 설치 전후 검증 기준 |

## 제거된 문서

| 문서 | 제거 이유 |
| --- | --- |
| `core-foundation-roadmap.md` | 초기 우선순위 로드맵이 완료되어 현재 상태 문서와 핵심 결정 로그로 대체됨 |
