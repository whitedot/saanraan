# 배포 보호 기준

산란은 공유호스팅과 Apache 배포를 위한 기본 `.htaccess`를 루트에 포함한다. 이 파일은 내부 디렉터리 직접 접근을 차단하고, 공개 정적 asset과 가상 URL 요청만 허용하는 기준선이다. 기본 아이콘셋인 Google Material Symbols는 Google Fonts CDN에서 호출하고, CDN이 막히는 환경을 위해 번들 폰트를 fallback으로 둔다.

다만 `.htaccess`는 Apache에서 `AllowOverride`와 `mod_rewrite`가 활성화된 경우에만 적용된다. Nginx, Caddy, IIS, 일부 관리형 호스팅, 또는 `.htaccess`를 무시하는 Apache 설정에서는 같은 차단 규칙을 서버 설정이나 호스팅 패널에서 별도로 적용해야 한다. nginx에서는 [nginx 샘플 설정](deployment/nginx-saanraan.conf)을 기준으로 운영 환경에 맞는 `server/location` 규칙을 구성한다.

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
.claude/
AGENTS.md
README.md
LICENSE
.gitignore
.htaccess
.env
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

Apache에서 위 경로가 열리면 루트 `.htaccess`가 적용되지 않는 상태다. 서버가 `.htaccess`를 지원하지 않는다면, 문서 루트를 `index.php`와 공개 asset만 노출되는 별도 공개 디렉터리로 조정하거나 호스팅 패널의 접근 제한 기능을 사용한다. nginx처럼 서버 설정을 직접 관리할 수 있는 환경에서는 아래 nginx 기준을 먼저 적용한다.

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

`ZipArchive`가 없으면 관리자 모듈 화면에서 zip 업로드를 사용할 수 없다. 이 경우 FTP나 호스팅 파일 관리자로 모듈 파일을 배치한 뒤 관리자 화면에서 설치와 DB 업데이트를 진행한다. zip 업로드는 소유자 비밀번호 재확인을 통과한 요청에서만 모듈 파일 반영을 일시적으로 허용하고, 업로드 처리가 끝나면 자동으로 다시 비활성화한다.

`storage/`에 쓸 수 없으면 설치 잠금 파일, 오류 로그, 업데이트 실패 marker, 모듈 백업 디렉터리, 업로드 파일 저장 디렉터리를 만들 수 없다. 설치 전에 웹서버/PHP 실행 사용자가 `storage/` 아래에 하위 디렉터리를 만들 수 있도록 쓰기 권한을 조정하고, 운영 중 권한을 바꾸는 경우 `storage/logs/error.log` 기록 여부와 이미지 업로드 저장 여부를 다시 확인한다. 권한을 넓힐 수 없는 호스팅에서는 사용하는 모듈의 저장 디렉터리 예를 들어 `storage/seo`, `storage/banner`, `storage/logo_manager`, `storage/content`, `storage/community`를 웹서버 사용자가 쓸 수 있게 미리 만든다.

## 서버별 처리

Apache 또는 Apache 호환 공유호스팅은 기본 제공 `.htaccess`를 우선 사용한다. 설치 전에 `/database/core/install.sql`, `/modules/member/install.sql`, `/.git/HEAD` 같은 내부 경로가 403 또는 404로 막히는지 확인하고, `/assets/tokens.css`, `/assets/public-foundation.css`, `/modules/admin/assets/tokens.css` 같은 공개 asset과 `/assets/fonts/material-symbols-outlined.ttf` fallback 폰트가 정적 파일로 응답하는지 확인한다.

nginx는 PHP-FPM과 front controller 구성을 사용한다. 저장소의 [nginx 샘플 설정](deployment/nginx-saanraan.conf)을 운영 서버 설정에 복사한 뒤 `server_name`, `root`, `fastcgi_pass`를 환경에 맞게 바꾼다. `location` 순서는 보안 규칙의 일부이므로 유지한다. 특히 `/modules/{module_key}/assets/`와 CKEditor 공개 파일은 허용하되, 그 밖의 `modules/` 내부 파일은 직접 열리지 않아야 한다.

nginx 적용 후 다음 응답을 확인한다.

