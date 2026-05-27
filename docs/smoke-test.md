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

현재 통합 점검은 코드 상태뿐 아니라 진행 중인 정책 TODO도 함께 검사한다. 2026-05-26 기준 전체 PHP 문법 검사와 `.tools/bin/check.php`는 통과하는 상태다. 이후 실패가 발생하면 실패 항목이 현재 변경의 회귀인지, 새 정책 점검 추가로 드러난 기존 보완 항목인지 먼저 분리한다.

1.0 릴리스 후보에서는 정적 점검 통과만으로 완료 판정하지 않는다. [1.0 범위 잠금 기준](1.0-scope.md)의 포함 모듈을 기준으로 HTTP 스모크와 필요한 브라우저 수동 점검을 함께 기록한다.

## HTTP 스모크 점검

로컬 PHP 내장 서버나 스테이징 서버가 떠 있으면 다음 명령을 실행한다.

```sh
php .tools/bin/smoke-http.php http://127.0.0.1:8080
```

같은 base URL은 환경변수로도 전달할 수 있다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 php .tools/bin/smoke-http.php
```

커뮤니티 모듈이 설치되어 있어야 하는 스테이징 검수에서는 404 허용을 제거한 강한 모드로 실행한다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_EXPECT_COMMUNITY=1 \
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
/ui-kit 응답이 500 없이 열리고 Public UI-KIT 화면이 출력되는지 확인
/admin 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/admin/updates 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
/content/example 응답이 500 없이 열리지 않고, 미설치/비활성/없는 slug 상태에서는 404 등 허용된 응답으로 막히는지 확인
/admin/content 응답이 500 없이 열리거나 로그인/권한 흐름으로 막히는지 확인
콘텐츠 모듈 설치 환경에서는 published 콘텐츠가 200으로 열리고 draft/hidden 콘텐츠는 공개 접근이 차단되는지 확인. `/admin/content` 조회 권한이 있는 관리자 세션에서는 draft 콘텐츠 공개 URL 미리보기가 열리고 열람 차감, 다운로드, 완료 버튼 처리가 실행되지 않는지 확인
콘텐츠 그룹을 만든 환경에서는 /content/group?key=... 공개 목록이 200으로 열리고 비사용/보관 그룹은 공개 접근이 차단되는지 확인
콘텐츠 모듈 설치 환경에서는 공용 배너/팝업레이어 직접 선택과 `content.view` 출력 위치 규칙이 공개 콘텐츠에 반영되는지 확인
콘텐츠 자산 기능을 활성화한 환경에서는 유료 열람, 유료 다운로드, 완료 버튼 자산 처리가 비로그인 접근을 로그인으로 보내고, 로그인 회원은 잔액/중복 정책에 맞게 처리되는지 확인. 회원 그룹 혜택을 저장하고 콘텐츠/콘텐츠 그룹/파일에서 선택한 경우 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, 차감 면제나 지급/차감 안 함처럼 최종 금액이 0인 경우 원장 거래 없이 접근권 또는 완료 로그가 남는지 확인. 최초 설치 후 새 콘텐츠와 새 콘텐츠 그룹의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 콘텐츠 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 콘텐츠에 한 번 복사되고, 콘텐츠 그룹 설정 변경 후에는 기존 콘텐츠의 자산 설정과 유효 동작이 바뀌지 않아야 한다. 완료 버튼 자산 처리는 콘텐츠 조회만으로 실행되지 않아야 한다. 복합 자산 차감 설정은 자산 선택과 금액 입력이 같은 묶음으로 보이고 선택 자산별 금액이 각각 차감되는지 확인하고, 모든 자산을 해제한 뒤 저장하면 다시 체크되지 않아야 한다. 완료 버튼 지급 방향은 첫 번째 선택 자산에 단일 금액이 지급되는지 확인

자산 환전 모듈을 활성화한 환경에서는 `/admin/asset-exchange`에서 활성 자산 모듈 간 정책을 저장하고, 같은 자산 조합 중복과 필수값/비율/최소·최대 금액/수수료 설정이 서버에서 검증되는지 확인한다. `/account/asset-exchange`에서 예상 출금액, 입금액, 수수료, 최종 증가액을 확인한 뒤 확정하면 출금 자산 원장 `exchange_out`, 입금 자산 원장 `exchange_in`, 수수료가 있는 경우 `exchange_fee`가 같은 환전 묶음 ID로 남고, `sr_asset_exchange_logs`에 비율/반올림/수수료 스냅샷이 저장되는지 확인한다. 자산 모듈을 비활성화하면 신규 정책 후보에서 제외되고 기존 정책 목록에는 실행 불가 상태가 표시되는지 확인한다.
콘텐츠 업데이트 후 기존 `/admin/content/settings` 권한 보유 운영자에게 `/admin/content/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인
콘텐츠 유료 열람 환경에서 쿠폰·이용권 모듈이 활성화되어 있고 대상 콘텐츠 쿠폰이 있으면 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. 같은 콘텐츠 재열람은 `dedupe_key` 기준으로 중복 사용되지 않아야 한다
/community 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/board?key=free 응답이 500 없이 열리거나 설치/비활성 상태에서 허용된 응답으로 막히는지 확인. `SR_SMOKE_EXPECT_COMMUNITY=1`이면 404는 실패로 본다.
/community/message/write 비로그인 접근이 로그인 흐름으로 막히는지 확인
/community/write?key=free 비로그인 접근이 로그인 흐름으로 막히는지 확인
커뮤니티 자산 기능을 활성화한 환경에서는 글/댓글 적립, 글/댓글 작성 차감, 게시글 유료 열람, 첨부 다운로드 차감이 비로그인/잔액 부족/중복 처리 정책에 맞게 동작하는지 확인. 회원 그룹/레벨 혜택을 저장하고 전역/게시판 그룹/게시판에서 선택한 경우 상속 기준에 따라 최종 금액과 `group_policy_snapshot_json`이 로그에 남고, 최종 금액 0이면 원장 거래 없이 유료 열람/첨부 다운로드 접근권 또는 처리 로그가 남는지 확인. 같은 회원 그룹에 최소 레벨별 정책을 저장하면 레벨 충족 여부에 따라 다른 정책이 적용되고, 같은 우선순위에서는 충족한 정책 중 최소 레벨이 높은 행이 먼저 적용되며, 스냅샷에 매칭 최소 레벨과 현재 레벨이 남는지 확인. 최초 설치 후 커뮤니티 환경설정, 새 게시판 그룹, 새 게시판의 자산 설정은 사용하지 않음이며 포인트 등 특정 자산이 미리 선택되어 있지 않아야 한다. 복합 자산 차감 설정은 선택 자산별 금액이 각각 차감되는지 확인. 첨부 URL 직접 접근도 게시글 유료 열람 정책을 우회하지 않는지 함께 확인
커뮤니티 업데이트 후 기존 `/admin/community/settings` 권한 보유 운영자에게 `/admin/community/asset-policy-sets`의 같은 액션 권한이 승계되는지 확인
커뮤니티 게시글 또는 게시판 대상 쿠폰이 있으면 게시글 유료 열람과 첨부 직접 접근에서 금액성 자산 차감보다 쿠폰 사용이 먼저 적용되는지 확인. `once` 정책에서는 같은 세션/대상 중복 차감과 중복 쿠폰 사용이 없어야 한다
커뮤니티 환경설정 저장은 레벨 점수, 쪽지 작성 정책/회원 그룹, 커뮤니티 홈 레이아웃, 게시글 에디터, 복합 자산 차감 선택과 금액을 함께 바꿔 `sr_module_settings` 값이 갱신되는지 확인한다. 복합 자산 차감에서 포인트/적립금/예치금을 모두 해제하고 저장하면 다시 체크되지 않아야 한다. 저장 실패가 발생하면 화면 검증 메시지 또는 `storage/logs/error.log`에 원인이 남아야 한다
커뮤니티 자산 관리자 설정을 바꾼 환경에서는 커뮤니티 전역, 게시판 그룹, 게시판 수정 화면의 `자산 변경 이력` 링크에서 대상별 변경 로그가 보이는지 확인. 게시판 수정 화면에서 `그룹`/`전체` 적용을 선택하면 현재 편집값이 대상 게시판에 한 번 복사되고, 전역 설정이나 게시판 그룹 설정 변경 후에는 기존 게시판의 자산 설정과 유효 동작이 바뀌지 않아야 한다. 게시판을 다른 그룹으로 옮기더라도 저장된 게시판 자산 설정은 현재 게시판 값으로 유지되어야 한다
CKEditor 플러그인을 활성화한 환경에서는 콘텐츠 본문, 커뮤니티 게시글, 관리자 본문 textarea가 설정에 따라 에디터로 강화되고, 초기화 성공 시에만 `body_format=html`로 저장되는지 확인. 직접 호스팅 모드는 `modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js`와 `ckeditor5.css`를 로드해야 한다. CKEditor asset 로딩을 실패시킨 경우에는 일반 textarea 제출이 유지되고 서버가 `plain` 저장으로 fallback해야 한다. 악성 HTML은 저장/출력 과정에서 허용 태그와 속성만 남아야 한다
알림 모듈이 활성화된 환경에서는 포인트/적립금/예치금 거래와 쿠폰 발급/사용/상태 변경 후 회원 대상 사이트 알림이 생성되는지 확인. 알림 모듈을 비활성화한 환경에서는 같은 자산 처리가 알림 실패 없이 성공해야 한다
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
/assets/saanraan.css 정적 파일 응답 확인
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
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
php .tools/bin/smoke-community-auth.php
```

전체 커뮤니티 흐름은 선택 계정을 함께 지정해 확인한다.

```sh
SR_SMOKE_BASE_URL=http://127.0.0.1:8080 \
SR_SMOKE_IDENTIFIER=writer@example.com \
SR_SMOKE_PASSWORD='password' \
SR_SMOKE_RECIPIENT_IDENTIFIER=recipient@example.com \
SR_SMOKE_RECIPIENT_PASSWORD='password' \
SR_SMOKE_REPORTER_IDENTIFIER=reporter@example.com \
SR_SMOKE_REPORTER_PASSWORD='password' \
SR_SMOKE_ADMIN_IDENTIFIER=admin@example.com \
SR_SMOKE_ADMIN_PASSWORD='password' \
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

