# 모듈 작성 가이드

이 문서는 Toycore 모듈을 실제로 만들고 유지보수할 때 따르는 기준이다.

Toycore의 모듈은 프레임워크 패키지가 아니다. 모듈은 정해진 디렉터리에 놓인 절차형 PHP 파일, 정적 SQL 파일, DB에 저장된 설치/활성 상태로 동작한다. 자동 발견, 서비스 프로바이더, ORM, 클래스 마이그레이션, DI 컨테이너, 이벤트 버스를 기본 전제로 두지 않는다.

모듈 작성의 목표는 기능을 빠르게 붙이는 것이 아니라 다음 상태를 유지하는 것이다.

- 요청 흐름이 파일을 열면 보일 것
- 코어가 도메인 정책을 대신 소유하지 않을 것
- 모듈이 자기 테이블, 화면, 정책, 업데이트를 책임질 것
- 저가형 웹호스팅에서도 PHP 파일과 SQL만으로 설치 가능할 것
- 보안 판단을 view나 클라이언트 코드에 미루지 않을 것

모듈 저장 위치와 배포 기준은 [모듈 저장 위치 기준](module-storage-policy.md)을 따른다. Toycore 안에서는 모듈을 항상 `modules/{module_key}` 폴더로 다룬다. zip 업로드 전 짧은 확인 목록은 [모듈 체크리스트](module-checklist.md)를 사용하고, 파일 교체와 DB 업데이트 흐름은 [모듈 배치와 업데이트 기준](module-update-policy.md)을 따른다.

## 1. 모듈 판단 기준

Toycore에서 설치/활성화 가능한 확장 단위는 같은 `toy_modules` 등록 흐름을 사용한다. 다만 개념은 구분한다.

```text
module = 자기 도메인과 정책을 소유하는 확장
plugin = 특정 모듈이나 계약 파일에 붙어 동작하는 확장
```

모듈로 만든다:

- 자기 테이블이 있다.
- 자기 관리자 화면이 있다.
- 자기 public/account/admin route가 있다.
- 자기 권한, 검증, 정책을 판단한다.
- 설치/업데이트 SQL을 소유한다.

플러그인으로 만든다:

- 다른 모듈의 계약 파일이나 출력 지점에 붙어 기능을 보강한다.
- 자기 테이블은 있을 수 있지만 독립 도메인이라기보다 어댑터 성격이다.
- 소셜 로그인 제공자, 결제 수단 어댑터, 에디터 연동처럼 특정 모듈의 확장점에 붙는다.

공식 선택 모듈로 만든다:

- 저가형 호스팅 운영, 보안 점검, 백업 확인처럼 여러 사이트에서 반복되지만 비즈니스 도메인은 아닌 기능이다.
- 코어와 다른 모듈은 해당 모듈이 비활성 상태여도 동작해야 한다.
- 다른 모듈이 이 모듈을 활용할 때는 양방향 공유 테이블보다 안정 식별자 기반의 단방향 참조나 명시적 계약 파일을 우선한다.
- 파일 업로드, 메일 운영 화면, 백업, healthcheck처럼 공통으로 보이지만 정책 판단이 들어가는 기능은 먼저 선택 모듈 후보로 검토한다.

코어에 넣지 않는다:

- 게시글, 상품, 주문, 댓글, 메뉴, 포인트, 쿠폰, 알림, SEO 판단 같은 도메인 기능
- 미래 모듈을 예상한 공통 컬럼
- 모듈별 workflow를 대신 처리하는 범용 관리자
- 자동 route 등록, 자동 hook 등록, 자동 migration

## 2. 기본 디렉터리 구조

권장 구조:

```text
modules/{module_key}/
- module.php
- helpers.php (optional)
- helpers/ (optional)
- paths.php (optional)
- admin-menu.php (optional)
- menu-links.php (optional)
- output-slots.php (optional)
- extension-points.php (optional)
- privacy-export.php (optional)
- sitemap.php (optional)
- actions/ (optional)
- views/ (optional)
- assets/ (optional)
- lang/ (optional)
- install.sql
- updates/ (optional)
```

최소 설치 가능한 모듈:

```text
modules/sample/
- module.php
- install.sql
```

관리자 화면이 있는 모듈:

```text
modules/sample/
- module.php
- helpers.php
- paths.php
- admin-menu.php
- actions/admin-sample.php
- views/admin-sample.php
- install.sql
```

공개 화면과 확장 지점이 있는 모듈:

```text
modules/board/
- module.php
- helpers.php
- paths.php
- extension-points.php
- sitemap.php
- privacy-export.php
- actions/list.php
- actions/view.php
- actions/admin-posts.php
- views/list.php
- views/view.php
- views/admin-posts.php
- install.sql
- updates/2026.05.002.sql
```

## 3. 이름 규칙

`module_key`는 `\A[a-z][a-z0-9_]{1,39}\z` 형식을 사용한다. 즉 영문 소문자로 시작하고, 전체 길이는 2-40자이며, 이후 문자는 영문 소문자, 숫자, 밑줄만 허용한다.

