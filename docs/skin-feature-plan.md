# 스킨 기능 추가 계획

## 목표

배너, 팝업레이어, 회원, 관리자 화면에 스킨 선택 기능을 추가한다.

스킨 기능은 화면 출력 방식을 바꾸는 모듈별 표현 계층이다. core는 스킨을 중앙 관리하는 CMS 기능을 갖지 않고, 각 모듈이 자기 스킨 목록, 선택 설정, view include 경로를 소유한다.

## 원칙

- core는 공통 helper를 추가하더라도 안전한 key 검증, 파일 경로 검증, fallback 같은 운영 helper에 머문다.
- 스킨의 의미, 제공 view, 설정 저장 위치는 각 모듈이 소유한다.
- 스킨은 DB 구조를 넓히기보다 우선 `toy_module_settings` 또는 기존 모듈 설정 테이블을 사용한다.
- 스킨 선택은 관리자 화면에서 명시적으로 저장한다.
- 스킨 파일은 PHP view로 두고, 숨은 자동 등록 대신 helper의 명시 배열로 읽는다.
- 스킨은 output slot, 배너, 팝업레이어 삽입 지점을 유지해야 한다.
- 스킨이 없어지거나 잘못 지정되면 `basic`으로 fallback한다.
- 스킨 기능은 테마/디자인 선택이지 도메인 정책이 아니다.

## 현재 상태

구현 상태:

- 배너는 `banner_skin_key` 설정과 `modules/banner/skins/basic/item.php`를 사용한다.
- 팝업레이어는 `popup_layer_skin_key` 설정과 `modules/popup_layer/skins/basic/layer.php`를 사용한다.
- 회원 public 화면은 `member_skin_key` 설정과 `modules/member/skins/basic/*.php` wrapper를 사용한다.
- 관리자 layout은 `admin_skin_key` 설정과 `modules/admin/skins/basic/layout-*.php`를 사용한다.

커뮤니티:

- `modules/community/helpers/themes.php`에 `theme_key`, `skin_key` helper가 있다.
- 커뮤니티 홈은 `themes/basic/home.php`를 사용한다.
- 커뮤니티 테마는 `/admin/community/settings`의 화면 섹션에서 선택하고 `toy_module_settings.theme_key`에 저장한다.
- 게시판 목록, 글보기, 글쓰기 폼은 `skins/basic/*.php`를 사용한다.
- 게시판별 스킨은 `/admin/community/boards`의 생성/수정 폼에서 선택하고 `toy_community_board_settings.skin_key`에 저장한다.
- 현재는 `basic`만 허용한다.

배너:

- `toy_banner_render_item()`이 스킨 view를 include하고, 기본 스킨은 `toy_banner_render_basic_item()`으로 출력한다.
- `toy_banner_render_public_banner()`와 `toy_banner_render_slot()`이 같은 item renderer를 사용한다.
- output slot 계약은 이미 있다.
- 개별 배너 스킨은 `/admin/banners` 추가/수정 폼에서 선택하고 `toy_banners.skin_key`에 저장한다.
- 배너 위치는 `placement_kind`를 갖고, 배너 스킨은 `supports` 목록으로 호환 위치를 선언한다.
- 렌더링 시 스킨이 위치와 맞지 않으면 호환되는 `basic` 스킨으로 fallback하고, 호환 스킨이 없으면 출력하지 않는다.

팝업레이어:

- 렌더링 helper와 JS asset이 있다.
- output slot 계약은 이미 있다.
- 팝업 stack은 스킨 view를 include하고, 기본 스킨은 `toy_popup_layer_render_basic_stack()`으로 출력한다.
- 개별 팝업 스킨은 `/admin/popup-layers` 추가/수정 폼에서 선택하고 `toy_popup_layers.skin_key`에 저장한다.

회원:

- 로그인, 회원가입, 계정, 비밀번호 재설정 등 public action이 `modules/member/skins/basic/*.php`를 통해 view를 출력한다.
- `extension-points.php`와 output slot은 일부 화면에 이미 있다.
- 스킨 선택 helper가 있다.

관리자:

- `modules/admin/views/layout-header.php`, `layout-footer.php`는 스킨 layout wrapper다.
- 관리자 메뉴는 관리자 기본 그룹과 모듈 그룹을 함께 묶는 구조로 정리되어 있다.
- 관리자 스킨 선택 helper가 있다.

