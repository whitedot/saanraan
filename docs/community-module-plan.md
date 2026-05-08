# 커뮤니티 모듈 제작 계획

이 문서는 Toycore의 첫 커뮤니티 모듈을 만들기 전 실행 계획을 정리한다. 커뮤니티 관련 판단은 가급적 이 문서 하나에서 추적한다.

## 1. 현재 main 확인

2026-05-08 기준으로 `git fetch origin main` 후 확인한 현재 워크스페이스의 `main`은 `origin/main`과 일치한다.

```text
main...origin/main: 0 ahead, 0 behind
HEAD: 07a30ea docs: 릴리스와 스모크 기준 보강
```

따라서 "main이 14커밋 뒤처진 상태"는 현재 이 워크스페이스의 `main`에는 적용되지 않는다. 최근 main에는 업로드 helper 관련 커밋이 이미 포함되어 있다.

확인된 관련 변경:

- `ee61052 feat: 업로드 보안 helper 추가`
- `4cddf93 fix: 업로드 저장 파일명 검증 보강`
- `09cf9d7 fix: 업로드 저장 대상 상태 검증 보강`
- `3c765db fix: 업로드 복합 실행 확장자 차단`
- `68443af fix: 업로드 검증 옵션 명시 강제`

업로드 primitive는 현재 core helper로 로드된다.

- `core/helpers.php`에서 `core/helpers/upload.php`를 require
- `toy_upload_validate_file()`
- `toy_upload_random_filename()`
- `toy_upload_move_uploaded_file()`
- `toy_upload_reencode_image()`
- `toy_download_token_create()`
- `toy_download_token_verify()`
- `toy_send_download_headers()`

rate limit도 현재 main에서는 core runtime helper로 이미 노출되어 있다.

- `toy_rate_limit_count()`
- `toy_rate_limit_increment()`

따라서 커뮤니티 v1은 rate limit 테이블을 직접 다루지 않고 이 낮은 수준 helper를 사용할 수 있다. 더 높은 수준의 `check + increment` 조합 helper는 커뮤니티 모듈 안에서 먼저 래핑하고, 두 번째 모듈에서도 같은 패턴이 반복될 때 core 승격을 검토한다.

## 2. 결론

커뮤니티 모듈 제작은 지금 시작 가능하다. 추가 코어 작업은 필수가 아니다.

다만 첫 도메인 모듈을 배포하기 전에 다음 보강은 실행 가치가 있다.

1. 모듈 설치/활성화 전 route 충돌 사전 검사
2. 계약 파일 소비부의 안전 로더 사용 통일
3. 커뮤니티 모듈에서 rate limit 사용 패턴을 모듈 helper로 먼저 고정

1, 2는 코어와 기본 모듈의 안정성 보강이다. 3은 코어 변경이 아니라 커뮤니티 모듈 내부 구현 기준이다.

## 3. 권장 보강 실행 계획

### 3-1. Route 충돌 사전 검사

현재 front controller는 런타임에서 활성 모듈 route 충돌을 감지하고 500으로 막는다. 첫 커뮤니티 모듈 설치 경험을 좋게 하려면 `/admin/modules`의 설치/활성화 단계에서 미리 같은 충돌을 보여주는 편이 낫다.

목표:

- 새 모듈을 `enabled` 상태로 설치할 때 기존 활성 모듈의 `paths.php`와 충돌하는 method/path를 사전에 차단한다.
- `disabled` 상태 설치는 허용한다.
- 기존 `disabled` 모듈을 `enabled`로 바꿀 때도 같은 검사를 수행한다.
- 충돌 메시지는 route와 충돌 모듈 key를 포함한다.

예상 변경 위치:

- `modules/admin/helpers/module-actions.php`
- 필요하면 `modules/admin/helpers/module-sources.php` 또는 별도 `modules/admin/helpers/module-routes.php`
- `.tools/bin/check.php` 또는 새 세부 검사 도구

구현 방향:

