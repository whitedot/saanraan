# 서버별 배포 예시

Toycore는 루트의 `index.php`를 공개 진입점으로 사용합니다. 저가형 웹호스팅에서는 문서 루트와 쓰기 권한을 먼저 확인합니다.

이 문서는 예시만 제공합니다. 프로젝트에는 `.htaccess`를 포함하지 않습니다.

## 공통 준비

필수 확인:

```text
PHP 8.1 이상
PDO MySQL 확장
MySQL 또는 MariaDB
config 디렉터리 쓰기 가능
storage 디렉터리 쓰기 가능
```

설치 후에는 다음 파일이 생성됩니다.

```text
config/config.php
storage/installed.lock
```

이 파일들은 Git에 포함하지 않습니다.

## 배포 파일 기준

Toycore 배포는 현재 저장소의 파일 구조를 기준으로 합니다. 필수 모듈은 `member`, `admin`이며, 선택 모듈은 `modules/{module_key}` 폴더에 코드가 있을 때 설치 화면이나 `/admin/modules`에서 설치할 수 있습니다.

필요한 모듈을 따로 받은 경우에도 설치 후 zip 업로드나 FTP/SFTP로 `modules/{module_key}`에 배치하고 `/admin/modules`에서 설치합니다.

```mermaid
flowchart TD
    repo["toycore.git"] --> release["Git tag 또는 릴리스 zip"]
    release --> user["사용자 설치"]
    user --> install["웹 설치 화면"]
    install --> modules["modules/{module_key}"]
```

## 설치 방식 선택

릴리스 zip 설치는 Git을 사용할 수 없는 호스팅에서 가장 단순하다. 하지만 zip만 업로드한 운영 서버는 Git 이력이 없으므로, 현재 운영 파일이 어떤 태그에서 왔는지 확인하거나 다음 릴리스와의 차이를 검토하기 어렵다. 업데이트를 반복할 운영 사이트라면 Git 기반 설치를 우선 검토한다.

권장 기준:

```text
Git 사용 가능, SSH 또는 배포 자동화 가능
-> clone 또는 fork 기반 설치

Git 불가, FTP/SFTP 또는 파일 관리자만 가능
-> release zip 설치
```

공식 코드를 그대로 운영하고 서버에서 직접 수정하지 않는 설치라면 clone으로 충분하다.

```sh
git clone https://github.com/whitedot/toycore.git toycore
cd toycore
git checkout v0.1.1
```

위의 `v0.1.1`은 현재 공개 릴리스 예시다. 실제 설치할 때는 원하는 릴리스 태그를 사용한다.

운영자가 자체 패치, 전용 모듈, 호스팅별 배포 스크립트를 함께 관리해야 한다면 fork를 만든 뒤 fork를 운영 기준 저장소로 둔다. 공식 저장소는 `upstream`으로 추가해 새 릴리스를 가져온다.

```sh
git clone git@github.com:your-org/toycore.git toycore
cd toycore
git remote add upstream https://github.com/whitedot/toycore.git
git fetch upstream --tags
git checkout -b release/<release-tag> <release-tag>
```

릴리스 업데이트 절차:

```text
1. 운영 DB와 파일 백업
2. 스테이징에서 git fetch upstream --tags
3. 새 릴리스 태그 병합 또는 rebase
4. 충돌과 로컬 패치 확인
5. 웹 설치 상태와 관리자 로그인 확인
6. /admin/updates에서 미적용 SQL 검토 후 실행
7. 운영 서버에 같은 commit 배포
```

운영 서버에서 바로 `main`을 따라가지 않는다. 운영 반영 기준은 릴리스 태그 또는 검증된 운영 브랜치의 commit SHA로 고정한다. `config/config.php`, `storage/installed.lock`, `storage/logs`, `storage/module-backups`는 Git에 포함하지 않는다.

릴리스 zip만 사용할 수 있는 호스팅에서는 업로드한 zip 파일명, 릴리스 태그 또는 commit SHA, 적용 일자를 운영 기록으로 남긴다. 다음 업데이트 때는 기존 파일을 덮어쓰기 전에 파일 백업을 만들고, zip 교체 후 `/admin/updates`에서 DB 업데이트를 실행한다.

## PHP 내장 서버

로컬 확인용입니다.

```sh
./.tools/bin/php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
```

브라우저에서 `http://127.0.0.1:8080/`로 접속합니다.

## Apache 가상 호스트

서버 설정을 직접 수정할 수 있는 환경에서는 문서 루트를 프로젝트 루트로 지정할 수 있습니다. 이 경우 `/assets/`와 `/modules/{module_key}/assets/`만 정적 파일로 열고, 나머지 내부 경로는 먼저 차단한 뒤 모든 동적 요청을 `index.php`로 전달합니다.

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/toycore

    RedirectMatch 404 "^/(config|core|database|docs|examples|storage|\.git|\.tools)(/|$)"
    RedirectMatch 404 "^/(AGENTS\.md|README\.md)$"
    RedirectMatch 404 "^/modules/(?![a-z][a-z0-9_]{1,39}/assets/)"

    <Directory /var/www/toycore>
        Require all granted
        DirectoryIndex index.php
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

`FallbackResource`를 사용할 수 없는 공유호스팅에서는 호스팅 패널의 프론트 컨트롤러, 기본 문서, 오류 문서 설정을 먼저 확인합니다. 프로젝트 자체에는 `.htaccess`를 추가하지 않습니다.

## Nginx

서버 설정을 직접 수정할 수 있는 환경에서는 내부 경로를 먼저 차단하고, `/assets/`와 `/modules/{module_key}/assets/`만 직접 응답합니다. PHP 실행은 루트 `index.php`로 제한합니다.

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/toycore;
    index index.php;

    location ~ ^/(config|core|database|docs|examples|storage|\.git|\.tools)(/|$) {
        return 404;
    }

    location ~ ^/(AGENTS\.md|README\.md)$ {
        return 404;
    }

    location /assets/ {
        try_files $uri =404;
    }

    location ~ ^/modules/[a-z][a-z0-9_]{1,39}/assets/ {
        try_files $uri =404;
    }

    location /modules/ {
        return 404;
    }

    location / {
        try_files /index.php =404;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

## 로드밸런서와 클라우드 런타임

로드밸런서, CDN, 컨테이너 런타임 뒤에서 실행할 때는 Toycore가 실제 HTTPS 상태와 클라이언트 IP를 올바르게 해석할 수 있게 `config/config.php`를 조정합니다.

```php
'security' => [
    'force_https' => true,
    'trusted_proxies' => ['10.0.0.0/8'],
],
'session' => [
    'handler' => 'database',
],
```

인증 메일을 외부 HTTP API로 보내야 하는 환경에서는 mail transport를 `http_api`로 설정할 수 있습니다. endpoint는 공개 HTTPS URL이어야 하며, Toycore는 사설망 주소와 redirect를 허용하지 않습니다.

```php
'mail' => [
    'transport' => 'http_api',
    'from_email' => 'noreply@example.com',
    'from_name' => 'Toycore',
    'http_api' => [
        'endpoint' => 'https://api.example-mail.test/send',
        'token' => 'replace-with-secret-token',
    ],
],
```