좋은 예:

```text
member
board
shop_order
payment_toss
```

피할 예:

```text
Member
1board
a
shop-order
vendor/package
../admin
```

DB 테이블은 프로젝트 prefix인 `toy_`로 시작한다.

좋은 예:

```text
toy_board_posts
toy_board_comments
toy_payment_toss_transactions
```

피할 예:

```text
core_posts
member_points
posts
```

모듈이 회원과 연결되는 데이터를 저장할 때는 `toy_member_accounts`를 넓히지 않고 자기 테이블에 `account_id`를 둔다.

```sql
CREATE TABLE IF NOT EXISTS toy_board_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    body_text TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_board_posts_account (account_id),
    KEY idx_toy_board_posts_status_created (status, created_at)
);
```

## 4. `module.php`

`module.php`는 모듈 메타데이터 파일이다. 코어와 admin 모듈은 이 파일을 필요할 때 명시적으로 읽는다.

예:

```php
<?php

return [
    'name' => 'Board',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => 'Simple board module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => [
            'member',
            'admin',
        ],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'extension-points.php',
            'privacy-export.php',
            'sitemap.php',
        ],
        'consumes' => [
            'output-slots.php',
        ],
    ],
    'settings' => [
        'posts_per_page' => 20,
        'allow_comments' => true,
    ],
];
```

필드 기준:

- `name`: 관리자 화면에 표시할 짧은 이름
- `version`: 코드 기준 현재 버전
- `type`: `module` 또는 `plugin`
- `description`: 운영자가 이해할 수 있는 설명
- `toycore.min_version`: 이 모듈을 설치하거나 활성화할 수 있는 Toycore 최소 버전. 필수이며 현재 `TOY_CORE_VERSION`과 실제 비교한다.
- `toycore.tested_with`: 모듈 릴리스 시 검증한 Toycore 버전 목록. 비어 있지 않은 배열이 필요하다.
- `toycore.module_contract`: 모듈이 지원하는 Toycore 모듈 계약 버전. 현재 코어의 계약 버전은 `TOY_MODULE_CONTRACT_VERSION`이며 필수다. 값이 맞지 않으면 계약 파일 로딩 대상에서 제외된다.
- `requires.modules`: 활성화 전에 필요한 모듈
- `requires.contracts`: 활성화 전에 필요한 계약 파일. 대상 모듈이 enabled여도 현재 코어와 메타데이터/계약이 맞지 않으면 요구사항을 만족하지 않은 것으로 본다.
- `contracts.provides`: 이 모듈이 제공하는 계약 파일. `paths.php`, `admin-menu.php`, `output-slots.php` 같은 계약 파일이 실제로 있으면 반드시 선언하고, 선언한 파일은 실제로 있어야 한다.
- `contracts.consumes`: 이 모듈이 읽는 계약 파일
- `settings`: 모듈 기본 설정 후보

`module.php`에서 하지 않는다:

- DB 변경
- route 등록
- action include
- output 출력
- 세션 변경
- 활성화되지 않은 모듈의 부팅 처리

`module.php`는 Service Provider가 아니다. 정보 파일이다.

## 5. 의존성 선언

다른 모듈이나 계약 파일이 있어야 정상 동작하는 모듈은 `requires`를 선언한다.

```php
<?php

return [
    'name' => 'Example Plugin',
    'version' => '2026.05.001',
    'type' => 'plugin',
    'requires' => [
        'modules' => [
            'member',
            'seo' => '2026.04.002',
        ],
        'contracts' => [
            [
                'module' => 'member',
                'file' => 'extension-points.php',
            ],
        ],
    ],
];
```

관리자 모듈 설치/활성화 흐름은 `enabled` 상태로 만들기 전에 의존 모듈이 활성화되어 있는지 확인한다. `module_key => version` 형태를 쓰면 최소 버전도 확인한다. 설치 후 `disabled` 상태로 둘 때는 의존성 검사를 강제하지 않는다.

의존성은 실행 순서가 아니라 운영 조건이다. 의존성 선언만으로 다른 모듈 파일을 자동 include하지 않는다.

## 6. 요청 흐름과 `paths.php`

Toycore 요청 흐름은 다음 형태다.

```text
index.php
-> method/path 확인
-> 설치 상태 확인
-> DB와 사이트 설정 로드
-> 활성 모듈 목록 조회
-> 각 활성 모듈의 paths.php 읽기
-> METHOD /path와 일치하는 action 파일 검증
-> 요청 contract 시작
-> action include
-> 요청 contract 검사
```

`paths.php`는 단순 배열만 반환한다.

```php
<?php

return [
    'GET /board' => 'actions/list.php',
    'GET /board/view' => 'actions/view.php',
    'GET /admin/board/posts' => 'actions/admin-posts.php',
    'POST /admin/board/posts' => 'actions/admin-posts.php',
];
```

규칙:

- key는 `METHOD /path` 형식이다.
- method는 보통 `GET` 또는 `POST`를 사용한다.
- action 경로는 `actions/...php`만 사용한다.
- action 파일은 실제로 모듈 디렉터리 안에 있어야 한다.
- path 등록 함수나 전역 dispatcher를 만들지 않는다.
- 같은 method/path를 여러 활성 모듈이 선언하면 요청은 실패한다.
- 관리자 화면 path는 `/admin/...` 아래에 둔다.
- 상태 변경은 `POST`로 처리한다.

경로 설계 기준:

- public: `/board`, `/board/view`
- account: `/account/notifications`
- admin: `/admin/board/posts`
- API처럼 보이는 endpoint도 처음에는 같은 action 흐름으로 둔다.
- 숨은 JSON router를 따로 만들지 않는다.

## 7. action 파일 작성

action 파일은 요청 판단과 상태 변경을 담당한다. view는 출력만 담당한다.

기본 골격:

```php
<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/board/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $title = toy_post_string('title', 160);

    if ($title === '') {
        $errors[] = '제목을 입력하세요.';
    }

    if ($errors === []) {
        toy_board_save_post($pdo, [
            'title' => $title,
            'account_id' => (int) $account['id'],
        ]);

        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'board.post.saved',
            'target_type' => 'board_post',
            'target_id' => '',
            'result' => 'success',
            'message' => 'Board post saved.',
        ]);

        $notice = '저장했습니다.';
    }
}

$posts = toy_board_recent_posts($pdo);
$adminPageTitle = '게시판';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
include TOY_ROOT . '/modules/board/views/admin-posts.php';
include TOY_ROOT . '/modules/admin/views/layout-footer.php';
```

action 파일 책임:

- 로그인/권한 검증
- 입력 읽기와 서버 검증
- CSRF 검증
- DB 조회/변경
- 감사 로그 또는 인증 로그 기록
- redirect 결정
- view에 필요한 변수 준비
- view include

action 파일에서 피한다:

- `exit`, `die` 직접 호출
- `header('Location: ...')` 직접 호출
- 전체 HTML을 heredoc 문자열로 출력
- 사용자 입력을 escape 없이 출력
- 권한 판단을 view에 맡기기
- 다른 모듈의 내부 helper 하위 파일을 직접 require
- path 등록 또는 자동 dispatcher 변경
- 토큰, 비밀번호, 개인정보 원문 로그 기록

action에서 응답을 끝내야 하면 `toy_redirect()`, `toy_render_error()`, `toy_finish_response()` 중 하나를 사용한다. 이 helper들은 dispatch contract 검사를 거친 뒤 종료한다. `header('Content-Type: ...')` 같은 응답 메타 제어는 허용하지만, redirect는 반드시 `toy_redirect()`를 통과해야 한다.

`toy_request_contract_mark()`와 `toy_request_contract_guard_blocked()`는 action 파일에서 직접 호출하지 않는다. action은 `toy_require_csrf()`, `toy_member_require_login()`, `toy_admin_require_role()` 같은 공개 helper를 호출해 contract mark가 자연스럽게 기록되게 둔다.

## 8. view 작성

view는 PHP와 HTML을 섞되, HTML을 기본으로 쓰고 필요한 위치에만 `<?php echo ...; ?>`를 둔다.

```php
<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<form method="post" action="<?php echo toy_e(toy_url('/admin/board/posts')); ?>">
    <?php echo toy_csrf_field(); ?>
    <label>
        제목
        <input type="text" name="title" value="<?php echo toy_e($title); ?>" maxlength="160" required>
    </label>
    <button type="submit">저장</button>
</form>
```

view 규칙:

- 변수 출력은 `toy_e()`로 escape한다.
- 줄바꿈이 필요한 텍스트는 `nl2br(toy_e($value))`를 사용한다.
- `<?= ... ?>` 숏 echo 태그를 쓰지 않는다.
- `echo <<<HTML`로 전체 레이아웃을 출력하지 않는다.
- view에서 `$_GET`, `$_POST`, `$_COOKIE`를 직접 읽지 않는다.
- view에서 DB 변경을 하지 않는다.
- 상태 변경 form에는 CSRF 필드를 넣는다.
- 권한 최종 판단은 action에서 끝낸다.

출력 예외:

- 이미 helper가 escape를 끝내고 반환한 HTML 조각은 그대로 출력할 수 있다.
- `toy_render_output_slot()`처럼 출력 확장 helper가 반환하는 HTML은 그 helper/모듈이 escape 책임을 가진다.
- 그래도 view 작성자는 사용자 입력 원문 HTML을 신뢰하지 않는다.

## 9. helper 파일

공통 함수가 필요하면 `helpers.php`를 모듈 helper 진입점으로 둔다.

```text
modules/board/
- helpers.php
- helpers/posts.php
- helpers/comments.php
- helpers/settings.php
```

`helpers.php` 예:

```php
<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/board/helpers/posts.php';
require_once TOY_ROOT . '/modules/board/helpers/settings.php';
```

규칙:

- action은 가능하면 모듈의 `helpers.php`만 require한다.
- 하위 helper 파일은 로드 시 부작용을 만들지 않는다.
- helper 함수는 `PDO $pdo`를 명시적으로 인자로 받는다.
- 함수명은 `toy_{module_key}_...` prefix를 사용한다.
- 다른 모듈의 테이블을 직접 조인하기 전에 공개 helper나 계약 파일로 대체 가능한지 본다.
- helper가 HTML을 반환한다면 escape 책임을 helper 안에서 끝낸다.

## 9-1. 정적 assets

모듈 전용 CSS, JavaScript, 이미지가 필요하면 `assets/` 아래에 둔다.

```text
modules/board/
- assets/
  - board.css
  - board.js
  - empty-state.png
```

규칙:

- 공개 URL은 `/modules/{module_key}/assets/...` 형태를 기준으로 한다.
- 파일명은 영문 소문자, 숫자, 밑줄, 하이픈처럼 안전한 이름을 사용한다.
- PHP, SQL, 설정 파일, 업로드 원본처럼 실행되거나 민감할 수 있는 파일은 `assets/`에 두지 않는다.
- 운영 서버가 `modules/` 전체 직접 접근을 차단하는 구성이라면, 필요한 정적 파일만 허용하거나 모듈 action을 통해 응답하는 별도 방식을 둔다.
- 사용자 업로드 파일 저장소로 `assets/`를 사용하지 않는다.

## 9-2. 파일 업로드

파일 업로드는 코어 기능처럼 보이지만 파일의 의미, 공개 범위, 다운로드 권한, 보존 정책은 모듈마다 다르다. 코어는 `core/helpers/upload.php`의 낮은 수준 helper만 제공하고, 파일 테이블과 관리자 화면은 파일을 소유한 모듈이 책임진다.

모듈 action 책임:

- `toy_upload_validate_file()`에는 `max_bytes`, `allowed_extensions`, `allowed_mime_types`를 명시하고 업로드 오류, 크기, 확장자 allowlist, MIME, 실행 가능 확장자 차단을 통과시킨다.
- 서버에서 MIME을 감지할 수 없으면 업로드는 실패로 처리한다. 모듈은 이를 설정 오류나 업로드 거부 메시지로 노출한다.
- 저장 파일명은 원본 이름을 신뢰하지 않고 `toy_upload_random_filename()`으로 만든다.
- 저장 위치는 웹에서 직접 실행되지 않는 디렉터리를 우선하고, 공개 파일이 필요하면 모듈이 별도 공개 응답 action을 둔다.
- `toy_upload_move_uploaded_file()` 또는 검증된 값 기반의 명시적 이동만 사용한다.
- 파일 metadata, 소유자, 공개/비공개 상태, 삭제/보존 정책은 모듈 테이블에 저장한다.
- 비공개 다운로드는 직접 파일 URL 대신 `toy_download_token_create()`와 `toy_download_token_verify()`를 사용해 단기 token으로 처리한다.
- 다운로드 응답은 `toy_send_download_headers()`로 헤더를 보내고 본문 출력 후 `toy_finish_response()`로 종료한다.
- 이미지 업로드는 필요할 때 `toy_upload_reencode_image()`를 호출하되, GD/Imagick이 없거나 재인코딩에 실패하면 모듈 정책에 따라 거부하거나 원본 저장을 중단한다.

하지 않는다:

- `toy_files` 같은 코어 공통 파일 테이블을 전제로 작성하지 않는다.
- 업로드 원본을 `modules/{module_key}/assets`에 저장하지 않는다.
- 파일명, MIME, 확장자 중 하나만 믿고 공개하지 않는다.
- 다운로드 권한 판단을 view나 클라이언트 JavaScript에 맡기지 않는다.

## 9-3. 번역 파일

모듈 UI 문구 번역은 `lang/{locale}.php` 파일로 둔다. 사용자 콘텐츠 다국어화는 각 모듈의 도메인 테이블과 화면에서 따로 설계한다.

```text
modules/board/
- lang/
  - ko.php
  - en.php
```

번역 파일 예:

```php
<?php

return [
    'admin.title' => '게시판',
    'post.saved' => '저장했습니다.',
];
```

사용 예:

```php
<?php echo toy_e(toy_t('board::admin.title')); ?>
```

규칙:

- 번역 파일은 배열만 반환한다.
- locale 파일명은 `ko`, `en-US` 같은 locale 값과 맞춘다.
- 최소 기본 locale 파일을 제공한다.
- 번역 값도 화면에 출력할 때는 `toy_e()`로 escape한다.
- 게시글 제목, 상품명 같은 사용자 콘텐츠 번역 테이블을 코어가 대신 만들지 않는다.

## 10. DB 접근

Toycore는 PDO prepared statement를 기본으로 한다.