```text
1. 후보 모듈의 paths.php를 toy_load_module_contract_file()로 읽는다.
2. action 경로 형식과 파일 존재 여부는 기존 검사 기준을 재사용한다.
3. enabled 상태인 다른 모듈의 paths.php를 읽는다.
4. 같은 METHOD /path가 있으면 활성화 또는 enabled 설치를 거부한다.
5. admin-menu.php의 GET route 일치 검사는 기존 check와 같은 의미를 유지한다.
```

완료 기준:

- 기존 번들 모듈 route 58개에서 중복 없음이 확인된다.
- 충돌 fixture 또는 임시 테스트 모듈로 `GET /community` 중복을 만들면 enabled 설치가 실패한다.
- `php .tools/bin/check.php`가 통과한다.

### 3-2. 계약 파일 안전 로더 사용 통일

현재 front controller, output slot, privacy export, admin menu는 `toy_load_module_contract_file()`를 사용한다. 일부 계약 소비부는 아직 직접 `include`를 사용한다. 깨진 선택 모듈 하나가 전체 화면을 깨지 않도록 안전 로더 사용을 통일한다.

대상:

- `modules/seo/helpers.php`의 `sitemap.php` 소비
- `modules/site_menu/helpers.php`의 `menu-links.php` 소비
- `modules/banner/helpers.php`의 `extension-points.php` 소비
- `modules/popup_layer/helpers.php`의 `extension-points.php` 소비

구현 방향:

```text
1. toy_enabled_module_contract_files() 결과의 moduleKey => file 구조를 유지한다.
2. 직접 include 대신 toy_load_module_contract_file($moduleKey, $file)를 사용한다.
3. 반환값이 배열 또는 callable인지 기존 소비 규칙대로 다시 검증한다.
4. 예외는 helper에서 로깅되고 해당 모듈 계약만 무시된다.
```

완료 기준:

- 깨진 `sitemap.php` 또는 `extension-points.php`가 있는 모듈이 있어도 사이트 전체가 500으로 죽지 않는다.
- 정상 계약 파일 소비 결과는 기존과 동일하다.
- `php .tools/bin/check.php`가 통과한다.

### 3-3. Rate limit helper 추출 판단

현재 main에는 `toy_rate_limit_count()`와 `toy_rate_limit_increment()`가 core helper로 있다. 커뮤니티 v1에서는 이를 직접 사용하되, 모듈 안에 의미 있는 wrapper를 둔다.

커뮤니티 모듈 wrapper 예:

```text
toy_community_rate_limited($pdo, $bucket, $subject, $windowSeconds, $maxAttempts)
toy_community_record_rate_attempt($pdo, $bucket, $subject, $windowSeconds)
```

코어 승격 조건:

- 커뮤니티 외 두 번째 도메인 모듈도 같은 wrapper 패턴을 필요로 한다.
- member 전용 throttle과 커뮤니티 throttle을 같은 의미로 설명할 수 있다.
- helper가 정책을 갖지 않고 단순 primitive로 유지된다.

당장 하지 않는 것:

- `toy_rate_limit_check()` 같은 고수준 core helper를 선제 추가하지 않는다.
- captcha 또는 spam policy를 core에 넣지 않는다.

## 4. 커뮤니티 모듈 v1 범위

모듈 key는 `community`로 가정한다. 테이블명은 프로젝트 prefix를 사용해 `toy_community_*`로 둔다.

v1은 "게시판형 커뮤니티" 하나를 작게 제공한다.

포함:

- 공개 게시글 목록
- 공개 게시글 보기
- 로그인 회원 게시글 작성/수정/삭제 요청
- 로그인 회원 댓글 작성/삭제 요청
- 관리자 게시판 설정
- 관리자 게시글/댓글 숨김, 복구, 삭제 상태 관리
- 이미지 첨부
- sitemap, menu link, privacy export, extension point 계약
- 선택적 notification 연동

제외:

- WYSIWYG 편집기
- Markdown
- HTML 본문 허용
- nested comment
- reaction, like, point 연동
- 신고/블라인드 workflow
- 별도 search 모듈
- captcha plugin
- 멀티사이트
- 커뮤니티 데이터를 core 또는 member 테이블에 추가하는 방식

