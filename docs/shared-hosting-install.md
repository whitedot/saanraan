# 저가형 호스팅 설치 절차

이 문서는 FTP, SFTP, 호스팅 패널 중심으로 운영하는 저가형 PHP 호스팅에 Toycore를 설치할 때의 순서를 정리한다. 목표는 설치 화면을 먼저 여는 것이 아니라, 내부 파일이 웹에서 직접 노출되지 않는 조건을 확인한 뒤 설치를 진행하는 것이다.

## 설치 전 준비

호스팅 관리자 화면에서 다음 값을 먼저 확인한다.

```text
PHP 8.1 이상 사용 가능 여부
PDO MySQL 확장 사용 가능 여부
MySQL 또는 MariaDB DB명
DB 사용자
DB 비밀번호
DB host
문서 루트 위치
FTP, SFTP 또는 SSH 접속 정보
config/ 디렉터리 쓰기 가능 여부
storage/ 디렉터리 쓰기 가능 여부
upload_max_filesize, post_max_size
ZipArchive 확장 사용 가능 여부
```

`ZipArchive`가 없으면 관리자 모듈 화면에서 zip 업로드를 사용할 수 없다. 이 경우 모듈 파일은 FTP나 호스팅 파일 관리자로 `modules/{module_key}`에 올리고, 설치와 DB 업데이트는 관리자 화면에서 진행한다.

## 업로드 전 로컬 점검

로컬에서 기본 점검을 먼저 실행한다.

```sh
php .tools/bin/check.php
```

릴리스 zip을 사용할 경우 릴리스 페이지에서 원하는 태그의 source zip 또는 maintainer가 제공한 단일 설치 zip을 내려받아 압축을 푼다.

서버에서 Git을 사용할 수 있다면 릴리스 zip 업로드보다 clone 또는 fork 기반 설치가 업데이트 관리에 유리하다. zip 설치는 Git, SSH, CLI를 사용할 수 없는 호스팅을 위한 경로로 보고, Git 사용 가능 환경의 구체 절차는 [서버별 배포 예시](deployment-examples.md)의 설치 방식 선택을 따른다.

테스트 설치라면 현재 저장소를 그대로 업로드할 수 있다. 운영 설치에서는 `.git/`, 로컬 로그, 임시 파일, 개발 중 생성된 `dist/` 외 파일을 올리지 않는다.

## 업로드 대상

최소 설치에 필요한 기본 구성은 다음과 같다.

```text
index.php
assets/
config/.gitignore
core/
database/
lang/
modules/member/
modules/admin/
storage/.gitignore
README.md
```

운영자가 서버에서 참고할 문서가 필요하면 `docs/`를 함께 올릴 수 있다. 단, `docs/`를 올리는 경우에도 웹 직접 접근은 차단되어야 한다.

## 문서 루트 배치

호스팅의 문서 루트에 Toycore 파일을 배치한다. 문서 루트 이름은 호스팅마다 다르므로, 기본 `index.html` 또는 `index.php`가 실행되는 위치를 기준으로 확인한다.

기존 기본 페이지 파일이 있다면 삭제하지 말고 먼저 백업한다.

```text
index.html -> index.html.bak
```

Toycore의 공개 진입점은 루트 `index.php` 하나다. `core/`, `database/`, `modules/` 내부 PHP와 SQL 파일은 직접 실행 대상이 아니다.

## 내부 경로 접근 차단

설치 화면에서 DB 정보를 입력하기 전에 다음 경로가 직접 열리지 않는지 확인한다.

```text
/database/core/install.sql
/modules/member/install.sql
/core/helpers.php
/config/.gitignore
/storage/.gitignore
/docs/deployment-protection.md
/examples/sample_module/module.php
/AGENTS.md
/README.md
/.tools/bin/check.php
/.git/HEAD
```

SQL 내용, PHP 코드, 문서 원문, `.gitignore` 내용, Git metadata가 브라우저에 그대로 보이면 설치를 진행하지 않는다.

호스팅에서 `.htaccess`를 지원한다면 운영 환경에 맞게 차단 규칙을 직접 추가한다. 프로젝트 저장소는 특정 서버용 차단 파일을 기본 배포물로 포함하지 않는다.

예시:

```apache
Options -Indexes

<FilesMatch "^(AGENTS\.md|README\.md)$">
    Require all denied
</FilesMatch>

RedirectMatch 404 ^/(config|core|database|docs|examples|modules|storage|\.git|\.tools)(/|$)
```

호스팅에서 위 문법을 지원하지 않으면 호스팅 패널의 디렉터리 접근 제한, 문서 루트 조정, 파일 관리자 접근 제한 기능을 사용한다. 차단할 방법이 없는 공개 호스팅에서는 운영 설치하지 않는다.

## 쓰기 권한 확인

설치 전에 다음 경로에 PHP가 파일을 만들 수 있어야 한다.

```text
config/
storage/
```

설치 후에는 다음 파일이 생성된다.

```text
config/config.php
storage/installed.lock
```

`storage/`에 쓸 수 없으면 오류 로그, 설치 실패 marker, 업데이트 실패 marker, 모듈 백업을 만들 수 없다. 설치 후 관리자에서 모듈 파일 교체나 업데이트를 사용할 계획이라면 `storage/module-backups`, `storage/logs` 생성도 가능해야 한다.

## 웹 설치

브라우저에서 사이트 루트로 접속한다.

```text
https://example.com/
```

설치 화면에서 DB 정보를 입력하고 최초 owner 계정을 만든다.

설치가 실패하면 다음 순서로 확인한다.

```text
1. storage/install-failed.json
2. storage/logs/error.log
3. config/config.php 생성 여부
4. storage/installed.lock 생성 여부
5. DB 테이블 일부 생성 여부
```

실패 원인을 확인하기 전에는 `config/config.php`, `storage/installed.lock`, 오류 로그를 삭제하지 않는다.

## 설치 후 확인

설치가 끝나면 다음 화면을 확인한다.

```text
/login
/account
/admin
/admin/modules
/admin/updates
```

관리자에서는 다음 항목을 확인한다.

```text
member 모듈 설치 상태
admin 모듈 설치 상태
설치 버전과 코드 버전 일치 여부
미적용 SQL 없음
대시보드 복구 marker 없음
```

## HTTP 스모크 점검

로컬 PC에서 설치한 도메인을 대상으로 HTTP 스모크 점검을 실행한다.

```sh
php .tools/bin/smoke-http.php https://example.com
```

이 점검은 홈, 로그인, 관리자 진입, 관리자 업데이트 진입, CSS 응답, 내부 파일 직접 접근 차단 여부를 확인한다. 내부 파일 노출 항목이 실패하면 Toycore 설정이 아니라 호스팅의 웹 접근 차단 설정을 먼저 수정한다.

## 운영 전 최종 기준

다음 조건을 모두 만족해야 운영 전환 대상으로 본다.

```text
루트 index.php가 실행된다.
config/와 storage/에 PHP가 쓸 수 있다.
내부 디렉터리와 루트 보조 파일이 웹에서 직접 열리지 않는다.
설치 후 config/config.php와 storage/installed.lock이 생성됐다.
/admin/modules에서 member, admin 모듈 상태가 정상이다.
/admin/updates에서 미적용 SQL이 없다.
HTTP 스모크 점검이 통과한다.
```