```php
<?php

$stmt = $pdo->prepare(
    'SELECT id, title
     FROM toy_board_posts
     WHERE status = :status
     ORDER BY id DESC
     LIMIT 20'
);
$stmt->execute(['status' => 'published']);
$posts = $stmt->fetchAll();
```

허용:

- 외부 값이 없는 고정 SQL에 `query()`
- 동적 값에 `prepare()`와 named placeholder
- 설치/업데이트 SQL 파일 실행에 `exec()`
- 테이블명/컬럼명은 허용 목록에서 선택한 값만 문자열 결합

금지:

```php
<?php

$pdo->query("SELECT * FROM toy_board_posts WHERE title = '" . $_GET['title'] . "'");
$pdo->exec("DELETE FROM " . $_POST['table']);
```

정렬 예:

```php
<?php

$allowedSorts = [
    'newest' => 'id DESC',
    'oldest' => 'id ASC',
];
$sort = $allowedSorts[$requestedSort] ?? $allowedSorts['newest'];
$stmt = $pdo->query('SELECT id, title FROM toy_board_posts ORDER BY ' . $sort . ' LIMIT 50');
```

자세한 기준은 [DB 접근 정책](database-access-policy.md)을 따른다.

## 11. 설치 SQL

`install.sql`은 모듈이 소유한 테이블과 초기 데이터를 만든다.

규칙:

- 모듈 소유 테이블만 만든다.
- 테이블명은 `toy_` prefix를 사용한다.
- `CREATE TABLE IF NOT EXISTS`를 사용한다.
- 초기 데이터는 재실행해도 안전하게 작성한다.
- 너무 많은 대량 데이터 seed를 넣지 않는다.
- 외래키는 공유호스팅 호환성을 고려해 선택적으로 사용한다.
- 실패 후 재시도를 고려해 unique key와 `ON DUPLICATE KEY UPDATE`를 함께 설계한다.

예:

```sql
CREATE TABLE IF NOT EXISTS toy_board_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_key VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_board_categories_key (category_key)
);

INSERT INTO toy_board_categories (category_key, label, status, created_at, updated_at)
VALUES ('notice', '공지사항', 'enabled', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    status = VALUES(status),
    updated_at = VALUES(updated_at);
```

## 12. 업데이트 SQL

이미 설치된 모듈의 구조 변경은 `updates/` 아래 SQL 파일로 처리한다.

```text
modules/board/updates/2026.05.002.sql
```

규칙:

- 파일명은 `YYYY.MM.NNN.sql` 형식이다.
- 한 파일은 한 버전의 변경만 담는다.
- 파일을 추가하면 `module.php`의 `version`도 올린다.
- 기본 설치 SQL에도 최신 구조를 반영한다.
- unique key 추가 전 중복 데이터를 정리한다.
- 실패한 SQL을 같은 version으로 조용히 바꾸지 않는다.
- 이미 배포된 update 파일을 수정해야 했다면 새 version 파일을 추가하는 것을 우선한다.

업데이트 작성 전 확인:

- 기존 설치에서 바로 올라올 수 있는가?
- 일부 DDL이 적용된 뒤 실패해도 복구 가능성이 있는가?
- 큰 테이블에서 오래 걸리는 작업은 없는가?
- 관리자 업데이트 화면에 표시될 변경 단위가 이해 가능한가?

업데이트 정책은 [모듈 배치와 업데이트 기준](module-update-policy.md)을 따른다.

## 13. 모듈 설정

모듈 설정은 `toy_module_settings`에 저장한다. 설정 조회는 코어 helper를 사용한다.

```php
<?php

$settings = toy_module_settings($pdo, 'board');
$postsPerPage = (int) toy_module_setting($pdo, 'board', 'posts_per_page', 20);
```

설정 기본값은 `module.php`에 둘 수 있지만, 실제 저장과 검증은 모듈 action에서 처리한다.

전용 설정 화면을 권장한다:

- 운영자가 자주 바꾸는 설정
- 값의 단위와 범위가 중요한 설정
- 보안에 영향을 주는 설정
- 공개 화면 동작이 바뀌는 설정

전용 설정 화면 규칙:

- `GET`은 현재 값 표시
- `POST`는 CSRF 검증 후 저장
- 서버에서 타입과 범위 검증
- 저장 후 `toy_clear_module_settings_cache('{module_key}')` 호출
- 변경 감사 로그 기록

범용 `/admin/modules` key/value 설정은 비상용 또는 낮은 수준의 관리 도구로 본다.

## 14. 관리자 메뉴

관리자 메뉴가 필요한 모듈은 `admin-menu.php`를 둔다.

```php
<?php

return [
    [
        'label' => '게시판',
        'path' => '/admin/board/posts',
        'order' => 40,
    ],
];
```

규칙:

- `path`는 `/admin/...` 아래만 사용한다.
- 같은 모듈의 `paths.php`에 `GET {path}`가 있어야 한다.
- 메뉴는 화면 등록이 아니라 노출 정보다.
- 권한 검사는 action 파일에서 한다.
- admin 모듈은 도메인 모듈의 메뉴 label/path를 하드코딩하지 않는다.

