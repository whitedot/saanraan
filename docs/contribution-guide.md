# 기여자 작업 기준

이 문서는 saanraan에 변경을 넣을 때 우선 확인할 작업 기준을 정리한다. 목표는 새 기여자가 전체 코드를 한 번에 외우지 않아도, 변경 위치와 검증 증거를 일관되게 남길 수 있게 하는 것이다.

## 먼저 읽을 문서

작업을 시작하기 전에 변경 성격에 맞는 문서를 확인한다.

| 변경 성격 | 먼저 볼 문서 |
| --- | --- |
| 프로젝트 방향, 경쟁 범위, 비목표 | [산란 포지셔닝 기준](positioning.md), GitHub 이슈와 마일스톤 |
| 코어와 모듈 경계 | [핵심 설계 결정](core-decisions.md), [모듈 작성 가이드](module-guide.md) |
| 모듈 상태와 릴리스 판단 | [1.0 범위](1.0-scope.md), [모듈 상태표](module-status.md), [검증 상태와 증거 기준](verification-status.md), [운영 상태 점검 기준](operational-status.md) |
| 보안, 인증, 개인정보 | [산란 보안 모델](security-model.md), [보안 체크리스트](security-checklist.md) |
| DB, 원장, 금액성 흐름 | [DB 접근 정책](database-access-policy.md), 관련 GitHub 이슈 |
| 외부 라이브러리와 vendored asset | [외부 의존성 배치 기준](dependency-policy.md) |
| 배포, smoke, 릴리스 기록 | [Smoke 테스트 기준](smoke-test.md), [릴리스 절차](release-process.md), [릴리스 검증 기록 템플릿](release-verification-template.md) |

## 작업 범위 잡기

기본 판단은 다음 순서로 한다.

1. 코어가 꼭 알아야 하는 실행 기반인지 확인한다.
2. 도메인 정책이면 owning module에 둔다.
3. 여러 모듈이 함께 쓰더라도, 먼저 좁은 helper나 contract로 해결한다.
4. DB table은 `sr_` prefix를 사용하고, 모듈 소유 table을 선호한다.
5. 요청 흐름은 `paths.php`와 action include로 따라갈 수 있게 둔다.

새 기능을 추가할 때는 기능 목록을 늘리는 것보다 검증 가능한 상태를 먼저 정한다. 모듈 상태 등급이 올라가려면 구현 설명이 아니라 테스트, smoke, 수동 점검, 운영 점검 같은 증거가 필요하다.

## 기능 변경 완결성 확인

기능 변경은 먼저 소유 모듈을 확정한 뒤 그 모듈의 요청 흐름 전체를 읽는다. 여러 모듈에 비슷한 화면이 있어도 자연스러운 공통 기능 소유자가 없는 경우 새 공통 모듈이나 코어 기능을 만들지 않는다. 각 모듈이 자기 댓글, 게시물, 설문, 퀴즈 같은 도메인 정책을 계속 소유하도록 둔다.

코드를 수정하기 전과 검토할 때 다음 순서로 확인한다.

1. public/admin 진입점과 `paths.php`에서 대상 route를 찾는다.
2. action에서 인증, 권한, 설정, 상태 계산과 데이터 조회 순서를 확인한다.
3. 기본 theme/view와 fallback skin/view가 같은 상태 모델을 받는지 확인한다.
4. layout context에 필요한 CSS와 JavaScript가 명시적으로 병합되는지 확인한다.
5. 관련 POST action의 server-side validation, flash, redirect 흐름까지 확인한다.
6. 비회원/회원, 권한 허용/거부, 기능 활성/비활성, 빈 데이터/데이터 있음, 성공/실패, 미리보기·embedded·fragment처럼 소스에 실제로 존재하는 상태 분기를 기록한다.

