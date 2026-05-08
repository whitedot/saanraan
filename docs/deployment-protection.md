# 배포 보호 기준

Toycore는 기본 배포 산출물에 특정 웹서버용 차단 파일을 포함하지 않는다. Apache `.htaccess`, Nginx location 규칙, 호스팅 패널 접근 제한은 운영 환경에 맞게 별도로 설정한다.

## 공개 진입점

운영 환경에서 웹 요청은 루트 `index.php`만 공개 진입점으로 사용한다.

직접 웹 접근을 차단해야 하는 경로:

```text
config/
core/
database/
docs/
examples/
modules/
storage/
.git/
.tools/
AGENTS.md
README.md
```

위 경로를 직접 열 수 있는 환경에서는 운영 설치를 진행하지 않는다.

## 설치 전 확인

설치 화면에서 DB 정보를 입력하기 전에 다음을 확인한다.

```text
/config/config.php 직접 접근 차단
/storage/installed.lock 직접 접근 차단
/database/core/install.sql 직접 접근 차단
/modules/member/install.sql 직접 접근 차단
/core/helpers.php 직접 접근 차단
/docs/deployment-protection.md 직접 접근 차단
/AGENTS.md 직접 접근 차단
/.tools/bin/check.php 직접 접근 차단
/.git/ 직접 접근 차단
```

서버가 차단 규칙을 지원하지 않는다면, 문서 루트를 `index.php`만 노출되는 별도 공개 디렉터리로 조정하거나 호스팅 패널의 접근 제한 기능을 사용한다.

## 공유호스팅 체크리스트

공유호스팅은 서버 설정을 직접 바꾸기 어려우므로 설치 전 다음 항목을 먼저 확인한다.

```text
PHP version이 프로젝트 지원 범위와 맞는지 확인
PDO MySQL 확장 사용 가능 여부 확인
ZipArchive 확장 사용 가능 여부 확인
storage/ 디렉터리 쓰기 가능 여부 확인
config/ 디렉터리 쓰기 가능 여부 확인
upload_max_filesize와 post_max_size가 모듈 zip 크기보다 큰지 확인
display_errors가 운영에서 꺼져 있는지 확인
```

`ZipArchive`가 없으면 관리자 모듈 화면에서 zip 업로드를 사용할 수 없다. 이 경우 FTP나 호스팅 파일 관리자로 모듈 파일을 배치한 뒤 관리자 화면에서 설치와 DB 업데이트를 진행한다.

`storage/`에 쓸 수 없으면 설치 잠금 파일, 오류 로그, 업데이트 실패 marker, 모듈 백업 디렉터리를 만들 수 없다. 설치 전에 쓰기 권한을 조정하고, 운영 중 권한을 바꾸는 경우 `storage/logs/error.log` 기록 여부를 다시 확인한다.

## 서버별 처리

서버별 예시는 프로젝트 기본 파일로 제공하지 않는다.

운영자는 다음 방식 중 환경에 맞는 방법을 선택한다.

```text
Apache: 가상호스트 또는 호스팅 패널의 접근 제한
Nginx: server/location 규칙
공유호스팅: 파일 관리자 또는 보안 메뉴의 디렉터리 접근 차단
```

## 로드밸런서와 클라우드 런타임

로드밸런서나 reverse proxy 뒤에서 운영할 때는 `trusted_proxies`에 신뢰할 수 있는 proxy IP/CIDR만 등록한다. Toycore는 신뢰된 proxy에서 온 `X-Forwarded-Proto`와 `X-Forwarded-For`만 HTTPS 여부와 클라이언트 IP 판단에 사용한다.

메일을 HTTP API transport로 보낼 때는 `mail.transport`를 `http_api`로 설정할 수 있다. 이 경우 endpoint는 공개 HTTPS URL이어야 하며, private/reserved/loopback/link-local/CGNAT/documentation/multicast 주소는 허용하지 않는다.

## 원칙

- 설정 파일과 저장소 메타데이터는 웹에서 읽을 수 없어야 한다.
- SQL 파일과 모듈 내부 PHP 파일은 직접 실행 대상이 아니다.
- 업로드나 생성 파일은 가능한 한 `storage/` 아래에 두고 직접 접근을 차단한다.
- 서버별 보호 규칙은 운영 환경 문서나 배포 자동화에서 관리한다.
- 웹에서 차단해야 할 경로를 차단할 수 없는 호스팅에는 운영 설치하지 않는다.
