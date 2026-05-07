# 설치 및 초기화 계획

Toycore는 저가형 웹호스팅에서도 설치할 수 있어야 하므로 CLI 설치를 필수로 하지 않습니다. 기본 설치는 웹 설치 화면을 기준으로 설계합니다.

## 목표

- DB 접속 정보 입력
- DB 테이블 prefix 선택
- 기본 설정 파일 생성
- 기본 코어 테이블 생성
- 기본 사이트 설정 저장
- 필수 모듈 설치와 등록
- 선택 모듈 설치 여부 선택
- 최초 관리자 계정 생성과 로그인 식별자 결정
- 설치 완료 후 설치 화면 잠금

## 설치 전 조건

- PHP 8.1 이상 충족
- PDO MySQL 또는 MariaDB 사용 가능
- 설정 파일을 쓸 수 있는 권한
- `storage/` 디렉터리 쓰기 가능
- DB 계정에 테이블 생성 권한 존재
- 내부 디렉터리와 설정 파일이 URL로 직접 열리지 않도록 서버 접근 차단 규칙 설정 가능

## 설치 흐름

```text
1. index.php 진입
2. 설정 파일 존재 여부 확인
3. 미설치 상태면 install 화면 include
4. 환경 점검
5. DB 접속 정보 입력
6. DB 테이블 prefix 확인
7. DB 연결 테스트
8. core install.sql 실행
9. `member` 모듈 install.sql 실행
10. `admin` 모듈 install.sql 실행
11. 배포본에 포함되어 있고 선택한 모듈의 install.sql 실행
12. 기본 사이트 설정 저장
13. 필수 모듈과 선택한 모듈 등록 및 활성화
14. 스키마 버전 기록
15. 최초 관리자 계정 생성
16. 설치 잠금 파일 생성
17. 관리자 화면으로 이동
```

## 설치 화면 고도화 기준

설치 화면은 단순 입력 폼이 아니라 운영자가 설치 전 상태와 설치 결과를 예측할 수 있는 초기 설정 화면으로 둡니다.

화면에서 바로 보여줄 항목:

- 현재 PHP 버전과 `pdo_mysql` 사용 가능 여부
- `config/config.php` 생성 가능 여부
- `storage/` 쓰기 가능 여부
- 현재 접속 URL과 HTTPS 필요 여부
- 이전 설치 실패 marker가 있다면 실패 단계와 잠금 파일 생성 여부
- 필수 모듈과 선택 모듈이 설치 후 제공하는 기능 요약
- 환경 확인 항목은 단순 상태만 표시하지 않고, 문제가 있을 때 운영자가 할 수 있는 조치 안내를 함께 표시

입력 그룹은 다음 순서를 유지합니다.

```text
환경 확인 -> DB 연결 정보 -> 사이트 기본 정보 -> 최초 관리자 -> 모듈 선택 -> 설치 시작
```

DB 비밀번호와 관리자 비밀번호는 설치 실패 후에도 HTML value로 다시 출력하지 않습니다.

최초 관리자 계정은 로그인 아이디를 비워두면 이메일로 로그인하고, 로그인 아이디를 입력하면 해당 아이디로 로그인합니다. 아이디를 입력해도 이메일은 비밀번호 재설정과 계정 안내를 위해 저장합니다.

관리자 아이디 허용 형식:

```text
영문 소문자로 시작
영문 소문자, 숫자, underscore 사용
4~40자
```

DB 테이블 prefix는 기본값 `toy_`를 우선합니다. 같은 DB에 여러 설치를 공존시키거나 호스팅 환경상 prefix 구분이 필요한 경우에만 설치 화면에서 바꿀 수 있습니다.

허용 형식:

```text
영문 소문자로 시작
영문 소문자와 숫자만 사용
마지막 문자는 underscore
최대 22자
```

예:

```text
toy_
site1_
demo2026_
```

설치 SQL과 런타임 SQL은 코드 기준의 `toy_` 식별자를 설정된 prefix로 실행 직전에 치환합니다. 기존 설치의 prefix를 운영 중 변경하면 기존 테이블을 찾지 못하므로 설치 후에는 변경하지 않습니다.

저가형 공유호스팅 기본 배포는 도메인이 프로젝트 루트를 직접 가리킨다고 가정합니다. 따라서 `도메인/` 요청은 루트 `index.php`로 진입합니다.

하위 디렉터리에 배포하는 경우도 지원합니다. 예를 들어 `https://example.com/toycore/`에서 설치하면 앱은 `SCRIPT_NAME` 기준으로 base path를 계산하고, CSS/JavaScript/form/link/redirect 경로를 `/toycore/...` 형태로 출력합니다. 설치 화면의 기본 URL도 현재 접속 base path를 포함한 값으로 제안합니다.

