# 스모크 테스트 기준

이 문서는 설치 직후, 배포 전, 운영 수정 후 최소한으로 확인할 HTTP 검증 범위를 정리한다. 목표는 모든 기능을 자동 테스트하는 것이 아니라, 핵심 요청 흐름이 깨졌거나 내부 파일이 노출되는 문제를 빠르게 발견하는 것이다.

## 기본 정적 점검

코드 변경 후 먼저 기본 점검을 실행한다.

```sh
php .tools/bin/check.php
```

이 점검은 다음을 확인한다.

```text
git diff --check
SQL 파일 비어 있음 여부
모듈 기본 계약 파일 구성
관리자 메뉴 path와 paths.php GET route 일치
전체 PHP 문법
```

## HTTP 스모크 점검

로컬 PHP 내장 서버나 스테이징 서버가 떠 있으면 다음 명령을 실행한다.

```sh
php .tools/bin/smoke-http.php http://127.0.0.1:8080
```

로컬 PHP 내장 서버는 개발용 router로 실행한다.

```sh
php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
```

router 없이 프로젝트 루트를 문서 루트로 내장 서버를 실행하면 실제 파일이 직접 응답될 수 있으므로 내부 파일 보호 검증에 사용하지 않는다.

직접 접근을 차단해야 하는 경로의 정본은 [배포 보호 기준](deployment-protection.md)이다. 이 문서의 HTTP 항목은 그 기준이 실제 서버 응답에서 지켜지는지 빠르게 확인하기 위한 스모크 검증 목록이다.

확인 항목:

```text
/ 응답이 500 없이 열리는지 확인
/login 응답이 500 없이 열리는지 확인
/admin 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/updates 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/assets/toycore.css 정적 파일 응답 확인
/database/core/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/member/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/core/helpers.php 직접 접근에서 PHP 코드가 노출되지 않는지 확인
/config/.gitignore 직접 접근에서 config 디렉터리 내용이 노출되지 않는지 확인
/storage/.gitignore 직접 접근에서 storage 디렉터리 내용이 노출되지 않는지 확인
/docs/deployment-protection.md 직접 접근에서 문서 내용이 노출되지 않는지 확인
/examples/sample_module/module.php 직접 접근에서 예제 모듈 코드가 노출되지 않는지 확인
/AGENTS.md 직접 접근에서 프로젝트 지침이 노출되지 않는지 확인
/README.md 직접 접근에서 루트 문서가 노출되지 않는지 확인
/.tools/bin/check.php 직접 접근에서 도구 코드가 노출되지 않는지 확인
/.git/HEAD 직접 접근에서 저장소 메타데이터가 노출되지 않는지 확인
```

설치 전 상태에서는 `/login`, `/admin`, 내부 경로 요청도 설치 화면으로 이어질 수 있다. 이 경우 200 또는 redirect는 허용한다. 중요한 기준은 PHP fatal error가 노출되지 않고, 보호되어야 할 내부 파일의 실제 내용이 직접 노출되지 않는 것이다.

## 수동 확인 시나리오

릴리스 전에는 다음 흐름을 브라우저에서 한 번 확인한다.

```text
1. 새 DB로 설치 화면 진입
2. 필수 모듈 설치 완료
3. 최초 owner 계정으로 로그인
4. /admin 대시보드 진입
5. /admin/modules에서 설치 버전과 코드 버전 확인
6. /admin/updates에서 미적용 SQL 목록 또는 없음 확인
7. /account에서 계정 화면 진입
8. 로그아웃 후 /admin 접근 시 로그인 흐름 확인
```

선택 모듈이 포함된 배포본은 다음 항목을 추가로 확인한다.

```text
선택 모듈 체크 후 설치 완료
선택 모듈 관리자 메뉴 노출
선택 모듈의 GET 관리자 path가 500 없이 열림
```

## 실패 시 확인 순서

HTTP 스모크 점검이 실패하면 다음 순서로 확인한다.

```text
1. 실패한 URL과 HTTP status
2. storage/logs/error.log
3. 최근 변경한 action 또는 helper의 PHP 문법
4. modules/{module_key}/paths.php의 method/path 매핑
5. 웹서버의 내부 디렉터리 접근 차단 규칙
```

보호 경로에서 내부 파일 내용이 보이면 코드 수정 전에 서버 배포 설정을 먼저 확인한다. 운영 환경에서 `config/`, `database/`, `modules/`, `storage/` 내부 파일이 직접 열리는 상태로 설치를 진행하지 않는다.