## 15. 계약 파일

모듈 간 영향은 숨은 event bus가 아니라 계약 파일로 연결한다.

대표 계약 파일:

- `admin-menu.php`: 관리자 메뉴 항목
- `menu-links.php`: 사이트 메뉴 후보 링크
- `output-slots.php`: 출력 renderer
- `extension-points.php`: 확장 가능한 화면/기능 위치
- `privacy-export.php`: 회원 개인정보 내보내기 확장
- `sitemap.php`: SEO sitemap URL 확장

계약 파일 규칙:

- 모듈 디렉터리 바로 아래에 둔다.
- 배열 또는 callable을 반환한다.
- 로드 시 상태 변경을 하지 않는다.
- 공개 가능한 정보만 선언한다.
- 소비 모듈은 값을 다시 검증한다.
- 사용자 요청마다 비싼 계약 파일 탐색을 반복하지 않는다.

계약 파일은 자동 등록이 아니다. 소비 모듈이 필요한 시점에 명시적으로 읽는 공개 약속이다.

## 15-1. 계약 파일 반환 구조

계약 파일의 최소 반환 구조는 전체 점검과 소비 모듈의 로드 시점 검증으로 확인한다. 더 깊은 의미 검증은 소비 모듈이 다시 수행한다.

`paths.php`:

- 배열을 반환한다.
- key는 `GET /path` 또는 `POST /path` 형식이다.
- value는 `actions/...php` 형식의 실제 action 파일이다.

`admin-menu.php`:

- 배열을 반환한다.
- 각 항목은 `label`, `path`, 선택 `order`를 가진 배열이다.
- `path`는 `/admin/...` 형식이어야 한다.
- 같은 모듈의 `paths.php`에 `GET {path}`가 있어야 한다.

`menu-links.php`:

- 배열을 반환한다.
- 각 항목은 `label`, `url`을 가진 배열이다.
- `url`은 내부 상대 경로(`/board`) 또는 허용된 `http/https` URL이다.

`output-slots.php`:

- callable을 반환한다.
- callable 형식은 `function (PDO $pdo, array $context): string`이다.
- 외부 저장소에서 로컬 점검될 수 있으므로 자기 helper를 읽을 때는 `__DIR__` 기준 경로를 사용한다.

`extension-points.php`:

- 배열을 반환한다.
- 각 항목은 `point_key`, `label`, 선택 `surface`, `output`, `slots`, `subjects`를 가진다.
- `point_key`는 `board.post.view`처럼 모듈 안에서 안정적인 key로 둔다.
- `slots`가 있으면 각 slot은 `slot_key`를 가진 배열이다.

`privacy-export.php`:

- 배열 또는 callable을 반환한다.
- callable 형식은 `function (PDO $pdo, int $accountId): array`이다.

`sitemap.php`:

- 배열 또는 callable을 반환한다.
- 배열 항목은 최소 `loc` 값을 가진다.
- callable 형식은 `function (PDO $pdo, ?array $site): array`이다.

## 16. Output Slots

화면 출력 지점에 여러 확장 모듈이 붙을 수 있을 때는 화면 소유 모듈 view에서 특정 확장 모듈 helper를 직접 호출하지 않는다.

화면 소유 모듈:

```php
<?php echo toy_render_output_slot($pdo, [
    'module_key' => 'board',
    'point_key' => 'board.post.view',
    'slot_key' => 'before_content',
    'subject_id' => (string) $post['id'],
]); ?>
```

출력 확장 모듈의 `output-slots.php`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, array $context): string {
    return toy_example_banner_render($pdo, $context);
};
```

renderer 규칙:

- 출력할 HTML 문자열을 반환한다.
- 아무것도 출력하지 않으면 빈 문자열을 반환한다.
- 화면 소유 모듈은 `slot_key`를 명시해서 호출한다.
- context 값을 검증한다.
- 사용자 입력과 DB 값은 escape 후 출력한다.
- DB 조회가 필요하면 인덱스가 있는 저장 규칙 테이블을 사용한다.

## 17. Extension Points

`extension-points.php`는 외부 확장이 붙을 수 있는 화면이나 기능 위치를 선언한다.

용어:

```text
extension point = 확장 가능한 화면/기능 단위
slot = extension point 안의 구체적인 출력 위치
subject = 특정 글, 상품, 게시판 같은 세부 대상
```

선택 깊이는 기본적으로 4단계를 넘기지 않는다.

```text
module -> point -> slot -> subject
```

예:

```php
<?php