## 5. v1 정책 결정

### 5-1. 렌더링

v1 본문 형식은 plain text로 고정한다.

- DB에는 `body_format = 'plain'`을 저장한다.
- 출력은 `toy_e()` 후 줄바꿈만 `nl2br()`로 반영한다.
- HTML 입력은 저장하지 않고 텍스트로 취급한다.
- Markdown과 WYSIWYG는 v2 이후 별도 결정한다.

이유:

- sanitizer를 core에 넣지 않는 프로젝트 방향과 맞다.
- 첫 모듈의 XSS 표면을 줄인다.
- 공유호스팅에서 외부 parser 의존성을 피한다.

### 5-2. 댓글

v1 댓글은 `community` 모듈 내부에 둔다.

이유:

- 게시글과 댓글의 공개 상태, 삭제 상태, 알림 정책이 강하게 묶인다.
- 첫 구현에서 별도 comment 모듈 계약을 만들면 코어와 모듈 경계가 불필요하게 커진다.
- 다른 콘텐츠 모듈이 생기고 댓글 요구가 반복될 때 분리한다.

### 5-3. 첨부

v1 첨부는 이미지 파일만 지원한다.

- 대상: 게시글 첨부만 지원
- 댓글 첨부는 제외
- 허용 형식: JPEG, PNG, WebP
- 저장 위치: `storage/community/attachments/...`
- 웹 직접 접근을 전제로 하지 않는다.
- 공개 응답 action이 파일을 읽어 `Content-Type`과 `X-Content-Type-Options`를 설정한 뒤 출력한다.

업로드 정책:

- `toy_upload_validate_file()` 사용
- `max_bytes`, `allowed_extensions`, `allowed_mime_types`를 명시
- 저장 파일명은 `toy_upload_random_filename()` 사용
- 가능하면 `toy_upload_reencode_image()`로 재인코딩
- 재인코딩에 실패하면 업로드를 거부한다.
- 이미지 기능은 환경 문제로 실패해도 게시글 텍스트 기능을 막지 않는다.

### 5-4. 공개 범위

v1 기본값은 비로그인 읽기 허용, 로그인 작성이다.

- 공개 게시판과 게시글은 비로그인 조회 가능
- 작성, 수정, 삭제 요청, 댓글 작성은 로그인 필수
- 관리자 화면은 `owner`, `admin`, `manager` 중 정책별로 제한
- SEO 목적의 sitemap은 공개 게시글만 포함

게시판별 정책은 `toy_community_boards`에 둔다.

```text
read_policy: public | member
write_policy: member | admin
comment_policy: member | disabled
```

## 6. 디렉터리 구조

```text
modules/community/
- module.php
- helpers.php
- helpers/
  - boards.php
  - posts.php
  - comments.php
  - attachments.php
  - settings.php
  - rate-limit.php
  - notifications.php
- paths.php
- admin-menu.php
- menu-links.php
- extension-points.php
- privacy-export.php
- sitemap.php
- actions/
  - list.php
  - view.php
  - write.php
  - edit.php
  - delete.php
  - comment.php
  - comment-delete.php
  - attachment.php
  - admin-boards.php
  - admin-posts.php
- views/
  - list.php
  - view.php
  - write.php
  - edit.php
  - admin-boards.php
  - admin-posts.php
- assets/
  - community.css
- lang/
  - ko.php
- install.sql
- updates/
```

`helpers.php`는 하위 helper만 require한다. action 파일은 가능한 한 `modules/community/helpers.php` 하나만 require한다.

## 7. module.php 계획

```php
<?php

return [
    'name' => 'Community',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => 'Board-style community module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'menu-links.php',
            'extension-points.php',
            'privacy-export.php',
            'sitemap.php',
        ],
    ],
    'settings' => [
        'posts_per_page' => 20,
        'comments_per_page' => 50,
        'post_create_window_seconds' => 300,
        'post_create_limit' => 10,
        'comment_create_window_seconds' => 300,
        'comment_create_limit' => 30,
        'image_upload_max_bytes' => 2097152,
        'image_uploads_enabled' => true,
    ],
];
```