```text
/assets/tokens.css 정적 CSS 응답
/assets/public-foundation.css 정적 CSS 응답
/assets/public-layout.css 정적 CSS 응답
/modules/admin/assets/tokens.css 정적 CSS 응답
/assets/fonts/material-symbols-outlined.ttf 정적 font/ttf 응답
/modules/content/assets/public.css 정적 CSS 응답
/modules/community/assets/community-public.css 정적 CSS 응답
/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js 정적 JavaScript 응답
/login 또는 /admin 같은 가상 경로가 index.php를 통해 응답
/database/core/install.sql 직접 접근 차단
/modules/member/install.sql 직접 접근 차단
/modules/member/module.php 직접 접근 차단
/storage/.gitignore 직접 접근 차단
/.git/HEAD 직접 접근 차단
```

운영자는 다음 방식 중 환경에 맞는 방법을 선택하거나 보완한다.

```text
Apache: 기본 .htaccess, 가상호스트, 또는 호스팅 패널의 접근 제한
Nginx: docs/deployment/nginx-saanraan.conf를 기준으로 한 server/location 규칙
공유호스팅: 파일 관리자 또는 보안 메뉴의 디렉터리 접근 차단
```

## 로드밸런서와 클라우드 런타임

로드밸런서나 reverse proxy 뒤에서 운영할 때는 `trusted_proxies`에 신뢰할 수 있는 proxy IP/CIDR만 등록한다. 산란은 신뢰된 proxy에서 온 `X-Forwarded-Proto`와 `X-Forwarded-For`만 HTTPS 여부와 클라이언트 IP 판단에 사용한다.

DB 비밀번호는 `config/config.php`의 `db.password_env`에 지정한 환경변수가 있으면 그 값을 우선 사용한다. VPS, 클라우드, PHP-FPM 설정을 직접 제어할 수 있는 서버에서는 `SR_DB_PASSWORD` 같은 환경변수로 DB 비밀번호를 주입하고 `db.password`를 비워 둔다.

카페24 일반 웹호스팅처럼 환경변수 주입을 보장하기 어려운 공유호스팅에서는 `db.password`에 DB 비밀번호를 저장하는 fallback을 허용한다. 이 경우 `config/config.php`는 웹 서버 사용자만 읽을 수 있도록 가능한 한 `600` 권한으로 두고, `config/` 직접 접근 차단과 `.htaccess` 적용 여부를 반드시 함께 점검한다.

배포 후에는 다음 로컬 점검으로 `config/config.php` 파일 권한과 DB 비밀번호 설정을 확인한다. 환경변수가 없고 `db.password` fallback이 있으면 공유호스팅용 경고를 출력하되 통과한다.

```bash
php .tools/bin/check-deployment-config.php
```

메일을 HTTP API transport로 보낼 때는 `mail.transport`를 `http_api`로 설정할 수 있다. 이 경우 endpoint는 공개 HTTPS URL이어야 하며, private/reserved/loopback/link-local/CGNAT/documentation/multicast 주소는 허용하지 않는다.

파일 저장소를 S3로 바꾸려면 `storage.default`를 `s3`로 설정하고 bucket, region, endpoint, credential env를 지정한다. 운영 환경에서는 `endpoint`와 `public_base_url`이 HTTPS여야 하며, HTTP S3-compatible endpoint는 개발 환경 검증용으로만 사용한다. 배너 이미지는 공개 URL이 있으면 `public_base_url`을 사용할 수 있고, 커뮤니티 첨부는 권한 확인 후 짧은 presigned URL로 전달한다.

```php
'storage' => [
    'default' => 's3',
    's3' => [
        'bucket' => 'example-bucket',
        'region' => 'ap-northeast-2',
        'endpoint' => '',
        'public_base_url' => '',
        'path_style' => false,
        'access_key_env' => 'SR_S3_ACCESS_KEY',
        'secret_key_env' => 'SR_S3_SECRET_KEY',
    ],
],
```

## 원칙

- 설정 파일과 저장소 메타데이터는 웹에서 읽을 수 없어야 한다.
- SQL 파일과 모듈 내부 PHP 파일은 직접 실행 대상이 아니다.
- 업로드나 생성 파일은 가능한 한 `storage/` 아래에 두고 직접 접근을 차단한다.
- Apache 기본 보호 규칙은 루트 `.htaccess`에 포함한다.
- nginx 기본 보호 예시는 `docs/deployment/nginx-saanraan.conf`에 포함한다.
- 서버별 추가 보호 규칙은 운영 환경 문서나 배포 자동화에서 관리한다.
- 웹에서 차단해야 할 경로를 차단할 수 없는 호스팅에는 운영 설치하지 않는다.