Toycore는 특정 서버용 보호 파일을 기본 배포 산출물로 제공하지 않습니다. `config/`, `storage/`, `database/`, `core/`, `modules/`, `docs/`, `examples/`, `.git/`, `.tools/`, `AGENTS.md`, `README.md` 같은 내부 경로와 문서는 호스팅 제어판, 서버 설정, 상위 디렉터리 배치 등 운영 환경의 방식으로 직접 접근을 차단해야 합니다. 설치기는 이 조건을 안내하고, 공개 라우팅 가능한 현재 URL에서는 직접 접근 가능 여부를 점검합니다.

테스트나 평가 목적의 설치는 공개 도메인의 HTTP URL에서도 진행할 수 있습니다. 다만 설치 화면은 HTTP 사용을 경고로 표시하고, 실제 운영 전에는 HTTPS 전환을 안내합니다. 내부 경로 점검 URL이 실제로 열리는 경우에는 HTTPS 여부와 관계없이 설치를 중단합니다.

## 설치 상태 판단

설치 상태는 다음 기준으로 판단합니다.

```text
config/config.php 존재
storage/installed.lock 존재
```

설치가 완료되면 `storage/installed.lock` 파일을 생성합니다.

DB의 `toy_site_settings` 필수 키와 `toy_modules` 기본 레코드는 설치 무결성 점검 기준으로 사용합니다. DB 장애가 발생했을 때 설치 화면으로 되돌아가지 않도록, 설치 여부 판단과 DB 상태 점검을 분리합니다.

## 생성할 설정 파일

예상 파일:

```text
config/config.php
```

`config/config.php`는 설치 화면이 생성하는 실제 설정 파일입니다. 별도 환경 파일 이름을 전제로 하지 않고, 저가형 웹호스팅에서 파일명을 보고 용도를 바로 알 수 있게 단순하게 둡니다.

예시:

```php
<?php

return [
    'env' => 'production',
    'debug' => false,
    'app_key' => 'generate-a-random-secret-during-install',
    'db' => [
        'host' => 'localhost',
        'name' => 'toycore',
        'user' => 'toycore',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
        'table_prefix' => 'toy_',
    ],
];
```

이 파일은 Git에 커밋하지 않습니다.

`app_key`는 설치 시 생성하는 사이트 비밀값입니다. CSRF, 서명, 로그인 식별자 HMAC hash처럼 같은 입력을 안전하게 다시 검증해야 하는 기능에 사용합니다. 운영 중 변경하면 기존 로그인 식별자 hash 조회가 깨질 수 있으므로, 설치 후에는 백업하고 신중하게 관리합니다.

## 기본 테이블

설치 시 기본 코어 테이블을 먼저 생성합니다.

```text
toy_site_settings
toy_modules
toy_module_settings
toy_schema_versions
```

`toy_schema_versions`는 설치된 core와 모듈의 스키마 버전을 기록합니다. 관리자 업데이트 화면은 이 기록을 기준으로 아직 적용되지 않은 SQL 파일만 실행합니다.

`member`, `admin`은 필수 모듈이고, `seo`, `site_menu`, `banner`는 standard 배포 패키지에 포함할 수 있는 선택 모듈입니다. `popup_layer`, `point`, `deposit`, `reward`, `notification`은 ops 배포 패키지에 포함할 수 있습니다. 테이블 생성은 각 모듈의 `install.sql` 책임으로 둡니다.

단일 사이트 기본값은 `toy_site_settings`의 다음 필수 키로 저장합니다.

```text
site.name
site.base_url
site.timezone
site.default_locale
site.status
```

## 기본 모듈 등록

초기 설치 시 다음 필수 모듈은 항상 등록하고 활성화합니다.

```text
member
admin
```

`member`와 `admin`은 코어에 내장하지 않지만, 기본 설치에 반드시 포함되는 필수 기본 모듈입니다.

다음 선택 모듈은 배포본에 코드가 포함되어 있고 설치 화면에서 선택한 경우에만 설치 SQL을 실행하고 `toy_modules`에 등록합니다.

```text
seo
site_menu
banner
popup_layer
point
deposit
reward
notification
```

선택하지 않은 모듈은 코드가 배포되어 있더라도 DB에는 등록하지 않습니다. 설치 후 `/admin/modules`에서 코드에 있지만 DB에 등록되지 않은 모듈을 설치할 수 있습니다. minimal 배포본처럼 선택 모듈 코드가 없으면 설치 화면의 선택 모듈 목록은 비어 있을 수 있습니다.

기본 배포 설치 단위:

```text
필수: core + member + admin
선택: seo + site_menu + banner + popup_layer + point + deposit + reward + notification
```

설치된 모듈은 기본 활성 상태로 시작합니다.