return [
    [
        'point_key' => 'board.post.view',
        'label' => '게시글 보기',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => '본문 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => '본문 아래',
                'kind' => 'content',
            ],
        ],
        'subjects' => [
            'type' => 'board',
            'label' => '게시판',
            'options' => [
                ['value' => 'notice', 'label' => '공지사항'],
                ['value' => 'free', 'label' => '자유게시판'],
            ],
        ],
    ],
];
```

필드 기준:

- `point_key`: 모듈 안에서 안정적으로 유지되는 key
- `label`: 관리자 화면 표시 이름
- `surface`: `public`, `account`, `admin` 등 노출 영역
- `output`: 출력형 확장이 붙을 수 있는지 여부
- `slots`: 실제 출력 위치 목록
- `slot_key`: point 안의 위치 key
- `kind`: `content`, `head`, `script` 같은 위치 성격. 배너와 팝업레이어처럼 화면 본문에 붙는 출력 모듈은 `content` slot을 대상으로 한다.
- `subjects`: 선택 대상 정보

대상이 많으면 `options` 전체를 반환하지 말고 검색형 selector를 선언한다.

```php
'subjects' => [
    'type' => 'product',
    'label' => '상품',
    'selector' => [
        'mode' => 'search',
        'action' => '/admin/shop/products/search',
    ],
],
```

성능 기준:

- 사용자 요청에서는 `extension-points.php`를 읽지 않는다.
- 관리자 설정 화면에서만 확장 대상 목록을 읽는다.
- 사용자 요청에서는 저장된 규칙 테이블만 조회한다.
- 대량 subject는 검색 selector를 사용한다.

팝업레이어 규칙:

- 팝업레이어는 배너와 같이 `kind=content`인 slot을 대상으로 한다.
- 화면 소유 모듈은 팝업 전용 호출을 따로 두지 않고 필요한 content slot에서 `toy_render_output_slot()`을 호출한다.
- 팝업레이어 모듈은 자신의 `output-slots.php`에서 저장된 대상 규칙, 기간, 닫기 유지 정책을 검증한 뒤 해당 slot에 출력할 HTML을 반환한다.

## 18. 사이트 메뉴 후보

사이트 공통 메뉴 구조는 `site_menu` 모듈이 소유한다. 각 모듈은 운영자가 선택할 수 있는 후보 링크만 `menu-links.php`로 제공한다.

```php
<?php

return [
    [
        'label' => '게시판',
        'url' => '/board',
    ],
];
```

규칙:

- 후보 제공은 메뉴 항목 자동 생성을 의미하지 않는다.
- 최종 메뉴 구성은 `site_menu` 관리자 화면에서 운영자가 결정한다.
- `url`은 내부 상대 경로 또는 허용된 외부 URL이어야 한다.
- 메뉴 후보는 화면 위치가 아니므로 `extension-points.php`로 선언하지 않는다.

## 19. 개인정보 내보내기

회원 모듈은 회원 계정, 인증, 동의처럼 자신이 소유한 데이터만 기본 JSON 내보내기에 포함한다. 게시판, 커머스, 예약, 알림 같은 확장 모듈의 개인정보는 각 모듈이 `privacy-export.php`로 제공한다.

```php
<?php

return function (PDO $pdo, int $accountId): array {
    $stmt = $pdo->prepare(
        'SELECT id, title, status, created_at
         FROM toy_board_posts
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'posts' => $stmt->fetchAll(),
    ];
};
```

규칙:

- 다른 회원 데이터가 섞이지 않게 `account_id` 조건을 명확히 둔다.
- 내부 hash, token hash, 비밀번호 hash는 내보내지 않는다.
- 전역 공지 원문처럼 특정 회원에게 귀속되지 않는 값은 신중히 포함한다.
- 회원 테이블에 도메인 컬럼을 추가하지 않는다.

## 20. Sitemap 확장

SEO sitemap에 공개 URL을 제공하려면 `sitemap.php`를 둔다.

배열 반환:

```php
<?php

return [
    [
        'loc' => '/board',
        'changefreq' => 'daily',
        'priority' => '0.5',
    ],
];
```

callable 반환:

```php
<?php

