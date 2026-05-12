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

같은 base URL은 환경변수로도 전달할 수 있다.

```sh
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 php .tools/bin/smoke-http.php
```

커뮤니티 모듈이 설치되어 있어야 하는 스테이징 검수에서는 404 허용을 제거한 강한 모드로 실행한다.

```sh
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 \
TOY_SMOKE_EXPECT_COMMUNITY=1 \
php .tools/bin/smoke-http.php
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
/community 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `TOY_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/board?key=free 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `TOY_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/message/write 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/write?key=free 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/edit?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/edit 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/comment/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/report 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/scraps 비로그인 접근이 로그인 흐름으로 막히는지 확인
POST /community/scrap 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/messages 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/message?id=1 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/message/write 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/community/message/delete 비로그인 POST 접근이 로그인 흐름으로 막히는지 확인
/admin/community/boards 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/reports 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/community/posts 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/sitemap.xml 응답이 200이면 sitemap XML 루트가 있고 404여도 PHP 오류가 노출되지 않는지 확인
/assets/toycore.css 정적 파일 응답 확인
/database/core/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/member/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/install.sql 직접 접근에서 SQL 내용이 노출되지 않는지 확인
/modules/community/module.php 직접 접근에서 커뮤니티 모듈 코드가 노출되지 않는지 확인
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

## 인증 커뮤니티 스모크 점검

커뮤니티 모듈이 설치되어 있고 테스트 계정이 준비된 환경에서는 인증 흐름까지 확인한다. 이 점검은 게시글, 댓글, 스크랩, 쪽지, 신고, 관리자 처리 데이터를 실제로 만든다. 운영 DB가 아닌 로컬 또는 스테이징 DB에서 실행한다.

최소 실행은 작성자 계정만 필요하다.

```sh
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 \
TOY_SMOKE_IDENTIFIER=writer@example.com \
TOY_SMOKE_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

전체 커뮤니티 흐름은 선택 계정을 함께 지정해 확인한다.

```sh
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 \
TOY_SMOKE_IDENTIFIER=writer@example.com \
TOY_SMOKE_PASSWORD='password' \
TOY_SMOKE_RECIPIENT_IDENTIFIER=recipient@example.com \
TOY_SMOKE_RECIPIENT_PASSWORD='password' \
TOY_SMOKE_REPORTER_IDENTIFIER=reporter@example.com \
TOY_SMOKE_REPORTER_PASSWORD='password' \
TOY_SMOKE_ADMIN_IDENTIFIER=admin@example.com \
TOY_SMOKE_ADMIN_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

확인 항목:

```text
작성자 로그인 후 /community/messages 접근
자유 게시판 게시글 작성과 상세 화면 제목 확인
작성자 게시글 수정과 상세 화면 수정 제목/본문 확인
댓글 작성과 상세 화면 댓글 본문 확인
스크랩 추가와 스크랩 목록 노출, 해제 후 목록 미노출 확인
수신자 계정 지정 시 쪽지 발송과 보낸 쪽지 본문 확인
수신자 비밀번호 지정 시 수신자 로그인 후 받은 쪽지 본문 확인
보낸 쪽지 삭제 후 보낸 쪽지함 미노출과 발신자 404 응답 확인
신고자 계정 지정 시 작성된 게시글 신고 확인
관리자 계정 지정 시 신고 처리, 댓글 숨김과 댓글 미노출, 게시글 숨김, 숨김 게시글 404 응답 확인
```

`TOY_SMOKE_RECIPIENT_PASSWORD`는 `TOY_SMOKE_RECIPIENT_IDENTIFIER`가 있을 때만 사용할 수 있다. 신고자와 관리자 계정은 identifier/password를 함께 지정해야 한다. 게시판 키를 바꿔야 하면 `TOY_SMOKE_BOARD_KEY`를 사용하고, 기존 게시글 ID를 보조값으로 넘겨야 하면 `TOY_SMOKE_POST_ID`를 사용한다.

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

모듈 설치/업데이트 흐름을 완료 판정할 때는 다음 상태도 함께 확인한다.

```text
/admin/modules에서 미설치 모듈이 미설치 또는 설치 차단 상태로 구분됨
/admin/modules에서 failed 또는 installing 모듈이 재설치 필요 상태로 구분됨
/admin/modules에서 코드 버전이 설치 버전보다 낮은 모듈이 파일 재배치 필요 상태로 구분됨
/admin/modules에서 pending SQL이 있는 모듈이 /admin/updates 이동 대상으로 구분됨
/admin/updates에서 pending SQL 적용 전 백업 확인을 요구함
/admin/updates에서 SQL 없는 파일 전용 업데이트가 설치 버전 반영 대상으로 구분됨
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
