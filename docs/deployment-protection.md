# 배포 보호 기준

산란은 공유호스팅과 Apache 배포를 위한 기본 `.htaccess`를 루트에 포함한다. 이 파일은 내부 디렉터리 직접 접근을 차단하고, 공개 정적 asset과 가상 URL 요청만 허용하는 기준선이다. 기본 아이콘셋인 Google Material Symbols는 CSP 친화적인 공유호스팅 배포를 위해 번들 폰트로 직접 제공한다.

다만 `.htaccess`는 Apache에서 `AllowOverride`와 `mod_rewrite`가 활성화된 경우에만 적용된다. Nginx, Caddy, IIS, 일부 관리형 호스팅, 또는 `.htaccess`를 무시하는 Apache 설정에서는 같은 차단 규칙을 서버 설정이나 호스팅 패널에서 별도로 적용해야 한다. nginx에서는 [nginx 루트 URL 샘플 설정](deployment/nginx-saanraan.conf) 또는 [nginx 서브디렉터리 URL 샘플 설정](deployment/nginx-saanraan-subdirectory.conf)을 기준으로 운영 환경에 맞는 `server/location` 규칙을 구성한다.

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
.env.*
```

위 경로를 직접 열 수 있는 환경에서는 운영 설치를 진행하지 않는다.

예외적으로 `storage/cache/thumbnails/` 아래의 생성된 이미지 썸네일은 공개 이미지 캐시로 직접 접근을 허용할 수 있다. 이 예외는 하위 디렉터리와 파일명 패턴이 helper가 만든 형식이고 확장자가 JPEG/PNG/GIF/WebP인 파일에만 적용해야 하며, `storage/`의 다른 파일이나 실행 가능한 파일은 계속 차단해야 한다. 새 썸네일 캐시는 `storage/cache/thumbnails/{module_key}/{hash-prefix}/{hash}_{variant}_{source_version}.{ext}` 형식을 사용하고, 구 버전 캐시 정리를 위해 기존 `{hash-prefix}/{hash}_{variant}_{mtime}.{ext}` 형식도 직접 접근 예외로 남긴다. 본문 에디터 이미지처럼 권한 검사가 필요한 private 썸네일은 `storage/cache/private-thumbnails/` 아래에 만들고 PHP endpoint가 스트리밍하므로 직접 접근 예외에 포함하지 않는다. 사이트가 하위 경로에 설치된 경우 썸네일 helper가 반환하는 공개 캐시 URL도 사이트 base path를 포함해야 한다.

## 설치 전 확인

설치 화면에서 DB 정보를 입력하기 전에 다음을 확인한다.

```text
/config/config.php 직접 접근 차단
/storage/installed.lock 직접 접근 차단
/database/core/install.sql 직접 접근 차단
/modules/member/install.sql 직접 접근 차단
/core/helpers.php 직접 접근 차단
/core/request-bootstrap.php 직접 접근 차단
/docs/deployment-protection.md 직접 접근 차단
/AGENTS.md 직접 접근 차단
/.tools/bin/check.php 직접 접근 차단
/.git/ 직접 접근 차단
/.env.local 직접 접근 차단
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

`ZipArchive`가 없으면 관리자 모듈 화면에서 zip 업로드를 처리할 수 없다. 이 경우 FTP나 호스팅 파일 관리자로 모듈 파일을 배치한 뒤 관리자 화면에서 설치, DB 업데이트, 파일 전용 업데이트 반영을 진행한다. 파일 전용 업데이트 반영은 `ZipArchive` 없이도 소유자가 `/admin/modules`에서 모듈 파일 반영을 일시 허용한 동안 사용할 수 있다. `ZipArchive`가 있으면 소유자가 `/admin/modules`에서 모듈 파일 반영을 일시 허용한 뒤 zip 업로드 버튼을 사용할 수 있다. zip 업로드 요청은 소유자 비밀번호 재확인을 통과하고 모듈 소스 반영이 일시 허용된 경우에만 처리하며, 업로드가 끝나면 자동으로 다시 비활성화한다.