`SR_SMOKE_RECIPIENT_PASSWORD`는 `SR_SMOKE_RECIPIENT_IDENTIFIER`가 있을 때만 사용할 수 있다. 신고자와 관리자 계정은 identifier/password를 함께 지정해야 한다. 게시판 키를 바꿔야 하면 `SR_SMOKE_BOARD_KEY`를 사용하고, 기존 게시글 ID를 보조값으로 넘겨야 하면 `SR_SMOKE_POST_ID`를 사용한다.

## 수동 확인 시나리오

릴리스 전에는 다음 흐름을 브라우저에서 한 번 확인한다.

```text
1. 새 DB로 설치 화면 진입
2. 필수 모듈 설치 완료
3. 최초 owner 계정으로 로그인
4. /admin 대시보드 진입
5. 대시보드 모듈별 섹션이 표시되고 드래그 앤 드롭으로 순서가 바뀌는지 확인
6. /admin/modules에서 설치 버전과 코드 버전 확인
7. /admin/updates에서 미적용 SQL 목록 또는 없음 확인
8. /account에서 계정 화면 진입
9. 로그아웃 후 /admin 접근 시 로그인 흐름 확인
```

선택 모듈이 포함된 배포본은 다음 항목을 추가로 확인한다.

```text
선택 모듈 체크 후 설치 완료
서비스 도메인 모듈 카드에서 초기화면으로 설정 체크를 선택한 경우 site.home_path가 저장되고 / 접속 시 해당 경로로 이동
/admin/settings 화면 섹션에서 기본 홈페이지 / 접속, 커뮤니티 홈/공개 콘텐츠/콘텐츠 그룹 초기화면 선택과 fallback 확인
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
모듈 수명주기 판정이 core helper 기준으로 유지되고, /admin/modules와 /admin/updates가 같은 pending SQL/버전 차이를 표시함
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