## 범위

1차 범위:

- 배너 출력 스킨
- 팝업레이어 출력 스킨
- 회원 public 화면 스킨
- 관리자 layout 스킨

제외:

- 스킨 marketplace
- 브라우저에서 스킨 파일 업로드/편집
- CSS/JS 빌드 파이프라인
- 페이지 빌더
- 모듈 간 스킨 자동 호환

## 공통 설계

스킨 key:

```text
basic
```

허용 형식:

```text
\A[a-z0-9][a-z0-9_-]{0,39}\z
```

공통 fallback:

```text
설정값 -> 모듈 helper allowlist 확인 -> view 파일 존재 확인 -> basic
```

권장 helper 형태:

```php
toy_{module}_skin_options(): array
toy_{module}_skin_key(array $settings): string
toy_{module}_skin_view(string $skinKey, string $viewKey): string
```

스킨 option 예:

```php
[
    'basic' => [
        'label' => '기본',
        'views' => [
            'item' => TOY_ROOT . '/modules/banner/skins/basic/item.php',
        ],
    ],
]
```

## 배너 스킨 계획

목표:

- 배너 item HTML을 `helpers.php` 문자열 조립에서 `skins/{skin}/item.php`로 이동한다.
- 모듈 설정의 기본 배너 스킨은 새 배너의 초기값으로 사용하고, 개별 배너별 스킨 선택을 둔다.

설정:

```text
banner_skin_key: basic
```

파일:

```text
modules/banner/skins/basic/item.php
```

helper:

```php
toy_banner_skin_options()
toy_banner_skin_key($settings)
toy_banner_skin_view($skinKey, 'item')
toy_banner_render_item($banner, $skinKey = null)
```

관리 화면:

- `/admin/banners` 목록 또는 별도 설정 섹션에서 기본 배너 스킨 선택
- `/admin/banners` 추가/수정 폼에서 개별 배너 스킨 선택
- 초기에는 선택지가 `basic` 하나여도 select를 노출해 구조를 고정한다.

검증:

- `toy_banner_render_item()`이 스킨 view를 통해 출력하는지 확인
- output slot 렌더링과 공용 배너 렌더링이 같은 fallback을 쓰는지 확인
- 클릭 추적 링크가 스킨에서도 유지되는지 확인
- 저장/렌더링 시 배너 출력 위치와 스킨 `supports` 호환성을 확인

## 팝업레이어 스킨 계획

목표:

- 팝업레이어 container HTML을 `skins/{skin}/layer.php`로 분리한다.
- JS asset과 닫기 정책은 helper가 계속 책임진다.
- 스킨은 markup 구조와 class hook만 다룬다.

설정:

```text
popup_layer_skin_key: basic
```

파일:

```text
modules/popup_layer/skins/basic/layer.php
```

helper:

```php
toy_popup_layer_skin_options()
toy_popup_layer_skin_key($settings)
toy_popup_layer_skin_view($skinKey, 'layer')
toy_popup_layer_render_item($popupLayer, $skinKey = null)
```

관리 화면:

- `/admin/popup-layers`에 기본 팝업레이어 스킨 선택 추가
- `/admin/popup-layers` 추가/수정 폼에서 개별 팝업 스킨 선택

검증:

- slot 대상 필터와 노출 기간 조건이 유지되는지 확인
- `toycore-popup-layer.js`가 계속 한 번만 출력되는지 확인
- content slot 외 위치를 받지 않는 기존 제한 유지

## 회원 스킨 계획

목표:

- 회원 public 화면을 `views` 직접 include에서 `skins/{skin}/{view}.php` include로 전환한다.
- 회원 도메인 정책과 인증 처리는 action에 남기고, 스킨은 form/layout 출력만 담당한다.

설정:

```text
member_skin_key: basic
```

파일:

```text
modules/member/skins/basic/login.php
modules/member/skins/basic/register.php
modules/member/skins/basic/account.php
modules/member/skins/basic/password-reset-request.php
modules/member/skins/basic/password-reset.php
modules/member/skins/basic/privacy-requests.php
modules/member/skins/basic/withdraw.php
```

helper:

```php
toy_member_skin_options()
toy_member_skin_key($settings)
toy_member_skin_view($skinKey, $viewKey)
```

전환 방식:

