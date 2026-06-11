# 모듈 작성 가이드

이 문서는 산란 모듈을 실제로 만들고 유지보수할 때 따르는 기준이다.

산란의 모듈은 프레임워크 패키지가 아니다. 모듈은 정해진 디렉터리에 놓인 절차형 PHP 파일, 정적 SQL 파일, DB에 저장된 설치/활성 상태로 동작한다. 자동 발견, 서비스 프로바이더, ORM, 클래스 마이그레이션, DI 컨테이너, 이벤트 버스를 기본 전제로 두지 않는다.

모듈 작성의 목표는 기능을 빠르게 붙이는 것이 아니라 다음 상태를 유지하는 것이다.

- 요청 흐름이 파일을 열면 보일 것
- 코어가 도메인 정책을 대신 소유하지 않을 것
- 모듈이 자기 테이블, 화면, 정책, 업데이트를 책임질 것
- 저가형 웹호스팅에서도 PHP 파일과 SQL만으로 설치 가능할 것
- 보안 판단을 view나 클라이언트 코드에 미루지 않을 것

산란 안에서는 모듈을 항상 `modules/{module_key}` 폴더로 다룬다. 파일 교체, zip 업로드, DB 업데이트 흐름은 [모듈 배치와 업데이트 기준](module-update-policy.md)을 따른다.
배포판 모듈을 사이트별 요구에 맞게 확장할 때 공식 테이블과 파일을 직접 수정할지, 별도 모듈/테이블/layout으로 분리할지 판단하는 기준은 [커스터마이징 가이드](customization-guide.md)를 따른다.

## 1. 모듈 판단 기준

산란에서 설치/활성화 가능한 확장 단위는 같은 `sr_modules` 등록 흐름을 사용한다. 다만 개념은 구분한다.

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

CKEditor 같은 에디터 연동은 플러그인이다. 플러그인은 `type => plugin`으로 등록하고, 적용 대상 textarea와 저장/출력 정책은 화면을 소유한 모듈이 결정한다. 콘텐츠는 콘텐츠 환경설정에서 에디터와 콘텐츠 본문 툴바 구성을 저장하고, 커뮤니티는 환경설정에서 게시글 툴바 구성을 저장하며 환경설정/게시판 그룹/게시판에서 에디터 선택을 저장한다. 관리자는 사이트 설정의 관리자 화면 섹션에서 에디터 선택을 저장한다. CKEditor 플러그인은 에셋 로딩, 초기화 스크립트, 전역 기본 툴바 preset, 화면 소유 모듈이 넘긴 upload endpoint에 대한 adapter 연결만 소유하고, 화면 소유 모듈은 `body_format`, HTML sanitizer, plain text URL 자동 링크 여부, 출력 helper, 파일 권한과 보존 정책을 책임진다. CKEditor 툴바 preset은 콘텐츠/커뮤니티 환경설정의 명시 override, CKEditor 전역 기본 `toolbar_preset`, 코드 fallback 순서로 적용한다. 관리자 공통 에디터 설정은 업로드 endpoint를 자동으로 제공하지 않으며, 알림/팝업레이어/설정 화면처럼 rich textarea를 가진 소유 모듈이 필요할 때만 자기 subject id 또는 setting key 기준 upload endpoint를 textarea에 명시한다. CKEditor 설정은 관리자 사이드바의 `플러그인` 분류에서 접근할 수 있지만, 이 메뉴는 도메인 관리 화면이 아니라 플러그인 런타임 자산 설정이다. 플러그인이 비활성화되거나 에셋 로딩에 실패하면 일반 textarea 제출이 유지되어야 한다.

공식 선택 모듈로 만든다:

- 저가형 호스팅 운영, 보안 점검, 백업 확인처럼 여러 사이트에서 반복되지만 비즈니스 도메인은 아닌 기능이다.
- 코어와 다른 모듈은 해당 모듈이 비활성 상태여도 동작해야 한다.
- 다른 모듈이 이 모듈을 활용할 때는 양방향 공유 테이블보다 안정 식별자 기반의 단방향 참조나 명시적 계약 파일을 우선한다.
- 파일 업로드, 메일 운영 화면, 백업, healthcheck처럼 공통으로 보이지만 정책 판단이 들어가는 기능은 먼저 선택 모듈 후보로 검토한다.

모듈 간 본문 연결은 `embed_manager`의 제한된 marker와 `sr_embed_manager_refs`를 사용한다. 콘텐츠와 커뮤니티의 본문에는 사용자가 작성한 plain text 또는 허용된 HTML만 저장해야 하며, legacy 링크 카드 토큰이나 hidden 참조 목록을 저장 진실원으로 삼지 않는다. 대상 검색 UI는 활성 모듈의 `embed-manager-targets.php` 계약을 `embed_manager`가 읽어 구성하고, CKEditor 본문에는 `sr-embed-manager-marker` span과 안전한 인용 HTML을 삽입한다. 일반 textarea fallback은 텍스트 링크만 삽입하므로 refs 동기화 대상이 아니다.
저장 action은 최종 정화 HTML에 남은 marker만 기준으로 ref_key, target module/type/id, variant, label을 검증하고, 소유 문서 저장 transaction 안에서 refs를 upsert한다. 계약의 허용 variant가 아닌 marker는 저장 실패로 처리한다. refs는 삭제/복사 차단을 자동 강제하는 hidden 원장이 아니며, broken/private/deleted/removed 상태 표시는 공개 렌더링 정책과 점검 화면으로 처리한다. 복사 흐름은 ref_key를 새로 발급해 본문을 rewrite하고 refs를 새 소유 대상으로 복사해야 한다.

공통 반응/추천 기능을 도입할 때는 커뮤니티 전용 테이블을 먼저 만들지 않고 공식 선택 모듈 `reaction` 후보로 설계한다. 대상 모듈은 `reaction-targets.php` 계약으로 단건 resolve와 batch resolve, target 상태, viewer 기준 공개 열람 가능 여부, 반응 가능 여부, 공개/관리자 URL, label snapshot, 적용 가능한 preset/key를 제공한다. reaction 모듈은 도메인 테이블을 직접 조회하지 않고 계약 결과 위에서 key 적용, 취소, 변경, 중복, throttle, CSRF, 개인정보 export/cleanup을 처리한다. 1차 target 후보는 `content/content`와 `community/post`이며, 퀴즈·설문·댓글·시리즈 반응은 별도 정책 이슈가 닫힌 뒤 확장한다. 익명 write는 허용하지 않고 회원 탈퇴/익명화 시 계정의 reaction record를 삭제한다. reaction은 스크랩, 콘텐츠 완료, 퀴즈 시도, 설문 응답, 보상 grant, 랭킹/SEO 정책을 대신하지 않는다.

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
- logo-positions.php (optional)
- privacy-export.php (optional)
- privacy-cleanup.php (optional)
- sitemap.php (optional)
- dashboard.php (optional)
- actions/ (optional)
- views/ (optional)
- themes/ (optional)
- skins/ (optional)
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

관리자 목록/폼 마크업은 [관리자 UI 작성 기준](admin-ui-guide.md)을 따른다. 특히 등록, 수정, 설정 화면은 `form.admin-form.ui-form-theme > section.admin-card.card` 구조를 실제 view에 직접 작성하고, 목록 검색/행 액션 폼과 구분한다.

관리자 목록에 선택 기반 일괄 작업을 추가할 때는 모듈 action이 작업 정책을 소유한다. view는 첫 열 체크박스, 현재 페이지 전체 선택, 선택 수 표시, 작업 바를 제공할 수 있지만, action은 CSRF, 권한, `intent` allowlist, `operation_key`, 대상 ID 배열, 대상 존재 여부, 현재 처리 가능 상태를 다시 검증해야 한다. 일괄 작업 컨트롤은 목록 요약 행 안에서 항상 보이게 두고, 상태 변경은 select 대신 상태별 작은 submit 버튼으로 제공하며, 선택 해제 버튼과 선택 수 숫자는 선택 항목이 있을 때만 표시한다. 처리 메모, 사유, 대상 조치처럼 실행 전 추가 입력이 필요한 작업은 목록 행이나 요약 행에 입력칸을 노출하지 않고 처리 버튼에서 모달을 열어 입력받는다. 클라이언트가 보낸 선택 수나 진행 수는 감사 로그와 완료 판정의 근거로 쓰지 않는다. 고부하 가능 작업은 모듈별 작업 테이블과 공통 상태 contract를 따르고, 작업 contract는 `{module_key}.{operation}` 형식의 operation key, 권한 path/action, 실행 모델, snapshot 모드, 위험도, handler를 명시한다.

공개 화면과 확장 지점이 있는 모듈:

```text
modules/board/
- module.php
- helpers.php
- paths.php
- extension-points.php
- sitemap.php
- privacy-export.php
- privacy-cleanup.php
- actions/list.php
- actions/view.php
- actions/admin-posts.php
- views/list.php
- views/view.php
- views/admin-posts.php
- themes/basic/home.php
- skins/basic/list.php
- skins/basic/view.php
- skins/basic/form.php
- install.sql
- updates/2026.05.002.sql
```

공개 화면 디자인 책임은 public layout, 모듈 theme, 모듈 skin을 구분한다.

- public layout은 사이트 전체 껍데기만 담당한다. `<html>`, `<head>`, 공통 header/footer, 사이트 메뉴, output slot, 전체 폭과 기본 여백이 여기에 속한다.
- public layout이 사이트 메뉴를 노출할 때는 하위 depth가 아니라 레이아웃 슬롯 구분으로 `primary_navigation`, `secondary_navigation`, `tertiary_navigation`, `quaternary_navigation`, `quinary_navigation` output slot을 사용한다. 콘텐츠와 커뮤니티처럼 레이아웃을 설정하는 모듈은 환경설정의 메뉴 key를 `sr_public_layout_begin()` layout context의 `site_menus.primary`, `site_menus.secondary`, `site_menus.tertiary`, `site_menus.quaternary`, `site_menus.quinary`로 전달한다. 관리자 화면에서는 고정 위치명 대신 `주 메뉴 슬롯`, `보조 메뉴 슬롯`, `추가 메뉴 슬롯 1`처럼 표시하고, 실제 위치는 레이아웃 구현이 결정한다. 번들 공통/콘텐츠/커뮤니티 레이아웃은 primary 메뉴를 header에, 나머지 메뉴 슬롯을 footer 영역에 렌더링한다.
- public layout은 선택적으로 `ui_kit` view를 제공할 수 있다. 기본 레이아웃의 `/ui-kit` 화면은 초기/기본 공개 페이지와 public layout 런타임 기준 공통 UI 원형을 확인하기 위한 원본 개발자 화면이며 admin 모듈에 의존하지 않는다. 모듈별 UI-KIT를 추가하더라도 이 화면은 초기 페이지용 기준으로 보존한다.
- 관리자 화면을 가진 모듈은 자기 화면 조합을 확인하기 위해 모듈 전용 UI-KIT 조회 화면을 둘 수 있다. 이때 route와 view/action은 소유 모듈에 두고, 보조 스타일은 `modules/{module_key}/assets/ui-kit.css` 또는 `modules/{module_key}/assets/{module_key}-ui-kit.css`, 샘플은 `modules/{module_key}/views/ui-kit-samples/`처럼 모듈 내부에 둔다. 공통 UI-KIT 샘플을 복제해 시작할 수 있지만, 실제 모듈 화면에서 쓰는 클래스와 타이포그래피 기준은 모듈 프리뷰에서 따로 검증한다.
- 레이아웃 제공 모듈은 `layout-options.php` 계약으로 `common.basic`, `content.basic`, `community.basic`, `quiz.basic` 같은 namespace 포함 key와 allowlist view를 제공할 수 있다.
- 번들 `content.basic`과 `community.basic`은 공통 레이아웃 파일을 공유하지 않는다. 콘텐츠는 `modules/content/layouts/basic/layout.php`, 커뮤니티는 허용된 theme 경계 안의 `modules/community/themes/basic/layout.php`를 사용한다. 헤더/푸터처럼 같은 시각 언어를 쓰더라도 모듈 전용 레이아웃 CSS를 사용해 모듈 경계를 유지한다.
- 모듈 theme는 모듈 홈이나 섹션 첫 화면처럼 모듈 단위의 큰 정보 배치를 담당한다.
- 모듈 skin은 목록, 상세, 작성 폼, 배너 item, 팝업 layer처럼 특정 기능 단위의 표시를 담당한다.
- 관리자 화면은 각 모듈 view가 본문을 만들고, 관리자 shell과 공통 관리자 asset은 admin 모듈의 skin이 담당한다. 관리자 shell은 화면 구성 편의를 위해 렌더 후 DOM을 다시 해석해 class나 레이블을 주입하지 않으므로, 폼 행과 선택 항목의 접근성 텍스트는 view가 최종 마크업으로 직접 출력한다. 보안 정화나 외부 HTML 변환처럼 렌더 후 DOM 처리가 정말 필요한 경우는 별도 helper나 모듈 책임으로 명확히 분리하고 테스트한다.

모듈은 DB에 view 파일 경로를 저장하지 않는다. `public_layout_key`, `layout_key`, `theme_key`, `skin_key`, `{module_key}_skin_key` 같은 key만 저장하고, 실제 파일 경로는 모듈 helper의 allowlist나 `layout-options.php` 계약에서 결정한다. 기존 공개 레이아웃 `basic` 값은 `common.basic`으로 정규화한다. 알 수 없는 사이트 공통 레이아웃 key는 기본 공통 레이아웃으로 fallback한다. 사이트 설정의 공통 레이아웃 선택지는 기본 공통 레이아웃을 먼저 표시하고, 모듈 제공 레이아웃은 관리자 사이드바의 모듈 순서대로 정렬한다. 콘텐츠, 커뮤니티, 퀴즈처럼 공개 레이아웃을 설정하는 모듈은 모듈 환경설정의 레이아웃 key를 메인, 그룹/게시판, 상세 하위 화면 전체에 적용한다.

CSS class는 범위를 드러내는 이름을 사용한다. 모듈 전용 class는 `{module_key}-*` 또는 `sr-{module_key}-*`, 특정 스킨 전용 class는 `{module_key}-skin-{skin_key}-*` 형식을 우선한다. 모듈 skin은 전역 `body`, `a`, `.container`, `.btn`처럼 넓은 선택자를 직접 재정의하지 않고, 필요한 경우 자기 wrapper 아래에서만 스타일을 제한한다.

