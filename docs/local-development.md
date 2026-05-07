# 로컬 개발 환경

이 저장소는 시스템 PHP 설치가 없어도 Docker 기반 래퍼로 PHP 명령을 사용할 수 있다.

이 래퍼는 로컬 개발과 문법 검사 편의를 위한 선택 도구이며, Toycore의 운영 배포 요건이나 기본 실행 환경은 아니다. 운영 배포는 일반 PHP 웹호스팅에서 루트 `index.php`가 실행되는 구조를 기준으로 한다.

## PHP 명령

```sh
./.tools/bin/php -v
```

첫 실행 때 `toycore-php:8.3-cli` 이미지를 만들며, `pdo_mysql` 확장을 포함한다.

## 내장 서버

```sh
./.tools/bin/php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
```

브라우저에서 `http://127.0.0.1:8080/`로 접속한다.

개발용 router는 빈 문서 루트인 `.tools/public`에서 실행한다. `/assets/`와 모듈 assets만 router가 직접 읽어 응답하고, `config/`, `database/`, `modules/`, `storage/` 같은 내부 경로 요청은 `index.php` 요청 흐름으로 보낸다. PHP 내장 서버를 프로젝트 루트 문서 루트로 실행하면 기존 파일을 직접 응답할 수 있으므로 배포 보호 검증에 사용하지 않는다.

Docker 래퍼로 실행하는 PHP는 컨테이너 안에서 동작한다. 호스트에서 실행 중인 MySQL에 연결할 때는 설치 화면의 DB host에 `host.docker.internal`을 입력한다.

## 문법 검사

```sh
find . -path './.git' -prune -o -path './.tools' -prune -o -name '*.php' -print0 | xargs -0 -n1 ./.tools/bin/php -l
```

## 전체 기본 점검

```sh
./.tools/bin/check
```

이 명령은 `git diff --check`, 전체 PHP 문법 검사, SQL 파일 비어 있음 여부, 모듈 기본 계약 파일 구성, 런타임/회원 인증 정책 검사를 함께 확인한다. PHP 문법 검사는 로컬 PHP 또는 Docker 기반 PHP 래퍼로 실행한다.

로컬 PHP가 있으면 Docker 없이 전체 점검을 실행한다. 로컬 PHP가 없으면 Docker 기반 PHP 래퍼를 사용하므로 Docker 또는 OrbStack 실행 상태가 필요하다. Docker 또는 OrbStack이 꺼져 있어도 `git diff --check`, SQL 파일 비어 있음 여부, 모듈 기본 계약 파일 구성 검사는 먼저 실행된다. 이 단계가 통과하면 `toycore non-Docker checks completed.` 메시지가 출력되고, 이후 정책 검사와 PHP 문법 검사 단계에서 PHP 실행 환경을 확인한다.

Windows처럼 sh 또는 WSL이 없는 환경에서 로컬 PHP를 사용할 수 있다면 다음 명령으로 같은 기본 검사를 실행한다.

```sh
php .tools/bin/check.php
```

이 PHP 점검 도구는 `git diff --check`, 전체 PHP 문법 검사, SQL 파일 비어 있음 여부, 모듈 기본 계약 파일 구성, 관리자 메뉴 path와 모듈 `paths.php` GET route 일치 여부, 런타임/회원 인증 정책 검사를 확인한다.

공식 모듈 registry 구조만 확인할 때:

```sh
php .tools/bin/check-module-index.php
```

## 외부 모듈 제작

외부 모듈 저장소의 최소 구조를 만들 때:

```sh
php .tools/bin/create-external-module.php banner /path/to/toycore-module-banner
```

GitHub Actions 자동 점검 파일 없이 시작하려면:

```sh
php .tools/bin/create-external-module.php banner /path/to/toycore-module-banner --no-ci
```

zip을 만들기 전 모듈 구조를 확인할 때는 Toycore Git 저장소와 모듈 Git 저장소의 상대 위치를 가정하지 않는다. Toycore 저장소 루트에서 실행한다면 모듈 저장소의 `module/` 경로를 명시한다.

```sh
php .tools/bin/check-external-module.php /path/to/toycore-module-banner/module banner
```

모듈 저장소 루트에서 실행한다면 Toycore 저장소 경로를 지정한다.

```sh
TOYCORE_REPO=/path/to/toycore
php "$TOYCORE_REPO/.tools/bin/check-external-module.php" module banner
```

스캐폴딩 도구가 만든 모듈 저장소에서는 다음 명령으로 업로드용 zip을 만들 수 있다.

```sh
php .tools/bin/package-module 2026.05.001
```

## HTTP 스모크 점검

내장 서버를 실행한 뒤 최소 HTTP 점검을 실행할 수 있다.

```sh
php .tools/bin/smoke-http.php http://127.0.0.1:8080
```

이 점검은 홈, 로그인, 관리자 진입, 관리자 업데이트 진입, 공통 CSS 응답과 내부 파일 직접 접근 차단 여부를 확인한다. 자세한 기준은 [스모크 테스트 기준](smoke-test.md)을 따른다.