`notification` 모듈은 필수 의존성에 넣지 않는다. 활성화되어 있으면 선택적으로 helper를 읽어 알림을 만든다.

## 8. Route 계획

```php
<?php

return [
    'GET /community' => 'actions/list.php',
    'GET /community/post' => 'actions/view.php',
    'GET /community/write' => 'actions/write.php',
    'POST /community/write' => 'actions/write.php',
    'GET /community/edit' => 'actions/edit.php',
    'POST /community/edit' => 'actions/edit.php',
    'POST /community/delete' => 'actions/delete.php',
    'POST /community/comment' => 'actions/comment.php',
    'POST /community/comment/delete' => 'actions/comment-delete.php',
    'GET /community/attachment' => 'actions/attachment.php',
    'GET /admin/community/boards' => 'actions/admin-boards.php',
    'POST /admin/community/boards' => 'actions/admin-boards.php',
    'GET /admin/community/posts' => 'actions/admin-posts.php',
    'POST /admin/community/posts' => 'actions/admin-posts.php',
];
```

규칙:

- 상태 변경은 모두 POST다.
- public action은 필요한 경우에만 로그인 guard를 호출한다.
- 관리자 action은 시작 부분에서 `toy_member_require_login()`과 `toy_admin_require_role()`을 호출한다.
- 모든 POST는 `toy_require_csrf()`를 호출한다.
- action 파일은 직접 `exit`, `die`, `header('Location: ...')`를 사용하지 않는다.

## 9. DB 스키마 초안

### 9-1. `toy_community_boards`

```text
id BIGINT UNSIGNED PK
board_key VARCHAR(60) UNIQUE
title VARCHAR(120)
description TEXT NULL
status VARCHAR(30) DEFAULT 'enabled'
read_policy VARCHAR(30) DEFAULT 'public'
write_policy VARCHAR(30) DEFAULT 'member'
comment_policy VARCHAR(30) DEFAULT 'member'
image_uploads_enabled TINYINT(1) DEFAULT 1
sort_order INT DEFAULT 0
created_at DATETIME
updated_at DATETIME
```

인덱스:

```text
UNIQUE board_key
status, sort_order, id
```

### 9-2. `toy_community_posts`

```text
id BIGINT UNSIGNED PK
board_id BIGINT UNSIGNED
author_account_id BIGINT UNSIGNED
title VARCHAR(160)
body_text MEDIUMTEXT
body_format VARCHAR(20) DEFAULT 'plain'
status VARCHAR(30) DEFAULT 'published'
view_count BIGINT UNSIGNED DEFAULT 0
last_commented_at DATETIME NULL
created_at DATETIME
updated_at DATETIME
```

상태:

```text
published
hidden
deleted
pending
```

인덱스:

```text
board_id, status, id
author_account_id, id
status, updated_at
```

### 9-3. `toy_community_comments`

```text
id BIGINT UNSIGNED PK
post_id BIGINT UNSIGNED
author_account_id BIGINT UNSIGNED
body_text TEXT
status VARCHAR(30) DEFAULT 'published'
created_at DATETIME
updated_at DATETIME
```

상태:

```text
published
hidden
deleted
```

인덱스:

```text
post_id, status, id
author_account_id, id
```

### 9-4. `toy_community_attachments`

```text
id BIGINT UNSIGNED PK
post_id BIGINT UNSIGNED
uploader_account_id BIGINT UNSIGNED
original_name VARCHAR(120)
stored_name VARCHAR(120)
storage_path VARCHAR(255)
mime_type VARCHAR(120)
size_bytes BIGINT UNSIGNED
checksum_sha256 CHAR(64)
width INT UNSIGNED NULL
height INT UNSIGNED NULL
status VARCHAR(30) DEFAULT 'active'
created_at DATETIME
```

인덱스:

```text
post_id, status, id
uploader_account_id, id
checksum_sha256
```