모듈 CSS에서 제목, 본문, 도움말, 메타, 캡션, 코드 텍스트를 새로 정의해야 할 때는 먼저 공통 역할 class인 `.type-page-title`, `.type-section-title`, `.type-card-title`, `.type-body`, `.type-small`, `.type-meta`, `.type-caption`, `.type-code` 또는 현재 런타임 토큰의 `--type-*-size`, `--type-*-line-height`, `--text-strong`, `--text-muted`, `--text-subtle` 값을 사용한다. 공개 런타임은 `assets/tokens.css`와 `assets/public-foundation.css`, 관리자 런타임은 `modules/admin/assets/tokens.css`와 `modules/admin/assets/common.css`를 기준으로 한다. 모듈 고유 CSS에 `.8125rem` 같은 임의 크기나 새 회색값을 반복하기 전에 `/admin/ui-kit`와 `/ui-kit`의 Typography 섹션에서 기존 역할이 맞는지 확인하고, 모듈 전용 UI-KIT가 있으면 그 Typography 섹션에서 실제 화면 조합도 확인한다.

모듈 theme나 skin처럼 특정 view 조합에만 전용 CSS가 필요하면 view에서 `sr_public_layout_begin()`의 네 번째 인자로 stylesheet를 요청한다. 공개 layout option이나 layout context의 `style_profile`은 `kit`, `minimal`, `install` 중 하나이며, 공통/콘텐츠/커뮤니티 layout은 `minimal`을 기본으로 사용한다. minimal profile은 `assets/common.css`와 `assets/public-ui.css`를 호출하지 않으므로, 버튼/입력/목록처럼 필요한 컨트롤 표현은 layout CSS나 모듈 CSS가 자기 wrapper 아래에서 직접 소유해야 한다. 홈/회원/계정/공개 UI-KIT처럼 공개 UI kit primitive가 필요한 화면은 `style_profile => 'kit'`을 명시한다. `assets/public-ui-kit.css`는 UI-KIT 조회 화면의 샘플 보조 CSS로만 쓰고, 모듈의 실제 사용자 화면은 이 파일에 의존하지 않는다. 공통 공개 layout은 `assets/public-layout.css`, 콘텐츠 layout은 `modules/content/assets/layout.css`, 커뮤니티 layout은 `modules/community/assets/community-layout.css`를 layout 파일이 직접 호출한다. 콘텐츠/커뮤니티 helper는 모듈 화면에 필요한 공개 CSS만 layout context에 추가한다. public layout은 `<head>` 출력만 담당한다. 출력 슬롯처럼 head 렌더링보다 뒤에서 HTML이 만들어지는 공개 모듈 출력도 해당 슬롯을 호출하는 view, skin, theme, public layout이 필요한 stylesheet를 layout context에 명시한다. 공개 CSS는 활성화된 모든 모듈 기준으로 자동 호출하지 않는다.

```php
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => [
        '/modules/community/skins/qna/assets/qna.css',
    ],
]);
```

커뮤니티 게시판처럼 스킨별 기능 차이가 자연스러운 모듈은 `skins/{skin_key}/skin.php` 계약 파일을 둔다. 이 파일은 필수 view, 선택 asset, 선택 action을 plain array로 드러낸다.

```php
<?php

return [
    'label' => 'Q&A',
    'views' => [
        'list' => __DIR__ . '/list.php',
        'post' => __DIR__ . '/view.php',
        'form' => __DIR__ . '/form.php',
    ],
    'actions' => [
        'accept_answer' => [
            'method' => 'POST',
            'file' => __DIR__ . '/actions/accept-answer.php',
        ],
    ],
    'stylesheets' => [
        '/modules/community/skins/qna/assets/qna.css',
    ],
];
```

스킨 action은 스킨 파일이 직접 실행하지 않는다. 스킨 view는 `/community/skin-action`으로 POST 폼을 만들고, 커뮤니티 모듈의 단일 action이 현재 게시판에 선택된 스킨인지 확인한 뒤 `skin.php`에 등록된 action 파일만 include한다. action 파일 안에서는 일반 모듈 action과 같이 로그인/권한 확인, 입력 검증, DB 변경, 감사 로그, redirect를 명시적으로 처리한다.

```php
<form method="post" action="<?php echo sr_e(sr_url('/community/skin-action')); ?>">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="skin_key" value="qna">
    <input type="hidden" name="action_key" value="accept_answer">
    <input type="hidden" name="board_id" value="<?php echo sr_e((string) $board['id']); ?>">
    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
    <button type="submit">답변 채택</button>
</form>
```

`list`, `post`, `form`은 필수 view다. 필수 view 파일이 없거나 스킨 폴더 밖을 가리키면 그 스킨은 선택 가능한 스킨 목록에서 제외된다. 이미 DB에 저장된 스킨 key가 더 이상 유효하지 않으면 `basic`으로 fallback한다. `basic`의 필수 view가 누락되면 복구가 필요한 설치 오류로 보고 예외를 발생시킨다.

커뮤니티 게시판 스킨은 게시판 유형별 기능 차이가 자연스럽기 때문에 선택 action 계약을 허용한다. 관리자 스킨, 회원 스킨, 배너 스킨, 팝업레이어 스킨, 공개 레이아웃, 커뮤니티 레이아웃은 현재 표시 전용 계약으로 유지한다. 이 표시 전용 계약들은 필수 view가 없는 option을 선택 목록에서 제외하고, 저장된 key가 무효가 되면 기본 option으로 fallback한다. 기본 필수 view가 없으면 설치 오류로 본다.

퀴즈와 설문 공개 화면도 모듈별 스킨 key를 설정으로 저장한다. 기본 key는 `basic`이고, 옵션 source of truth는 각각 `sr_quiz_skin_options()`, `sr_survey_skin_options()`다. 퀴즈 스킨 필수 view는 `home`, `view`, `result`이고, 설문 스킨 필수 view는 `home`, `view`, `complete`다. 공개 action은 설정된 `skin_key`와 내부 view 이름을 helper가 검증한 파일 경로로 매핑한 뒤 include한다. 스킨 또는 view 파일이 없으면 해당 view만 `basic`으로 fallback하고 운영 로그에 module, skin key, view, fallback file을 남긴다. 스킨 출력은 기존 공개 레이아웃 안쪽 본문을 대체하며, 퀴즈는 `quiz-theme-*`와 `quiz-skin-*`, 설문은 `survey-theme-basic`과 `survey-skin-*` class hook을 유지해야 한다. 퀴즈 결과와 설문 완료 스킨은 보상 지급 결과 안내 surface를 빠뜨리지 않아야 한다.

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

DB 테이블은 프로젝트 prefix인 `sr_`로 시작한다.

좋은 예:

```text
sr_board_posts
sr_board_comments
sr_payment_toss_transactions
```

피할 예:

```text
core_posts
member_points
posts
```

모듈이 회원과 연결되는 데이터를 저장할 때는 `sr_member_accounts`를 넓히지 않고 자기 테이블에 `account_id`를 둔다.

```sql
CREATE TABLE IF NOT EXISTS sr_board_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    body_text TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_board_posts_account (account_id),
    KEY idx_sr_board_posts_status_created (status, created_at)
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
    'admin' => [
        'category' => 'service',
        'category_label' => '서비스',
        'category_order' => 30,
        'menu_order' => 10,
        'icon' => ['type' => 'symbol', 'name' => 'message-circle'],
        'stylesheets' => ['assets/admin.css'],
    ],
    'saanraan' => [
        'min_version' => '0.2.0',
        'tested_with' => ['0.2.0'],
        'module_contract' => '2.0',
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
- `saanraan.min_version`: 이 모듈을 설치하거나 활성화할 수 있는 산란 최소 버전. 필수이며 현재 `SR_CORE_VERSION`과 실제 비교한다.
- `saanraan.tested_with`: 모듈 릴리스 시 검증한 산란 버전 목록. 비어 있지 않은 배열이 필요하다.
- `saanraan.module_contract`: 모듈이 지원하는 산란 모듈 계약 버전. 현재 코어의 계약 버전은 `SR_MODULE_CONTRACT_VERSION`이며 필수다. 값이 맞지 않으면 계약 파일 로딩 대상에서 제외된다.
- `requires.modules`: 활성화 전에 필요한 모듈
- `requires.contracts`: 활성화 전에 필요한 계약 파일. 대상 모듈이 enabled여도 현재 코어와 메타데이터/계약이 맞지 않으면 요구사항을 만족하지 않은 것으로 본다.
- 숨김 기반 모듈은 `admin.hidden => true`, `admin.foundation => true`를 선언할 수 있다. `asset_ledger`는 `point`, `reward`, `deposit` 설치/활성화 시 자동 준비되며, 활성 자산 모듈이 있는 동안 비활성화가 차단된다. 새 기반 모듈을 추가할 때는 자동 준비 대상, 실패 표시, 감사 로그, 삭제/비활성화 차단 기준을 함께 문서화한다.
- `contracts.provides`: 이 모듈이 제공하는 계약 파일. `paths.php`, `admin-menu.php`, `output-slots.php` 같은 계약 파일이 실제로 있으면 반드시 선언하고, 선언한 파일은 실제로 있어야 한다.
- `contracts.consumes`: 이 모듈이 읽는 계약 파일
- `admin`: 관리자 메뉴 분류, 아이콘, 관리자 전용 stylesheet 같은 선택 메타데이터
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

산란 요청 흐름은 다음 형태다.

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
    'GET /content/*' => 'actions/view.php',
    'GET /admin/board/posts' => 'actions/admin-posts.php',
    'POST /admin/board/posts' => 'actions/admin-posts.php',
];
```

규칙:

- key는 `METHOD /path` 형식이다.
- method는 보통 `GET` 또는 `POST`를 사용한다.
- `/content/*`처럼 path 끝에 `/*`를 붙이면 해당 prefix 아래의 한 모듈 action으로 요청을 보낼 수 있다. wildcard는 끝에만 둘 수 있고, 루트 catch-all 용도로 사용하지 않는다.
- action 경로는 `actions/...php`만 사용한다.
- action 파일은 실제로 모듈 디렉터리 안에 있어야 한다.
- path 등록 함수나 전역 dispatcher를 만들지 않는다.
- 같은 method/path 또는 겹치는 wildcard path를 여러 활성 모듈이 선언하면 요청은 실패한다.
- 관리자 화면 path는 `/admin/...` 아래에 둔다.
- 상태 변경은 `POST`로 처리한다.
- 상태 변경 `POST`는 저장, 오류 수집, flash 결과 저장 뒤 `sr_redirect()`로 `GET` 화면에 돌아가는 PRG 흐름으로 끝낸다. 같은 요청에서 view를 렌더링하면 브라우저 새로고침이 저장이나 메일 발송 같은 작업을 다시 실행할 수 있다.
- 회원 전용 사이트 모드가 켜지면 코어 guard는 활성 모듈 `paths.php`에서 실제 match된 공개 서비스 route에만 적용된다. 방문자용 화면 `GET`은 로그인 redirect 대상인지, 파일/다운로드/JSON/상태 변경 endpoint는 403 대상인지 모듈 문서와 smoke 기준에 남긴다.
- 외부 PG, 본인확인, 배송사처럼 provider가 호출하는 callback/webhook route는 일반 공개 화면 route처럼 설계하지 않는다. 회원 전용 모드에서도 `/login` HTML로 redirect되면 안 되므로, 모듈 action이 provider 서명, state/nonce, idempotency key, 감사 로그 기준으로 직접 검증하고 후속 계약 테스트에 회원 전용 ON 상태를 포함한다.

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

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/board/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/board', 'view');

