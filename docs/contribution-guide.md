# 기여자 작업 기준

이 문서는 saanraan에 변경을 넣을 때 우선 확인할 작업 기준을 정리한다. 목표는 새 기여자가 전체 코드를 한 번에 외우지 않아도, 변경 위치와 검증 증거를 일관되게 남길 수 있게 하는 것이다.

## 먼저 읽을 문서

작업을 시작하기 전에 변경 성격에 맞는 문서를 확인한다.

| 변경 성격 | 먼저 볼 문서 |
| --- | --- |
| 프로젝트 방향, 경쟁 범위, 비목표 | [산란 포지셔닝 기준](positioning.md), GitHub 이슈와 마일스톤 |
| 코어와 모듈 경계 | [핵심 설계 결정](core-decisions.md), [모듈 작성 가이드](module-guide.md) |
| 모듈 상태와 릴리스 판단 | GitHub 이슈와 마일스톤 |
| 보안, 인증, 개인정보 | [산란 보안 모델](security-model.md), [보안 체크리스트](security-checklist.md) |
| DB, 원장, 금액성 흐름 | [DB 접근 정책](database-access-policy.md), 관련 GitHub 이슈 |
| 외부 라이브러리와 vendored asset | [외부 의존성 배치 기준](dependency-policy.md) |
| 배포, smoke, 릴리스 기록 | GitHub 이슈와 마일스톤 체크리스트 |

## 작업 범위 잡기

기본 판단은 다음 순서로 한다.

1. 코어가 꼭 알아야 하는 실행 기반인지 확인한다.
2. 도메인 정책이면 owning module에 둔다.
3. 여러 모듈이 함께 쓰더라도, 먼저 좁은 helper나 contract로 해결한다.
4. DB table은 `sr_` prefix를 사용하고, 모듈 소유 table을 선호한다.
5. 요청 흐름은 `paths.php`와 action include로 따라갈 수 있게 둔다.

새 기능을 추가할 때는 기능 목록을 늘리는 것보다 검증 가능한 상태를 먼저 정한다. 모듈 상태 등급이 올라가려면 구현 설명이 아니라 테스트, smoke, 수동 점검, 운영 점검 같은 증거가 필요하다.

## 고위험 변경 기준

다음 변경은 작은 수정처럼 보여도 고위험으로 취급한다.

- 로그인, 세션, CSRF, 권한, rate limit
- HTML sanitizer, CKEditor, 업로드, 다운로드, URL 임베드
- 포인트, 적립금, 예치금, 쿠폰, 유료 열람, 환불, 환전
- 개인정보 export, cleanup, 회원 탈퇴, 보존 정책
- queue, cron, 배치, 예약 발행, storage cleanup
- 설치, 업데이트, 배포 보호, nginx/Apache 설정

고위험 변경은 최소한 관련 전용 check나 smoke를 추가하거나 갱신한다. 자동화가 어려운 경우 GitHub 이슈나 마일스톤 체크리스트에 수동 검증 항목과 미검증 사유를 남긴다.

## 검증 명령

PHP 코드 변경 후 최소 다음 명령을 실행한다.

```bash
php .tools/bin/check.php
```

문서를 추가하거나 링크를 바꾼 경우 통합 점검 안의 `.tools/bin/check-doc-links.php`가 로컬 Markdown 링크 대상 파일 존재 여부와 문서에 적힌 `.tools/bin/*.php` 명령의 실제 파일 존재 여부를 확인한다. 외부 URL의 생존 여부를 보증하지는 않지만, 저장소 안 문서 이동과 삭제로 생기는 링크 부패와 오래된 로컬 점검 명령은 이 단계에서 잡아야 한다.

새 `check-*.php` 검증 도구를 추가할 때는 `.tools/bin/check.php` 통합 게이트에 연결한다. 오래 걸리는 deep QA처럼 의도적으로 독립 실행해야 하는 도구만 `.tools/bin/check-tool-gate-coverage.php`의 standalone 목록에 이유를 두고 남기며, 이 점검은 새 도구가 통합 게이트에서 빠지는 상태를 실패로 처리한다. 새 `smoke-*.php` 실행 도구를 추가할 때는 실행 조건과 위험을 해당 GitHub 이슈나 마일스톤 체크리스트에 기록한다.

HTTP 요청 흐름, 설치 화면, 공개/관리자 route, 보호 파일 노출에 영향이 있으면 로컬 서버와 HTTP smoke를 함께 실행한다.

```bash
php -S 127.0.0.1:8097 -t .tools/public .tools/bin/dev-router.php
SR_SMOKE_BASE_URL=http://127.0.0.1:8097 php .tools/bin/smoke-http.php
```

설치 DB가 필요한 고위험 변경은 설치 게이트 상태표를 먼저 남긴다. 자산 정합성, 운영 지연, 포인트 만료 dry-run 같은 read-only CLI 게이트는 이 명령으로 함께 기록한다. 현재 CLI 사용자가 `config/config.php`를 읽지 못하면 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 다시 실행한다.

```bash
php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved
```

로컬/staging base URL과 관리자 계정이 준비된 경우에는 구조화 증거도 함께 남긴다.

```bash
SR_SMOKE_BASE_URL=http://127.0.0.1:8097 \
SR_SMOKE_ADMIN_IDENTIFIER=<admin> \
SR_SMOKE_ADMIN_PASSWORD=<password> \
php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved
```

커뮤니티 인증 smoke처럼 데이터를 생성하거나 수정하는 검사는 운영 DB에서 실행하지 않는다.

## 문서 갱신 기준

다음 변경은 같은 작업에서 관련 문서를 갱신한다.

- DB schema, migration, module contract, request flow
- 관리자 화면, 입력 검증, 권한, 보안 정책
- 개인정보 처리, 보존, 삭제, export, cleanup
- 배포 가정, 운영 절차, 외부 의존성
- 검증 명령, smoke 기대값, 릴리스 판정 기준

Wiki 구현 명세가 아직 늦을 수 있는 1.0 전 작업에서는 저장소 `docs/`를 먼저 맞추고, Wiki 정리 필요성은 작업 기록이나 최종 보고에 남긴다.

## 리뷰 관점

리뷰할 때는 기능이 동작하는지보다 다음 질문을 먼저 본다.

- 요청 흐름이 파일에서 읽히는가?
- 코어와 모듈 경계가 지켜졌는가?
- 서버 측 검증이 프론트엔드 표시와 일치하는가?
- 실패했을 때 데이터가 중복 지급, 중복 차감, 개인정보 잔존으로 남지 않는가?
- 미설치, 비활성 모듈, 공유호스팅 제약에서 설명 가능한 상태로 실패하는가?
- 자동 또는 수동 검증 증거가 변경 위험에 맞는가?