`storage/`에 쓸 수 없으면 설치 잠금 파일, 오류 로그, 업데이트 실패 marker, 모듈 백업 디렉터리, 업로드 파일 저장 디렉터리를 만들 수 없다. 설치 전에 웹서버/PHP 실행 사용자가 `storage/` 아래에 하위 디렉터리를 만들 수 있도록 쓰기 권한을 조정하고, 운영 중 권한을 바꾸는 경우 `storage/logs/error.log` 기록 여부와 이미지 업로드 저장 여부를 다시 확인한다. 권한을 넓힐 수 없는 호스팅에서는 사용하는 모듈의 저장 디렉터리 예를 들어 `storage/seo`, `storage/banner`, `storage/logo_manager`, `storage/content`, `storage/community`를 웹서버 사용자가 쓸 수 있게 미리 만든다.

## 서버별 처리

Apache 또는 Apache 호환 공유호스팅은 기본 제공 `.htaccess`를 우선 사용한다. 설치 전에 `/database/core/install.sql`, `/modules/member/install.sql`, `/.git/HEAD` 같은 내부 경로가 403 또는 404로 막히는지 확인하고, `/assets/reset.css`, `/assets/layout.css`, `/assets/module.css`, `/assets/editor-md.css`, `/assets/editor-ck.css`, `/assets/theme/sample.css`, `/assets/ui-kit.css`, `/assets/ui-kit-layout.css`, `/modules/content/theme/basic/assets/reset.css`, `/modules/content/theme/basic/assets/layout.css`, `/modules/content/theme/basic/assets/module.css`, `/modules/content/theme/sample/assets/theme.css`, `/modules/content/assets/layout.js`, `/modules/content/assets/module.js`, `/modules/community/theme/basic/assets/reset.css`, `/modules/community/theme/basic/assets/layout.css`, `/modules/community/theme/basic/assets/module.css`, `/modules/community/theme/sample/assets/theme.css`, `/modules/community/assets/layout.js`, `/modules/community/assets/module.js`, `/modules/member/skins/basic/skin.css`, `/modules/quiz/theme/basic/assets/layout.css`, `/modules/quiz/theme/sample/assets/theme.css`, `/modules/quiz/assets/layout.js`, `/modules/quiz/assets/module.js`, `/modules/survey/theme/basic/assets/layout.css`, `/modules/survey/theme/sample/assets/theme.css`, `/modules/survey/assets/layout.js`, `/modules/survey/assets/module.js`, `/modules/admin/assets/tokens.css` 같은 공개 asset과 `/assets/fonts/material-symbols-outlined.ttf` fallback 폰트가 정적 파일로 응답하는지 확인한다. 썸네일 캐시를 사용하는 환경에서는 `/storage/cache/thumbnails/community/{hash-prefix}/{hash}_{variant}_{source_version}.jpg` 같은 생성 파일만 열리고 `/storage/cache/private-thumbnails/...`, `/storage/.gitignore`, 임의 storage 파일은 계속 막히는지도 확인한다.

nginx는 PHP-FPM과 front controller 구성을 사용한다. 도메인 루트 URL에 배포하면 저장소의 [nginx 루트 URL 샘플 설정](deployment/nginx-saanraan.conf)을 운영 서버 설정에 복사한 뒤 `server_name`, `root`, `fastcgi_pass`를 환경에 맞게 바꾼다. `/saanraan/` 같은 URL 서브디렉터리에 배포하면 [nginx 서브디렉터리 URL 샘플 설정](deployment/nginx-saanraan-subdirectory.conf)을 사용한다. 이때 `root`는 프로젝트 디렉터리가 아니라 그 부모 디렉터리를 가리켜야 한다. 예를 들어 파일이 `/var/www/example.com/saanraan`에 있고 URL이 `/saanraan/`이면 `root /var/www/example.com;`로 둔다. `location` 순서는 보안 규칙의 일부이므로 유지한다. 특히 `/modules/{module_key}/assets/`, `/modules/{module_key}/theme/{theme_key}/assets/`, `/modules/{module_key}/skins/{skin_key}/`와 CKEditor 공개 파일은 허용하되, 그 밖의 `modules/` 내부 파일은 직접 열리지 않아야 한다.

nginx 적용 후 다음 응답을 확인한다.