return function (PDO $pdo, ?array $site): array {
    unset($site);

    $stmt = $pdo->query(
        "SELECT id, updated_at
         FROM toy_board_posts
         WHERE status = 'published'
         ORDER BY id DESC
         LIMIT 1000"
    );

    $urls = [];
    foreach ($stmt->fetchAll() as $post) {
        $urls[] = [
            'loc' => '/board/view?id=' . (int) $post['id'],
            'lastmod' => substr((string) $post['updated_at'], 0, 10),
        ];
    }

    return $urls;
};
```

규칙:

- 공개 가능한 URL만 반환한다.
- 비공개/삭제/임시저장 콘텐츠는 반환하지 않는다.
- SEO 모듈은 URL 형식과 XML escape를 처리하지만 콘텐츠 공개 정책은 모듈이 판단한다.
- 너무 많은 URL을 한 번에 반환하지 않는다.

## 21. 보안 체크리스트

모듈 PR 또는 배포 전 확인한다.

- 모든 상태 변경 요청이 `POST`인가?
- 모든 `POST` action에서 `toy_require_csrf()`를 호출하는가?
- 관리자 action 시작 부분에서 로그인과 role을 검증하는가?
- 회원 전용 action에서 `toy_member_require_login()`을 사용하는가?
- 출력 값은 `toy_e()` 또는 동등한 escape를 거쳤는가?
- SQL 동적 값은 prepared statement로 바인딩했는가?
- 정렬 컬럼, 테이블명, 상태 값은 allowlist를 사용하는가?
- redirect 대상은 내부 상대 경로로 제한했는가?
- 토큰 원문을 DB나 로그에 저장하지 않는가?
- 개인정보 내보내기에 hash/token/password가 빠져 있는가?
- 감사 로그에 민감 원문을 넣지 않았는가?
- 파일 경로 입력이 있으면 모듈 디렉터리 밖으로 나갈 수 없는가?
- 외부 링크 출력에는 `rel="noopener noreferrer"`가 붙는가?

## 22. 성능 기준

Toycore는 저가형 웹호스팅을 고려한다.

- 요청마다 전체 모듈 디렉터리를 깊게 스캔하지 않는다.
- 사용자 요청에서 관리자용 계약 파일을 반복 파싱하지 않는다.
- 목록 조회는 기본적으로 limit을 둔다.
- 대량 데이터 export는 한 번에 너무 크게 만들지 않는다.
- 캐시는 필수가 아니라 선택 최적화로 둔다.
- DB 인덱스는 실제 조회 조건에 맞춘다.
- 백그라운드 worker가 필수인 설계는 초기 모듈로 피한다.

## 23. 테스트와 점검

기본 점검:

```sh
./.tools/bin/check
```

확인 항목:

- `git diff --check`
- SQL 파일이 비어 있지 않은지
- 모듈 기본 구조
- `admin-menu.php` path와 `paths.php` GET route 일치
- Docker 또는 OrbStack 실행 시 PHP lint

수동 점검:

- 설치 전 새 모듈을 선택 설치할 수 있는가?
- 이미 설치된 사이트에서 `/admin/modules`로 설치할 수 있는가?
- 비활성 상태에서는 route가 열리지 않는가?
- 활성화 의존성 오류가 이해 가능하게 표시되는가?
- POST를 새로고침해도 중복 문제가 생기지 않는가?
- 업데이트 실패 시 재시도 가능한가?

## 24. 배포와 버전

모듈 버전은 `module.php`의 `version`과 `updates/` 파일명을 같이 관리한다. 표기 형식은 정렬 가능한 날짜 기반 `YYYY.MM.NNN`을 사용한다.

권장:

- 기능 추가: version 증가, 필요하면 update SQL 추가
- SQL 구조 변경: install.sql 최신화 + updates 파일 추가
- 문서만 변경: module.php version은 보통 유지
- 릴리스 zip 이름: `{module_key}-{module.php version}.zip`
- 배포된 update SQL 수정 대신 새 update SQL 추가
- 릴리스 노트에 설치/업데이트/호환 버전을 적는다.

공개 배포나 반복 배포를 고려하는 모듈은 모듈 폴더 옆에 `README.md`, `CHANGELOG.md`, `LICENSE` 같은 문서를 둘 수 있다. 다만 Toycore에 배치되는 런타임 파일은 `modules/{module_key}` 아래에 있어야 한다.

Git을 사용할 수 없는 운영 환경을 기본 지원 대상으로 본다. 따라서 운영 설치는 zip 업로드 또는 FTP/파일 관리자 배치를 기준으로 설명한다.

모듈 배포 흐름:

```text
1. 모듈 폴더에서 개발
2. {module_key}-{version}.zip 생성
3. 운영자가 zip을 업로드하거나 압축 해제 후 modules/{module_key}/에 배치
4. /admin/modules에서 설치/활성화
5. 파일 교체 후 /admin/updates에서 DB 업데이트 실행
```

릴리스 zip은 압축 해제 시 바로 모듈 키 디렉터리가 나오도록 만든다.

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
   - paths.php
   - actions/
   - views/
```

새 모듈을 추가할 때는 먼저 `modules/{module_key}` 폴더 안에서 책임 경계를 잡는다. Toycore 런타임은 최종 배치된 모듈 폴더만 읽는다.

## 25. 금지하는 방향

Toycore 기본 구현에서는 다음 방식을 사용하지 않는다.

- Laravel Service Provider 같은 부팅 클래스
- Composer 자동 패키지 발견을 필수로 하는 구조
- Artisan 같은 CLI 필수 명령
- ORM 모델 중심의 데이터 접근
- 클래스 기반 migration 필수화
- DI 컨테이너 필수화
- 이벤트 버스 중심 실행
- reflection 기반 자동 요청 분기
- 모듈이 부팅 중 path를 몰래 등록하는 구조
- 코어/member 테이블을 미래 도메인 요구로 넓히는 구조

도구를 쓰더라도 프로젝트 실행에 필수가 되면 안 된다. Toycore의 기본 가정은 일반 웹호스팅에서 PHP 파일과 SQL만으로 설치되고 동작하는 구조다.