외래키는 공유호스팅 호환성을 위해 필수로 두지 않는다. 관계 무결성은 helper와 관리 화면에서 유지한다.

## 10. 권한과 정책

공개 조회:

- `read_policy = public`이고 게시판/게시글 상태가 공개이면 누구나 조회 가능
- `read_policy = member`이면 `toy_member_require_login()` 필요

작성:

- `write_policy = member`이면 로그인 회원 작성 가능
- `write_policy = admin`이면 관리자만 작성 가능
- 계정 상태가 active가 아니면 member helper에서 로그인 상태가 유지되지 않는다.

수정/삭제:

- 작성자는 자기 게시글을 수정하거나 삭제 상태로 전환할 수 있다.
- 관리자는 숨김, 복구, 삭제 상태 전환을 할 수 있다.
- 물리 삭제는 v1 관리자 화면에서 기본 제공하지 않고 soft delete를 우선한다.

댓글:

- `comment_policy = member`이면 로그인 회원 작성 가능
- 작성자는 자기 댓글을 삭제 상태로 전환할 수 있다.
- 관리자는 숨김, 복구, 삭제 상태 전환을 할 수 있다.

관리자 role:

```text
GET /admin/community/*: owner, admin, manager
POST /admin/community/boards: owner, admin
POST /admin/community/posts: owner, admin, manager
```

게시판 구조 변경은 `owner`, `admin`으로 제한한다. 게시글 moderation은 `manager`도 허용한다.

## 11. 출력과 SEO

각 public view는 자체 `$seo` 배열을 만든 뒤 `toy_seo_tags()`를 사용한다.

게시글 보기:

- title: 게시글 제목
- canonical: `/community/post?id={id}`
- description: 본문 plain text 앞부분
- robots: 공개 게시글은 `index, follow`, 비공개 또는 hidden은 `noindex, nofollow`

`sitemap.php`:

- 공개 게시판 목록 URL 포함
- 공개 게시글 중 `published` 상태만 포함
- `lastmod`는 `updated_at`
- 한 번에 너무 많은 URL을 반환하지 않도록 우선 1000건 제한

`menu-links.php`:

```text
커뮤니티 -> /community
```

## 12. Extension Points

`extension-points.php`는 배너와 팝업레이어가 커뮤니티 화면에 붙을 수 있는 위치만 선언한다.

계획:

```text
community.list
- before_list
- after_list

community.post.view
- before_content
- after_content
- before_comments
- after_comments
```

게시글 보기 view는 다음처럼 호출한다.

```php
<?php echo toy_render_output_slot($pdo, [
    'module_key' => 'community',
    'point_key' => 'community.post.view',
    'slot_key' => 'before_content',
    'subject_id' => (string) $post['id'],
]); ?>
```

대량 subject selector는 v1에서 만들지 않는다. 게시글 수가 늘어나면 검색형 selector를 별도 관리자 action으로 추가한다.

## 13. 개인정보와 탈퇴

회원 탈퇴는 member 모듈에서 계정을 익명화하고 프로필/동의를 처리한다. 커뮤니티 모듈은 `author_account_id`를 기준으로 단방향 참조를 유지한다.

작성자 표시:

- `toy_member_public_account_summary()`로 표시 이름을 가져온다.
- 계정이 없거나 익명화 상태이면 `탈퇴 회원` 같은 fallback을 사용한다.
- 이메일은 출력하지 않는다.

`privacy-export.php` 포함 범위:

- 해당 회원이 작성한 게시글
- 해당 회원이 작성한 댓글
- 해당 회원이 업로드한 첨부 metadata
- 관리자 moderation 이력은 직접 개인정보인 최소 범위만 포함

제외:

- 비밀번호, token, hash, secret-like 필드
- 첨부 바이너리 원문
- 다른 회원 댓글/본문

개인정보 삭제 요청:

- 자동 물리 삭제는 v1에서 하지 않는다.
- 관리자가 개인정보 요청을 검토한 뒤 커뮤니티 관리자 화면에서 게시글/댓글을 숨김 또는 삭제 상태로 바꾼다.
- 필요하면 후속 버전에서 `privacy-erasure.php` 같은 별도 계약을 검토한다. v1에서 core 계약을 늘리지 않는다.

## 14. 알림 연동

`notification` 모듈은 선택 모듈이다. 커뮤니티는 이를 필수 의존성으로 만들지 않는다.

v1 선택 연동:

- 내 게시글에 새 댓글이 달리면 글 작성자에게 site notification 생성
- 자기 댓글에는 알림 생성하지 않음
- notification 모듈이 활성화되어 있고 helper가 로드 가능할 때만 실행
- 실패해도 댓글 작성 자체는 롤백하지 않음
- 실패는 예외 로그 또는 감사 로그 metadata에 최소 정보만 기록

사용 후보:

```text
toy_module_enabled($pdo, 'notification')
require_once TOY_ROOT . '/modules/notification/helpers.php'
toy_notification_create($pdo, ...)
```

## 15. Rate Limit 계획

커뮤니티 모듈은 core의 낮은 수준 helper를 사용한다.

게시글 작성:

```text
bucket: community.post.create
subject: account:{account_id}
window: module setting post_create_window_seconds
limit: module setting post_create_limit
```

댓글 작성:

```text
bucket: community.comment.create
subject: account:{account_id}
window: module setting comment_create_window_seconds
limit: module setting comment_create_limit
```

보조 IP 기준:

```text
subject: ip:{toy_client_ip()}
```

실행 순서:

```text
1. 현재 count 확인
2. 제한 초과면 사용자 오류
3. 검증 실패/성공과 무관하게 의미 있는 작성 시도에서 increment
4. 성공 시 감사 로그에는 본문을 넣지 않음
```

## 16. 구현 단계

### Phase 0. 권장 보강

- route 충돌 사전 검사 추가
- 계약 파일 안전 로더 사용 통일
- 보강 후 `php .tools/bin/check.php`

이 단계는 커뮤니티 모듈 시작의 필수 조건은 아니지만, 첫 도메인 모듈을 설치/활성화하는 운영 경험을 안정화한다.

### Phase 1. 모듈 골격과 설치

- `modules/community` 구조 생성
- `module.php`, `install.sql`, `paths.php`, `admin-menu.php` 작성
- 기본 게시판 seed 1개 추가
- `/admin/modules`에서 설치 가능하게 확인
- disabled 상태에서 route가 열리지 않는지 확인

완료 기준:

- 설치 SQL 재실행 가능
- 모듈 설치 후 enabled/disabled 전환 가능
- admin menu path와 GET route 일치
- `php .tools/bin/check.php` 통과

### Phase 2. 공개 목록/보기

- `/community` 목록
- `/community/post?id=...` 보기
- 게시판 상태와 게시글 상태 필터
- 작성자 fallback 표시
- plain text escape 출력
- 기본 SEO 태그

완료 기준:

- 비로그인 공개 조회 가능
- hidden/deleted 글은 일반 조회에서 제외
- 출력 XSS가 escape됨

### Phase 3. 회원 작성/댓글

- 게시글 작성/수정/삭제 요청
- 댓글 작성/삭제 요청
- CSRF 적용
- 작성자 권한 검증
- rate limit 적용
- 감사 로그 기록

완료 기준:

- 비로그인 작성 요청은 로그인으로 이동
- 타인 글 수정/삭제 차단
- POST 새로고침 중복 문제를 redirect로 줄임
- 본문 원문이 감사 로그에 남지 않음

### Phase 4. 관리자 화면

- `/admin/community/boards`
- `/admin/community/posts`
- 게시판 생성/수정/상태 변경
- 게시글/댓글 상태 변경
- 관리자 role 구분
- 운영자 메시지와 감사 로그

완료 기준:

- `manager`는 moderation만 가능
- `owner/admin`은 게시판 설정 가능
- 모든 관리자 POST는 로그인, role, CSRF 순서 준수

### Phase 5. 이미지 첨부