```text
/assets/reset.css 정적 CSS 응답
/assets/layout.css 정적 CSS 응답
/assets/module.css 정적 CSS 응답
/assets/editor-md.css 정적 CSS 응답
/assets/editor-ck.css 정적 CSS 응답
/assets/theme/sample.css 정적 CSS 응답
/assets/ui-kit.css 정적 CSS 응답
/assets/ui-kit-layout.css 정적 CSS 응답
/assets/public-layout.js 정적 JavaScript 응답
/modules/admin/assets/tokens.css 정적 CSS 응답
/assets/fonts/material-symbols-outlined.ttf 정적 font/ttf 응답
/modules/content/theme/basic/assets/reset.css 정적 CSS 응답
/modules/content/theme/basic/assets/layout.css 정적 CSS 응답
/modules/content/theme/basic/assets/module.css 정적 CSS 응답
/modules/content/theme/sample/assets/theme.css 정적 CSS 응답
/modules/content/assets/layout.js 정적 JavaScript 응답
/modules/content/assets/module.js 정적 JavaScript 응답
/modules/community/theme/basic/assets/reset.css 정적 CSS 응답
/modules/community/theme/basic/assets/layout.css 정적 CSS 응답
/modules/community/theme/basic/assets/module.css 정적 CSS 응답
/modules/community/theme/sample/assets/theme.css 정적 CSS 응답
/modules/community/assets/layout.js 정적 JavaScript 응답
/modules/community/assets/module.js 정적 JavaScript 응답
/modules/member/skins/basic/skin.css 정적 CSS 응답
/modules/quiz/theme/basic/assets/layout.css 정적 CSS 응답
/modules/quiz/theme/sample/assets/theme.css 정적 CSS 응답
/modules/quiz/assets/layout.js 정적 JavaScript 응답
/modules/quiz/assets/module.js 정적 JavaScript 응답
/modules/survey/theme/basic/assets/layout.css 정적 CSS 응답
/modules/survey/theme/sample/assets/theme.css 정적 CSS 응답
/modules/survey/assets/layout.js 정적 JavaScript 응답
/modules/survey/assets/module.js 정적 JavaScript 응답
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
Nginx subdirectory URL: docs/deployment/nginx-saanraan-subdirectory.conf를 기준으로 prefix가 포함된 server/location 규칙
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

현재 CLI 사용자가 안전한 `600` 설정 파일을 읽을 수 없는 경우에는 `config-mode`와 `config-owner-group`을 출력하고 권한 검사만 통과시킨 뒤 내용 검사를 건너뛴다. 이때 파일 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 내용 검사를 다시 실행한다.

메일을 HTTP API transport로 보낼 때는 `mail.transport`를 `http_api`로 설정할 수 있다. 이 경우 endpoint는 공개 HTTPS URL이어야 하며, private/reserved/loopback/link-local/CGNAT/documentation/multicast 주소는 허용하지 않는다.

파일 저장소를 S3로 바꾸려면 `storage.default`를 `s3`로 설정하고 bucket, region, endpoint, credential env를 지정한다. 운영 환경에서는 `endpoint`와 `public_base_url`이 HTTPS여야 하며, HTTP S3-compatible endpoint는 개발 환경 검증용으로만 사용한다. 배너 이미지는 공개 URL이 있으면 `public_base_url`을 사용할 수 있고, 커뮤니티 첨부와 커뮤니티 본문 원본 이미지는 권한 확인 후 짧은 presigned URL로 전달한다. 커뮤니티 본문 썸네일은 권한 확인 뒤 서버가 S3 원본을 임시로 읽어 local private cache에 생성하고 스트리밍한다. 커뮤니티 본문 이미지 임시 객체는 `community/body/tmp/` prefix를 사용하며, S3에는 서버 파일 스캔 기반 만료 정리가 적용되지 않는다. S3 운영 버킷에는 `community/body/tmp/` 객체를 1-2일 안에 만료시키는 lifecycle rule을 별도로 설정해야 한다.

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
- nginx 기본 보호 예시는 `docs/deployment/nginx-saanraan.conf`와 `docs/deployment/nginx-saanraan-subdirectory.conf`에 포함한다.
- 서버별 추가 보호 규칙은 운영 환경 문서나 배포 자동화에서 관리한다.
- 웹에서 차단해야 할 경로를 차단할 수 없는 호스팅에는 운영 설치하지 않는다.