```text
member: enabled
admin: enabled
선택한 모듈: enabled
```

단, 코어는 이 모듈들의 내부 테이블 구조를 직접 알지 않습니다. 설치 과정에서 각 모듈의 `install.sql`을 명시적으로 실행합니다.

`admin` 모듈은 별도 계정 체계를 만들지 않고 `member` 모듈의 계정/인증 기반에 의존합니다.

## 최초 관리자 계정

최초 관리자 계정 생성은 `member` 모듈과 `admin` 모듈의 협력으로 처리합니다.

역할:

- `member` 모듈: 계정 생성과 비밀번호 hash 저장
- `admin` 모듈: 관리자 권한 부여
- 코어: 설치 흐름과 CSRF, 입력 검증, DB 트랜잭션 보조

기본 관리자 비밀번호는 설치 화면에서 직접 입력하게 하고, 하드코딩된 기본 비밀번호를 제공하지 않습니다.

최초 관리자 로그인 식별자는 설치 화면의 로그인 아이디 입력 여부로 결정합니다.

```text
로그인 아이디 비움: account_identifier_hash = email_hash
로그인 아이디 입력: account_identifier_hash = login_id_hash, email_hash는 별도 저장
```

로그인용 아이디 원문은 저장하지 않고 hash만 저장합니다. 이메일은 메일 발송과 계정 안내를 위해 저장합니다.

## 보안 원칙

- 설치 화면은 미설치 상태에서만 접근 가능
- 설치 완료 후 `installed.lock`이 있으면 설치 화면 접근 차단
- DB 비밀번호는 화면에 다시 출력하지 않음
- 설치 실패 시 민감 정보를 노출하지 않음
- 설치 중 생성한 관리자 비밀번호 원문은 저장하지 않음
- 설치 화면의 상태 변경 요청도 CSRF 검증

## 실패 처리

설치 중 실패하면 어느 단계에서 실패했는지 명확히 보여주되, DB 비밀번호나 상세 SQL 오류는 운영 모드에서 숨깁니다.

설치 실패 후 재시도 가능해야 합니다.

```text
DB 연결 실패 -> 설정 파일 생성 전이면 입력 화면으로 복귀
테이블 생성 실패 -> 생성된 테이블 목록 확인 안내
관리자 계정 생성 실패 -> 설치 완료 처리 금지
```

현재 구현은 설치 실패 시 `storage/install-failed.json`에 실패 단계를 기록합니다. 이 파일은 운영자가 재시도 전에 현재 상태를 확인하기 위한 marker이며, DB 비밀번호나 관리자 비밀번호 원문을 저장하지 않습니다. 설치가 성공하면 marker는 삭제됩니다.

## 설치 실패 후 재시도 가이드

설치 실패 후에는 먼저 다음을 확인합니다.

```text
1. storage/install-failed.json의 stage 확인
2. storage/logs/error.log의 같은 시각 오류 요약 확인
3. config/config.php 생성 여부 확인
4. storage/installed.lock 생성 여부 확인
5. DB에 toy_* 테이블이 부분 생성되었는지 확인
```

재시도 기준:

- `installed.lock`이 없으면 설치 완료 상태가 아니므로 설치 화면에서 다시 시도할 수 있습니다.
- `config.php`만 생성된 경우, 같은 DB 정보와 같은 `app_key`를 유지한 채 재시도합니다.
- 일부 테이블이 생성된 경우, 설치 SQL은 `CREATE TABLE IF NOT EXISTS`와 중복 방지 쿼리를 사용하므로 같은 빈도 낮은 실패는 재시도할 수 있습니다.
- 잘못된 DB를 지정했거나 초기 데이터를 폐기해도 되는 상황이면 DB의 `toy_` prefix 테이블을 백업 후 정리하고 다시 설치합니다.
- 관리자 계정 생성 이후 실패했다면, 같은 관리자 이메일로 재시도해 owner 권한 부여가 끝나는지 확인합니다.

재시도 전 삭제하면 안 되는 것:

- 운영 중인 사이트의 `config/config.php`
- 운영 중인 사이트의 `storage/installed.lock`
- 원인 확인 전 `storage/logs/error.log`
- 백업 없는 DB 테이블

## 설치 후 처리

설치 완료 후 다음을 수행합니다.

- `storage/installed.lock` 생성
- 설정 캐시가 있다면 삭제
- 관리자 로그인 화면 또는 관리자 대시보드로 이동
- 설치 관련 임시 세션 삭제

## 금지하는 방향

- CLI 설치만 지원
- 기본 관리자 계정/비밀번호 하드코딩
- 설치 완료 후에도 설치 화면 접근 가능
- 모듈 내부 테이블을 코어가 직접 생성
- DB 비밀번호를 로그에 기록