- 기존 `modules/member/views/*.php`는 바로 삭제하지 않는다.
- 1차에서는 동일 내용을 `skins/basic/*.php`로 복사한 뒤 action include 경로만 helper로 바꾼다.
- 안정화 후 기존 views는 관리자 전용 view만 남기거나, 호환 wrapper로 줄인다.

관리 화면:

- `/admin/member-settings`에 회원 스킨 선택 추가
- 선택 저장은 member module setting으로 처리

검증:

- 로그인, 회원가입, 계정 수정, 탈퇴, 비밀번호 재설정 흐름 smoke
- output slot 지점 유지
- CSRF field와 `next` 값 유지
- 이메일 인증/비밀번호 reset debug notice 유지

## 관리자 스킨 계획

목표:

- 관리자 공통 layout을 `modules/admin/skins/{skin}/layout-header.php`, `layout-footer.php`로 분리한다.
- 관리자 action과 helper는 그대로 두고, layout include만 admin skin helper를 통한다.
- 관리자 메뉴 묶음 구조는 스킨이 아니라 navigation helper의 결과로 유지한다.

설정:

```text
admin_skin_key: basic
```

파일:

```text
modules/admin/skins/basic/layout-header.php
modules/admin/skins/basic/layout-footer.php
```

helper:

```php
toy_admin_skin_options()
toy_admin_skin_key($settings)
toy_admin_skin_view($skinKey, $viewKey)
toy_admin_layout_header()
toy_admin_layout_footer()
```

전환 방식:

- 기존 `modules/admin/views/layout-header.php`와 `layout-footer.php`는 wrapper로 남긴다.
- wrapper가 admin skin helper를 호출해 실제 skin layout을 include한다.
- 각 관리자 view의 include 경로를 한 번에 바꾸지 않아도 동작하게 한다.

관리 화면:

- `/admin/settings` 또는 `/admin/modules`가 아니라 `/admin/settings`에 관리자 스킨 선택 추가
- admin module setting으로 저장

검증:

- 모든 `/admin/*` 화면 PHP lint
- 관리자 메뉴 그룹 표시 유지
- 로그인/권한 guard와 CSRF 흐름 영향 없음
- noindex SEO 유지

## 단계별 구현 순서

1. 공통 스킨 key 검증 helper를 module helper에 둘지 core helper에 둘지 결정한다.
2. 배너 스킨을 먼저 구현한다.
3. 팝업레이어 스킨을 구현한다.
4. 회원 public 스킨을 구현한다.
5. 관리자 layout 스킨을 구현한다.
6. 문서와 체크 스크립트를 갱신한다.
7. smoke test에 회원 화면과 관리자 layout 기준 확인을 추가한다.

권장 순서의 이유:

- 배너와 팝업레이어는 출력 단위가 작아 스킨 helper/fallback 패턴을 검증하기 좋다.
- 회원은 public form 흐름이 많아 두 번째 묶음으로 처리한다.
- 관리자는 모든 관리자 화면에 영향을 주므로 마지막에 wrapper 방식으로 좁게 적용한다.

## 완료 기준

- 각 대상 모듈에 `basic` 스킨이 명시적으로 존재한다.
- 관리자 설정에서 각 스킨 key를 저장할 수 있다.
- 잘못된 스킨 key 저장 시 `basic`으로 fallback한다.
- 기존 output slot, 배너, 팝업레이어 삽입 지점이 사라지지 않는다.
- 회원 인증/관리자 권한 흐름이 스킨 변경과 무관하게 유지된다.
- `.tools/bin/check`가 통과한다.

## 리스크와 대응

| 리스크 | 대응 |
| --- | --- |
| 스킨 기능이 core CMS 기능으로 커짐 | core는 helper 수준만 허용하고 스킨 목록/정책은 모듈별 소유 |
| 스킨 파일 삭제로 화면 오류 | helper fallback과 파일 존재 확인 필수 |
| 회원 form 스킨에서 보안 필드 누락 | 체크 스크립트에 CSRF, next, output slot 필수 fragment 추가 |
| 관리자 layout 변경으로 전체 관리자 화면 영향 | 기존 layout 파일을 wrapper로 남겨 점진 전환 |
| 배너/팝업레이어 추적/닫기 동작 손상 | 렌더링 데이터 준비는 helper에 두고 skin은 출력만 담당 |
