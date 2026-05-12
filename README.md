# Toycore

저가형 PHP 웹호스팅에서도 운영 가능한 회원 중심 모듈형 웹 솔루션 베이스.

- 절차형 PHP 기반.
- 코어는 작게, 도메인은 모듈로 분리.
- 요청 흐름은 파일을 열면 보이는 구조 지향.
- 회원, 관리자, 업데이트, 보안 helper 같은 운영 기준선 제공.
- 현재 상태: 실험적 코어 프로젝트. 웹 설치, 회원/관리자, 업데이트, 커뮤니티·배너·팝업·알림 등 번들 모듈 구현 포함.

## 프로젝트 동기

- [G5 Codex 프로젝트](https://github.com/whitedot/g5codex)를 진행하며 얻은 경험에서 출발.
- 토이 프로젝트처럼 가볍게 시작해, 아이디어가 떠오를 때마다 조금씩 다듬는 프로젝트.
- 완성 제품을 서둘러 만들기보다 구조를 바꿔 보고 가능성을 확인하는 작업.
- AI와 자동화 도구가 만든 코드도 사람이 파일을 열어 요청 흐름을 이해하고 검토할 수 있어야 한다는 관점.
- 이 동기는 Toycore의 정체성. 직접 수정을 전제로 하기보다, 운영자가 구조와 변경 범위를 파악할 수 있는 코드 우선.

## 한눈에 보기

| 항목 | 내용 |
| --- | --- |
| 언어 | PHP 8.1 이상 |
| DB | MySQL 또는 MySQL 호환 DB, `pdo_mysql` 필요 |
| 프론트엔드 | Vanilla JavaScript, plain CSS |
| 기본 설치 | `core + member + admin` |
| 모듈 위치 | `modules/{module_key}` |
| 요청 흐름 | `index.php` -> `paths.php` -> action include |
| 주요 관리자 화면 | `/admin`, `/admin/modules`, `/admin/updates` |
| 목표 환경 | Apache 또는 Apache 호환 공유호스팅 |
| 보안 피드백 | `kimminsup@gmail.com` |

## 사용 판단 기준

| 기준 | 잘 맞는 방향 | 다른 선택이 나은 방향 |
| --- | --- | --- |
| 운영 규모 | 소규모 회원 기반 사이트 | 대규모 트래픽, 분산 아키텍처 |
| 배포 환경 | 공유호스팅, 단순 PHP 배포 | 상시 worker, 고성능 서버 기능 필수 |
| 개발 방식 | 파일을 열어 흐름 추적 | ORM, DI, middleware 중심 개발 |
| 화면 구성 | 서버 렌더링, 단순 JS | SPA, headless API-first |
| 기능 경계 | 도메인별 모듈 분리 | 페이지 빌더, 플러그인 마켓형 CMS |
| 커스터마이징 | 사이트별 정책 구현 | 설정 조합만으로 끝나는 제품 선호 |

## 빠른 시작

```sh
php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
```

- 접속: `http://127.0.0.1:8080/`
- 필요: PHP 8.1 이상, `pdo_mysql`, 쓰기 가능한 `config/`, `storage/`, 빈 MySQL 호환 DB
- 설치 후 관리자 진입: `/admin`

## 점검

```sh
./.tools/bin/check
```

Windows 또는 `sh`/WSL 없는 환경:

```sh
php .tools/bin/check.php
```

HTTP 스모크 점검:

```sh
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 php .tools/bin/smoke-http.php
```

- 상세 기준: [스모크 테스트 기준](docs/smoke-test.md)

## 설치와 배포

```sh
cd /path/to/document-root
git clone https://github.com/whitedot/toycore.git .
git checkout v0.1.1
```

- `v0.1.1`: 릴리스 태그 예시.
- 권장 설치: 문서 루트에 저장소 파일 직접 배치.
- 하위 경로 설치 시 `document-root/toycore/` 형태 사용.
- Git 사용 가능 환경: clone 또는 fork 기반 운영 권장.
- Git 사용 불가 공유호스팅: 릴리스 zip 사용 가능.
- DB 변경 반영: 파일 배포 후 `/admin/updates`에서 명시 실행.
- 커밋 제외: `config/config.php`, `storage/installed.lock`, 로그, 백업 파일.
- 내부 경로 보호: 루트 `.htaccess` 또는 서버 설정으로 `config/`, `database/`, `modules/`, `storage/`, `.git/` 직접 접근 차단.
- 배포 보호 기준: [deployment-protection.md](docs/deployment-protection.md)

## 현재 구현 범위

코어 담당:

- 웹 설치.
- 설정 파일 생성.
- DB 연결.
- 사이트 설정 조회.
- 모듈 설치/활성 상태 확인.
- 코어/모듈 SQL 업데이트.
- CSRF, 안전한 redirect, 보안 응답 헤더.
- DB 세션, 로그인 실패 제한, token hash 저장.
- 감사 로그, 예외 로그 마스킹.
- 개인정보 export 확장 지점.
- `paths.php` 기반 요청 처리.
- action 경로 검증, route 충돌 감지.
- request contract 기반 관리자 POST 보호 확인.

기본 설치:

```text
필수: core + member + admin
선택: modules/{module_key}에 포함된 번들/외부 모듈
```

## 번들 모듈

| 구분 | 모듈 | 역할 |
| --- | --- | --- |
| 필수 | `member` | 회원가입, 로그인, 계정, 비밀번호 재설정, 이메일 인증, 탈퇴/익명화, 개인정보 요청, 회원 그룹 |
| 필수 | `admin` | 대시보드, 사이트 설정, 모듈 관리, 회원 관리, 역할, 감사 로그, 개인정보 요청, 보관 정리, 업데이트 |
| 선택 운영 | `seo` | 기본 메타, `robots.txt`, `sitemap.xml`, 모듈 sitemap 수집 |
| 선택 운영 | `site_menu` | 사이트 메뉴, 모듈 `menu-links.php` 수집, 메뉴 출력 슬롯 |
| 선택 운영 | `banner` | 배너 관리, 클릭 집계, extension point 대상 출력 |
| 선택 운영 | `popup_layer` | 팝업레이어 관리, 노출 기간, 닫기 쿠키, extension point 대상 출력 |
| 선택 운영 | `notification` | 사이트 알림, 외부 발송 대기열, delivery 상태 |
| 선택 운영 | `community` | 게시판, 글, 댓글, 첨부, 신고, 쪽지, 스크랩, 회원 그룹 규칙, sitemap |
| 회원 연계 | `point` | 포인트 잔액, 거래 원장, 관리자 조정 |
| 회원 연계 | `deposit` | 예치금 잔액, 거래 원장, 관리자 조정 |
| 회원 연계 | `reward` | 적립금 잔액, 거래 원장, 관리자 조정 |

## 설계 원칙

- 코어는 작은 실행 기반.
- 도메인 정책은 모듈 소유.
- 자동 등록보다 명시적 파일 읽기.
- `index.php` -> `paths.php` -> action include 흐름.
- 모듈 연결은 계약 파일과 helper 호출로 처리.
- 모듈은 자기 테이블, 관리자 화면, 권한 정책, 업데이트 SQL 소유.
- 기능 추가보다 경계와 요청 흐름의 가독성 우선.

## 모듈 구조

```text
modules/{module_key}/
- module.php
- paths.php
- admin-menu.php
- output-slots.php
- actions/
- views/
- assets/
- lang/
- install.sql
- updates/
```

- 최소 설치 모듈: `module.php` + `install.sql`.
- URL 처리 모듈: `paths.php` + `actions/{name}.php` 추가.
- 모듈 작성 기준: [module-guide.md](docs/module-guide.md)
- 모듈 체크리스트: [module-checklist.md](docs/module-checklist.md)
- 예제 모듈: [sample_module](examples/sample_module/README.md)

## Extension Points

- 전역 hook 대신 명시적 계약 파일 사용.
- 화면/기능 확장 위치: `extension-points.php`.
- 배너/팝업레이어: 관리자 설정 시점에 extension point 읽기.
- 사용자 요청 시점: 저장된 대상 규칙 조회.

```text
module -> point -> slot -> subject
```

- 출력 호출: `toy_render_output_slot()`.
- 상세 규칙: [module-guide.md](docs/module-guide.md)

## 운영 범위

- 소규모 회원 기반 사이트와 모듈형 운영 화면에 초점.
- 프레임워크 생태계보다 파일 기반 요청 흐름과 배포 단순성 우선.
- 절차형 코드 특성상 규칙 문서와 모듈 경계 관리 중요.
- 프론트엔드는 서버 렌더링, vanilla JavaScript, plain CSS 중심.
- 공유호스팅 기준으로 설계해 worker, queue, 고성능 서버 기능은 별도 확장 영역.

## 보안 피드백

- 보안 취약점 또는 민감한 운영 위험: `kimminsup@gmail.com`
- 공개 이슈 등록 전 사전 제보 권장.

## 문서

- [핵심 설계 결정](docs/core-decisions.md)
- [Toycore 보안 모델](docs/security-model.md)
- [보안 체크리스트](docs/security-checklist.md)
- [DB 접근 정책](docs/database-access-policy.md)
- [배포 보호 기준](docs/deployment-protection.md)
- [릴리스 절차](docs/release-process.md)
- [스모크 테스트 기준](docs/smoke-test.md)
- [모듈 작성 가이드](docs/module-guide.md)
- [모듈 저장 위치 기준](docs/module-storage-policy.md)
- [모듈 배치와 업데이트 기준](docs/module-update-policy.md)
- [관리자 POST action 작성 규칙](docs/admin-post-action-rules.md)

## 예제

- [절차형 요청 흐름 예제](examples/procedural-flow-sample.php.txt)

## 라이선스

- [MIT License](LICENSE)