전용 검사는 각 상태의 정상 출력뿐 아니라 없어야 하는 버튼, 링크, 문구, fallback, 중복 asset 같은 부정 조건도 확인한다. route가 200인지, 특정 class가 한 번 등장하는지만 확인하는 검사는 상태별 렌더링 증거를 대신하지 않는다. 브라우저나 테스트 계정이 없어 사용자가 지적한 조건을 실제로 렌더링하지 못했다면 완료 보고에서 그 범위를 분리하고 정확한 동일성이나 완전한 완료로 판정하지 않는다.

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

코드 변경 후에는 변경 범위에 맞는 자동 검사를 실행한다. 기본 검증은 `git diff --check`, 변경한 PHP 파일의 `php -l`, 그리고 가장 가까운 전용 fixture/check다. 예를 들어 관리자 메뉴 계약만 바꿨다면 `php .tools/bin/check-admin-navigation-runtime.php`처럼 해당 영역의 runtime fixture를 우선 실행한다.

공유 helper, 라우팅, 보안/권한, DB/schema, 설치/업데이트, cross-module contract, 공통 UI shell처럼 영향 범위가 넓은 변경이나 릴리스 판단이 필요한 변경은 통합 게이트를 실행한다.

GitHub `main` push와 pull request에서는 `Checks` workflow가 PHP 8.1/8.3의 통합 게이트, 번들 Composer 보안 권고, 깨끗한 checkout의 미설치 HTTP smoke를 다시 실행한다. 로컬 통과와 원격 통과를 모두 확인하되, 설치 DB나 테스트 계정이 필요한 mutation/browser smoke는 릴리스 검증 기록에서 별도로 다룬다.

```bash
php .tools/bin/check.php
```

문구, 번역, 관리자 메뉴 라벨, 아이콘 메타데이터처럼 request flow, route, schema, public contract, asset serving에 영향이 없는 표시명-only 변경은 관련 fixture와 문법 검사로 충분하면 통합 게이트를 생략할 수 있다. 이 경우 완료 보고에 생략 이유를 남긴다.

문서를 추가하거나 링크를 바꾼 경우 통합 점검 안의 `.tools/bin/check-doc-links.php`가 로컬 Markdown 링크 대상 파일 존재 여부와 문서에 적힌 `.tools/bin/*.php` 명령의 실제 파일 존재 여부를 확인한다. 외부 URL의 생존 여부를 보증하지는 않지만, 저장소 안 문서 이동과 삭제로 생기는 링크 부패와 오래된 로컬 점검 명령은 이 단계에서 잡아야 한다.

새 `check-*.php` 검증 도구를 추가할 때는 `.tools/bin/check.php` 통합 게이트에 연결한다. 오래 걸리는 deep QA처럼 의도적으로 독립 실행해야 하는 도구만 `.tools/bin/check-tool-gate-coverage.php`의 standalone 목록에 이유를 두고 남기며, 이 점검은 새 도구가 통합 게이트에서 빠지는 상태를 실패로 처리한다. 새 `smoke-*.php` 실행 도구를 추가할 때는 실행 조건과 위험을 해당 GitHub 이슈나 마일스톤 체크리스트에 기록한다.

HTTP 요청 흐름, 설치 화면, 공개/관리자 route, asset serving, auth guard, layout shell, 보안 헤더, 보호 파일 노출에 영향이 있으면 로컬 서버와 HTTP smoke를 함께 실행한다.

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

1.0 전 작업에서는 저장소 `docs/`를 현재 구현 기준으로 맞춘다. GitHub Wiki는 현재 운영 문서의 정본으로 사용하지 않는다.

## 리뷰 관점

리뷰할 때는 기능이 동작하는지보다 다음 질문을 먼저 본다.

- 요청 흐름이 파일에서 읽히는가?
- 코어와 모듈 경계가 지켜졌는가?
- 서버 측 검증이 프론트엔드 표시와 일치하는가?
- 실패했을 때 데이터가 중복 지급, 중복 차감, 개인정보 잔존으로 남지 않는가?
- 미설치, 비활성 모듈, 공유호스팅 제약에서 설명 가능한 상태로 실패하는가?
- 자동 또는 수동 검증 증거가 변경 위험에 맞는가?