- 이미지 업로드 POST 처리
- 저장소 디렉터리 생성
- MIME/확장자/크기 검증
- 랜덤 파일명
- 재인코딩
- `/community/attachment?id=...` 응답

완료 기준:

- PHP 실행 가능 확장자와 복합 실행 확장자 차단
- storage 파일 직접 접근 없이 action으로만 응답
- 삭제/hidden 게시글 첨부는 일반 조회에서 열리지 않음

### Phase 6. 계약 파일

- `menu-links.php`
- `extension-points.php`
- `sitemap.php`
- `privacy-export.php`
- 선택적 notification 연동

완료 기준:

- site_menu가 커뮤니티 링크 후보를 읽음
- banner/popup_layer가 커뮤니티 출력 지점을 읽음
- seo sitemap에 공개 글만 포함
- 개인정보 export에 해당 회원 데이터만 포함

### Phase 7. 점검과 릴리스 준비

- `php .tools/bin/check.php`
- 수동 HTTP smoke
- 설치 전/후 시나리오 확인
- zip 구조 확인
- `module.php` 버전과 update SQL 정책 확인

수동 smoke:

```text
1. /admin/modules에서 community 설치
2. /community 목록 조회
3. 로그인 후 게시글 작성
4. 게시글 보기
5. 댓글 작성
6. 관리자에서 게시글 숨김
7. 숨김 글 일반 조회 차단
8. sitemap.xml에 공개 글만 포함
9. 개인정보 export에 작성 글/댓글 포함
10. 모듈 disabled 후 route 404 확인
```

## 17. 업데이트 계획

v1 최초 버전:

```text
2026.05.001
```

규칙:

- SQL 구조 변경은 `install.sql` 최신화와 `updates/YYYY.MM.NNN.sql` 추가를 함께 한다.
- 배포된 update SQL은 같은 버전에서 수정하지 않는다.
- 파일만 바뀐 업데이트는 필요 시 `/admin/modules`의 version sync 흐름을 사용한다.
- 모듈 계약이 바뀌지 않으면 `TOY_MODULE_CONTRACT_VERSION`을 올리지 않는다.

예상 후속 버전 후보:

```text
2026.05.002: 검색/정렬 개선
2026.05.003: 신고/관리자 moderation workflow
2026.05.004: Markdown 또는 editor plugin 계약 검토
```

## 18. 리스크와 대응

| 리스크 | 대응 |
| --- | --- |
| 공개 본문 XSS | plain text + `toy_e()` + `nl2br()`만 허용 |
| route 충돌 | Phase 0 사전 검사 |
| 첨부 파일 실행 | storage 저장, 확장자/MIME allowlist, random filename, action 응답 |
| 이미지 재인코딩 환경 부재 | 업로드만 실패시키고 텍스트 게시판은 동작 유지 |
| 개인정보 export 과다 포함 | 회원 본인 데이터만 조회, hash/token/password 필드 제외 |
| 탈퇴 회원 표시 | `account_id` 유지, 익명화 계정 fallback 표시 |
| notification 선택 모듈 실패 | 댓글 작성과 알림 생성을 느슨하게 분리 |
| 커뮤니티가 CMS로 커짐 | v1에서 board/post/comment/image까지만 제한 |

## 19. 완료 정의

첫 커뮤니티 모듈 v1은 다음 조건을 만족하면 완료로 본다.

- core/member/admin 테이블을 넓히지 않는다.
- 모든 커뮤니티 도메인 테이블은 `toy_community_*`에 있다.
- 모든 route는 `paths.php`에 보인다.
- 모든 상태 변경은 POST와 CSRF를 사용한다.
- 관리자 action은 로그인과 role을 검사한다.
- 공개 출력은 escape된다.
- 설치/비활성화/업데이트 흐름이 기존 모듈과 같은 방식으로 동작한다.
- `php .tools/bin/check.php`가 통과한다.
- 개인정보 export, sitemap, menu-links, extension-points 계약을 제공한다.
- 커뮤니티 모듈 없이 `core + member + admin` 기준선이 계속 동작한다.