$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/board', 'edit');

    $title = sr_post_string('title', 160);

    if ($title === '') {
        $errors[] = '제목을 입력하세요.';
    }

    if ($errors === []) {
        sr_board_save_post($pdo, [
            'title' => $title,
            'account_id' => (int) $account['id'],
        ]);

        sr_audit_log($pdo, [
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

$posts = sr_board_recent_posts($pdo);
$adminPageTitle = '게시판';
include SR_ROOT . '/modules/admin/views/layout-header.php';
include SR_ROOT . '/modules/board/views/admin-posts.php';
include SR_ROOT . '/modules/admin/views/layout-footer.php';
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

action에서 응답을 끝내야 하면 `sr_redirect()`, `sr_render_error()`, `sr_finish_response()` 중 하나를 사용한다. 이 helper들은 dispatch contract 검사를 거친 뒤 종료한다. `header('Content-Type: ...')` 같은 응답 메타 제어는 허용하지만, redirect는 반드시 `sr_redirect()`를 통과해야 한다.

`sr_request_contract_mark()`와 `sr_request_contract_guard_blocked()`는 action 파일에서 직접 호출하지 않는다. action은 `sr_require_csrf()`, `sr_member_require_login()`, `sr_admin_require_permission()`, `sr_admin_require_owner()` 같은 공개 helper를 호출해 contract mark가 자연스럽게 기록되게 둔다.

## 8. view 작성

view는 PHP와 HTML을 섞되, HTML을 기본으로 쓰고 필요한 위치에만 `<?php echo ...; ?>`를 둔다.

```php
<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/board/posts')); ?>">
    <?php echo sr_csrf_field(); ?>
    <label>
        제목
        <input type="text" name="title" value="<?php echo sr_e($title); ?>" maxlength="160" required>
    </label>
    <button type="submit">저장</button>
</form>
```

view 규칙:

- 변수 출력은 `sr_e()`로 escape한다.
- 줄바꿈이 필요한 텍스트는 `nl2br(sr_e($value))`를 사용한다.
- `<?= ... ?>` 숏 echo 태그를 쓰지 않는다.
- `echo <<<HTML`로 전체 레이아웃을 출력하지 않는다.
- view에서 `$_GET`, `$_POST`, `$_COOKIE`를 직접 읽지 않는다.
- view에서 DB 변경을 하지 않는다.
- 상태 변경 form에는 CSRF 필드를 넣는다.
- 권한 최종 판단은 action에서 끝낸다.

출력 예외:

- 이미 helper가 escape를 끝내고 반환한 HTML 조각은 그대로 출력할 수 있다.
- `sr_render_output_slot()`처럼 출력 확장 helper가 반환하는 HTML은 그 helper/모듈이 escape 책임을 가진다.
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

require_once SR_ROOT . '/modules/board/helpers/posts.php';
require_once SR_ROOT . '/modules/board/helpers/settings.php';
```

규칙:

- action은 가능하면 모듈의 `helpers.php`만 require한다.
- 하위 helper 파일은 로드 시 부작용을 만들지 않는다.
- helper 함수는 `PDO $pdo`를 명시적으로 인자로 받는다.
- 함수명은 `sr_{module_key}_...` prefix를 사용한다.
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

- `sr_upload_validate_file()`에는 `max_bytes`, `allowed_extensions`, `allowed_mime_types`를 명시하고 업로드 오류, 크기, 확장자 allowlist, MIME, 실행 가능 확장자 차단을 통과시킨다.
- 서버에서 MIME을 감지할 수 없으면 업로드는 실패로 처리한다. 모듈은 이를 설정 오류나 업로드 거부 메시지로 노출한다.
- 저장 파일명은 원본 이름을 신뢰하지 않고 `sr_upload_random_filename()`으로 만든다.
- 저장 위치는 웹에서 직접 실행되지 않는 디렉터리를 우선하고, 공개 파일이 필요하면 모듈이 별도 공개 응답 action을 둔다.
- `sr_upload_move_uploaded_file()` 또는 검증된 값 기반의 명시적 이동만 사용한다.
- 파일 metadata, 소유자, 공개/비공개 상태, 삭제/보존 정책은 모듈 테이블에 저장한다.
- 비공개 다운로드는 직접 파일 URL 대신 `sr_download_token_create()`와 `sr_download_token_verify()`를 사용해 단기 token으로 처리한다.
- 다운로드 응답은 `sr_send_download_headers()`로 헤더를 보내고 본문 출력 후 `sr_finish_response()`로 종료한다.
- 이미지 업로드는 필요할 때 `sr_upload_reencode_image()`를 호출하되, GD/Imagick이 없거나 재인코딩에 실패하면 모듈 정책에 따라 거부하거나 원본 저장을 중단한다.
- SVG처럼 재인코딩하지 않는 공개 이미지 형식은 XML 루트, 크기, 금지 요소, 이벤트 속성, 외부 URL 참조를 별도로 검증하고 정리한 결과만 저장한다.
- 로고 매니저처럼 공개 이미지 자산을 다루는 모듈은 JPEG/PNG/WebP를 재인코딩하고 SVG는 정리본만 저장하는 식으로 형식별 정책을 관리자 도움말과 문서에 남긴다.

하지 않는다:

- `sr_files` 같은 코어 공통 파일 테이블을 전제로 작성하지 않는다.
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
<?php echo sr_e(sr_t('board::admin.title')); ?>
```

규칙:

- 번역 파일은 배열만 반환한다.
- locale 파일명은 `ko`, `en-US` 같은 locale 값과 맞춘다.
- 최소 기본 locale 파일을 제공한다.
- 번역 값도 화면에 출력할 때는 `sr_e()`로 escape한다.
- `module.php`의 `name`, `description`, `admin.category_label`, `service_domain.main_page.label` 같은 메타데이터는 설치/업로드 정적 파서가 읽으므로 함수 호출 없이 문자열 리터럴로 둔다.
- `admin-menu.php`, `menu-links.php`, `dashboard.php`, `layout-options.php`, `member-group-rules.php`처럼 런타임에 읽는 화면 라벨 계약 파일은 하드코딩 문구 대신 `sr_t('{module_key}::...')` 값을 반환한다.
- 게시글 제목, 상품명 같은 사용자 콘텐츠 번역 테이블을 코어가 대신 만들지 않는다.

## 10. DB 접근

산란은 PDO prepared statement를 기본으로 한다.

```php
<?php

$stmt = $pdo->prepare(
    'SELECT id, title
     FROM sr_board_posts
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
- 설치/업데이트 SQL 파일 실행은 코어 SQL 실행 helper에 위임
- 테이블명/컬럼명은 허용 목록에서 선택한 값만 문자열 결합

금지:

```php
<?php

$pdo->query("SELECT * FROM sr_board_posts WHERE title = '" . $_GET['title'] . "'");
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
$stmt = $pdo->query('SELECT id, title FROM sr_board_posts ORDER BY ' . $sort . ' LIMIT 50');
```

자세한 기준은 [DB 접근 정책](database-access-policy.md)을 따른다.

## 11. 설치 SQL

`install.sql`은 모듈이 소유한 테이블과 초기 데이터를 만든다.

규칙:

- 모듈 소유 테이블만 만든다.
- 테이블명은 `sr_` prefix를 사용한다.
- `CREATE TABLE IF NOT EXISTS`를 사용한다.
- 초기 데이터는 재실행해도 안전하게 작성한다.
- 너무 많은 대량 데이터 seed를 넣지 않는다.
- 외래키는 공유호스팅 호환성을 고려해 선택적으로 사용한다.
- 실패 후 재시도를 고려해 unique key와 `ON DUPLICATE KEY UPDATE`를 함께 설계한다.

예:

```sql
CREATE TABLE IF NOT EXISTS sr_board_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_key VARCHAR(60) NOT NULL,
    label VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_board_categories_key (category_key)
);

INSERT INTO sr_board_categories (category_key, label, status, created_at, updated_at)
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

모듈 설정은 `sr_module_settings`에 저장한다. 설정 조회는 코어 helper를 사용한다.

```php
<?php

$settings = sr_module_settings($pdo, 'board');
$postsPerPage = (int) sr_module_setting($pdo, 'board', 'posts_per_page', 20);
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
- 저장 후 `sr_clear_module_settings_cache('{module_key}')` 호출
- 변경 감사 로그 기록
- 목록/검색/행 관리 화면에 모듈 전역 설정을 섞지 않고, 가능하면 `admin-menu.php`에 별도 설정 항목을 둔다.

범용 `/admin/modules` key/value 설정 화면은 제공하지 않는다. 모듈 설정은 의미, 단위, 허용 범위가 드러나는 전용 관리자 화면에서 수정한다.

모듈 수명주기 자체는 관리자 모듈의 사유 API가 아니라 코어 API를 기준으로 처리한다. 모듈 설치, 상태 변경, route 충돌 검사, pending SQL 계산, update SQL 적용, 파일 전용 버전 반영, 모듈 소스 배치 검증은 코어 helper를 사용하고, 관리자 화면은 권한 확인과 결과 표시를 덧붙인다. 새 관리자 UI나 CLI를 만들 때도 같은 코어 helper를 호출해야 기존 `/admin/modules`, `/admin/updates`와 같은 판정을 공유한다.

## 14. 관리자 메뉴

관리자 메뉴가 필요한 모듈은 `admin-menu.php`를 둔다.
관리자 메뉴의 자산 분류와 모듈 단위 정렬은 새 계약 파일을 만들지 않고 `module.php`의 선택적 `admin` 메타데이터로 선언한다.

```php
'admin' => [
    'category' => 'site',
    'category_label' => '사이트',
    'category_order' => 20,
    'menu_order' => 10,
    'icon' => ['type' => 'symbol', 'name' => 'menu-list'],
    'stylesheets' => ['assets/admin.css'],
],
```

`admin.category`가 없으면 관리자 모듈은 `기타` 분류로 묶는다. 사이트 메뉴, 로고 매니저, 배너, 팝업레이어, SEO처럼 공개 사이트 구성과 노출에 연결되는 번들 모듈은 `site` 카테고리로 묶어 `사이트` 라벨 아래 표시한다. 콘텐츠, 커뮤니티, 쇼핑몰, 티켓팅처럼 사이트 방문자가 직접 이용하는 서비스 도메인 모듈은 `service` 카테고리로 묶어 `서비스` 라벨 아래 표시한다. 포인트, 적립금, 예치금처럼 회원 계정 없이는 성립하지 않는 번들 모듈은 `member` 카테고리로 묶어 `회원` 라벨 아래 표시한다. `admin-menu.php`의 `order`는 모듈 안의 메뉴 항목 정렬에 사용하고, 모듈끼리의 정렬은 `admin.menu_order`를 우선 사용한다. 하위 메뉴 항목이 하나뿐인 모듈 그룹은 관리자 사이드바에서 그룹 클릭 시 해당 항목 화면을 바로 연다.

CKEditor처럼 독립 도메인 관리 화면이 아니라 다른 화면의 입력 경험을 보강하는 플러그인은 적용 대상과 도메인 정책을 자기 설정에 두지 않는다. 설정 route가 필요하면 `paths.php`로 URL을 소유하고, 접근성이 필요한 번들 플러그인은 `플러그인` 같은 별도 분류 아래 설정 메뉴를 제공할 수 있다.

`admin.icon`은 모듈 메뉴 그룹의 기본 아이콘 표현을 맡는다. 관리자 shell이 제공하는 허용 심볼을 쓸 때는 `['type' => 'symbol', 'name' => 'users']`처럼 선언한다. 허용 심볼 이름과 Google Material Symbols 매핑은 admin 모듈의 공통 아이콘 계약이 소유하며, admin skin은 이 계약으로 Material 아이콘을 렌더링한다. 모듈 고유 이미지가 필요하면 `['type' => 'asset', 'path' => 'assets/admin-menu-icon.png', 'alt' => '배너']`처럼 자기 모듈의 `assets/` 아래 파일을 선언한다. 자산 아이콘은 `jpg`, `jpeg`, `png`, `gif`, `webp`만 허용하며 외부 URL이나 `..` 경로는 무시된다. 선언이 없거나 유효하지 않으면 카테고리 기본 아이콘으로 표시한다. 운영자가 `/admin/menu`에서 모듈 그룹 아이콘을 선택하면 해당 공용 아이콘 키 오버라이드가 이 기본 선언보다 우선한다.

`admin.stylesheets`는 모듈 관리자 본문에만 필요한 CSS 파일 목록이다. 파일은 자기 모듈의 `assets/` 아래 `.css` 파일만 선언한다. admin skin은 공용 UI kit과 공통 관리자 CSS 뒤에 현재 관리자 화면을 소유한 모듈의 stylesheet만 출력한다. `/admin` 대시보드는 실제 대시보드 섹션을 제공하는 모듈의 stylesheet만 추가한다. 따라서 모듈 CSS는 공통 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 재정의하지 않고 자기 모듈 class 또는 필요한 관리자 본문 class 아래로 범위를 좁힌다.

공개 모듈 stylesheet는 해당 공개 화면의 `sr_public_layout_begin()` layout context에서 직접 요청한다. 파일은 자기 모듈의 `assets/` 아래 `.css` 파일이나 선택된 skin/theme의 allowlist가 반환한 파일만 사용한다. 모듈 공개 CSS는 `{module_key}-*`, `sr-{module_key}-*`, 스킨 class처럼 소유권이 분명한 선택자만 사용한다.

허용 심볼 이름은 다음과 같다. `settings`, `admin-mode`, `users`, `user`, `content`, `stats`, `home`, `folder`, `image`, `layers`, `search`, `menu-list`, `bell`, `shield`, `coins`, `wallet`, `gift`, `message-circle`, `service`.

프로젝트 기본 아이콘셋은 Google Fonts CDN으로 호출하는 Google Material Symbols Outlined다. 공용 helper `sr_icon()`으로 출력하면 `assets/icons.css`와 `sr_icon_bootstrap_script()`가 폰트 준비 전 ligature 텍스트 노출을 막는다. 기존 `sr_material_icon_html()`은 호환 래퍼로 유지된다. 운영자는 `/admin/settings`에서 아이콘 키별로 Material Symbols 이름을 바꾸거나 이미지 파일을 업로드할 수 있고, 기본 키 외 사용자 키를 추가할 수 있다. 기본 키는 초기화만 가능하며 제거할 수 없고, 추가한 키만 제거할 수 있다. 운영자가 업로드한 공용 아이콘 이미지는 admin 모듈 설정 저장소와 권한 확인이 있는 `/admin/icon-image`가 소유하며, 개별 모듈의 계약 파일이나 자산 소유권으로 편입하지 않는다. 모듈은 렌더링 구현을 직접 고정하지 말고 안정적인 심볼 키만 선언해야 한다.

Material Symbols는 페이지에서 독립 아이콘을 표시할 때 사용한다. 체크박스 체크 표시나 드롭다운 caret 같은 컴포넌트 내부 상태 표시는 UI-KIT의 컴포넌트 CSS가 소유한다. 방향 화살표가 필요한 컴포넌트는 재사용 가능한 `sr_ui_arrow_icon_html()` helper를 사용한다.

운영자가 `/admin/menu`에서 저장한 표시 순서와 숨김 여부는 이 기본 선언 위에 마지막으로 적용된다. 이 오버라이드는 관리자 내비게이션 표시 정책일 뿐이며, 모듈 계약 파일이나 실제 route 소유권을 바꾸지 않는다.

모듈에 목록/콘텐츠 관리 화면과 전역 설정 화면이 모두 있으면 같은 메뉴 그룹 안에 별도 항목으로 둔다. 예를 들어 배너 모듈은 `/admin/banners`와 `/admin/banners/settings`, 팝업레이어 모듈은 `/admin/popup-layers`와 `/admin/popup-layers/settings`를 분리한다.

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
- `menu-links.php`: 사이트 메뉴 항목에 연결할 링크 자산
- `output-slots.php`: 출력 renderer
- `extension-points.php`: 확장 가능한 화면/기능 위치
- `privacy-export.php`: 회원 개인정보 사본 제공 확장
- `sitemap.php`: SEO sitemap URL 확장
- `member-group-rules.php`: 회원 그룹 자동 부여 조건 후보
- `dashboard.php`: 관리자 대시보드 모듈 섹션 후보
- `layout-options.php`: 공개 레이아웃 후보
- `asset-exchange.php`: 자산 환전 후보와 원장 helper 계약
- `member-assets.php`: 콘텐츠/커뮤니티에서 쓰는 금액성 회원 자산 후보
- `member-withdrawal-assets.php`: 회원 탈퇴 시 정리할 자산 후보와 처리 계약
- `member-registration.php`: 회원가입 추가 입력, 검증, 저장. 확장 입력값은 `registration_extensions[...]` POST namespace로 전달된다.
- `homepage-candidates.php`: 과거 초기화면 저장 경로와 모듈 소유 공개 경로 사용 가능 여부
- `editor-options.php`: textarea 강화 에디터 후보
- `coupon-targets.php`: 쿠폰 사용처 후보
- `logo-positions.php`: 모듈별 로고 용도 후보. 계약 파일명은 호환을 위해 position을 유지하지만 관리자 UI에서는 `로고 용도`로 표시한다.
- `notification-events.php`: 계정 이벤트 알림 생성 후보
- `embed-manager-targets.php`: 임베드 매니저 대상 검색, 검증, 렌더링 snapshot 후보

계약 파일 규칙:

- 모듈 디렉터리 바로 아래에 둔다.
- 배열 또는 callable을 반환한다.
- 로드 시 상태 변경을 하지 않는다.
- 공개 가능한 정보만 선언한다.
- 소비 모듈은 값을 다시 검증한다.
- 사용자 요청마다 비싼 계약 파일 탐색을 반복하지 않는다.

계약 파일은 자동 등록이 아니다. 소비 모듈이 필요한 시점에 명시적으로 읽는 공개 약속이다.
계약 파일이 `helpers`와 함수명을 반환하는 단순 callable 계약이면 소비 모듈은 `sr_module_contract_function()`으로 활성 상태, 계약 호환성, helper 경로, 함수 존재 여부를 한 번에 확인할 수 있다.

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
- 신규 설치에서 `site_menu`가 선택되면 설치 후 seed helper가 `service_domain.main_page`를 선언한 설치 모듈의 메인 페이지를 기본 `header` 메뉴에 추가한다. `menu-links.php` 후보 전체를 자동 등록하지 않으며, 로그인/회원가입 링크도 기본 header에 자동 삽입하지 않는다.

`output-slots.php`:

- callable을 반환한다.
- callable 형식은 `function (PDO $pdo, array $context): string`이다.
- 외부 저장소에서 로컬 점검될 수 있으므로 자기 helper를 읽을 때는 `__DIR__` 기준 경로를 사용한다.

`extension-points.php`:

- 배열을 반환한다.
- 각 항목은 `point_key`, `label`, 선택 `surface`, `output`, `slots`, `subjects`를 가진다.
- `point_key`는 `board.post.view`처럼 모듈 안에서 안정적인 key로 둔다.
- `slots`가 있으면 각 slot은 `slot_key`를 가진 배열이다.

`asset-exchange.php`:

- 배열을 반환한다.
- 자산 모듈 폴더 기준 `helpers`, `balance_function`, `transaction_function`, 표시용 `label` 또는 `label_function`, `unit_label` 또는 `unit_function`을 제공한다.
- `deduction_order`가 같으면 소비 모듈과 공통 helper는 `asset_module` 사전순으로 정렬해야 하며, 같은 입력이 다른 차감 snapshot을 만들면 안 된다. 다중 자산 row lock도 같은 순서로 잡아 동시 복합 차감 간 deadlock 가능성을 낮춘다.
- `transaction_function`은 호출자가 이미 시작한 같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 안 된다. 이 계약을 지키지 않는 자산 모듈은 콘텐츠/커뮤니티의 복합 차감 후보에서 제외하고 설정 오류 또는 단일 자산 fallback으로 처리한다.
- settlement 기반 복합 차감을 지원하는 자산은 `purchase_power => ['asset_units' => 양의 정수, 'settlement_units' => 양의 정수, 'settlement_currency' => 통화 코드]`를 snapshot 가능한 구조로 제공한다. 내부 계산은 `settlement_units / asset_units` rational로 수행하고, 방향이 모호한 스칼라 rate만 저장하지 않는다. `asset_units`와 `settlement_units`는 양의 정수, `settlement_currency`는 core/settings min-unit registry에 존재하는 통화인지 자산 설정 저장 또는 관리자 config 로드 시점에 검증해 setup 오류로 노출한다.
- settlement 기반 차감의 멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 안정 입력만 사용한다. 클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반한다. 실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며, 이 key에는 DB unique 제약을 둔다. duplicate-key가 나면 동시 중복으로 보고 `processing` 또는 저장된 성공 결과를 반환하며, lock 획득 뒤에도 claim row 상태를 다시 확인한다. 성공 결과만 claim row와 함께 커밋해 sticky 저장하고, 재검증 거부나 실행 실패는 rollback으로 claim row도 사라지게 두어 재시도 시 현재 상태로 부작용 없이 재평가한다. 자산별 차감량, 잔액 snapshot, settlement 배분 결과, 확인 fingerprint는 검증과 기록 대상이지 dedupe key 입력이 아니다.
- settlement 통화는 사이트 기본 통화가 아니라 가격 row의 통화와 자산 `purchase_power.settlement_currency`가 일치해야 한다. `site.default_currency`는 새 가격 생성 기본값으로만 사용하고, 통화 min-unit registry와 settlement `snapshot_schema_version`, rounding/carry `rounding_policy_version`, 0원/legacy 분류 `settlement_kind`는 core/settings 소유 계약을 참조해 도메인 로그에 저장한다. `settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나로 시작한다. `free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고, 자산 증감량은 `direction`과 `asset_amount`로 별도 해석한다.
- 마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용한다. 1 자산 단위가 통화 최소단위보다 큰 경우 ceil overpay를 허용하지 않으며, 예를 들어 1P = 10 KRW로 1,005 KRW를 포인트만으로 충당할 수 없으면 잔액 부족/결제 불가로 거부하고 재계획 없이 재확인을 요구한다.
- 확인 화면 이후 실행 전 잔액이 줄어든 경우와 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 바뀐 경우를 별도 무효화 사유로 기록하고 모두 재확인 대상으로 처리한다.
- 운영자가 통화 min-unit 또는 rounding/carry `rounding_policy_version`을 변경하면 기존 확인 화면의 in-flight 요청이 fail-closed 재확인으로 떨어질 수 있음을 변경 워크플로에 안내한다.
- 정적 체크는 계약 문구 회귀 방지용이며 transaction 동참, carry, overpay, lock 순서의 런타임 준수는 구현 시점 테스트 fixture로 검증한다. InnoDB의 미커밋 unique claim 중복 insert는 선행 트랜잭션 commit/rollback까지 블록될 수 있으므로 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 함께 확인한다.
- `cash_like`는 예치금처럼 환금성 자산 재환전 수수료 판단에 사용할 수 있는 좁은 힌트다.
- 거래 유형은 기본적으로 `exchange_out`, `exchange_in`, `exchange_fee`이며 자산 모듈의 서버 측 거래 유형 검증에서 부호를 다시 확인해야 한다.
- 자산 모듈은 자기 balance/transaction 테이블을 계속 소유하고, 환전 모듈은 `reference_type=asset_exchange`와 환전 묶음 ID를 넘겨 원장 간 연결만 남긴다.

`member-assets.php`:

- 배열을 반환한다.
- 콘텐츠와 커뮤니티가 금액성 회원 자산 후보를 읽을 때 사용한다.
- 자산 모듈 폴더 기준 `helpers`, `balance_function`, `transaction_function`, 선택 `transaction_table`, 표시용 `label` 또는 `label_function`, `unit_label` 또는 `unit_function`을 제공한다.
- `use_type`, `credit_type`, `refund_type`으로 소비 모듈이 원장에 넘길 거래 유형을 선언한다.
- `deduction_order`는 여러 자산을 함께 선택했을 때 기본 차감 순서에 사용한다.
- 쿠폰처럼 금액 잔액을 차감하지 않는 권리성 자산은 이 계약에 넣지 않는다.

`member-withdrawal-assets.php`:

- 배열을 반환한다.
- 회원 탈퇴 시 정리할 회원 자산 후보를 회원 모듈이 읽을 때 사용한다.
- 금액성 원장 자산은 `helpers`, `balance_function`, `transaction_function`, `balance_table`, `transaction_table`, `transaction_type`, `process_label`, `ledger_process_label`을 제공한다.
- 쿠폰처럼 자체 상태 전환이 필요한 자산은 `balance_function`과 `process_function`을 제공할 수 있다.
- `sort_order`는 탈퇴 화면과 처리 요약의 표시 순서를 정한다.

`editor-options.php`:

- 배열을 반환한다.
- `key`, `label`, 선택 `helpers`, 선택 `assets_function`을 제공한다.
- core의 기본 `textarea` 외 에디터 플러그인이 관리자 설정 후보로 노출될 때 사용한다.
- `assets_function` callable 형식은 `function (PDO $pdo, string $presetKey): string`이다.
- 화면 소유 모듈은 저장한 editor key를 core helper에 넘기고, core helper는 활성 플러그인의 계약만 읽어 textarea 속성과 에셋 HTML을 만든다.

`logo-positions.php`:

- 배열 또는 callable을 반환한다.
- 각 항목은 `position_key`, `label`, 선택 `hint`, 선택 `surface`, 선택 `max_bytes`를 제공한다.
- `position_key`는 `module.area.name`처럼 점으로 구분한 소문자/숫자/underscore key를 사용한다.
- 로고매니저는 이 계약을 로고 배치 생성 화면의 로고 용도 선택지로만 사용한다.
- 앱아이콘 기본 용도 `public.favicon`은 `사용자 화면 심볼로 사용` 옵션을 저장할 수 있다. 기본 공개/콘텐츠/커뮤니티/퀴즈 레이아웃은 공개 헤더 전용 로고가 둘 다 없을 때 이 심볼 후보를 브랜드 이미지 fallback으로 사용한다. 별도 레이아웃/테마에서는 `sr_logo_manager_public_symbol_logo()`, `sr_logo_manager_public_symbol_url()`, 또는 `sr_logo_manager_render_public_symbol_logo()`를 명시적으로 호출해야 반영된다.
- `public.favicon` 용도 로고는 로컬 저장소의 PNG/JPEG/WebP 원본에서 16, 32, 48, 180, 192, 512 정사각 PNG 파생 아이콘을 생성할 수 있다. 활성 아이콘 세트가 있으면 `sr_logo_manager_favicon_link_tag()`가 사이즈별 `icon`/`apple-touch-icon` 링크를 출력하고, 생성된 세트가 없거나 생성할 수 없는 원본이면 기존 단일 favicon URL로 fallback한다. 파비콘 로고가 등록된 적은 있지만 현재 활성 후보가 없으면 이전 브라우저 favicon이 남지 않도록 빈 data 아이콘 링크를 출력한다. SVG 원본과 S3 원본은 공유호스팅 rasterize/다운로드 제약 때문에 현재 파생 생성 대상에서 제외한다.
- 실제 출력은 화면 소유 모듈이나 레이아웃이 `sr_logo_manager_render_logo($pdo, $positionKey, ...)`를 명시적으로 호출해야 한다.

`notification-events.php`:

- 배열을 반환한다.
- 알림 모듈 폴더 기준 `helpers`, `create_function`, `create_account_event_function`을 제공한다.
- 소비 모듈은 알림 모듈이 활성화되어 있고 계약 파일이 loadable일 때만 helper를 로드한다.
- 알림 제목, 본문, 링크, 채널, delivery queue 정책은 알림 모듈의 템플릿과 설정이 소유한다.
- 자산, 쿠폰, 콘텐츠/커뮤니티 댓글 작성자 알림과 댓글 멘션, 퀴즈/설문 댓글 멘션처럼 템플릿 기반 알림을 쓰는 모듈은 `module_key`, `event_key`, `metadata`만 넘긴다.
- 댓글 멘션 대상 해석은 member 공개 이름/멘션 helper를 사용한다. `@공개이름#prefix`는 현재 공개 이름과 public account hash prefix가 함께 단일 활성 회원에 일치할 때만 확정하고, 동명이인에게 `@공개이름`만 입력한 모호한 멘션은 알림 대상으로 확정하지 않는다.
- 쪽지, 닉네임 강제 초기화처럼 회원에게 보여줄 비템플릿 알림도 계약의 일반 생성 함수만 호출하며 알림 저장 테이블을 직접 쓰지 않는다.
- 후속 `notification-catalog.php`가 도입되더라도 1차 역할은 이벤트 seed/default 후보와 설정 UI metadata 선언으로 제한하고, 런타임 권한 정책이나 알림 저장 정책은 이 계약에 숨기지 않는다.

`admin-notification-events.php`:

- 배열을 반환한다.
- 알림 모듈 폴더 기준 `helpers`, `create_function`, `summary_function`을 제공한다.
- 소비 모듈은 운영자가 조치해야 하는 이벤트를 생성할 때 `create_function`만 호출하고, `sr_admin_notifications` 저장 정책을 직접 알지 않는다.
- 생성 데이터는 제목, 본문, 중요도, source module/event, target type/id, `/admin/...` 상대 action URL, 권한 path/action, dedupe key를 포함한다.
- action URL은 관리자 내부 상대 경로만 허용하고, 알림 목록과 헤더 요약은 조회 시점에 관리자 권한을 다시 확인한다.
- 알림 모듈이 비활성화되었거나 계약이 없으면 소비 모듈의 원래 업무 저장은 실패하지 않아야 한다.

`privacy-export.php`:

- 배열 또는 callable을 반환한다.
- 배열 반환값은 이미 구성된 사본 제공 데이터로 취급되며, `query` 선언을 자동 실행하지 않는다.
- callable 형식은 `function (PDO $pdo, int $accountId): array`이다.
- 계정별 DB 조회가 필요하면 callable을 반환한다.

`sitemap.php`:

- 배열 또는 callable을 반환한다.
- 배열 항목은 최소 `loc` 값을 가진다.
- callable 형식은 `function (PDO $pdo, ?array $site): array`이다.

`member-group-rules.php`:

- 배열을 반환한다.
- 각 항목은 `rule_key`, `label`, 선택 `description`, 선택 `params`, `evaluator`를 가진다.
- `rule_key`는 `{module_key}.domain.condition` 형태로 제공 모듈 key로 시작한다.
- `params`는 관리자 설정 UI와 JSON 저장 검증에 사용할 parameter schema이다. 파라미터가 게시판, 게시판 그룹, 콘텐츠, 콘텐츠 그룹처럼 기존 도메인 대상을 고르는 값이면 숫자 직접 입력 대신 `options` 또는 `options_callback`으로 선택지를 제공한다.
- `evaluator` callable 형식은 `function (PDO $pdo, int $accountId, array $params): array`이다.
- evaluator는 자기 모듈 테이블만 조회하고 member 그룹 membership을 직접 변경하지 않는다.
- 평가 실행은 회원 모듈의 로그인 성공, 회원 목록의 단일 회원 재평가, 회원 그룹 규칙 화면의 그룹 기준 평가, 그리고 각 제공 모듈이 명시적으로 연결한 도메인 이벤트에서 일어난다. 그룹 기준 평가는 대상 그룹에 이미 속한 회원, 운영자가 뱃지로 추가한 제외 그룹에 속한 회원, 보관 상태 그룹에 속한 회원을 후보에서 제외한 뒤, 대상 그룹의 활성 규칙을 만족하는 회원을 자동 배정한다. 등록된 회원 그룹이 없으면 그룹 기준 재평가 버튼을 표시하지 않고, 보관 그룹을 제외한 회원 그룹이 1개이면 제외 그룹 선택을 제공하지 않는다. 보관 그룹을 제외한 회원 그룹이 2개 이상이면 초기에는 대상 그룹과 제외 그룹 모두 전체 후보를 선택할 수 있고, 대상 그룹을 선택하면 해당 그룹을 제외 후보에서 제거한다. 대상 그룹을 선택하지 않은 상태에서 제외 그룹 선택으로 대상 후보가 하나만 남으면 그 그룹을 대상 그룹으로 자동 지정한다. 예를 들어 콘텐츠 모듈은 유료 열람, 유료 파일 다운로드, 완료 버튼 포인트/금액 처리 성공 직후 `source_module_key=content` 규칙만 재평가한다.

`dashboard.php`:

- 배열을 반환한다.
- 각 섹션은 `key`, `title`, 선택 `order`, 선택 `default_visible`, 선택 `view`, 선택 `layout`, `rows` 또는 `items`를 가진다.
- `default_visible`은 사용자가 별도 표시 설정을 저장하지 않았을 때의 기본 노출 여부다. 생략하면 표시하고, `false`, `0`, `hidden`, `no`, `off` 값은 기본 숨김으로 처리한다.
- `view`는 모듈 폴더 기준 `views/*.php` 상대 경로다. admin 모듈은 경로가 모듈 폴더 안에 있는지 검증한 뒤 대시보드 섹션 내부로 include한다.
- `view`를 제공한 모듈은 섹션 내부 구성과 도메인별 표시 리듬을 직접 맡는다. 카드형 UI를 사용할 수도 있고 카드가 아닌 자체 레이아웃을 렌더링할 수도 있다. 이때 사용할 수 있는 변수는 `$pdo`, `$dashboardSection`, `$dashboardRows`, `$dashboardModuleKey`, `$dashboardSectionTitle`이다.
- admin 모듈은 대시보드 섹션의 외곽 wrapper, 이동 핸들, 정렬 저장만 맡는다. wrapper는 최소 drop target과 drop line 기준을 안정적으로 제공하는 배치 단위이며, 모듈 view의 시각 구조를 카드로 강제하지 않는다. 모듈 view는 핸들 주변의 시각적 안전 여백을 포함해 전체 HTML layout을 렌더링하되 출력값을 직접 escape하고, 필요한 스타일은 `module.php`의 `admin.stylesheets`로 선언한 모듈 내부 CSS에서 소유한다.
- `layout`은 기존 모듈 호환용 fallback이며 `table` 또는 `stats`만 지원한다. 생략하거나 알 수 없는 값이면 `table`로 처리한다. view를 제공하는 모듈에는 HTML 구조를 강제하지 않는 호환 옵션이다.
- `table` layout은 기존 `rows`를 사용하고 `항목 / 주요 수치 / 상세` 표로 표시한다.
- `stats` layout은 `items`를 우선 사용하고, 없으면 `rows`를 사용한다. 각 item은 지표 카드로 표시한다.
- 각 row는 `label`과 `value_sql` 또는 `value`, 선택 `detail_sql` 또는 `detail`, 선택 `detail_prefix`, 선택 `detail_suffix`를 가진다.
- `stats` item은 선택 `state`와 선택 `emphasis`를 가질 수 있다. `state` 허용 값은 `default`, `success`, `warning`, `danger`, `info`이고, `emphasis` 허용 값은 `default`, `primary`이다. 알 수 없는 값은 각각 `default`로 처리한다.
- SQL은 단일 `SELECT`만 사용하고 `value_sql`은 `value`, `detail_sql`은 `detail` 컬럼을 반환한다. locale 전환이 필요한 화면 문구는 SQL 문자열 안에 넣지 않고, `label`, `detail_prefix`, `detail_suffix`에서 번역 값을 조합한다.
- admin 모듈은 SQL 실행 실패를 해당 row의 빈 값으로 처리하므로, 모듈은 자기 테이블이 없거나 비활성 상태인 경우에도 전체 대시보드를 깨지 않게 작성한다.
- `view`가 없거나 view 렌더링 중 예외가 발생하면 admin 모듈의 fallback renderer가 `layout`과 `rows`/`items`를 사용해 `admin-card` 기반 기본 카드 UI로 표시한다. 이 fallback은 기존 모듈을 깨지 않기 위한 안전한 기본 표시이며, 새 모듈 view의 권장 HTML 구조를 뜻하지 않는다.

`layout-options.php`:

- 배열을 반환한다.
- 각 항목 key는 `common.basic`, `content.basic`, `community.basic`, `quiz.basic`처럼 provider namespace를 포함한 안정적인 layout key다.
- 각 항목은 `label`, `provider_module_key`, 선택 `provider_label`, 선택 `supports`, `views`를 가진다.
- `views.layout`은 필수이며 public layout 파일을 가리킨다.
- `views.community_home`처럼 특정 화면용 view는 선택으로 제공할 수 있다.
- 파일 경로는 모듈 또는 코어가 선언한 allowlist 값이어야 하며 DB에 저장하지 않는다.

## 15-2. 계약 파일 소비 지도

계약 파일은 "제공하는 모듈"과 "읽는 소비 주체"가 분리된다. 제공 모듈은 `module.php`의 `contracts.provides`에 파일을 선언하고 실제 파일을 둔다. 소비 모듈은 `contracts.consumes`에 읽는 계약 파일을 기록하고, 필요한 시점에 `sr_enabled_module_contract_files()`와 `sr_load_module_contract_file()`로 명시적으로 읽는다. 단, 회원 탈퇴/익명화 개인정보 정리처럼 보관 데이터 삭제가 목적인 계약은 비활성 모듈의 데이터도 정리해야 하므로 설치된 모듈의 `privacy-cleanup.php`를 읽는다.

코어가 읽는 계약 파일은 특정 모듈의 `contracts.consumes`에 적지 않는다. 예를 들어 front controller가 읽는 `paths.php`와 `sr_render_output_slot()`이 읽는 `output-slots.php`는 코어 실행 기반의 소비다.

계약 파일별 소비 주체:

| 계약 파일 | 읽는 주체 | 읽는 시점 | 목적 |
| --- | --- | --- | --- |
| `paths.php` | core front controller | 모든 요청의 route 매칭 | 활성 모듈 action include 허용 목록 |
| `paths.php` | `admin` 모듈 | 관리자 내비게이션 구성 | `admin-menu.php` path가 실제 GET route인지 확인 |
| `admin-menu.php` | `admin` 모듈 | 관리자 레이아웃/내비게이션 구성 | 활성 모듈 관리자 메뉴 노출 |
| `menu-links.php` | `site_menu` 모듈 | 사이트 메뉴 관리자 화면 | 운영자가 메뉴 항목에 연결할 수 있는 링크 자산 |
| `extension-points.php` | `banner` 모듈 | 배너 관리자 대상 선택 | content slot 대상 목록 |
| `extension-points.php` | `popup_layer` 모듈 | 팝업 관리자 대상 선택 | public overlay/content 대상 목록 |
| `output-slots.php` | core output helper | 화면 소유 모듈이 `sr_render_output_slot()` 호출 시 | 저장된 출력 규칙 렌더링 |
| `privacy-export.php` | `privacy` 모듈 | 개인정보 사본 생성 | 모듈별 회원 귀속 데이터 수집 |
| `privacy-cleanup.php` | `member` 모듈 | 회원 탈퇴/익명화 트랜잭션 | 설치된 모듈별 회원 재식별 개인정보 정리. 계약 로드 또는 실행 실패 시 탈퇴 처리를 중단 |
| `sitemap.php` | `seo` 모듈 | sitemap 응답 생성 | 모듈별 공개 URL 수집 |
| `member-group-rules.php` | `member` 모듈 | 회원 그룹 자동화 관리자 화면과 재평가 | 모듈별 자동 그룹 부여 조건 후보 |
| `dashboard.php` | `admin` 모듈 | 관리자 대시보드 렌더링 | 모듈별 대시보드 요약 섹션 |
| `layout-options.php` | core public layout helper | 공개 레이아웃 선택 목록 구성 | 모듈별 공개 레이아웃 후보 |
| `member-assets.php` | `content`, `community` 모듈 | 자산 정책 화면과 금액성 자산 처리 | 금액성 회원 자산 후보와 원장 호출 정보 |
| `member-withdrawal-assets.php` | `member` 모듈 | 회원 탈퇴/정리 처리 | 탈퇴 시 정리할 회원 자산 후보와 처리 함수 |
| `member-registration.php` | `member` 모듈 | 회원가입 추가 필드 렌더링, `registration_extensions[...]` POST 값 검증, 가입 트랜잭션 저장 | 서비스 모듈이 회원가입 시 필요한 추가 입력 |
| `homepage-candidates.php` | core/admin | `available_function`으로 저장값 사용 가능 여부 확인. `available_function`은 가능 `true`, 소유 경로의 불가 `false`, 미소유 경로 `null`을 반환 | 과거 저장값이나 모듈 소유 공개 경로 검증 |
| `editor-options.php` | core editor helper | 관리자/공개 textarea 에디터 설정과 렌더링 | 플러그인별 textarea 강화 에디터 후보 |
| `coupon-targets.php` | `coupon` 모듈 | 쿠폰 종류 생성 화면, 저장 검증, 대상 검색, 환불 시 접근권 회수 | 모듈별 쿠폰 사용처 후보와 선택적 콜백 |
| `coupon-targets.php` | `banner` 모듈 | 배너 특정 대상 검색 모달 | 배너 노출 대상 번호 선택에 재사용할 대상 검색 후보 |
| `embed-manager-targets.php` | `embed_manager` 모듈 | 임베드 검색, 저장 검증, 렌더링, 상태 점검 | 모듈별 임베드 대상 후보와 snapshot/status/variant 계약 |
| `coupon-references.php` | `coupon` 모듈 | 쿠폰 정의 상태 변경 전 | 발급/사용 이력 기준 쿠폰 정의 역방향 참조 조회 |
| `banner-references.php` | `banner` 모듈 | 배너 삭제/상태 변경 전 | 콘텐츠/커뮤니티가 직접 저장한 배너 ID 역방향 참조 조회 |
| `popup-layer-references.php` | `popup_layer` 모듈 | 팝업레이어 삭제/상태 변경 전 | 콘텐츠/커뮤니티가 직접 저장한 팝업레이어 ID 역방향 참조 조회 |
| `member-group-references.php` | `member` 모듈 | 회원 그룹 비활성/보관/key 변경 전 | 회원 그룹 ID/key를 저장한 모듈 정책 역방향 참조 조회 |
| `site-setting-references.php` | `admin` 모듈 | 사이트명 변경 전 | 사이트명을 복사 저장한 모듈 설정 역방향 참조 조회 |
| `logo-positions.php` | `logo_manager` 모듈 | 로고 배치 생성 화면 | 모듈별 로고 용도 후보 |
| `notification-events.php` | `point`, `reward`, `deposit`, `coupon`, `content`, `community`, `quiz`, `survey` 모듈 | 거래/쿠폰 상태 변경 성공 뒤, 댓글 작성자/멘션/쪽지 알림 생성 시 | 선택 알림 모듈의 계정 알림 생성 함수 |
| `admin-notification-events.php` | `content`, `community`, `privacy`, `notification` 모듈 | 신고, 개인정보 요청, 작성자 신청, 발송 실패, 저장소 정리 실패 같은 관리자 운영 알림 생성 시 | 선택 알림 모듈의 운영 알림 생성/요약 함수 |

읽기 참조 계약의 `count_function`은 `rows_function`이 반환할 row 수와 같은 기준이어야 한다. 번들 계약 검사는 `count_function` 함수 본문 전체가 대응 `rows_function($pdo, $target, $context)` 결과를 직접 세는 단일 반환문인지 확인한다.

현재 번들 모듈 기준 제공/소비 지도:

| 모듈 | 제공하는 계약 파일 | 읽는 계약 파일 |
| --- | --- | --- |
| `admin` | `paths.php` | `admin-menu.php`, `paths.php`, `homepage-candidates.php`, `site-setting-references.php`, `admin-notification-events.php` |
| `member` | `paths.php`, `admin-menu.php`, `extension-points.php`, `menu-links.php`, `privacy-export.php`, `dashboard.php`, `member-group-references.php` | `member-registration.php`, `member-group-rules.php`, `privacy-cleanup.php`, `member-withdrawal-assets.php`, `member-group-references.php` |
| `privacy` | `paths.php`, `admin-menu.php`, `menu-links.php` | `privacy-export.php`, `admin-notification-events.php` |
| `site_menu` | `paths.php`, `admin-menu.php`, `output-slots.php` | `menu-links.php` |
| `seo` | `paths.php`, `admin-menu.php`, `site-setting-references.php` | `sitemap.php` |
| `content` | `paths.php`, `admin-menu.php`, `extension-points.php`, `menu-links.php`, `privacy-export.php`, `sitemap.php`, `dashboard.php`, `homepage-candidates.php`, `member-group-rules.php`, `coupon-targets.php`, `banner-references.php`, `popup-layer-references.php`, `member-group-references.php`, `layout-options.php`, `embed-manager-targets.php` | `member-assets.php`, `notification-events.php`, `admin-notification-events.php` |
| `logo_manager` | `paths.php`, `admin-menu.php`, `site-setting-references.php` | `logo-positions.php` |
| `banner` | `paths.php`, `admin-menu.php`, `output-slots.php` | `extension-points.php`, `coupon-targets.php`, `banner-references.php` |
| `popup_layer` | `paths.php`, `admin-menu.php`, `output-slots.php` | `extension-points.php`, `popup-layer-references.php` |
| `notification` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `notification-events.php`, `admin-notification-events.php` | 없음 |
| `embed_manager` | `paths.php`, `admin-menu.php` | `embed-manager-targets.php` |
| `point` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `asset-exchange.php`, `member-assets.php`, `member-withdrawal-assets.php`, `dashboard.php` | `notification-events.php` |
| `deposit` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `asset-exchange.php`, `member-assets.php`, `member-withdrawal-assets.php`, `member-group-references.php`, `dashboard.php` | `notification-events.php` |
| `reward` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `asset-exchange.php`, `member-assets.php`, `member-withdrawal-assets.php`, `member-group-references.php`, `dashboard.php` | `notification-events.php` |
| `asset_exchange` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `dashboard.php` | `asset-exchange.php`, `notification-events.php` |
| `coupon` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `member-withdrawal-assets.php`, `coupon-references.php`, `dashboard.php` | `coupon-references.php`, `coupon-targets.php`, `notification-events.php` |
| `community` | `paths.php`, `admin-menu.php`, `menu-links.php`, `extension-points.php`, `privacy-export.php`, `privacy-cleanup.php`, `sitemap.php`, `member-group-rules.php`, `dashboard.php`, `layout-options.php`, `coupon-targets.php`, `banner-references.php`, `popup-layer-references.php`, `member-group-references.php`, `embed-manager-targets.php` | `member-assets.php`, `notification-events.php`, `admin-notification-events.php`, `output-slots.php`는 core helper 경유, member 그룹/공개 이름 helper |
| `quiz` | `paths.php`, `admin-menu.php`, `menu-links.php`, `layout-options.php`, `privacy-export.php`, `privacy-cleanup.php`, `dashboard.php`, `coupon-references.php`, `sitemap.php`, `embed-manager-targets.php` | `member-assets.php`, `notification-events.php` |
| `survey` | `paths.php`, `admin-menu.php`, `menu-links.php`, `privacy-export.php`, `privacy-cleanup.php`, `sitemap.php`, `homepage-candidates.php`, `dashboard.php`, `layout-options.php`, `coupon-references.php`, `member-group-references.php`, `embed-manager-targets.php` | `member-assets.php`, `notification-events.php` |
| `ckeditor` | `paths.php`, `admin-menu.php`, `editor-options.php` | `플러그인` 분류에서 설정 화면 제공, 적용 대상은 화면 소유 모듈 설정이 결정 |

모듈 메타데이터 작성 기준:

- 실제 파일을 제공하면 `contracts.provides`에 반드시 선언한다.
- 다른 모듈의 계약 파일을 직접 읽으면 `contracts.consumes`에 기록한다.
- `sr_render_output_slot()`처럼 코어 helper를 호출해 출력 renderer를 실행하는 경우, 화면 소유 모듈은 어떤 point/slot을 호출하는지 view에서 명시한다. `output-slots.php` 파일 탐색 자체는 core helper가 담당한다.
- 계약 파일을 읽는 모듈은 반환 구조를 다시 검증하고, 깨진 계약 파일 하나 때문에 전체 화면이 500으로 죽지 않게 안전 로더를 사용한다.
- 계약 파일 소비 관계가 새로 생기면 이 표와 `module.php`의 `contracts.consumes`를 함께 갱신한다.

서비스 도메인 모듈이 설치 시 메인 페이지 후보가 될 수 있으면 `module.php`에 선택 메타데이터를 둔다.

```php
'service_domain' => [
    'main_page' => [
        'label' => '커뮤니티 홈',
        'path' => '/community',
    ],
],
```

설치 화면은 이 값을 읽어 해당 모듈 카드에 `초기화면으로 설정` 체크를 제공하고, 선택값을 `site.home_path`에 저장한다. 값은 `/`로 시작하는 안전한 내부 경로여야 하며, 해당 모듈을 함께 설치하지 않으면 선택할 수 없다. 설치 후에는 관리자 설정의 `화면` 섹션에서 기본 홈페이지와 제한된 service domain 메인 후보 중 초기화면을 다시 선택할 수 있다. 현재 사이트 설정 후보는 기본 홈페이지 다음에 관리자 사이드바 순서대로 콘텐츠 메인, 커뮤니티 홈, 퀴즈 메인만 표시한다. 후보가 비활성화되거나 숨김 상태가 되면 `/`는 public layout/theme이 제공하는 기본 홈페이지로 fallback한다. 기본 홈페이지 본문은 관리자 설정이 아니라 public layout/theme의 홈 템플릿을 직접 작성해 구성한다.

## 16. Output Slots

화면 출력 지점에 여러 확장 모듈이 붙을 수 있을 때는 화면 소유 모듈 view에서 특정 확장 모듈 helper를 직접 호출하지 않는다.

화면 소유 모듈:

```php
<?php echo sr_render_output_slot($pdo, [
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
    return sr_example_banner_render($pdo, $context);
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
- `banner_kind`: 선택 항목. 배너 스킨 호환성 판단에 쓰는 위치 성격이며 `inline`, `compact`, `sidebar`, `hero`, `wide` 중 하나를 쓴다. 생략하면 `inline`으로 본다.
- `subjects`: 선택 대상 정보

배너 관리자 화면은 운영자에게 `서비스 -> 상세` 2단계 노출 위치 선택으로 보여주되, 저장값은 기존 `module_key + point_key + slot_key` 조합으로 유지한다. 기본적으로 선택한 조합 전체에 노출한다. 특정 대상 노출이 필요하면 노출 범위 항목에서 특정 대상 노출을 선택하고, 쿠폰 대상 조회와 같은 검색 모달을 사용해 노출 대상 번호를 채운다. 노출 범위와 대상 번호 입력은 특정 대상 선택을 지원하는 노출 위치에서만 표시한다. 현재 번들 기준으로 `content.view`는 콘텐츠, `community.board.list`와 `community.post.form`은 게시판, `community.post.view`는 게시글 검색으로 연결한다. 검색 가능한 대상 계약이 없는 노출 위치는 전체 노출로만 저장한다.

배너 표시 내용은 이미지 배너와 텍스트 배너를 같은 `sr_banners` 행에서 구분한다. 이미지 배너는 `image_url`이 있는 상태이며 관리자 화면에서 이미지 URL이나 업로드 이미지 중 하나를 필수로 받고 `body_text`를 이미지 대체텍스트로 사용한다. 텍스트 배너는 `image_url`을 비운 상태이며 `body_text`를 공개 배너 텍스트로 표시한다.

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
- 화면 소유 모듈은 팝업 전용 호출을 따로 두지 않고 필요한 content slot에서 `sr_render_output_slot()`을 호출한다.
- 팝업레이어 모듈은 자신의 `output-slots.php`에서 저장된 대상 규칙, 기간, 닫기 유지 정책을 검증한 뒤 해당 slot에 출력할 HTML을 반환한다.

번들 콘텐츠 모듈은 `content.view` point와 `before_content`, `after_content` content slot을 제공한다. 배너/팝업레이어 관리 화면에서 콘텐츠 전체 또는 특정 콘텐츠 ID를 대상으로 출력 규칙을 저장할 수 있고, 콘텐츠 관리자 화면에서는 공용 배너/팝업레이어를 직접 선택할 수도 있다. 콘텐츠 그룹은 `sr_content_groups`가 소유하고 콘텐츠는 `sr_content_items.content_group_id`로 선택적으로 연결한다. 콘텐츠 환경설정의 기본 콘텐츠 레이아웃은 공개 콘텐츠 메인, 콘텐츠 그룹 목록, 콘텐츠 상세의 header/footer 레이아웃 선택에 전체 적용된다. 콘텐츠 생성/수정 화면의 상태, 커버 이미지 URL, 배너, 팝업레이어, 유료 열람, 완료 버튼 포인트/금액 처리는 현재 콘텐츠에 저장하며, 운영자가 선택한 `그룹`/`전체` 적용 옵션은 현재 편집값을 같은 그룹 또는 전체 콘텐츠에 한 번 복사한다.
커버 이미지는 `sr_content_items.cover_image_url`에 저장하고 URL 입력, 파일 업로드, 현재 이미지 삭제를 지원한다. 파일 업로드는 콘텐츠 모듈 저장소 `content/cover-images/{Y}/{m}`에 저장하며 공개 출력은 `/content/cover-image` 프록시를 사용할 수 있다. 업로드 커버가 교체/삭제되거나 콘텐츠 그룹 삭제로 더 이상 참조되지 않으면 저장소 파일을 정리하고, 실패한 정리는 `sr_content_storage_cleanup_failures`에 기록한다. 다운로드 파일은 `sr_content_files` 저장소와 `/admin/content/files` 관리 화면에서 먼저 등록하고, 콘텐츠 생성/수정 화면은 `sr_content_file_links`로 사용 상태 파일을 연결만 한다. 파일 제목, 숨김 상태, 다운로드 과금 정책은 다운로드 파일 관리 화면이 소유한다.
CKEditor 본문 이미지는 다운로드 파일과 DB 참조 테이블 없이 콘텐츠 모듈의 로컬 경로 `storage/content/body/tmp/{upload_token}`와 `storage/content/body/{content_id}`가 소유한다. 콘텐츠 저장 시 정화된 HTML의 `/content/body-file?tmp=...&file=...` 참조를 다시 파싱해 콘텐츠별 경로로 옮기고, 저장된 HTML에 남지 않은 같은 콘텐츠 경로의 파일은 정리한다. 커뮤니티 게시글 본문 이미지는 기존 첨부파일과 분리해 `storage/community/body/tmp/{upload_token}`와 shard가 포함된 `storage/community/body/{shard1}/{shard2}/{post_id}` 경로가 소유하며, 게시글 저장/수정/삭제 흐름에서 같은 방식으로 이동과 정리를 수행한다. 팝업레이어 본문 이미지는 `storage/popup_layer/body/tmp/{upload_token}`와 `storage/popup_layer/body/{popup_layer_id}` 경로가 소유하며, 팝업레이어 저장/복사/삭제 흐름에서 자신의 경로로 보존하거나 정리한다. 관리자 설정형 rich textarea는 레코드 ID가 없으므로 공통 저장소를 자동으로 쓰지 않고, 실제 설정 필드가 생길 때 소유 모듈이 안정적인 setting subject key를 경로로 정해 명시적으로 upload endpoint를 붙인다. 본문 이미지 프록시는 각 소유 모듈의 공개/권한 정책을 확인하고, 저장 전 temporary 이미지는 업로드 권한이 있는 현재 사용자에게만 보여준다.
콘텐츠 그룹은 `sr_content_group_settings`에 상태, 배너, 팝업레이어, 유료 열람, 완료 버튼 포인트/금액 처리, 새 파일 과금 기본값을 저장하고 콘텐츠별 `sr_content_setting_sources`는 기존 호환과 적용 상태 기록에 사용한다. 콘텐츠 그룹 생성 화면은 콘텐츠 환경설정의 기본 콘텐츠 설정을 미리 채우며, 저장 후에는 그룹 설정값으로 고정한다. 콘텐츠 그룹 목록이나 콘텐츠 목록의 그룹 필터에서 새 콘텐츠를 만들면 선택한 그룹의 콘텐츠 자체 기본값을 새 콘텐츠 입력값으로 복사하되, 다운로드 파일 기본값은 콘텐츠 본문 레코드에 복사하지 않고 `그룹` 적용 범위도 자동 선택하지 않는다. 그룹 설정 변경은 기존 콘텐츠 값을 바꾸거나 기존 콘텐츠의 유효값을 다시 계산하지 않는다. 사용 상태의 콘텐츠 그룹은 `/content/group?key={group_key}` 공개 목록, 사이트 메뉴 연결 자산, sitemap 후보로 노출된다. 과거 콘텐츠별/그룹별 공개 레이아웃 저장값은 revision과 기존 데이터 호환을 위해 남을 수 있지만 공개 렌더링은 콘텐츠 환경설정의 레이아웃을 따른다. 콘텐츠나 커뮤니티 게시판처럼 공용 배너/팝업레이어를 직접 선택하는 관리자 화면은 선택 영역 근처에 배너/팝업레이어 관리 화면으로 이동하는 링크를 제공한다. 사이트 설정의 초기화면 후보는 콘텐츠 개별/그룹이 아니라 콘텐츠 메인으로 제한하며, 기본 홈페이지 자체의 본문 구성은 public layout/theme의 홈 템플릿 책임이다.

콘텐츠 유료 열람, 다운로드 과금, 완료 버튼 포인트/금액 처리는 콘텐츠 모듈이 접근/액션 정책과 로그를 소유하고, 포인트/적립금/예치금 모듈의 잔액 조회와 원장 생성 helper만 호출한다. 완료 버튼 처리는 공개 콘텐츠에서 회원이 버튼을 눌렀을 때만 실행되며, 단순 콘텐츠 조회는 포함하지 않는다. 관리자 화면에는 설치되어 있고 활성화된 자산 모듈만 표시하되, 운영자 라벨은 `자산`보다 `처리 항목`, `포인트/금액`, `잔액` 표현을 우선 사용한다. 완료 버튼 처리 항목은 항목 선택과 항목별 금액을 같은 입력 묶음으로 보여주되, 지급 방향은 한 항목만 선택할 수 있고 차감 방향은 여러 항목을 선택할 수 있다. 차감 방향은 입력 금액 합계를 기준 통화 가격으로 보고 각 항목의 `purchase_power`에 따라 정확히 충당 가능한 자산 단위만 배분한다. 회원 그룹별 적용은 항목 선택과 다른 의미의 정책 세트 선택이므로 별도 행으로 표시하되, 실제 설정 화면에서는 현재 선택한 항목과 맞는 정책 세트만 선택할 수 있고 뱃지 요약도 같은 항목 기준으로 표시한다. 콘텐츠와 커뮤니티의 실제 자산 설정 화면에서는 미리 만든 정책을 `회원 그룹별 적용`으로 표시하고 셀렉트로 여러 개 선택할 수 있으며, 그룹별 계산 방식과 조정값 편집은 전용 관리 화면에서 수행한다. 실제 자산 설정 화면은 선택한 회원 그룹별 적용을 하단 뱃지로 추가하고 그룹별 포인트/금액 실제 적용값 요약을 함께 보여준다. 기존 단일 금액은 호환용 합계로 유지한다. 차감 로그의 `amount`는 실제 자산 단위이고 `settlement_amount`/`settlement_currency`는 기준가격 충당 단위, `purchase_power_snapshot_json`은 실행 당시 구매력/통화/정책 snapshot이다. 콘텐츠 차감 로그에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`도 함께 저장해 통계/export와 환불 정책이 같은 정규 필드를 참조한다. 결제 자산 모듈은 콘텐츠 도메인을 알 필요가 없으며, 거래 참조는 열람 `reference_type=content.view`, 다운로드 `reference_type=content.download`, 완료 버튼 처리 `reference_type=content.action`으로 남긴다. 파일 다운로드 성공 이력은 무료/유료를 함께 `sr_content_file_download_logs`에 남기고, 유료 행은 `sr_content_asset_access_logs`와 접근권을 대조한다. 유료 다운로드 수동 환불은 환불 사유와 포인트 환불 유효기간 기준을 받아 연결된 차감/접근 로그가 있는 이력만 처리하고, 차감 로그별 `refund` 원장 거래를 새로 만들며, 다운로드 이력 행의 `refund_status`로 중복 환불을 막는다. 포인트 환불 유효기간 기본값은 참조 원거래 유효기간이고, 포인트가 아닌 항목에는 적용하지 않는다. 최초 1회 접근권이나 최종 금액 0으로 생긴 접근권은 환불/회수 처리 시 함께 삭제한다. 최초 1회 정책에서 기존 접근권으로 내려받은 반복 다운로드처럼 이번 요청에 연결된 차감/접근 로그가 없는 이력은 금액을 0으로 남기고 환불/회수 대상에서 제외한다. 파일이 여러 콘텐츠에 연결된 경우 공개 다운로드 URL의 `content_id`를 우선 맥락으로 기록하고, 누락되면 활성/공개 연결 중 하나로 fallback한다. 계정별 열람/다운로드/완료 버튼 처리 로그와 접근권 기록은 콘텐츠 모듈의 `privacy-export.php`에 포함한다. 최초 1회 열람/다운로드 판정은 거래 원장이나 쿠폰 사용 로그를 직접 읽지 않고 `sr_content_access_entitlements`를 우선 사용한다. 거래 원장과 쿠폰 사용 로그는 회계/운영 증빙 보관 대상으로 남기고, 회원 탈퇴/익명화 시 콘텐츠 접근권의 `account_id`와 원천 참조, 파일 다운로드 이력의 `account_id`를 비워 개인정보 보유기간과 접근권 상태를 분리한다. 콘텐츠 모듈은 이 세 로그를 기준으로 전체/특정 콘텐츠/특정 콘텐츠 그룹 회원 그룹 규칙을 제공하고, 성공 로그가 생긴 직후 콘텐츠 출처 규칙을 즉시 재평가한다.

커뮤니티 모듈도 같은 원칙을 따른다. 게시글/댓글 적립, 글쓰기/댓글 차감, 게시글 열람 차감, 첨부 다운로드 차감은 커뮤니티 설정과 게시판 설정에서 결정하고, 실제 포인트/적립금/예치금 증감은 활성 자산 모듈 helper를 호출한다. 관리자 자산 선택 UI에는 설치되어 있고 활성화된 자산 모듈만 표시한다. 글쓰기/댓글 차감, 게시글 열람 차감, 첨부 다운로드 차감은 여러 자산을 함께 선택할 수 있으며, 자산 선택과 자산별 금액을 같은 입력 묶음으로 보여준다. 차감 실행은 입력 금액 합계를 기준 통화 가격으로 보고 각 자산의 `purchase_power`에 따라 정확히 충당 가능한 자산 단위만 배분한다. 회원 그룹별 적용 선택은 자산 선택과 다른 의미의 정책 세트 선택이므로 별도 행으로 표시하되, 실제 설정 화면에서는 현재 선택한 자산과 맞는 정책 세트만 선택할 수 있고 뱃지 요약도 같은 자산 기준으로 표시한다. 기존 단일 금액은 호환용 합계로 유지한다. 커뮤니티 자산 로그의 `amount`는 실제 자산 단위이고 `settlement_amount`/`settlement_currency`는 기준가격 충당 단위, `purchase_power_snapshot_json`은 실행 당시 구매력/통화/정책 snapshot이다. 커뮤니티 자산 로그에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`도 함께 저장해 통계/export와 환불 정책이 같은 정규 필드를 참조한다. 게시글/댓글 적립은 단일 자산 지급으로 유지한다. 게시판의 상태, 스킨, 접근, 레벨 활동 점수, 첨부, 배너, 팝업레이어, 게시글 적립, 댓글 적립, 글쓰기 차감, 댓글 차감, 유료 열람, 첨부 다운로드 차감은 현재 게시판에 저장하며, 운영자가 선택한 `그룹`/`전체` 적용 옵션은 현재 편집값을 같은 그룹 또는 전체 게시판에 한 번 복사한다. 게시판 그룹 생성 화면은 전역 에디터, 레벨 점수, 첨부, 파일, 회원 자산 기본값으로 시작하고, 저장 후에는 게시판 그룹 설정값으로 고정한다. 게시판 그룹 목록이나 게시판 목록의 그룹 필터에서 새 게시판을 만들면 선택한 그룹의 기본 설정을 새 게시판 입력값으로 복사하되, 게시판 상태와 스킨은 그룹 기본값으로 복사하지 않고 `그룹` 적용 범위도 자동 선택하지 않는다. 게시판 생성 화면은 저장 시점의 게시판 값을 자체 설정으로 저장한다. 전역 설정이나 게시판 그룹 설정 변경은 기존 게시판 값을 바꾸거나 기존 게시판의 유효값을 다시 계산하지 않는다. 회원 자산 항목의 사용 여부, 자산, 금액, 과금 방식은 같은 항목 설정 묶음으로 저장한다. 커뮤니티 자동 회원 그룹 규칙은 전체 활동, 특정 게시판, 특정 게시판 그룹 기준을 제공하고, 게시판과 게시판 그룹 대상은 관리자 화면에서 선택 셀렉트로 고른다. 별도 적용값 미리보기 문구는 표시하지 않는다. 첨부 직접 접근도 게시글 유료 열람 정책을 확인하며, `once` 정책은 같은 세션의 중복 차감을 피하고 `every_view` 정책은 첨부 접근도 별도 열람으로 처리한다. 중복 방지는 원장 기록에서는 `sr_community_asset_logs.dedupe_key`로 처리하되, 최초 1회 접근권 판정은 `sr_community_access_entitlements`를 우선 사용한다. 계정별 자산 로그와 접근권 기록은 커뮤니티 모듈의 `privacy-export.php`에 포함하고, 회원 탈퇴/익명화 시 접근권의 `account_id`와 원천 참조를 비운다. 게시글 스크랩과 시리즈 스크랩도 계정별 개인 보관 데이터이므로 커뮤니티 모듈의 `privacy-export.php`에 포함한다.

쿠폰은 포인트/적립금/예치금과 다른 지급형 회원 자산이므로 `coupon` 모듈이 쿠폰 종류, 회원별 지급, 사용 이력을 독립 소유한다. 현재 구현된 런타임 사용은 열람권 성격의 `access` 쿠폰이다. 쿠폰 사용처 후보는 소비 모듈이 `coupon-targets.php` 계약으로 제공하고, 쿠폰 모듈은 활성 모듈의 계약만 읽어 관리자 선택지와 저장 검증에 사용한다. 사용처 검색과 수동 환불 시 접근권 회수도 `search_function`, `revoke_access_function` 같은 소비 모듈 콜백으로 위임한다. 콘텐츠 유료 열람은 `target_type=content`, 커뮤니티 게시글 열람은 `target_type=community_post`, 게시판 열람권은 `target_type=community_board` 쿠폰을 먼저 확인하고, 사용할 쿠폰이 없을 때 기존 금액성 자산 차감으로 내려간다. 쿠폰 사용은 `sr_coupon_redemptions.dedupe_key`로 중복 사용을 막고, 회원 탈퇴 시 활성 쿠폰은 환급 정책에 따라 `withdrawn_expired` 또는 `refund_requested` 상태로 전환한다. 쿠폰 지급/사용 내역과 수동 환불 이력은 쿠폰·이용권 모듈의 `privacy-export.php`에 포함한다. 관리자 화면은 쿠폰 관리(`/admin/coupons`), 지급 내역(`/admin/coupons/issues`), 사용 내역(`/admin/coupons/redemptions`)으로 나뉘며, 쿠폰 추가와 개별 쿠폰 지급은 쿠폰 관리 화면의 모달에서 처리한다. 쿠폰 종류 목록은 상태, 사용처, 검색어로 좁히고 헤더에서 관리용 키, 이름, 사용처, 상태를 정렬할 수 있다. 지급 내역은 상태, 사용처, 회원 검색, 쿠폰 검색어로 필터링하고 회원, 쿠폰, 사용처, 상태, 사용 횟수, 지급일을 정렬할 수 있다. 사용 내역은 상태, 환급 정책, 사용처, 회원 검색, 쿠폰 검색어로 필터링하고 회원, 쿠폰, 사용 대상, 상태, 사용일, 환불일을 정렬할 수 있다. 지급하기 모달은 회원 검색 스택 모달로 개별 회원을 선택하거나 활성 회원 전체, 활성 회원 그룹을 대상으로 지급할 수 있다. 쿠폰 추가 모달의 대상 번호는 사용처별 검색 스택 모달에서 소비 모듈이 제공한 검색 콜백으로 대상을 찾아 채울 수 있다. `/admin/coupons/issues`는 최근 지급 내역과 지급 취소를 제공하고, `/admin/coupons/redemptions`는 최근 사용 내역과 환급 가능 정책의 사용 완료 내역 수동 환불을 제공한다. 수동 환불은 환불 사유를 필수로 받아 사용 로그를 `refunded`로 바꾸고 지급 건의 사용 횟수를 1회 되돌리며, 지급 건이 `used` 상태였으면 `active`로 되돌린다. 쿠폰 사용으로 생긴 소비 모듈 접근권은 사용 로그의 `dedupe_key`와 연결된 `source_reference`를 기준으로 각 소비 모듈 콜백이 회수한다.

정액 할인, 정률 할인, 특정 주문/상품 금액 차감권처럼 금액 계산이 필요한 쿠폰은 쿠폰 모듈 단독 선행 구현 대상이 아니다. 실제 주문, 결제, 콘텐츠 구매, 커뮤니티 유료 기능처럼 적용 도메인이 생길 때 해당 모듈과 함께 계약을 정의한다. 적용 도메인이 최소 금액, 과세/비과세, 배송비, 부분 취소, 환불, 중복 할인 같은 정책을 소유하고, 쿠폰 모듈은 쿠폰 종류/지급/사용 이력과 중복 사용 방지처럼 쿠폰 자체의 생명주기를 맡는다. 금액형 쿠폰을 구현하더라도 포인트/적립금/예치금 원장에 합쳐서 잔액처럼 관리하지 않고, 사용 결과는 `sr_coupon_redemptions` 또는 쿠폰 모듈 소유의 확장 사용 이력 테이블에 기록한다. 소비 도메인은 쿠폰 helper를 통해 계산/사용을 요청하고, 자기 도메인 로그에는 적용된 쿠폰 사용 ID와 할인 결과만 참조한다.

회원 탈퇴는 member 모듈이 조정하되 잔액 처리는 각 자산 모듈의 원장 helper를 호출한다. 탈퇴 화면은 포인트/적립금/예치금 잔액이 남아 있을 때만 남은 자산 처리 섹션을 표시한다. 포인트와 적립금은 `member.withdrawal` 참조의 `expire` 거래로 0 처리하고, 예치금은 환불 은행/예금주/계좌번호를 입력받은 뒤 `member.withdrawal` 참조의 `withdraw` 거래로 0 처리한다. 이 자산 원장 처리는 프로필 삭제, 세션 폐기, 동의 철회, 계정 익명화와 같은 탈퇴 트랜잭션 안에서 수행한다.

회원이 로그인 상태에서 보유 자산을 정산 요청하는 흐름은 각 자산 모듈이 소유한다. 적립금은 `/account/rewards`에서 출금 신청을 받고 `sr_reward_withdrawal_requests`에 계좌 정보, 금액, 상태, 처리 메모를 남긴다. `/admin/rewards/settings`는 출금 신청 사용 여부와 신청 대상을 저장한다. 출금 신청을 사용하지 않으면 회원 화면 폼과 직접 POST를 막고, 사용할 때는 전체 회원 또는 허용 회원 그룹 중 하나에 속한 회원만 신청할 수 있다. 지정값이 없으면 회원이 출금 신청을 할 수 없다. 예치금은 `/account/deposits`에서 환불 신청을 받고 `sr_deposit_refund_requests`에 같은 형태로 남긴다. `/admin/deposits/settings`는 환불 신청 사용 여부와 신청 대상을 저장한다. 환불 신청을 사용하지 않으면 회원 화면 폼과 직접 POST를 막고, 사용할 때는 전체 회원 또는 허용 회원 그룹 중 하나에 속한 회원만 신청할 수 있다. 지정값이 없으면 회원이 환불 신청을 할 수 없다. 대기 중 신청 금액은 신청 가능액에서 제외하며, 회원은 대기 상태 신청만 직접 취소할 수 있다. 관리자는 `/admin/rewards/withdrawal-requests`, `/admin/deposits/refund-requests`에서 대기 신청을 완료 또는 거부한다. 개별 거부는 별도 모달에서 거부 사유를 입력해 처리한다. 일괄처리는 현재 필터와 검색 조건에 맞는 대기 신청을 최대 100건까지 서버에서 다시 조회해 처리한다. 완료 처리만 각 원장에 `transaction_type=withdraw` 음수 거래를 만들고, 거부/취소는 신청 상태만 바꾼다. 신청 계좌 정보와 처리 이력은 해당 자산 모듈의 `privacy-export.php`에 포함한다.

포인트/적립금/예치금 원장 helper와 쿠폰·이용권 지급/사용/상태 변경/수동 환불 helper는 거래 또는 권리 상태 변경 성공 후 알림 모듈이 활성화되어 있고 `notification-events.php` 계약을 제공하면 계약의 계정 이벤트 생성 함수를 통해 회원 사이트 알림을 만든다. 콘텐츠/커뮤니티 댓글 작성자 알림과 콘텐츠/커뮤니티/퀴즈/설문 댓글 멘션도 같은 계정 이벤트 생성 함수와 DB 템플릿을 사용한다. 댓글 멘션 대상은 member 공개 이름/멘션 helper로 해석하며, 후보 자동완성은 `@공개이름#prefix`를 삽입해 동명이인을 구분한다. 커뮤니티 쪽지와 닉네임 강제 초기화처럼 회원에게 보여줄 비템플릿 알림은 같은 계약의 일반 알림 생성 함수만 호출하고 알림 저장 테이블을 직접 쓰지 않는다. 커뮤니티 신고, 콘텐츠 등록자 신청, 개인정보 처리 요청, 이메일 발송 실패 표시, 저장소 정리 재시도 실패처럼 운영자가 조치해야 하는 이벤트는 `admin-notification-events.php` 계약으로 관리자 운영 알림을 만들며, 알림 모듈이 비활성화되었거나 운영 알림 테이블이 없으면 기존 업무 처리를 실패시키지 않는다. 포인트 모듈은 `default_expiration_days` 설정이 1 이상이면 `sr_point_create_transaction()`으로 생성되는 새 `grant` 양수 거래에 유효기간을 붙이고, 이후 사용/차감 거래는 만료 예정 지급분부터 `expires_remaining`을 줄이며 `sr_point_expiration_consumptions`에 소비 거래와 원 지급 거래를 연결한다. 포인트 환불 거래는 환불 건마다 `refund_expiration_policy`를 받아 기본값 `original`이면 환불 참조 원거래의 `expires_at`을 우선 사용하고, 참조가 사용/차감 거래이면 소비 매핑에 남은 원 지급 거래의 유효기간을 사용한다. 이전에 같은 원거래를 일부 환불한 경우 그 수량만큼 소비 매핑을 건너뛰고, 여러 유효기간 지급분을 복원해야 하면 유효기간별 `refund` 거래를 나누어 만든다. 포인트/적립금/예치금 원장 환불은 차감된 원거래를 선택한 경우에만 허용하며, 참조 없는 수동 환불과 양수 원거래 환불은 서버에서 거부한다. 원거래의 남은 환불 가능 수량/금액을 넘는 환불도 트랜잭션 안에서 다시 검증해 거부한다. 포인트 환불에서 `reset`이면 환불 시점부터 기본 유효기간을 계산한다. `original`을 선택했지만 참조 원거래나 소비 매핑에 유효기간이 없으면 무기한 복원을 피하기 위해 기본 유효기간이 켜진 경우 환불 시점 기준으로 계산한다. 기한이 지난 지급분은 다음 포인트 거래 전 또는 운영 스크립트 `.tools/bin/expire-points.php` 실행 시 `transaction_type=expire`, `reference_type=point_expiration`, `reference_id=point_transaction:{id}` 거래로 차감한다. 적립금 지급 내역을 되돌릴 때는 환불이 아니라 `transaction_type=reclaim` 음수 거래를 사용한다. 예치금을 회원에게 내보내는 처리는 원장 환불이 아니라 `withdraw` 음수 거래 또는 예치금 환불 신청 완료 흐름을 사용한다. 적립금 운영자 직접 회수는 별도 회수 알림 템플릿을 사용한다. 회수 거래는 양수 원거래를 `reference_type=reclaim`, `reference_id=reward_transaction:{id}`로 참조해야 하며, 같은 원거래의 누적 회수액은 원거래 금액을 넘을 수 없다. 회수는 거래 내역 화면에서 대상 내역을 선택해 처리하고, 회수 거래는 다시 환불하지 않는다. 알림 모듈이 비활성화되어 있거나 설치되어 있지 않으면 no-op으로 처리하고 자산 처리나 댓글 저장을 실패시키지 않는다. 이벤트 템플릿은 `sr_notification_event_templates`가 소유하며 기본 채널은 `site`다. 외부 채널은 템플릿의 `channels_json`에 명시된 경우에만 delivery queue를 만든다. 회원에게 노출되는 링크는 각 자산의 `/account/points`, `/account/rewards`, `/account/deposits`, `/account/coupons` 화면 또는 댓글 대상 URL을 사용한다.

커뮤니티의 게시판 읽기/쓰기/댓글과 쪽지 발송에서 회원 그룹과 최소 레벨이 함께 설정된 경우 두 조건을 모두 통과해야 허용한다. 회원 그룹만 설정된 조건은 회원 그룹만, 최소 레벨만 설정된 조건은 최소 레벨만 확인한다. 게시판과 게시판 그룹에서 선택한 회원 그룹에게만 권한을 부여하려면 해당 읽기/쓰기/댓글 정책을 그룹으로 선택해야 한다. 회원 그룹이 비어 있으면 회원 그룹 제한을 적용하지 않고, 최소 레벨이 있으면 레벨 조건만 확인한다. 게시판과 게시판 그룹 저장 시 쓰기/댓글 회원 그룹은 읽기 회원 그룹의 하위 집합으로 저장한다. 쓰기/댓글에서 선택하면 읽기에도 포함하고, 읽기에서 제거하면 쓰기/댓글에서도 제거한다. 커뮤니티 활동 점수는 게시글과 댓글을 게시판별로 집계한 뒤 각 게시판의 유효 게시글 점수와 댓글 점수를 곱해서 합산한다. 커뮤니티 레벨 사용, 자동 재계산, 최대 레벨, 전역 게시글/댓글 점수는 `/admin/community/settings`의 레벨 섹션에서 관리하며, 전역 게시글/댓글 점수 입력은 자동 재계산을 사용할 때만 노출한다. 커뮤니티 최대 레벨은 `level_max_value` 모듈 설정으로 1-100 범위에서 관리하며, 값을 늘리면 부족한 `sr_community_levels` 행을 기본 최소 점수로 자동 추가한다. 값을 줄여도 기존 레벨 행은 삭제하지 않고 레벨 판정과 선택지를 새 최대값까지만 사용한다. 최대 레벨 변경은 모달의 영향 안내 확인 단계와 확인 문구 입력 단계, 서버 확인 플래그/문구 검증을 거쳐 저장한다. 커뮤니티 활동으로 레벨이 바뀔 수 있는 게시글/댓글 생성, 삭제, 상태 변경 흐름은 레벨을 먼저 재계산한 뒤 커뮤니티 자동 회원 그룹 규칙을 평가해야 `community.level_at_least` 규칙이 최신 레벨을 기준으로 동작한다. 레벨 설정 페이지는 레벨 미사용 상태에서 환경설정으로 이동해 레벨을 먼저 켜라는 안내를 제공한다. 레벨 최대값이나 최소 점수 변경 후 기존 회원 레벨을 반영하려면 관리자가 재계산을 실행해야 하며, 재계산은 모달의 부하 가능성 확인 단계와 확인 문구 입력 단계, 서버 확인 플래그/문구 검증을 거쳐 활성/대기 회원을 배치로 처리한다. 레벨 자동 재계산을 사용하지 않을 때 관리자는 멤버 관리 목록에서 활성 멤버를 선택한 뒤 상단 일괄 작업으로 레벨을 직접 변경할 수 있으며, 목록의 레벨 열은 현재 레벨 텍스트만 표시한다.

회원 탈퇴/익명화 시 member 모듈은 설치된 모듈의 `privacy-cleanup.php` 계약을 탈퇴 트랜잭션 안에서 실행한다. 관리자가 회원 상태를 탈퇴 또는 익명화로 직접 바꾸는 경우에도 같은 계약을 실행한다. member 모듈은 회원 닉네임을 삭제하고, 콘텐츠와 커뮤니티 모듈은 이 계약에서 접근권 테이블의 회원 연결을 제거한다. 커뮤니티 모듈은 계정별 레벨 스냅샷/레벨 변경 로그도 삭제한다. 커뮤니티 게시글/댓글 작성자와 커뮤니티 시리즈 소유자는 해시가 아니라 내부 계정 ID이며 편집, 삭제, private 시리즈 열람 같은 권한 판정에 쓰이므로 null 처리하지 않고 계정 행 익명화로 처리한다. 콘텐츠/커뮤니티 시리즈의 nullable 작성자, 수정자, 검토자 메타데이터와 시리즈 항목 작성자 메타데이터는 cleanup에서 null 처리한다. 회원 닉네임은 멤버 간 중복을 허용하지 않으며, 가입과 계정/관리자 저장 시 서버 검증과 DB 유니크 제약으로 함께 막는다. 탈퇴 또는 익명화 상태의 회원은 공개 이름과 회원 목록에서 재식별 가능한 닉네임을 사용하지 않는다.

## 18. 사이트 메뉴 추가 링크

사이트 공통 메뉴 구조는 `site_menu` 모듈이 소유한다. 각 모듈은 운영자가 메뉴 항목에 연결할 수 있는 링크 자산만 `menu-links.php`로 제공한다. 이 링크 자산은 자동으로 메뉴에 등록되지 않고, 사이트 메뉴 항목 추가/수정 모달에서 서비스를 먼저 고른 뒤 대상 종류와 연결 자산으로 선택한다. 서비스는 모듈의 관리자 표시 이름을 사용하고, `asset_type`은 서비스 안의 대상 종류를 구분한다.

```php
<?php

return [
    [
        'asset_type' => 'board',
        'asset_type_label' => '게시판',
        'label' => '게시판',
        'url' => '/board',
    ],
];
```

규칙:

- 링크 자산 제공은 메뉴 항목 자동 생성을 의미하지 않는다.
- 최종 메뉴 구성은 `site_menu` 관리자 화면에서 운영자가 결정한다.
- 콘텐츠와 커뮤니티 환경설정에서 번들 공개 레이아웃의 주 메뉴 슬롯, 보조 메뉴 슬롯, 추가 메뉴 슬롯 1, 추가 메뉴 슬롯 2, 추가 메뉴 슬롯 3에 연결할 사이트 메뉴를 선택할 수 있다. 기존 `layout_primary_menu_key`, `layout_secondary_menu_key`, `layout_tertiary_menu_key`는 유지하고, 추가 슬롯은 `layout_quaternary_menu_key`, `layout_quinary_menu_key`로 저장한다.
- 주 메뉴 슬롯을 사용 안 함으로 설정하면 명시적인 사이트 메뉴 대신 모듈이 공개 가능한 fallback 링크를 렌더링할 수 있다. 번들 콘텐츠 레이아웃은 공개 가능한 콘텐츠 그룹, 번들 커뮤니티 레이아웃은 `/community/group?key=...`로 연결되는 사용 상태인 게시판 그룹을 우선 후보로 사용하고, 사용 상태인 게시판 그룹이 하나도 없을 때만 공개 게시판을 후보로 사용한다.
- 사이트 메뉴 항목은 `parent_id` 기반으로 최대 3단계까지 구성할 수 있으며, 관리자 화면은 메뉴 묶음과 항목을 단일 계층 테이블에서 관리한다.
- 사이트 메뉴 항목은 선택적으로 `icon_name`을 저장해 공개 메뉴 라벨 앞에 아이콘을 표시할 수 있다. 선택지는 관리자 메뉴 아이콘 helper의 허용 심볼과 공개 출력 가능한 Material 공용 아이콘 키를 사용한다.
- `asset_type`과 `asset_type_label`을 제공하면 항목 모달에서 서비스 안의 대상 종류를 나눠서 선택할 수 있다. 예를 들어 커뮤니티는 `게시판 그룹`, `게시판`, 콘텐츠는 `콘텐츠 그룹`, `콘텐츠`로 표시한다.
- `url`은 내부 상대 경로 또는 허용된 외부 URL이어야 한다.
- 메뉴 항목에 연결할 링크 자산은 화면 위치가 아니므로 `extension-points.php`로 선언하지 않는다.

## 19. 개인정보 사본 제공

`privacy` 모듈은 개인정보 처리 요청과 개인정보 사본 제공 흐름을 조정한다. 회원 계정, 인증, 동의처럼 `member`가 소유한 데이터도 `modules/member/privacy-export.php`로 제공하고, 게시판, 커머스, 예약, 알림, 회원 자산 같은 확장 모듈의 개인정보도 각 모듈이 `privacy-export.php`로 제공한다. 포인트/적립금/예치금 원장, 쿠폰 지급/사용 로그, 콘텐츠/커뮤니티 계정별 자산 처리 로그와 접근권 테이블처럼 `account_id`에 연결되는 운영 기록은 개인정보 사본 제공 대상인지 먼저 검토한다. 접근권 테이블은 서비스 이용 상태이고, 거래 원장과 쿠폰 사용 로그는 증빙 기록이므로 개인정보처리방침의 보유기간을 서로 다르게 둘 수 있다.

```php
<?php

return function (PDO $pdo, int $accountId): array {
    $stmt = $pdo->prepare(
        'SELECT id, title, status, created_at
         FROM sr_board_posts
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
         FROM sr_board_posts
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
- 모든 `POST` action에서 `sr_require_csrf()`를 호출하는가?
- 관리자 action 시작 부분에서 로그인과 메뉴 권한 또는 owner 권한을 검증하는가?
- 회원 전용 action에서 `sr_member_require_login()`을 사용하는가?
- 출력 값은 `sr_e()` 또는 동등한 escape를 거쳤는가?
- SQL 동적 값은 prepared statement로 바인딩했는가?
- 정렬 컬럼, 테이블명, 상태 값은 allowlist를 사용하는가?
- redirect 대상은 내부 상대 경로로 제한했는가?
- 상태 변경 `POST`가 flash 결과와 redirect로 끝나 새로고침 재실행을 막는가?
- 토큰 원문을 DB나 로그에 저장하지 않는가?
- 개인정보 사본 제공에 hash/token/password가 빠져 있는가?
- 감사 로그에 민감 원문을 넣지 않았는가?
- 파일 경로 입력이 있으면 모듈 디렉터리 밖으로 나갈 수 없는가?
- 외부 링크 출력에는 `rel="noopener noreferrer"`가 붙는가?

## 22. 성능 기준

산란은 저가형 웹호스팅을 고려한다.

- 요청마다 전체 모듈 디렉터리를 깊게 스캔하지 않는다.
- 사용자 요청에서 관리자용 계약 파일을 반복 파싱하지 않는다.
- 목록 조회는 기본적으로 limit을 둔다.
- 대량 데이터 export는 한 번에 너무 크게 만들지 않는다.
- 캐시는 필수가 아니라 선택 최적화로 둔다.
- DB 인덱스는 실제 조회 조건에 맞춘다.
- 백그라운드 worker가 필수인 설계는 기본 모듈로 피한다.

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

공개 배포나 반복 배포를 고려하는 모듈은 모듈 폴더 옆에 `README.md`, `CHANGELOG.md`, `LICENSE` 같은 문서를 둘 수 있다. 다만 산란에 배치되는 런타임 파일은 `modules/{module_key}` 아래에 있어야 한다.

Git을 사용할 수 없는 운영 환경을 기본 지원 대상으로 본다. 따라서 운영 설치는 zip 업로드 또는 FTP/파일 관리자 배치를 기준으로 설명한다.

모듈 단독 배포물은 같은 모듈 key를 유지하는 한 해당 모듈 폴더만 포함한다. 산란은 `modules/{module_key}/module.php`, `install.sql`, `updates/`, `paths.php`와 `module.php`에 선언된 계약 파일을 현재 폴더에서 읽는다. 본체 파일, 다른 모듈 파일, 저장소 문서, Wiki 문서, 로컬 점검 스크립트는 모듈 zip에 포함하지 않고 본체 릴리스나 별도 문서 작업으로 관리한다.

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

새 모듈을 추가할 때는 먼저 `modules/{module_key}` 폴더 안에서 책임 경계를 잡는다. 산란 런타임은 최종 배치된 모듈 폴더만 읽는다.

## 25. 금지하는 방향

산란 기본 구현에서는 다음 방식을 사용하지 않는다.

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

도구를 쓰더라도 프로젝트 실행에 필수가 되면 안 된다. 산란의 기본 가정은 일반 웹호스팅에서 PHP 파일과 SQL만으로 설치되고 동작하는 구조다.
